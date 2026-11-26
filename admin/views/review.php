<?php
/**
 * Final check before sending.
 *
 * @package AWD_Client_Mailer
 * @var object $campaign
 */

defined( 'ABSPATH' ) || exit;

$s         = AWDCM_Settings::all();
$tag_ids   = AWDCM_Campaigns::tag_ids( $campaign );
$tag_names = array_intersect_key( AWDCM_Contacts::tag_names_by_id(), array_flip( $tag_ids ) );
$r         = AWDCM_Contacts::recipients( $tag_ids );
$count     = count( $r['eligible'] );
$sample    = $count ? $r['eligible'][0] : AWDCM_Renderer::sample_contact();
$preview   = AWDCM_Renderer::render( $campaign, $sample );
$issues    = AWDCM_Settings::setup_issues();
$minutes   = max( 1, (int) ceil( $count / max( 1, (int) $s['batch_size'] ) ) );

AWDCM_Admin::header( 'Review & Send' );
?>
<div class="awdcm-grid">
	<div class="awdcm-card">
		<h2>Summary</h2>
		<table class="awdcm-summary">
			<tr><th>Subject</th><td><?php echo esc_html( $preview['subject'] ); ?></td></tr>
			<tr><th>From</th><td><?php echo esc_html( AWDCM_SMTP2GO::address( $s['from_email'], $s['from_name'] ) ); ?></td></tr>
			<tr><th>Replies go to</th><td><?php echo esc_html( is_email( $s['reply_to'] ) ? $s['reply_to'] : $s['from_email'] ); ?></td></tr>
			<tr><th>Style</th><td><?php echo esc_html( 'plain' === $campaign->style ? 'Plain' : 'Branded' ); ?></td></tr>
			<tr><th>Send to</th><td><?php echo esc_html( $tag_names ? 'Clients tagged: ' . implode( ', ', $tag_names ) : 'All active clients' ); ?></td></tr>
			<tr><th>Recipients</th><td><strong><?php echo (int) $count; ?></strong></td></tr>
		</table>

		<?php if ( $r['excluded'] ) : ?>
			<p><strong>Left out automatically:</strong></p>
			<ul class="awdcm-list">
				<?php foreach ( $r['excluded'] as $reason => $n ) : ?>
					<li><?php echo (int) $n; ?> &times; <?php echo esc_html( AWDCM_Contacts::exclusion_label( $reason ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $count ) : ?>
			<details>
				<summary>See who will receive it</summary>
				<ul class="awdcm-list awdcm-recipients">
					<?php foreach ( $r['eligible'] as $c ) : ?>
						<li><?php echo esc_html( trim( AWDCM_Contacts::full_name( $c ) . ' <' . $c->email . '>' ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>

		<?php if ( $issues ) : ?>
			<div class="notice notice-error inline"><p><strong>Finish the setup first:</strong> <?php echo esc_html( implode( ' ', $issues ) ); ?> <a href="<?php echo esc_url( AWDCM_Admin::url( 'awdcm-settings' ) ); ?>">Go to Settings</a></p></div>
		<?php elseif ( ! $count ) : ?>
			<div class="notice notice-warning inline"><p>There is nobody eligible to receive this email. Check the selected tags or your contacts.</p></div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="awdcm_send_campaign">
				<input type="hidden" name="campaign" value="<?php echo (int) $campaign->id; ?>">
				<?php wp_nonce_field( 'awdcm_send_campaign' ); ?>
				<p class="awdcm-confirm">
					<label><input type="checkbox" name="confirm_consent" value="1" required>
					I confirm these people are current or recent clients, or have agreed to receive emails from <?php echo esc_html( $s['business_name'] ); ?> (Canada's Anti-Spam Legislation).</label>
				</p>
				<p class="description">Emails go out one at a time, about <?php echo (int) $s['batch_size']; ?> per minute, so this should take about <?php echo (int) $minutes; ?> minute<?php echo 1 === $minutes ? '' : 's'; ?>.</p>
				<p class="awdcm-buttons">
					<a class="button button-large" href="<?php echo esc_url( AWDCM_Admin::url( 'awdcm-compose', array( 'campaign' => $campaign->id ) ) ); ?>">Back to editing</a>
					<button type="submit" class="button button-primary button-large">Send to <?php echo (int) $count; ?> client<?php echo 1 === $count ? '' : 's'; ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>

	<div class="awdcm-card">
		<h2>Preview</h2>
		<p class="awdcm-muted">As <?php echo esc_html( $count ? AWDCM_Contacts::full_name( $sample ) : 'a sample client' ); ?> will see it.</p>
		<iframe class="awdcm-preview" sandbox="" srcdoc="<?php echo esc_attr( $preview['html'] ); ?>" title="Email preview"></iframe>
	</div>
</div>
<?php
AWDCM_Admin::footer();
