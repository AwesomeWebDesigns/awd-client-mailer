<?php
/**
 * SMTP2GO API client (https://developers.smtp2go.com/).
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_SMTP2GO {

	const ENDPOINT = 'https://api.smtp2go.com/v3/email/send';

	/**
	 * Send one email to one recipient.
	 *
	 * @return array ok (bool), id (string), error (string), fatal (bool: stop the whole send, e.g. bad API key).
	 */
	public static function send( $to_email, $to_name, $subject, $html, $text, array $headers = array() ) {
		$key = AWDCM_Settings::api_key();
		$s   = AWDCM_Settings::all();

		if ( ! $key ) {
			return self::fail( 'The SMTP2GO API key is not set.', true );
		}
		if ( ! is_email( $s['from_email'] ) ) {
			return self::fail( 'The "From" email address is not set.', true );
		}

		$custom = array();
		foreach ( $headers as $name => $value ) {
			$custom[] = array(
				'header' => $name,
				'value'  => $value,
			);
		}

		$payload = array(
			'sender'         => self::address( $s['from_email'], $s['from_name'] ),
			'to'             => array( self::address( $to_email, $to_name ) ),
			'subject'        => $subject,
			'html_body'      => $html,
			'text_body'      => $text,
			'custom_headers' => $custom,
		);

		/** Filter the payload before it is sent to SMTP2GO. */
		$payload = apply_filters( 'awdcm_smtp2go_payload', $payload );

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'      => 'application/json',
					'Accept'            => 'application/json',
					'X-Smtp2go-Api-Key' => $key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return self::fail( 'Could not reach SMTP2GO: ' . $response->get_error_message(), false );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $body ) && isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();

		if ( 200 === $code && ! empty( $data['succeeded'] ) ) {
			return array(
				'ok'    => true,
				'id'    => (string) ( $data['email_id'] ?? '' ),
				'error' => '',
				'fatal' => false,
			);
		}

		$error = '';
		if ( ! empty( $data['error'] ) ) {
			$error = (string) $data['error'];
		} elseif ( ! empty( $data['failures'] ) ) {
			$parts = array();
			foreach ( (array) $data['failures'] as $f ) {
				$parts[] = is_scalar( $f ) ? (string) $f : wp_json_encode( $f );
			}
			$error = implode( '; ', $parts );
		} else {
			$error = 'SMTP2GO returned HTTP ' . $code . '.';
		}

		// Problems that will affect every recipient: stop and let the admin fix them.
		$fatal = in_array( $code, array( 401, 403 ), true )
			|| false !== stripos( $error, 'api key' )
			|| false !== stripos( $error, 'api_key' )
			|| ( false !== stripos( $error, 'sender' ) && false !== stripos( $error, 'verif' ) );

		return self::fail( $error, $fatal );
	}

	private static function fail( $error, $fatal ) {
		return array(
			'ok'    => false,
			'id'    => '',
			'error' => $error,
			'fatal' => $fatal,
		);
	}

	public static function address( $email, $name ) {
		$name = trim( preg_replace( '/[\r\n"<>]+/', '', (string) $name ) );
		if ( '' === $name ) {
			return $email;
		}
		if ( preg_match( '/[,;:()@\[\]]/', $name ) ) {
			$name = '"' . $name . '"';
		}
		return $name . ' <' . $email . '>';
	}
}
