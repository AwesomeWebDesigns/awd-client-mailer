<?php
/**
 * Contacts, tags and sending eligibility.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Contacts {

	const STATUSES = array(
		'active'       => 'Active',
		'inactive'     => 'Inactive',
		'unsubscribed' => 'Unsubscribed',
		'bounced'      => 'Bounced',
	);

	const CONSENT_TYPES = array(
		''        => 'Not recorded',
		'express' => 'Express',
		'implied' => 'Implied',
	);

	const EXCLUSION_LABELS = array(
		'inactive'     => 'Inactive',
		'unsubscribed' => 'Unsubscribed',
		'bounced'      => 'Bounced (address does not exist)',
		'expired'      => 'Implied consent expired (more than 2 years since last transaction)',
		'missing'      => 'Contact deleted',
	);

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'awdcm_contacts';
	}

	public static function tags_table() {
		global $wpdb;
		return $wpdb->prefix . 'awdcm_tags';
	}

	public static function pivot_table() {
		global $wpdb;
		return $wpdb->prefix . 'awdcm_contact_tags';
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------- */

	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) );
	}

	public static function get_by_email( $email ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email = %s', strtolower( trim( (string) $email ) ) ) );
	}

	public static function full_name( $c ) {
		return trim( $c->first_name . ' ' . $c->last_name );
	}

	/**
	 * Paged, filtered list for the admin table.
	 *
	 * @return array [ rows, total ]
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$a = wp_parse_args(
			$args,
			array(
				'search'   => '',
				'status'   => '',
				'tag'      => 0,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
				'per_page' => 50,
				'paged'    => 1,
			)
		);

		$where  = array( '1=1' );
		$params = array();
		$join   = '';

		if ( '' !== $a['search'] ) {
			$like     = '%' . $wpdb->esc_like( $a['search'] ) . '%';
			$where[]  = '(c.email LIKE %s OR c.first_name LIKE %s OR c.last_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( $a['status'] && isset( self::STATUSES[ $a['status'] ] ) ) {
			$where[]  = 'c.status = %s';
			$params[] = $a['status'];
		}
		if ( (int) $a['tag'] > 0 ) {
			$join = ' INNER JOIN ' . self::pivot_table() . ' ct ON ct.contact_id = c.id AND ct.tag_id = ' . (int) $a['tag'];
		}

		$allowed = array( 'email', 'first_name', 'last_name', 'status', 'created_at', 'last_transaction' );
		$orderby = in_array( $a['orderby'], $allowed, true ) ? $a['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $a['order'] ) ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );
		$count_sql = 'SELECT COUNT(*) FROM ' . self::table() . " c $join WHERE $where_sql";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$per_page = max( 1, (int) $a['per_page'] );
		$offset   = max( 0, ( (int) $a['paged'] - 1 ) * $per_page );
		$sql      = 'SELECT c.* FROM ' . self::table() . " c $join WHERE $where_sql ORDER BY c.$orderby $order, c.id DESC LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) ) );

		return array( $rows, $total );
	}

	public static function status_counts() {
		global $wpdb;
		$out  = array_fill_keys( array_keys( self::STATUSES ), 0 );
		$rows = $wpdb->get_results( 'SELECT status, COUNT(*) AS n FROM ' . self::table() . ' GROUP BY status' );
		foreach ( $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
		}
		$out['all'] = array_sum( $out );
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Writing
	 * ------------------------------------------------------------------- */

	public static function normalize_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		$ts = strtotime( $value );
		return $ts ? gmdate( 'Y-m-d', $ts ) : null;
	}

	public static function sanitize( array $data ) {
		$out = array();
		if ( isset( $data['email'] ) ) {
			$out['email'] = strtolower( sanitize_email( $data['email'] ) );
		}
		foreach ( array( 'first_name', 'last_name' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$out[ $k ] = mb_substr( sanitize_text_field( $data[ $k ] ), 0, 100 );
			}
		}
		if ( isset( $data['status'] ) && isset( self::STATUSES[ $data['status'] ] ) ) {
			$out['status'] = $data['status'];
		}
		if ( isset( $data['consent_type'] ) && isset( self::CONSENT_TYPES[ $data['consent_type'] ] ) ) {
			$out['consent_type'] = $data['consent_type'];
		}
		foreach ( array( 'consent_date', 'last_transaction' ) as $k ) {
			if ( array_key_exists( $k, $data ) ) {
				$out[ $k ] = self::normalize_date( $data[ $k ] );
			}
		}
		if ( isset( $data['notes'] ) ) {
			$out['notes'] = sanitize_textarea_field( $data['notes'] );
		}
		return $out;
	}

	public static function consent_summary( $c ) {
		$c    = (object) $c;
		$type = isset( $c->consent_type ) ? $c->consent_type : '';
		$text = self::CONSENT_TYPES[ $type ] ?? 'Not recorded';
		if ( ! empty( $c->consent_date ) ) {
			$text .= ', consent date ' . $c->consent_date;
		}
		if ( ! empty( $c->last_transaction ) ) {
			$text .= ', last transaction ' . $c->last_transaction;
		}
		return $text;
	}

	/**
	 * @return int|WP_Error New contact ID.
	 */
	public static function insert( array $data, $source = 'added manually' ) {
		global $wpdb;
		$d = self::sanitize( $data );

		if ( empty( $d['email'] ) || ! is_email( $d['email'] ) ) {
			return new WP_Error( 'invalid_email', 'Please enter a valid email address.' );
		}
		if ( self::get_by_email( $d['email'] ) ) {
			return new WP_Error( 'exists', 'A contact with this email address already exists.' );
		}

		$d = array_merge(
			array(
				'first_name'   => '',
				'last_name'    => '',
				'status'       => 'active',
				'consent_type' => '',
			),
			$d
		);

		// Never silently re-subscribe someone who unsubscribed or bounced before.
		$suppressed = AWDCM_Log::suppression_status( $d['email'] );
		if ( $suppressed && 'active' === $d['status'] ) {
			$d['status'] = $suppressed;
		}

		$now             = current_time( 'mysql' );
		$d['created_at'] = $now;
		$d['updated_at'] = $now;

		if ( ! $wpdb->insert( self::table(), $d ) ) {
			return new WP_Error( 'db', 'Could not save the contact.' );
		}
		$id = (int) $wpdb->insert_id;

		AWDCM_Log::add( $id, $d['email'], 'created', ucfirst( $source ) . '. Consent: ' . self::consent_summary( $d ) . '.' );
		if ( $suppressed ) {
			AWDCM_Log::add( $id, $d['email'], 'suppressed', 'This address previously ' . ( 'bounced' === $suppressed ? 'bounced' : 'unsubscribed' ) . ', so it was kept as ' . $suppressed . '.' );
		}
		return $id;
	}

	/**
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		$c = self::get( $id );
		if ( ! $c ) {
			return new WP_Error( 'missing', 'Contact not found.' );
		}
		$d = self::sanitize( $data );

		if ( isset( $d['email'] ) ) {
			if ( ! is_email( $d['email'] ) ) {
				return new WP_Error( 'invalid_email', 'Please enter a valid email address.' );
			}
			$other = self::get_by_email( $d['email'] );
			if ( $other && (int) $other->id !== (int) $c->id ) {
				return new WP_Error( 'exists', 'Another contact already uses this email address.' );
			}
		}

		$reactivating = isset( $d['status'] ) && 'active' === $d['status'] && in_array( $c->status, array( 'unsubscribed', 'bounced' ), true );
		if ( $reactivating && empty( $data['reconsent'] ) ) {
			return new WP_Error( 'reconsent', 'This contact is ' . $c->status . '. To make them active again, confirm that they have given you consent again.' );
		}

		$d['updated_at'] = current_time( 'mysql' );
		$wpdb->update( self::table(), $d, array( 'id' => (int) $c->id ) );

		$email = isset( $d['email'] ) ? $d['email'] : $c->email;

		if ( isset( $d['email'] ) && $d['email'] !== $c->email ) {
			AWDCM_Log::add( $c->id, $email, 'email', 'Email changed from ' . $c->email . ' to ' . $d['email'] . '.' );
		}
		if ( isset( $d['status'] ) && $d['status'] !== $c->status ) {
			if ( $reactivating ) {
				AWDCM_Log::add( $c->id, $email, 'resubscribed', 'Set back to active by an administrator, who confirmed the contact gave consent again.' );
			} elseif ( 'unsubscribed' === $d['status'] ) {
				AWDCM_Log::add( $c->id, $email, 'unsubscribed', 'Unsubscribed manually by an administrator.' );
			} else {
				AWDCM_Log::add( $c->id, $email, 'status', 'Status changed from ' . $c->status . ' to ' . $d['status'] . '.' );
			}
		}
		$consent_changed = false;
		foreach ( array( 'consent_type', 'consent_date', 'last_transaction' ) as $k ) {
			if ( array_key_exists( $k, $d ) && (string) $d[ $k ] !== (string) $c->$k ) {
				$consent_changed = true;
			}
		}
		if ( $consent_changed ) {
			AWDCM_Log::add( $c->id, $email, 'consent', 'Consent: ' . self::consent_summary( array_merge( (array) $c, $d ) ) . '.' );
		}
		return true;
	}

	/** Status change from an automated source (unsubscribe link, webhook). */
	public static function set_status( $id, $status, $event, $details ) {
		global $wpdb;
		$c = self::get( $id );
		if ( ! $c || ! isset( self::STATUSES[ $status ] ) ) {
			return;
		}
		if ( $c->status !== $status ) {
			$wpdb->update(
				self::table(),
				array(
					'status'     => $status,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $id )
			);
		}
		AWDCM_Log::add( $c->id, $c->email, $event, $details );
	}

	public static function delete( array $ids ) {
		global $wpdb;
		$n = 0;
		foreach ( array_map( 'intval', $ids ) as $id ) {
			$c = self::get( $id );
			if ( ! $c ) {
				continue;
			}
			$wpdb->delete( self::pivot_table(), array( 'contact_id' => $id ) );
			$wpdb->delete( self::table(), array( 'id' => $id ) );
			AWDCM_Log::add( $id, $c->email, 'deleted', 'Contact deleted. Status at deletion: ' . $c->status . '.' );
			++$n;
		}
		return $n;
	}

	/* ---------------------------------------------------------------------
	 * Tags
	 * ------------------------------------------------------------------- */

	public static function all_tags() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT t.id, t.name, COUNT(ct.contact_id) AS total FROM ' . self::tags_table() . ' t LEFT JOIN ' . self::pivot_table() . ' ct ON ct.tag_id = t.id GROUP BY t.id, t.name ORDER BY t.name ASC'
		);
	}

	public static function tag_names_by_id() {
		$out = array();
		foreach ( self::all_tags() as $t ) {
			$out[ (int) $t->id ] = $t->name;
		}
		return $out;
	}

	public static function create_tag( $name ) {
		global $wpdb;
		$name = mb_substr( trim( sanitize_text_field( $name ) ), 0, 100 );
		if ( '' === $name ) {
			return 0;
		}
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::tags_table() . ' WHERE name = %s', $name ) );
		if ( $existing ) {
			return (int) $existing;
		}
		$wpdb->insert( self::tags_table(), array( 'name' => $name ) );
		return (int) $wpdb->insert_id;
	}

	public static function delete_tag( $tag_id ) {
		global $wpdb;
		$wpdb->delete( self::pivot_table(), array( 'tag_id' => (int) $tag_id ) );
		$wpdb->delete( self::tags_table(), array( 'id' => (int) $tag_id ) );
	}

	public static function tag_ids_for( $contact_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT tag_id FROM ' . self::pivot_table() . ' WHERE contact_id = %d', (int) $contact_id ) ) );
	}

	/** @return array contact_id => [ tag names ] */
	public static function tags_for_many( array $contact_ids ) {
		global $wpdb;
		$contact_ids = array_filter( array_map( 'intval', $contact_ids ) );
		if ( ! $contact_ids ) {
			return array();
		}
		$in   = implode( ',', $contact_ids );
		$rows = $wpdb->get_results( 'SELECT ct.contact_id, t.name FROM ' . self::pivot_table() . ' ct INNER JOIN ' . self::tags_table() . " t ON t.id = ct.tag_id WHERE ct.contact_id IN ($in) ORDER BY t.name" );
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->contact_id ][] = $r->name;
		}
		return $out;
	}

	public static function set_tags( $contact_id, array $tag_ids ) {
		global $wpdb;
		$wpdb->delete( self::pivot_table(), array( 'contact_id' => (int) $contact_id ) );
		self::add_tag( array( $contact_id ), $tag_ids );
	}

	public static function add_tag( array $contact_ids, $tag_ids ) {
		global $wpdb;
		foreach ( array_filter( array_map( 'intval', (array) $tag_ids ) ) as $tag_id ) {
			foreach ( array_filter( array_map( 'intval', $contact_ids ) ) as $cid ) {
				$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::pivot_table() . ' WHERE contact_id = %d AND tag_id = %d', $cid, $tag_id ) );
				if ( ! $exists ) {
					$wpdb->insert(
						self::pivot_table(),
						array(
							'contact_id' => $cid,
							'tag_id'     => $tag_id,
						),
						array( '%d', '%d' )
					);
				}
			}
		}
	}

	public static function remove_tag( array $contact_ids, $tag_id ) {
		global $wpdb;
		foreach ( array_filter( array_map( 'intval', $contact_ids ) ) as $cid ) {
			$wpdb->delete(
				self::pivot_table(),
				array(
					'contact_id' => $cid,
					'tag_id'     => (int) $tag_id,
				)
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Eligibility (CASL)
	 * ------------------------------------------------------------------- */

	/** Date implied consent runs out, or null if not applicable. */
	public static function implied_expiry( $c ) {
		if ( 'implied' !== $c->consent_type ) {
			return null;
		}
		$base = $c->last_transaction ? $c->last_transaction : $c->consent_date;
		if ( ! $base ) {
			return null;
		}
		return gmdate( 'Y-m-d', strtotime( $base . ' +2 years' ) );
	}

	/** @return array [ bool $eligible, string $reason_key ] */
	public static function eligibility( $c ) {
		if ( ! $c ) {
			return array( false, 'missing' );
		}
		if ( 'active' !== $c->status ) {
			return array( false, $c->status );
		}
		if ( AWDCM_Settings::get( 'track_expiry' ) ) {
			$expiry = self::implied_expiry( $c );
			if ( $expiry && $expiry < current_time( 'Y-m-d' ) ) {
				return array( false, 'expired' );
			}
		}
		return array( true, '' );
	}

	/**
	 * Recipients for a send. Empty $tag_ids means everyone.
	 * Contacts with any of the given tags are included.
	 *
	 * @return array { eligible: object[], excluded: array reason => count }
	 */
	public static function recipients( $tag_ids = array() ) {
		global $wpdb;
		$tag_ids = array_filter( array_map( 'intval', (array) $tag_ids ) );
		if ( $tag_ids ) {
			$in   = implode( ',', $tag_ids );
			$rows = $wpdb->get_results( 'SELECT DISTINCT c.* FROM ' . self::table() . ' c INNER JOIN ' . self::pivot_table() . " ct ON ct.contact_id = c.id WHERE ct.tag_id IN ($in) ORDER BY c.first_name, c.email" );
		} else {
			$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY first_name, email' );
		}
		$eligible = array();
		$excluded = array();
		foreach ( $rows as $c ) {
			list( $ok, $reason ) = self::eligibility( $c );
			if ( $ok ) {
				$eligible[] = $c;
			} else {
				$excluded[ $reason ] = ( $excluded[ $reason ] ?? 0 ) + 1;
			}
		}
		return array(
			'eligible' => $eligible,
			'excluded' => $excluded,
		);
	}

	public static function exclusion_label( $key ) {
		return self::EXCLUSION_LABELS[ $key ] ?? ucfirst( $key );
	}
}
