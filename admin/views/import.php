<?php
/**
 * CSV import.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

$tags = AWDCM_Contacts::all_tags();

AWDCM_Admin::header( 'Import Contacts' );
AWDCM_Admin::contacts_tabs( 'import' );
?>
<div class="awdcm-grid">
	<div class="awdcm-card">
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="awdcm_import">
			<?php wp_nonce_field( 'awdcm_import' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="awdcm-csv">CSV file</label></th>
					<td><input type="file" id="awdcm-csv" name="csv" accept=".csv,text/csv" required></td>
				</tr>
				<tr>
					<th>Add tags to everyone imported</th>
					<td>
						<div class="awdcm-tag-list">
							<?php foreach ( $tags as $t ) : ?>
								<label><input type="checkbox" name="tag_ids[]" value="<?php echo (int) $t->id; ?>"> <?php echo esc_html( $t->name ); ?></label>
							<?php endforeach; ?>
						</div>
						<input type="text" name="new_tags" class="regular-text" placeholder="New tags, separated by commas">
					</td>
				</tr>
				<tr>
					<th><label for="awdcm-default-consent">Consent type</label></th>
					<td>
						<select id="awdcm-default-consent" name="default_consent">
							<?php foreach ( AWDCM_Contacts::CONSENT_TYPES as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Used for rows that do not have a consent_type column. Existing clients are usually "Implied".</p>
					</td>
				</tr>
				<tr>
					<th>Existing contacts</th>
					<td><label><input type="checkbox" name="update_existing" value="1"> Update contacts that already exist (matched by email)</label>
					<p class="description">Unsubscribed or bounced contacts are never re-activated by an import.</p></td>
				</tr>
			</table>
			<?php submit_button( 'Import contacts' ); ?>
		</form>
	</div>
	<div class="awdcm-card">
		<h2>How to prepare the file</h2>
		<p>Export your client list from Outlook, Excel or Google Sheets as a <strong>CSV</strong> file. The first row should have column names. Only <code>email</code> is required.</p>
		<pre class="awdcm-code">email,first_name,last_name,tags,consent_type,last_transaction
jane@example.com,Jane,Smith,VIP|Lessons,implied,2026-05-14
sam@example.com,Sam,Lee,,express,</pre>
		<ul class="awdcm-list">
			<li><strong>tags</strong>: separate several tags with a <code>|</code></li>
			<li><strong>consent_type</strong>: <code>express</code> or <code>implied</code> (optional)</li>
			<li><strong>dates</strong>: use the format <code>YYYY-MM-DD</code> (optional)</li>
		</ul>
		<p class="awdcm-muted">Addresses that unsubscribed or bounced before are imported as unsubscribed, so nobody is emailed by mistake.</p>
	</div>
</div>
<?php
AWDCM_Admin::footer();
