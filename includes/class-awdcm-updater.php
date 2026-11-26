<?php
/**
 * Updates from the AWD Digital GitHub repository.
 *
 * New versions appear on the WordPress Plugins screen like any other plugin
 * update. Each GitHub release has an "awd-client-mailer.zip" asset built by
 * the release workflow, and that zip is what gets installed.
 *
 * Optional wp-config.php constants:
 *   AWDCM_GITHUB_TOKEN      Read-only token, needed only while the repository is private.
 *   AWDCM_DISABLE_UPDATES   Set to true to turn update checks off on a site.
 *
 * Uses Plugin Update Checker by Janis Elsts (MIT licence), bundled in lib/.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Updater {

	const REPO = 'https://github.com/AwesomeWebDesigns/awd-client-mailer/';

	/** @var object|null The Plugin Update Checker instance. */
	public static $checker = null;

	public static function init() {
		if ( defined( 'AWDCM_DISABLE_UPDATES' ) && AWDCM_DISABLE_UPDATES ) {
			return;
		}

		$library = AWDCM_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $library ) ) {
			return;
		}
		require_once $library;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v6\PucFactory' ) ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v6\PucFactory::buildUpdateChecker(
			apply_filters( 'awdcm_update_repository', self::REPO ),
			AWDCM_FILE,
			'awd-client-mailer'
		);

		$checker->setBranch( 'main' );

		if ( defined( 'AWDCM_GITHUB_TOKEN' ) && AWDCM_GITHUB_TOKEN ) {
			$checker->setAuthentication( AWDCM_GITHUB_TOKEN );
		}

		// Install the zip built by the release workflow, not GitHub's source archive.
		$checker->getVcsApi()->enableReleaseAssets( '/awd-client-mailer\.zip($|[?&#])/i' );

		self::$checker = $checker;
	}
}
