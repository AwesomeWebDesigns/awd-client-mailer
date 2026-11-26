<?php
/**
 * Settings.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

$s        = AWDCM_Settings::all();
$constant = AWDCM_Settings::api_key_from_constant();
$has_key  = '' !== AWDCM_Settings::api_key();
$logo     = $s['logo_id'] ? wp_get_attachment_image_url( (int) $s['logo_id'], 'medium' ) : '';
$domain   = is_email( $s['from_email'] ) ? substr( strrchr( $s['from_email'], '@' ), 1 ) : 'yourdomain.com';

AWDCM_Admin::header( 'Client Mailer Settings' );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="awdcm-settings">
	<input type="hidden" name="action" value="awdcm_save_settings">
	<?php wp_nonce_field( 'awdcm_save_settings' ); ?>

	<div class="awdcm-card">
		<h2>Sending</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="awdcm-api-key">SMTP2GO API key</label></th>
				<td>
					<?php if ( $constant ) : ?>
						<p><span class="awdcm-ok">Set in wp-config.php</span> (<code>AWDCM_SMTP2GO_API_KEY</code>).</p>
					<?php else : ?>
						<input type="password" id="awdcm-api-key" name="api_key" class="regular-text" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr( 'Saved. Leave blank to keep it.' ) : 'api-XXXXXXXXXXXXXXXX'; ?>">
						<?php if ( $has_key ) : ?>
							<label><input type="checkbox" name="remove_api_key" value="1"> Remove saved key</label>
						<?php endif; ?>
						<p class="description">In SMTP2GO, go to <strong>Sending &gt; API Keys</strong> and create a key with permission to send emails. For extra security, define it in wp-config.php instead: <code>define( 'AWDCM_SMTP2GO_API_KEY', 'api-...' );</code></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="awdcm-from-name">From name</label></th>
				<td><input type="text" id="awdcm-from-name" name="from_name" class="regular-text" value="<?php echo esc_attr( $s['from_name'] ); ?>">
				<p class="description">A person's name (for example "Alex at Your Business") feels more personal and is opened more often.</p></td>
			</tr>
			<tr>
				<th><label for="awdcm-from-email">From email</label></th>
				<td><input type="email" id="awdcm-from-email" name="from_email" class="regular-text" value="<?php echo esc_attr( $s['from_email'] ); ?>">
				<p class="description">Must be on a domain you have verified in SMTP2GO.</p></td>
			</tr>
			<tr>
				<th><label for="awdcm-reply-to">Reply-to email</label></th>
				<td><input type="email" id="awdcm-reply-to" name="reply_to" class="regular-text" value="<?php echo esc_attr( $s['reply_to'] ); ?>">
				<p class="description">Where client replies go, usually your normal inbox. Leave blank to use the From email.</p></td>
			</tr>
			<tr>
				<th><label for="awdcm-batch">Emails per minute</label></th>
				<td><input type="number" id="awdcm-batch" name="batch_size" min="1" max="100" value="<?php echo (int) $s['batch_size']; ?>" class="small-text">
				<p class="description">Sending gradually looks more natural to inbox providers. 20 is a good default.</p></td>
			</tr>
		</table>
	</div>

	<div class="awdcm-card">
		<h2>Business details (CASL footer)</h2>
		<p class="awdcm-muted">Canada's Anti-Spam Legislation requires every commercial email to identify the sender, include a mailing address and a way to contact you, and offer an unsubscribe link. These details are added to the bottom of every email automatically.</p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="awdcm-business">Business name</label></th>
				<td><input type="text" id="awdcm-business" name="business_name" class="regular-text" value="<?php echo esc_attr( $s['business_name'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="awdcm-address">Mailing address</label></th>
				<td><textarea id="awdcm-address" name="mailing_address" rows="3" class="regular-text"><?php echo esc_textarea( $s['mailing_address'] ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="awdcm-contact-line">Phone or website</label></th>
				<td><input type="text" id="awdcm-contact-line" name="contact_line" class="regular-text" value="<?php echo esc_attr( $s['contact_line'] ); ?>" placeholder="905-555-0123 | example.com"></td>
			</tr>
			<tr>
				<th><label for="awdcm-reason">Why they get this email</label></th>
				<td><input type="text" id="awdcm-reason" name="reason_line" class="large-text" value="<?php echo esc_attr( $s['reason_line'] ); ?>">
				<p class="description"><code>{business_name}</code> is replaced with your business name.</p></td>
			</tr>
		</table>
	</div>

	<div class="awdcm-card">
		<h2>Email style</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th>Default style</th>
				<td>
					<label class="awdcm-radio"><input type="radio" name="default_style" value="plain" <?php checked( $s['default_style'], 'plain' ); ?>> Plain</label>
					<label class="awdcm-radio"><input type="radio" name="default_style" value="branded" <?php checked( $s['default_style'], 'branded' ); ?>> Branded</label>
					<p class="description">You can still choose a different style for each email.</p>
					<?php AWDCM_Admin::style_help( true ); ?>
				</td>
			</tr>
			<tr>
				<th>Logo</th>
				<td>
					<input type="hidden" id="awdcm-logo-id" name="logo_id" value="<?php echo (int) $s['logo_id']; ?>">
					<div id="awdcm-logo-preview" class="awdcm-logo-preview">
						<?php if ( $logo ) : ?>
							<img src="<?php echo esc_url( $logo ); ?>" alt="">
						<?php endif; ?>
					</div>
					<button type="button" class="button" id="awdcm-logo-select">Choose logo</button>
					<button type="button" class="button-link awdcm-danger" id="awdcm-logo-remove">Remove</button>
					<p class="description">Used in branded emails. A wide PNG or JPG works best. Without a logo, your business name is shown instead.</p>
				</td>
			</tr>
			<tr>
				<th><label for="awdcm-color">Brand colour</label></th>
				<td><input type="text" id="awdcm-color" name="brand_color" class="awdcm-color" value="<?php echo esc_attr( $s['brand_color'] ); ?>" data-default-color="#3059E3"></td>
			</tr>
		</table>
	</div>

	<div class="awdcm-card">
		<h2>Consent tracking</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th>Consent expiry</th>
				<td>
					<label><input type="checkbox" name="track_expiry" value="1" <?php checked( $s['track_expiry'] ); ?>> Automatically stop emailing implied-consent contacts 2 years after their last transaction</label>
					<p class="description">Off by default. When it is off, you manage your list manually: mark contacts inactive or delete them when you no longer do business with them. Contacts with no date on file are never blocked.</p>
				</td>
			</tr>
		</table>
		<div class="awdcm-callout">
			<strong>Your responsibilities under CASL</strong>
			<ul class="awdcm-list">
				<li>Only email people who are clients (implied consent) or who agreed to hear from you (express consent).</li>
				<li>Implied consent from a purchase lasts 2 years after the last transaction. Remove or deactivate clients you have not done business with in that time, unless they gave express consent.</li>
				<li>Unsubscribes are processed instantly by this plugin. If someone asks you in person or by phone, mark them as unsubscribed.</li>
			</ul>
			<p class="awdcm-muted">This plugin gives you the tools to comply; it is not legal advice.</p>
		</div>
	</div>

	<div class="awdcm-card">
		<h2>Bounces and spam complaints (SMTP2GO webhook)</h2>
		<p>In SMTP2GO, go to <strong>Settings &gt; Webhooks</strong>, add this address, and select the <strong>bounce</strong>, <strong>spam</strong> and <strong>unsubscribe</strong> events. Contacts are then updated automatically.</p>
		<p><input type="text" class="large-text code" readonly value="<?php echo esc_attr( AWDCM_Webhook::url() ); ?>" onclick="this.select();"></p>
		<p><button type="submit" name="regenerate_secret" value="1" class="button-link" data-awdcm-confirm="Create a new webhook address? The old one stops working.">Create a new webhook address</button></p>
	</div>

	<div class="awdcm-card">
		<h2>Data</h2>
		<label><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $s['delete_on_uninstall'] ); ?>> Delete all contacts, emails and logs when the plugin is deleted</label>
		<p class="description">Leave this off unless you are sure. Export your contacts first.</p>
	</div>

	<?php submit_button( 'Save settings' ); ?>
</form>

<div class="awdcm-card">
	<h2>Deliverability checklist</h2>
	<p class="awdcm-muted">Do this once per website so emails land in the inbox instead of junk. Your web developer or IT person can help.</p>
	<ol class="awdcm-list">
		<li><strong>Verify your sending domain in SMTP2GO</strong> (Sending &gt; Verified Senders &gt; Add a sender domain). Add the CNAME records it gives you to your DNS. This sets up DKIM and the return path for <code><?php echo esc_html( $domain ); ?></code>.</li>
		<li><strong>SPF:</strong> if you send other email from this domain (for example Microsoft 365), keep a single SPF record that includes all your senders.</li>
		<li><strong>DMARC:</strong> add a TXT record at <code>_dmarc.<?php echo esc_html( $domain ); ?></code>, starting with <code>v=DMARC1; p=none; rua=mailto:you@<?php echo esc_html( $domain ); ?></code>.</li>
		<li><strong>Microsoft 365 users:</strong> also turn on DKIM for your domain in the Microsoft Defender portal, so your everyday Outlook emails are protected too.</li>
		<li><strong>Webhook:</strong> add the webhook address above so bounces and complaints update your contacts.</li>
		<li><strong>Test:</strong> send a test email to the address from <a href="https://www.mail-tester.com/" target="_blank" rel="noopener">mail-tester.com</a> and aim for 9/10 or higher.</li>
	</ol>
</div>
<?php
AWDCM_Admin::footer();
