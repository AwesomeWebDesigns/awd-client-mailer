<?php
/**
 * Sends queued emails in small batches, through WP-Cron in the background
 * and through AJAX while the progress screen is open.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Queue {

	const HOOK       = 'awdcm_process_queue';
	const LOCK       = 'awdcm_queue_lock';
	const MAX_TRIES  = 3;
	const LOCK_LIMIT = 120;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::HOOK, array( __CLASS__, 'run_cron' ) );
		add_action( 'wp_ajax_awdcm_process_queue', array( __CLASS__, 'ajax' ) );
	}

	public static function cron_schedules( $schedules ) {
		$schedules['awdcm_every_minute'] = array(
			'interval' => 60,
			'display'  => 'Every minute (AWD Client Mailer)',
		);
		return $schedules;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 10, 'awdcm_every_minute', self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run_cron() {
		$result = self::process_batch();
		if ( ! $result['remaining'] ) {
			self::unschedule();
		}
	}

	public static function ajax() {
		check_ajax_referer( 'awdcm_process', 'nonce' );
		if ( ! current_user_can( AWDCM_CAP ) ) {
			wp_send_json_error( 'You do not have permission to do this.', 403 );
		}
		$id = isset( $_POST['campaign'] ) ? absint( $_POST['campaign'] ) : 0;
		self::process_batch();
		$c = AWDCM_Campaigns::get( $id );
		if ( ! $c ) {
			wp_send_json_error( 'Email not found.' );
		}
		$stats = AWDCM_Campaigns::queue_stats( $id );
		wp_send_json_success(
			array(
				'status'     => $c->status,
				'total'      => (int) $c->total,
				'sent'       => $stats['sent'],
				'failed'     => $stats['failed'],
				'skipped'    => $stats['skipped'],
				'pending'    => $stats['pending'],
				'last_error' => (string) $c->last_error,
			)
		);
	}

	/** Option-based lock: add_option fails if the row already exists. */
	private static function lock() {
		if ( add_option( self::LOCK, time(), '', 'no' ) ) {
			return true;
		}
		$since = (int) get_option( self::LOCK );
		if ( $since && time() - $since > self::LOCK_LIMIT ) {
			delete_option( self::LOCK );
			return add_option( self::LOCK, time(), '', 'no' );
		}
		return false;
	}

	private static function unlock() {
		delete_option( self::LOCK );
	}

	/**
	 * @return array processed (int), remaining (bool)
	 */
	public static function process_batch() {
		global $wpdb;

		if ( ! self::lock() ) {
			return array(
				'processed' => 0,
				'remaining' => true,
			);
		}

		$processed = 0;
		try {
			$limit = max( 1, min( 100, (int) AWDCM_Settings::get( 'batch_size' ) ) );
			$q     = AWDCM_Campaigns::queue_table();
			$cmp   = AWDCM_Campaigns::table();
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT q.* FROM $q q INNER JOIN $cmp c ON c.id = q.campaign_id WHERE q.status = 'pending' AND c.status = 'sending' ORDER BY q.id ASC LIMIT %d",
					$limit
				)
			);

			$campaigns = array();
			foreach ( $rows as $row ) {
				$cid = (int) $row->campaign_id;
				if ( ! isset( $campaigns[ $cid ] ) ) {
					$campaigns[ $cid ] = AWDCM_Campaigns::get( $cid );
				}
				$campaign = $campaigns[ $cid ];
				if ( ! $campaign || 'sending' !== $campaign->status ) {
					continue;
				}

				$contact            = AWDCM_Contacts::get( $row->contact_id );
				list( $ok, $reason ) = AWDCM_Contacts::eligibility( $contact );
				if ( ! $ok ) {
					self::mark( $row->id, 'skipped', AWDCM_Contacts::exclusion_label( $reason ) );
					continue;
				}

				$msg    = AWDCM_Renderer::render( $campaign, $contact );
				$result = AWDCM_SMTP2GO::send( $contact->email, AWDCM_Contacts::full_name( $contact ), $msg['subject'], $msg['html'], $msg['text'], $msg['headers'] );
				++$processed;

				if ( $result['ok'] ) {
					self::mark( $row->id, 'sent', '', $result['id'], (int) $row->attempts + 1 );
					continue;
				}

				if ( $result['fatal'] ) {
					self::mark( $row->id, 'pending', $result['error'], '', (int) $row->attempts );
					AWDCM_Campaigns::pause( $cid, $result['error'] );
					$campaigns[ $cid ]->status = 'paused';
					continue;
				}

				$attempts = (int) $row->attempts + 1;
				self::mark( $row->id, $attempts >= self::MAX_TRIES ? 'failed' : 'pending', $result['error'], '', $attempts );
			}

			foreach ( array_keys( $campaigns ) as $cid ) {
				AWDCM_Campaigns::refresh_counts( $cid );
			}

			$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $q q INNER JOIN $cmp c ON c.id = q.campaign_id WHERE q.status = 'pending' AND c.status = 'sending'" );
		} finally {
			self::unlock();
		}

		return array(
			'processed' => $processed,
			'remaining' => $remaining > 0,
		);
	}

	private static function mark( $id, $status, $error = '', $message_id = '', $attempts = null ) {
		global $wpdb;
		$row = array(
			'status'     => $status,
			'error'      => mb_substr( (string) $error, 0, 500 ),
			'message_id' => mb_substr( (string) $message_id, 0, 100 ),
			'updated_at' => current_time( 'mysql' ),
		);
		if ( null !== $attempts ) {
			$row['attempts'] = $attempts;
		}
		$wpdb->update( AWDCM_Campaigns::queue_table(), $row, array( 'id' => (int) $id ) );
	}
}
