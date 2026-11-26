<?php
/**
 * Unsubscribe links: signed per contact, with a confirmation page and
 * RFC 8058 one-click support for the "Unsubscribe" button in email apps.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Unsubscribe {

	const PARAM = 'awdcm_unsub';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_handle' ), 1 );
	}

	private static function secret() {
		$secret = get_option( 'awdcm_token_secret' );
		if ( ! $secret ) {
			AWDCM_Settings::ensure_secrets();
			$secret = get_option( 'awdcm_token_secret' );
		}
		return (string) $secret;
	}

	public static function token( $contact ) {
		$id  = (int) $contact->id;
		$sig = substr( hash_hmac( 'sha256', $id . '|' . strtolower( $contact->email ), self::secret() ), 0, 32 );
		return $id . '-' . $sig;
	}

	public static function url( $contact ) {
		$token = empty( $contact->id ) ? 'test' : self::token( $contact );
		return add_query_arg( self::PARAM, $token, home_url( '/' ) );
	}

	public static function verify( $token ) {
		if ( ! preg_match( '/^(\d+)-([a-f0-9]{32})$/', (string) $token, $m ) ) {
			return null;
		}
		$contact = AWDCM_Contacts::get( (int) $m[1] );
		if ( ! $contact ) {
			return null;
		}
		return hash_equals( self::token( $contact ), $token ) ? $contact : null;
	}

	public static function maybe_handle() {
		if ( ! isset( $_GET[ self::PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		$token   = sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$is_post = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );
		$s       = AWDCM_Settings::all();
		$name    = $s['business_name'] ? $s['business_name'] : get_bloginfo( 'name' );

		if ( 'test' === $token ) {
			self::page( 'This was a test email', '<p>This unsubscribe link came from a test email, so nothing was changed. In real emails, this link unsubscribes the person who received it.</p>' );
		}

		$contact = self::verify( $token );

		if ( $is_post ) {
			// phpcs:ignore WordPress.Security.NonceVerification -- the signed token is the authorization.
			$one_click = isset( $_POST['List-Unsubscribe'] ) && 'One-Click' === sanitize_text_field( wp_unslash( $_POST['List-Unsubscribe'] ) );
			if ( $contact && 'unsubscribed' !== $contact->status ) {
				AWDCM_Contacts::set_status( $contact->id, 'unsubscribed', 'unsubscribed', $one_click ? 'Unsubscribed with the one-click button in their email app.' : 'Unsubscribed using the link in an email.' );
			}
			if ( $one_click ) {
				status_header( 200 );
				header( 'Content-Type: text/plain; charset=utf-8' );
				echo 'Unsubscribed';
				exit;
			}
			if ( $contact ) {
				self::page( 'You have been unsubscribed', '<p>You will no longer receive emails from ' . esc_html( $name ) . '.</p><p>Unsubscribed by mistake? Just reply to any of our emails and we will add you back.</p>' );
			}
		}

		if ( ! $contact ) {
			self::page( 'This link is not valid', '<p>We could not find your subscription from this link. To stop receiving emails, reply to any email from ' . esc_html( $name ) . ' and ask to be removed.</p>', 404 );
		}

		if ( 'unsubscribed' === $contact->status ) {
			self::page( 'You are already unsubscribed', '<p>You will not receive any more emails from ' . esc_html( $name ) . '.</p>' );
		}

		$action = add_query_arg( self::PARAM, rawurlencode( $token ), home_url( '/' ) );
		self::page(
			'Unsubscribe',
			'<p>Click the button below to stop receiving emails from ' . esc_html( $name ) . ' at <strong>' . esc_html( self::mask( $contact->email ) ) . '</strong>.</p>'
			. '<form method="post" action="' . esc_url( $action ) . '"><button type="submit">Unsubscribe</button></form>'
		);
	}

	private static function mask( $email ) {
		list( $user, $domain ) = array_pad( explode( '@', $email, 2 ), 2, '' );
		$keep                  = mb_substr( $user, 0, min( 2, mb_strlen( $user ) ) );
		return $keep . str_repeat( '*', max( 1, mb_strlen( $user ) - 2 ) ) . '@' . $domain;
	}

	private static function page( $title, $content, $code = 200 ) {
		$s     = AWDCM_Settings::all();
		$color = sanitize_hex_color( $s['brand_color'] ) ? $s['brand_color'] : '#3059E3';
		$name  = $s['business_name'] ? $s['business_name'] : get_bloginfo( 'name' );
		status_header( $code );
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">';
		echo '<title>' . esc_html( $title . ' | ' . $name ) . '</title>';
		echo '<style>body{margin:0;background:#f3f5f9;font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.6}'
			. '.box{max-width:480px;margin:10vh auto;background:#fff;border-radius:12px;padding:32px;border-top:6px solid ' . esc_attr( $color ) . '}'
			. 'h1{font-size:22px;margin:0 0 12px}.brand{font-size:13px;color:#777;margin-bottom:16px}'
			. 'button{background:' . esc_attr( $color ) . ';color:#fff;border:0;border-radius:999px;padding:12px 28px;font-size:15px;cursor:pointer;min-height:48px}'
			. 'a{color:' . esc_attr( $color ) . '}</style></head><body><div class="box">';
		echo '<div class="brand">' . esc_html( $name ) . '</div>';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo wp_kses(
			$content,
			array(
				'p'      => array(),
				'strong' => array(),
				'form'   => array(
					'method' => true,
					'action' => true,
				),
				'button' => array( 'type' => true ),
			)
		);
		echo '</div></body></html>';
		exit;
	}
}
