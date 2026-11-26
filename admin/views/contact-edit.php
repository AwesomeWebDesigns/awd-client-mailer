<?php
/**
 * Add or edit a contact.
 *
 * @package AWD_Client_Mailer
 * @var object|null $contact
 */

defined( 'ABSPATH' ) || exit;

$is_new   = empty( $contact );
$c        = $contact ? $contact : (object) array(
	'id'               => 0,
	'email'            => '',
	'first_name'       => '',
	'last_name'        => '',
	'status'           => 'active',
	'consent_type'     => '',
	'consent_date'     => current_time( 'Y-m-d' ),
	'last_transaction' => '',
	'notes'            => '',
);
$tags     = AWDCM_Contacts::all_tags();
$mine     = $is_new ? array() : AWDCM_Contacts::tag_ids_for( $c->id );
$tracking = (bool) AWDCM_Settings::get( 'track_expiry' );
$blocked  = in_array( $c->status, array( 'unsubscribed', 'bounced' ), true );

AWDCM_Admin::header( $is_new ? 'Add Contact' : 'Edit Contact' );
AWDCM_Admin::contacts_tabs( $is_new ? 'new' : 'edit' );
?>
<div class="awdcm-grid">
	<div class="awdcm-card">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="awdcm_save_contact">
			<input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">
			<?php wp_nonce_field( 'awdcm_save_contact' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="awdcm-email">Email</label></th>
					<td><input type="email" id="awdcm-email" name="email" class="regular-text" value="<?php echo esc_attr( $c->email ); ?>" required></td>
				</tr>
				<tr>
					<th><label for="awdcm-first">First name</label></th>
					<td><input type="text" id="awdcm-first" name="first_name" class="regular-text" value="<?php echo esc_attr( $c->first_name ); ?>"></td>
				</tr>
				<tr>
					<th><label for="awdcm-last">Last name</label></th>
					<td><input type="text" id="awdcm-last" name="last_name" class="regular-text" value="<?php echo esc_attr( $c->last_name ); ?>"></td>
				</tr>
				<tr>
					<th>Tags</th>
					<td>
						<div class="awdcm-tag-list">
							<?php foreach ( $tags as $t ) : ?>
								<label><input type="checkbox" name="tag_ids[]" value="<?php echo (int) $t->id; ?>" <?php checked( in_array( (int) $t->id, $mine, true ) ); ?>> <?php echo esc_html( $t->name ); ?></label>
							<?php endforeach; ?>
						</div>
						<input type="text" name="new_tags" class="regular-text" placeholder="Add new tags, separated by commas">
					</td>
				</tr>
				<tr>
					<th><label for="awdcm-status">Status</label></th>
					<td>
						<?php if ( $blocked ) : ?>
							<p><?php echo AWDCM_Admin::status_badge( $c->status, AWDCM_Contacts::STATUSES ); // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
							<p class="description">This contact <?php echo 'bounced' === $c->status ? 'bounced (the address did not accept email)' : 'unsubscribed'; ?>, so they will not receive emails.</p>
							<label><input type="checkbox" name="status" value="active"> Make active again</label><br>
							<label><input type="checkbox" name="reconsent" value="1"> <?php echo 'bounced' === $c->status ? 'I have checked the address is correct and the client wants to receive emails' : 'The client has given me consent again (for example, asked in writing to be added back)'; ?></label>
						<?php else : ?>
							<select id="awdcm-status" name="status">
								<option value="active" <?php selected( $c->status, 'active' ); ?>>Active: receives emails</option>
								<option value="inactive" <?php selected( $c->status, 'inactive' ); ?>>Inactive: paused, receives nothing</option>
								<option value="unsubscribed" <?php selected( $c->status, 'unsubscribed' ); ?>>Unsubscribed: asked to stop</option>
							</select>
							<p class="description">Mark a client inactive when you have not seen them in a while. Use "Unsubscribed" if they ask you in person or by phone to stop.</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="awdcm-consent">Consent type</label></th>
					<td>
						<select id="awdcm-consent" name="consent_type">
							<?php foreach ( AWDCM_Contacts::CONSENT_TYPES as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $c->consent_type, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><strong>Express</strong>: they clearly agreed to receive emails (for example, a sign-up form or a written "yes"). <strong>Implied</strong>: they are a client who bought from you, but never explicitly agreed. Optional.</p>
					</td>
				</tr>
				<tr>
					<th><label for="awdcm-consent-date">Consent date</label></th>
					<td><input type="date" id="awdcm-consent-date" name="consent_date" value="<?php echo esc_attr( (string) $c->consent_date ); ?>"> <span class="description">Optional.</span></td>
				</tr>
				<tr>
					<th><label for="awdcm-last-tx">Last transaction</label></th>
					<td>
						<input type="date" id="awdcm-last-tx" name="last_transaction" value="<?php echo esc_attr( (string) $c->last_transaction ); ?>">
						<p class="description">
							<?php if ( $tracking ) : ?>
								Consent expiry tracking is on: implied consent ends 2 years after this date, and the contact is then left out automatically.
							<?php else : ?>
								Optional. Only used when consent expiry tracking is turned on in <a href="<?php echo esc_url( AWDCM_Admin::url( 'awdcm-settings' ) ); ?>">Settings</a>.
							<?php endif; ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><label for="awdcm-notes">Notes</label></th>
					<td><textarea id="awdcm-notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea( (string) $c->notes ); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button( $is_new ? 'Add contact' : 'Save contact' ); ?>
		</form>
	</div>

	<?php if ( ! $is_new ) : ?>
		<div class="awdcm-card">
			<h2>Can they receive emails?</h2>
			<?php
			list( $ok, $reason ) = AWDCM_Contacts::eligibility( $c );
			if ( $ok ) {
				echo '<p class="awdcm-ok">Yes.</p>';
				$expiry = $tracking ? AWDCM_Contacts::implied_expiry( $c ) : null;
				if ( $expiry ) {
					echo '<p class="awdcm-muted">Implied consent is valid until ' . esc_html( $expiry ) . '.</p>';
				}
			} else {
				echo '<p class="awdcm-warn">No: ' . esc_html( AWDCM_Contacts::exclusion_label( $reason ) ) . '.</p>';
			}
			?>
			<h2>Activity and consent log</h2>
			<p class="awdcm-muted">A permanent record of consent and unsubscribes, useful if CASL compliance is ever questioned.</p>
			<ul class="awdcm-log">
				<?php foreach ( AWDCM_Log::for_contact( $c->id, $c->email ) as $entry ) : ?>
					<li>
						<span class="awdcm-log-date"><?php echo esc_html( mysql2date( 'Y-m-d H:i', $entry->created_at ) ); ?></span>
						<strong><?php echo esc_html( AWDCM_Log::label( $entry->event ) ); ?></strong>
						<span><?php echo esc_html( $entry->details ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
<?php
AWDCM_Admin::footer();
