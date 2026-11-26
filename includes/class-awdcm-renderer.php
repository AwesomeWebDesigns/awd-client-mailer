<?php
/**
 * Builds the HTML and plain-text versions of each email.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Renderer {

	const FONT = 'Arial, Helvetica, sans-serif';

	/**
	 * Replace {first_name}, {last_name}, {email}. A fallback can be given
	 * with a pipe: {first_name|there}.
	 */
	public static function merge( $text, $contact, $escape ) {
		return preg_replace_callback(
			'/\{(first_name|last_name|email)(?:\|([^}]*))?\}/',
			function ( $m ) use ( $contact, $escape ) {
				$field = $m[1];
				$value = trim( (string) ( $contact->$field ?? '' ) );
				if ( '' === $value && isset( $m[2] ) ) {
					$value = $m[2];
				}
				return $escape ? esc_html( $value ) : $value;
			},
			(string) $text
		);
	}

	/**
	 * @param object $campaign Needs subject, body, style.
	 * @param object $contact  Needs id, email, first_name, last_name. id 0 = test send.
	 * @return array subject, html, text, headers, unsubscribe_url
	 */
	public static function render( $campaign, $contact ) {
		$s     = AWDCM_Settings::all();
		$color = sanitize_hex_color( $s['brand_color'] ) ? $s['brand_color'] : '#3059E3';
		$unsub = AWDCM_Unsubscribe::url( $contact );

		$subject = wp_strip_all_tags( self::merge( $campaign->subject, $contact, false ) );
		$body    = wpautop( self::merge( $campaign->body, $contact, true ) );
		$body    = self::inline_body_styles( $body, 'plain' === $campaign->style ? '#1a0dab' : $color );

		$html = 'plain' === $campaign->style
			? self::plain_html( $subject, $body, $unsub, $s )
			: self::branded_html( $subject, $body, $unsub, $s, $color );

		$text = self::to_text( $body ) . "\n\n--\n" . self::footer_text( $unsub, $s );

		$list_unsub = '<' . $unsub . '>';
		if ( is_email( $s['reply_to'] ) || is_email( $s['from_email'] ) ) {
			$mailbox     = is_email( $s['reply_to'] ) ? $s['reply_to'] : $s['from_email'];
			$list_unsub .= ', <mailto:' . $mailbox . '?subject=unsubscribe>';
		}
		$headers = array(
			'List-Unsubscribe'      => $list_unsub,
			'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
		);
		if ( is_email( $s['reply_to'] ) ) {
			$headers['Reply-To'] = $s['reply_to'];
		}

		return array(
			'subject'         => $subject,
			'html'            => $html,
			'text'            => $text,
			'headers'         => $headers,
			'unsubscribe_url' => $unsub,
		);
	}

	/** Email clients ignore stylesheets, so link and image styles go inline. */
	private static function inline_body_styles( $html, $link_color ) {
		$html = preg_replace( '/<a\s(?![^>]*\bstyle=)/i', '<a style="color:' . $link_color . ';text-decoration:underline;" ', $html );
		$html = preg_replace( '/<img\s(?![^>]*\bstyle=)/i', '<img style="max-width:100%;height:auto;border:0;" ', $html );
		$html = preg_replace( '/<p>/i', '<p style="margin:0 0 16px;">', $html );
		return $html;
	}

	public static function footer_html( $unsub, $s, $link_color ) {
		$lines = array();
		if ( '' !== trim( $s['business_name'] ) ) {
			$lines[] = '<strong>' . esc_html( $s['business_name'] ) . '</strong>';
		}
		if ( '' !== trim( $s['mailing_address'] ) ) {
			$lines[] = nl2br( esc_html( trim( $s['mailing_address'] ) ) );
		}
		if ( '' !== trim( $s['contact_line'] ) ) {
			$lines[] = esc_html( $s['contact_line'] );
		}
		return '<p style="margin:0 0 8px;">' . implode( '<br>', $lines ) . '</p>'
			. '<p style="margin:0;">' . esc_html( AWDCM_Settings::reason_line() ) . ' '
			. '<a href="' . esc_url( $unsub ) . '" style="color:' . $link_color . ';text-decoration:underline;">Unsubscribe</a></p>';
	}

	public static function footer_text( $unsub, $s ) {
		$lines = array_filter(
			array(
				trim( $s['business_name'] ),
				trim( $s['mailing_address'] ),
				trim( $s['contact_line'] ),
				'',
				AWDCM_Settings::reason_line(),
				'Unsubscribe: ' . $unsub,
			),
			function ( $l ) {
				return null !== $l;
			}
		);
		return implode( "\n", $lines );
	}

	private static function head( $subject ) {
		return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta name="x-apple-disable-message-reformatting">'
			. '<title>' . esc_html( $subject ) . '</title></head>';
	}

	private static function plain_html( $subject, $body, $unsub, $s ) {
		return self::head( $subject )
			. '<body style="margin:0;padding:16px;background:#ffffff;">'
			. '<div style="max-width:600px;font-family:' . self::FONT . ';font-size:15px;line-height:1.6;color:#222222;">'
			. $body
			. '<div style="margin-top:32px;padding-top:12px;border-top:1px solid #e5e5e5;font-size:12px;line-height:1.5;color:#777777;">'
			. self::footer_html( $unsub, $s, '#777777' )
			. '</div></div></body></html>';
	}

	private static function logo_html( $s, $color ) {
		$logo_id = (int) $s['logo_id'];
		$src     = $logo_id ? wp_get_attachment_image_src( $logo_id, 'medium' ) : false;
		if ( $src ) {
			list( $url, $w, $h ) = $src;
			$height = 56;
			$width  = ( $w && $h ) ? min( 240, (int) round( $height * $w / $h ) ) : 160;
			$height = ( $w && $h ) ? (int) round( $width * $h / $w ) : $height;
			return '<img src="' . esc_url( $url ) . '" width="' . $width . '" height="' . $height . '" alt="' . esc_attr( $s['business_name'] ) . '" style="display:block;border:0;width:' . $width . 'px;height:auto;max-width:100%;">';
		}
		return '<span style="font-family:' . self::FONT . ';font-size:20px;font-weight:bold;color:' . $color . ';">' . esc_html( $s['business_name'] ) . '</span>';
	}

	private static function branded_html( $subject, $body, $unsub, $s, $color ) {
		return self::head( $subject )
			. '<body style="margin:0;padding:0;background:#f3f5f9;">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f5f9;"><tr><td align="center" style="padding:24px 12px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:8px;">'
			. '<tr><td style="background:' . $color . ';height:6px;line-height:6px;font-size:0;border-radius:8px 8px 0 0;">&nbsp;</td></tr>'
			. '<tr><td style="padding:28px 32px 8px;">' . self::logo_html( $s, $color ) . '</td></tr>'
			. '<tr><td style="padding:16px 32px 24px;font-family:' . self::FONT . ';font-size:15px;line-height:1.6;color:#222222;">' . $body . '</td></tr>'
			. '</table>'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">'
			. '<tr><td align="center" style="padding:16px 32px;font-family:' . self::FONT . ';font-size:12px;line-height:1.5;color:#777777;">'
			. self::footer_html( $unsub, $s, '#777777' )
			. '</td></tr></table>'
			. '</td></tr></table></body></html>';
	}

	public static function to_text( $html ) {
		$text = preg_replace_callback(
			'/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',
			function ( $m ) {
				$label = trim( wp_strip_all_tags( $m[2] ) );
				$url   = html_entity_decode( $m[1], ENT_QUOTES );
				return ( '' === $label || $label === $url ) ? $url : $label . ' (' . $url . ')';
			},
			$html
		);
		$text = preg_replace( '/<br\s*\/?>/i', "\n", $text );
		$text = preg_replace( '/<li[^>]*>/i', "\n- ", $text );
		$text = preg_replace( '/<\/(p|div|h[1-6]|ul|ol|blockquote|tr)>/i', "\n\n", $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t]+\n/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( $text );
	}

	/** Contact used for previews and test sends. */
	public static function sample_contact( $first_name = '' ) {
		$user = wp_get_current_user();
		return (object) array(
			'id'         => 0,
			'email'      => $user && $user->exists() ? $user->user_email : 'client@example.com',
			'first_name' => $first_name ? $first_name : ( $user && $user->exists() && $user->first_name ? $user->first_name : 'Alex' ),
			'last_name'  => $user && $user->exists() ? $user->last_name : '',
		);
	}
}
