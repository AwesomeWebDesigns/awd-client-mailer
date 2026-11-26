<?php
/**
 * Contacts list.
 *
 * @package AWD_Client_Mailer
 * @var AWDCM_Contacts_Table $table
 */

defined( 'ABSPATH' ) || exit;

AWDCM_Admin::header( 'Contacts', array( 'Add Contact' => AWDCM_Admin::url( 'awdcm-contacts', array( 'tab' => 'new' ) ) ) );
AWDCM_Admin::contacts_tabs( '' );
?>
<?php $table->views(); ?>
<form method="get">
	<input type="hidden" name="page" value="awdcm-contacts">
	<?php
	// phpcs:ignore WordPress.Security.NonceVerification
	if ( ! empty( $_GET['status'] ) ) {
		echo '<input type="hidden" name="status" value="' . esc_attr( sanitize_key( $_GET['status'] ) ) . '">'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	$table->search_box( 'Search contacts', 'awdcm-search' );
	$table->display();
	?>
</form>
<?php
AWDCM_Admin::footer();
