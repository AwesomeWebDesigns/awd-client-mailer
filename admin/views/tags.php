<?php
/**
 * Tags.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

$tags = AWDCM_Contacts::all_tags();

AWDCM_Admin::header( 'Tags' );
AWDCM_Admin::contacts_tabs( 'tags' );
?>
<div class="awdcm-grid">
	<div class="awdcm-card">
		<table class="widefat striped">
			<thead><tr><th>Tag</th><th>Contacts</th><th></th></tr></thead>
			<tbody>
			<?php if ( ! $tags ) : ?>
				<tr><td colspan="3">No tags yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $tags as $t ) : ?>
				<tr>
					<td><span class="awdcm-tag"><?php echo esc_html( $t->name ); ?></span></td>
					<td><a href="<?php echo esc_url( AWDCM_Admin::url( 'awdcm-contacts', array( 'tag' => $t->id ) ) ); ?>"><?php echo (int) $t->total; ?></a></td>
					<td><a class="awdcm-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=awdcm_delete_tag&id=' . (int) $t->id ), 'awdcm_delete_tag' ) ); ?>" data-awdcm-confirm="Delete this tag? Contacts are not deleted.">Delete</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="awdcm-card">
		<h2>Add a tag</h2>
		<p class="awdcm-muted">Tags let you email a smaller group, for example "Newsletter", "VIP" or "Past clients".</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="awdcm_save_tag">
			<?php wp_nonce_field( 'awdcm_save_tag' ); ?>
			<input type="text" name="name" class="regular-text" placeholder="Tag name" required>
			<?php submit_button( 'Add tag', 'primary', 'submit', false ); ?>
		</form>
	</div>
</div>
<?php
AWDCM_Admin::footer();
