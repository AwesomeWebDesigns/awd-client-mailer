<?php
/**
 * Plugin Name:       AWD Client Mailer
 * Plugin URI:        https://awddigital.ca/
 * Description:       Send simple, CASL-friendly emails to your clients from WordPress, delivered one by one through SMTP2GO.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            AWD Digital
 * Author URI:        https://awddigital.ca/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       awd-client-mailer
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

define( 'AWDCM_VERSION', '1.0.0' );
define( 'AWDCM_FILE', __FILE__ );
define( 'AWDCM_DIR', plugin_dir_path( __FILE__ ) );
define( 'AWDCM_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'AWDCM_CAP' ) ) {
	// Capability required to use the plugin. Override in wp-config.php if needed.
	define( 'AWDCM_CAP', 'manage_options' );
}

require_once AWDCM_DIR . 'includes/class-awdcm-install.php';
require_once AWDCM_DIR . 'includes/class-awdcm-settings.php';
require_once AWDCM_DIR . 'includes/class-awdcm-log.php';
require_once AWDCM_DIR . 'includes/class-awdcm-contacts.php';
require_once AWDCM_DIR . 'includes/class-awdcm-campaigns.php';
require_once AWDCM_DIR . 'includes/class-awdcm-renderer.php';
require_once AWDCM_DIR . 'includes/class-awdcm-smtp2go.php';
require_once AWDCM_DIR . 'includes/class-awdcm-queue.php';
require_once AWDCM_DIR . 'includes/class-awdcm-unsubscribe.php';
require_once AWDCM_DIR . 'includes/class-awdcm-webhook.php';

register_activation_hook( __FILE__, array( 'AWDCM_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AWDCM_Queue', 'unschedule' ) );

add_action(
	'plugins_loaded',
	function () {
		AWDCM_Install::maybe_upgrade();
		AWDCM_Queue::init();
		AWDCM_Unsubscribe::init();
		AWDCM_Webhook::init();

		if ( is_admin() ) {
			require_once AWDCM_DIR . 'admin/class-awdcm-admin.php';
			AWDCM_Admin::init();
		}
	}
);
