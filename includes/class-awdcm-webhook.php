<?php
/**
 * SMTP2GO webhook: bounces, spam complaints and unsubscribes update contacts.
 *
 * Endpoint: /wp-json/awdcm/v1/smtp2go?key=SECRET
 * (an "Authorization: Bearer SECRET" header is also accepted).
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Webhook {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function url() {
		return add_query_arg( 'key', AWDCM_Settings::get( 'webhook_secret' ), rest_url( 'awdcm/v1/smtp2go' ) );
	}

	public static function routes() {
		register_rest_route(
			'awdcm/v1',
			'/smtp2go',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'authorize' ),
			)
		);
	}

	public static function authorize( WP_REST_Request $request ) {
		$secret = (string) AWDCM_Settings::get( 'webhook_secret' );
		if ( '' === $secret ) {
			return false;
		}
		$given = (string) $request->get_param( 'key' );
		if ( '' === $given ) {
			$auth = (string) $request->get_header( 'authorization' );
			if ( preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ) {
				$given = trim( $m[1] );
			}
		}
		return '' !== $given && hash_equals( $secret, $given );
	}

	public static function handle( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( empty( $payload ) ) {
			$payload = $request->get_body_params();
		}
		$events = ( is_array( $payload ) && isset( $payload[0] ) && is_array( $payload[0] ) ) ? $payload : array( $payload );
		foreach ( $events as $event ) {
			if ( is_array( $event ) ) {
				self::process( $event );
			}
		}
		return rest_ensure_response( array( 'ok' => true ) );
	}

	public static function process( array $e ) {
		$event = strtolower( (string) ( $e['event'] ?? '' ) );
		$email = strtolower( sanitize_email( (string) ( $e['rcpt'] ?? ( $e['email'] ?? '' ) ) ) );
		if ( '' === $event || ! is_email( $email ) ) {
			return;
		}
		$contact = AWDCM_Contacts::get_by_email( $email );
		$detail  = trim( sanitize_text_field( (string) ( $e['message'] ?? '' ) ) );

		switch ( $event ) {
			case 'bounce':
				if ( 'hard' === strtolower( (string) ( $e['bounce'] ?? '' ) ) ) {
					self::apply( $contact, $email, 'bounced', 'bounced', 'Email address does not exist or permanently rejected mail (hard bounce).' . ( $detail ? ' ' . $detail : '' ) );
				} else {
					self::log_only( $contact, $email, 'soft_bounce', 'Temporary delivery problem (soft bounce).' . ( $detail ? ' ' . $detail : '' ) );
				}
				break;
			case 'spam':
				self::apply( $contact, $email, 'unsubscribed', 'spam', 'Marked an email as spam. Unsubscribed automatically.' );
				break;
			case 'unsubscribe':
				self::apply( $contact, $email, 'unsubscribed', 'unsubscribed', 'Unsubscribed through SMTP2GO.' );
				break;
			case 'reject':
				self::log_only( $contact, $email, 'rejected', 'SMTP2GO rejected the email (the address may be on its suppression list).' );
				break;
		}
	}

	private static function apply( $contact, $email, $status, $event, $details ) {
		if ( $contact ) {
			if ( 'bounced' === $status && 'unsubscribed' === $contact->status ) {
				AWDCM_Log::add( $contact->id, $email, $event, $details );
				return;
			}
			AWDCM_Contacts::set_status( $contact->id, $status, $event, $details );
		} else {
			// Remember it anyway, so the address stays suppressed if it is added later.
			AWDCM_Log::add( 0, $email, $event, $details );
		}
	}

	private static function log_only( $contact, $email, $event, $details ) {
		AWDCM_Log::add( $contact ? $contact->id : 0, $email, $event, $details );
	}
}
