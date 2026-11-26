<?php
/**
 * Emails ("campaigns") and their send queue.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Campaigns {

	const STATUSES = array(
		'draft'     => 'Draft',
		'sending'   => 'Sending',
		'paused'    => 'Paused',
		'sent'      => 'Sent',
		'cancelled' => 'Cancelled',
	);

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'awdcm_campaigns';
	}

	public static function queue_table() {
		global $wpdb;
		return $wpdb->prefix . 'awdcm_queue';
	}

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) );
	}

	public static function tag_ids( $campaign ) {
		return array_values( array_filter( array_map( 'intval', explode( ',', (string) $campaign->tag_ids ) ) ) );
	}

	public static function all( $paged = 1, $per_page = 30 ) {
		global $wpdb;
		$total = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d OFFSET %d',
				$per_page,
				max( 0, ( $paged - 1 ) * $per_page )
			)
		);
		return array( $rows, $total );
	}

	/**
	 * Create or update a draft.
	 *
	 * @return int|WP_Error Campaign ID.
	 */
	public static function save_draft( array $data, $id = 0 ) {
		global $wpdb;
		$tag_ids = array_filter( array_map( 'intval', (array) ( $data['tag_ids'] ?? array() ) ) );
		$row     = array(
			'subject'    => mb_substr( sanitize_text_field( $data['subject'] ?? '' ), 0, 255 ),
			'body'       => wp_kses_post( $data['body'] ?? '' ),
			'style'      => ( isset( $data['style'] ) && 'plain' === $data['style'] ) ? 'plain' : 'branded',
			'tag_ids'    => implode( ',', $tag_ids ),
			'updated_at' => current_time( 'mysql' ),
		);

		if ( $id ) {
			$existing = self::get( $id );
			if ( ! $existing ) {
				return new WP_Error( 'missing', 'Email not found.' );
			}
			if ( 'draft' !== $existing->status ) {
				return new WP_Error( 'locked', 'This email has already been sent and can no longer be edited. Duplicate it to send a new version.' );
			}
			$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
			return (int) $id;
		}

		$row['status']     = 'draft';
		$row['created_by'] = get_current_user_id();
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function duplicate( $id ) {
		$c = self::get( $id );
		if ( ! $c ) {
			return 0;
		}
		return self::save_draft(
			array(
				'subject' => $c->subject,
				'body'    => $c->body,
				'style'   => $c->style,
				'tag_ids' => self::tag_ids( $c ),
			)
		);
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::queue_table(), array( 'campaign_id' => (int) $id ) );
		$wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Snapshot the eligible recipients into the queue and start sending.
	 *
	 * @return int|WP_Error Number of queued recipients.
	 */
	public static function start( $id ) {
		global $wpdb;
		$c = self::get( $id );
		if ( ! $c ) {
			return new WP_Error( 'missing', 'Email not found.' );
		}
		if ( 'draft' !== $c->status ) {
			return new WP_Error( 'locked', 'This email has already been sent.' );
		}
		if ( '' === trim( $c->subject ) || '' === trim( wp_strip_all_tags( $c->body ) ) ) {
			return new WP_Error( 'empty', 'Add a subject and a message before sending.' );
		}
		$issues = AWDCM_Settings::setup_issues();
		if ( $issues ) {
			return new WP_Error( 'setup', 'Finish the setup first: ' . implode( ' ', $issues ) );
		}

		$r = AWDCM_Contacts::recipients( self::tag_ids( $c ) );
		if ( ! $r['eligible'] ) {
			return new WP_Error( 'none', 'There is nobody eligible to receive this email.' );
		}

		$now = current_time( 'mysql' );
		foreach ( array_chunk( $r['eligible'], 200 ) as $chunk ) {
			$values = array();
			$params = array();
			foreach ( $chunk as $contact ) {
				$values[] = '(%d, %d, %s, %s, %s)';
				array_push( $params, (int) $c->id, (int) $contact->id, $contact->email, 'pending', $now );
			}
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO ' . self::queue_table() . ' (campaign_id, contact_id, email, status, updated_at) VALUES ' . implode( ',', $values ),
					$params
				)
			);
		}

		$wpdb->update(
			self::table(),
			array(
				'status'     => 'sending',
				'total'      => count( $r['eligible'] ),
				'excluded'   => wp_json_encode( $r['excluded'] ),
				'sent_at'    => $now,
				'updated_at' => $now,
				'last_error' => '',
			),
			array( 'id' => (int) $c->id )
		);

		AWDCM_Queue::schedule();
		return count( $r['eligible'] );
	}

	public static function set_status( $id, $status, $error = null ) {
		global $wpdb;
		$row = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);
		if ( null !== $error ) {
			$row['last_error'] = $error;
		}
		$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
	}

	public static function pause( $id, $error = '' ) {
		self::set_status( $id, 'paused', $error );
	}

	public static function resume( $id ) {
		$c = self::get( $id );
		if ( $c && 'paused' === $c->status ) {
			self::set_status( $id, 'sending', '' );
			AWDCM_Queue::schedule();
		}
	}

	public static function cancel( $id ) {
		global $wpdb;
		$wpdb->update(
			self::queue_table(),
			array(
				'status' => 'skipped',
				'error'  => 'Cancelled before sending',
			),
			array(
				'campaign_id' => (int) $id,
				'status'      => 'pending',
			)
		);
		self::set_status( $id, 'cancelled' );
		self::refresh_counts( $id );
	}

	public static function queue_stats( $id ) {
		global $wpdb;
		$out  = array(
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
			'skipped' => 0,
		);
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM ' . self::queue_table() . ' WHERE campaign_id = %d GROUP BY status', (int) $id ) );
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
		}
		return $out;
	}

	public static function queue_rows( $id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT q.*, c.first_name, c.last_name FROM ' . self::queue_table() . ' q LEFT JOIN ' . AWDCM_Contacts::table() . ' c ON c.id = q.contact_id WHERE q.campaign_id = %d ORDER BY q.id ASC',
				(int) $id
			)
		);
	}

	/** Sync the counters and mark the email as sent once the queue is empty. */
	public static function refresh_counts( $id ) {
		global $wpdb;
		$s   = self::queue_stats( $id );
		$row = array(
			'sent_count'    => $s['sent'],
			'failed_count'  => $s['failed'],
			'skipped_count' => $s['skipped'],
		);
		$c   = self::get( $id );
		if ( $c && 'sending' === $c->status && 0 === $s['pending'] ) {
			$row['status']       = 'sent';
			$row['completed_at'] = current_time( 'mysql' );
		}
		$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
		return $s;
	}
}
