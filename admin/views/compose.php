<?php
/**
 * Write or edit a draft.
 *
 * @package AWD_Client_Mailer
 * @var object|null $campaign
 */

defined( 'ABSPATH' ) || exit;

$tags      = AWDCM_Contacts::all_tags();
$subject   = $campaign ? $campaign->subject : '';
$body      = $campaign ? $campaign->body : "<p>Hi {first_name|there},</p>\n<p></p>\n<p>Thanks,<br>" . esc_html( AWDCM_Settings::get( 'from_name' ) ) . '</p>';
$style     = $campaign ? $campaign->style : AWDCM_Settings::get( 'default_style' );
$tag_ids   = $campaign ? AWDCM_Campaigns::tag_ids( $campaign ) : array();
$mode      = $tag_ids ? 'tags' : 'all';
$test_to   = AWDCM_Settings::get( 'test_email' ) ? AWDCM_Settings::get( 'test_email' ) : wp_get_current_user()->user_email;
$all_count = count( AWDCM_Contacts::recipients()['eligible'] );

AWDCM_Admin::header( $campaign ? 'Edit Email' : 'New Email' );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="awdcm-compose">
	<input type="hidden" name="action" value="awdcm_save_campaign">
	<input type="hidden" name="campaign" value="<?php echo $campaign ? (int) $campaign->id : 0; ?>">
	<?php wp_nonce_field( 'awdcm_save_campaign' ); ?>

	<div class="awdcm-compose-grid">
		<div class="awdcm-compose-main">
			<label for="awdcm-subject" class="awdcm-label">Subject</label>
			<input type="text" id="awdcm-subject" name="subject" class="large-text awdcm-subject" value="<?php echo esc_attr( $subject ); ?>" placeholder="For example: New Saturday sessions are open" required>

			<label class="awdcm-label">Message</label>
			<?php
			wp_editor(
				$body,
				'awdcm_body',
				array(
					'textarea_name' => 'body',
					'textarea_rows' => 18,
					'media_buttons' => true,
					'teeny'         => false,
				)
			);
			?>
			<p class="description">
				Personalize with <code>{first_name}</code> or <code>{last_name}</code>. Add a fallback for contacts without a name: <code>{first_name|there}</code> shows "there".
				Your business details and an unsubscribe link are added at the bottom automatically.
			</p>
		</div>

		<div class="awdcm-compose-side">
			<div class="awdcm-card">
				<h3>Send to</h3>
				<label class="awdcm-radio"><input type="radio" name="recipients_mode" value="all" <?php checked( $mode, 'all' ); ?>> All active clients <span class="awdcm-muted">(<?php echo (int) $all_count; ?>)</span></label>
				<label class="awdcm-radio"><input type="radio" name="recipients_mode" value="tags" <?php checked( $mode, 'tags' ); ?> <?php disabled( ! $tags ); ?>> Clients with these tags</label>
				<div id="awdcm-tag-list" class="awdcm-tag-list">
					<?php if ( ! $tags ) : ?>
						<p class="awdcm-muted">No tags yet. <a href="<?php echo esc_url( AWDCM_Admin::url( 'awdcm-contacts', array( 'tab' => 'tags' ) ) ); ?>">Create tags</a> to email smaller groups.</p>
					<?php endif; ?>
					<?php foreach ( $tags as $t ) : ?>
						<label><input type="checkbox" name="tag_ids[]" value="<?php echo (int) $t->id; ?>" <?php checked( in_array( (int) $t->id, $tag_ids, true ) ); ?>> <?php echo esc_html( $t->name ); ?> <span class="awdcm-muted">(<?php echo (int) $t->total; ?>)</span></label>
					<?php endforeach; ?>
				</div>
				<p class="description">Unsubscribed, bounced and inactive contacts are always left out.</p>
			</div>

			<div class="awdcm-card">
				<h3>Email style</h3>
				<label class="awdcm-radio"><input type="radio" name="style" value="plain" <?php checked( $style, 'plain' ); ?>> <strong>Plain</strong>: looks like a personal email</label>
				<label class="awdcm-radio"><input type="radio" name="style" value="branded" <?php checked( $style, 'branded' ); ?>> <strong>Branded</strong>: logo and brand colour</label>
				<?php AWDCM_Admin::style_help(); ?>
			</div>

			<div class="awdcm-card">
				<h3>Test it first</h3>
				<label for="awdcm-test-to" class="screen-reader-text">Send a test to</label>
				<input type="email" id="awdcm-test-to" name="test_to" class="widefat" value="<?php echo esc_attr( $test_to ); ?>">
				<p><button type="submit" name="do" value="test" class="button">Send test email</button></p>
			</div>

			<div class="awdcm-card awdcm-actions">
				<button type="submit" name="do" value="draft" class="button button-large">Save draft</button>
				<button type="submit" name="do" value="review" class="button button-primary button-large">Review &amp; send</button>
			</div>
		</div>
	</div>
</form>
<?php
AWDCM_Admin::footer();
