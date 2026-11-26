<?php
/**
 * Database tables and default options.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Install {

	const DB_VERSION = '1';

	public static function activate() {
		self::create_tables();
		AWDCM_Settings::ensure_secrets();
		update_option( 'awdcm_db_version', self::DB_VERSION, false );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'awdcm_db_version' ) !== self::DB_VERSION ) {
			self::activate();
		}
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'awdcm_';

		dbDelta(
			"CREATE TABLE {$p}contacts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(190) NOT NULL,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'active',
			consent_type varchar(20) NOT NULL DEFAULT '',
			consent_date date NULL DEFAULT NULL,
			last_transaction date NULL DEFAULT NULL,
			notes text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}tags (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(100) NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY name (name)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}contact_tags (
			contact_id bigint(20) unsigned NOT NULL,
			tag_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (contact_id,tag_id),
			KEY tag_id (tag_id)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}campaigns (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			subject varchar(255) NOT NULL DEFAULT '',
			body longtext NULL,
			style varchar(20) NOT NULL DEFAULT 'branded',
			tag_ids varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'draft',
			total int(11) NOT NULL DEFAULT 0,
			sent_count int(11) NOT NULL DEFAULT 0,
			failed_count int(11) NOT NULL DEFAULT 0,
			skipped_count int(11) NOT NULL DEFAULT 0,
			excluded text NULL,
			last_error text NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			sent_at datetime NULL DEFAULT NULL,
			completed_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			campaign_id bigint(20) unsigned NOT NULL,
			contact_id bigint(20) unsigned NOT NULL,
			email varchar(190) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
			error text NULL,
			message_id varchar(100) NOT NULL DEFAULT '',
			updated_at datetime NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY campaign_status (campaign_id,status)
			) $c;"
		);

		dbDelta(
			"CREATE TABLE {$p}log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(190) NOT NULL DEFAULT '',
			event varchar(40) NOT NULL,
			details text NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY email (email)
			) $c;"
		);
	}

	public static function table_names() {
		global $wpdb;
		$p = $wpdb->prefix . 'awdcm_';
		return array( $p . 'contacts', $p . 'tags', $p . 'contact_tags', $p . 'campaigns', $p . 'queue', $p . 'log' );
	}
}
