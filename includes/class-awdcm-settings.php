<?php
/**
 * Plugin settings.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Settings {

	const OPTION = 'awdcm_settings';

	private static $cache = null;

	public static function defaults() {
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		return array(
			'api_key'             => '',
			'from_name'           => $site,
			'from_email'          => get_option( 'admin_email' ),
			'reply_to'            => '',
			'test_email'          => '',
			'batch_size'          => 20,
			'business_name'       => $site,
			'mailing_address'     => '',
			'contact_line'        => '',
			'reason_line'         => 'You are receiving this email because you are a client of {business_name}.',
			'default_style'       => 'branded',
			'logo_id'             => 0,
			'brand_color'         => '#3059E3',
			'track_expiry'        => 0,
			'webhook_secret'      => '',
			'delete_on_uninstall' => 0,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		$new = array_merge( self::all(), $values );
		update_option( self::OPTION, $new, false );
		self::$cache = null;
	}

	/** Generates the webhook secret and the unsubscribe-token secret if missing. */
	public static function ensure_secrets() {
		if ( ! self::get( 'webhook_secret' ) ) {
			self::update( array( 'webhook_secret' => wp_generate_password( 32, false, false ) ) );
		}
		if ( ! get_option( 'awdcm_token_secret' ) ) {
			update_option( 'awdcm_token_secret', wp_generate_password( 64, true, true ), false );
		}
	}

	public static function api_key() {
		if ( defined( 'AWDCM_SMTP2GO_API_KEY' ) && AWDCM_SMTP2GO_API_KEY ) {
			return (string) AWDCM_SMTP2GO_API_KEY;
		}
		return (string) self::get( 'api_key' );
	}

	public static function api_key_from_constant() {
		return defined( 'AWDCM_SMTP2GO_API_KEY' ) && AWDCM_SMTP2GO_API_KEY;
	}

	/** Returns a list of setup problems that block or weaken sending. */
	public static function setup_issues() {
		$issues = array();
		if ( ! self::api_key() ) {
			$issues[] = 'Add your SMTP2GO API key.';
		}
		if ( ! is_email( self::get( 'from_email' ) ) ) {
			$issues[] = 'Set a valid "From" email address.';
		}
		if ( '' === trim( (string) self::get( 'business_name' ) ) || '' === trim( (string) self::get( 'mailing_address' ) ) ) {
			$issues[] = 'Add your business name and mailing address (CASL requires them in every email).';
		}
		return $issues;
	}

	public static function reason_line() {
		return str_replace( '{business_name}', (string) self::get( 'business_name' ), (string) self::get( 'reason_line' ) );
	}
}
