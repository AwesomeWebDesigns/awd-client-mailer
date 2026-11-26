<?php
/**
 * Removes plugin data on uninstall, only if the site owner opted in.
 *
 * @package AWD_Client_Mailer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$awdcm_settings = get_option( 'awdcm_settings', array() );
if ( empty( $awdcm_settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
foreach ( array( 'contacts', 'tags', 'contact_tags', 'campaigns', 'queue', 'log' ) as $awdcm_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'awdcm_' . $awdcm_table ); // phpcs:ignore WordPress.DB
}
foreach ( array( 'awdcm_settings', 'awdcm_db_version', 'awdcm_token_secret', 'awdcm_queue_lock' ) as $awdcm_option ) {
	delete_option( $awdcm_option );
}
wp_clear_scheduled_hook( 'awdcm_process_queue' );
