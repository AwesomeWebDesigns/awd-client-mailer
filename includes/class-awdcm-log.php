<?php
/**
 * Consent and activity log. Rows are kept even when a contact is deleted,
 * so a past unsubscribe is still honoured if the address is added again.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Log {

	const LABELS = array(
		'created'      => 'Added',
		'updated'      => 'Updated',
		'consent'      => 'Consent updated',
		'status'       => 'Status changed',
		'email'        => 'Email changed',
		'unsubscribed' => 'Unsubscribed',
		'resubscribed' => 'Re-subscribed',
		'bounced'      => 'Hard bounce',
		'soft_bounce'  => 'Soft bounce',
		'spam'         => 'Spam complaint',
		'rejected'     => 'Rejected',
		'suppressed'   => 'Kept unsubscribed',
		'deleted'      => 'Deleted',
	);

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'awdcm_log';
	}

	public static function add( $contact_id, $email, $event, $details = '' ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'contact_id' => (int) $contact_id,
				'email'      => strtolower( (string) $email ),
				'event'      => substr( sanitize_key( $event ), 0, 40 ),
				'details'    => (string) $details,
				'user_id'    => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	public static function for_contact( $contact_id, $email, $limit = 100 ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE contact_id = %d OR email = %s ORDER BY id DESC LIMIT %d',
				(int) $contact_id,
				strtolower( (string) $email ),
				(int) $limit
			)
		);
	}

	/**
	 * Returns 'unsubscribed', 'bounced' or '' based on the latest
	 * suppression-related event recorded for this address.
	 */
	public static function suppression_status( $email ) {
		global $wpdb;
		$event = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT event FROM ' . self::table() . " WHERE email = %s AND event IN ('unsubscribed','spam','bounced','resubscribed') ORDER BY id DESC LIMIT 1",
				strtolower( (string) $email )
			)
		);
		if ( in_array( $event, array( 'unsubscribed', 'spam' ), true ) ) {
			return 'unsubscribed';
		}
		if ( 'bounced' === $event ) {
			return 'bounced';
		}
		return '';
	}

	public static function label( $event ) {
		return isset( self::LABELS[ $event ] ) ? self::LABELS[ $event ] : ucfirst( str_replace( '_', ' ', $event ) );
	}
}
