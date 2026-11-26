<?php
/**
 * One sent (or sending) email: progress, results and preview.
 *
 * @package AWD_Client_Mailer
 * @var object $campaign
 */

defined( 'ABSPATH' ) || exit;

$stats    = AWDCM_Campaigns::queue_stats( $campaign->id );
$done     = $stats['sent'] + $stats['failed'] + $stats['skipped'];
$total    = max( 1, (int) $campaign->total );
$pct      = (int) floor( 100 * $done / $total );
$rows     = AWDCM_Campaigns::queue_rows( $campaign->id );
$excluded = json_decode( (string) $campaign->excluded, true );
$preview  = AWDCM_Renderer::render( $campaign, AWDCM_Renderer::sample_contact() );
$q_labels = array(
	'pending' => 'Waiting',
	'sent'    => 'Sent',
	'failed'  => 'Failed',
	'skipped' => 'Skipped',
);

AWDCM_Admin::header( $campaign->subject, array( 'Back to all emails' => AWDCM_Admin::url( 'awdcm-emails' ) ) );
?>
<div class="awdcm-card">
	<div class="awdcm-stats">
		<div><span class="awdcm-stat-label">Status</span><?php echo AWDCM_Admin::status_badge( $campaign->status, AWDCM_Campaigns::STATUSES ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<div><span class="awdcm-stat-label">Recipients</span><strong><?php echo (int) $campaign->total; ?></strong></div>
		<div><span class="awdcm-stat-label">Sent</span><strong id="awdcm-n-sent"><?php echo (int) $stats['sent']; ?></strong></div>
		<div><span class="awdcm-stat-label">Failed</span><strong id="awdcm-n-failed"><?php echo (int) $stats['failed']; ?></strong></div>
		<div><span class="awdcm-stat-label">Skipped</span><strong id="awdcm-n-skipped"><?php echo (int) $stats['skipped']; ?></strong></div>
		<div><span class="awdcm-stat-label">Started</span><?php echo esc_html( $campaign->sent_at ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $campaign->sent_at ) : '' ); ?></div>
	</div>

	<div id="awdcm-progress" data-campaign="<?php echo (int) $campaign->id; ?>" data-status="<?php echo esc_attr( $campaign->status ); ?>" data-total="<?php echo (int) $campaign->total; ?>">
		<div class="awdcm-bar"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
		<p id="awdcm-progress-text">
			<?php
			if ( 'sending' === $campaign->status ) {
				echo esc_html( sprintf( 'Sending: %d of %d done. You can leave this page; sending continues in the background, but it goes faster while this page is open.', $done, $campaign->total ) );
			} else {
				echo esc_html( sprintf( '%d of %d processed.', $done, $campaign->total ) );
			}
			?>
		</p>
	</div>

	<?php if ( $campaign->last_error ) : ?>
		<div class="notice notice-error inline"><p><strong>Sending paused:</strong> <?php echo esc_html( $campaign->last_error ); ?> Fix the problem in <a href="<?php echo esc_url( AWDCM_Admin::url( 'awdcm-settings' ) ); ?>">Settings</a>, then click Resume.</p></div>
	<?php endif; ?>

	<p class="awdcm-buttons">
		<?php if ( 'sending' === $campaign->status ) : ?>
			<a class="button" href="<?php echo esc_url( AWDCM_Admin::action_url( $campaign->id, 'pause' ) ); ?>">Pause</a>
		<?php endif; ?>
		<?php if ( 'paused' === $campaign->status ) : ?>
			<a class="button button-primary" href="<?php echo esc_url( AWDCM_Admin::action_url( $campaign->id, 'resume' ) ); ?>">Resume</a>
		<?php endif; ?>
		<?php if ( in_array( $campaign->status, array( 'sending', 'paused' ), true ) ) : ?>
			<a class="button awdcm-danger" href="<?php echo esc_url( AWDCM_Admin::action_url( $campaign->id, 'cancel' ) ); ?>" data-awdcm-confirm="Stop sending? Clients who have not received it yet will not get it.">Stop sending</a>
		<?php endif; ?>
		<?php if ( $stats['failed'] && in_array( $campaign->status, array( 'sent', 'paused' ), true ) ) : ?>
			<a class="button" href="<?php echo esc_url( AWDCM_Admin::action_url( $campaign->id, 'retry' ) ); ?>">Retry failed</a>
		<?php endif; ?>
		<a class="button" href="<?php echo esc_url( AWDCM_Admin::action_url( $campaign->id, 'duplicate' ) ); ?>">Duplicate as new email</a>
	</p>

	<?php if ( $excluded ) : ?>
		<p class="awdcm-muted"><strong>Not sent to</strong> (excluded when sending started):
			<?php
			$parts = array();
			foreach ( $excluded as $reason => $n ) {
				$parts[] = (int) $n . ' ' . strtolower( AWDCM_Contacts::exclusion_label( $reason ) );
			}
			echo esc_html( implode( ', ', $parts ) );
			?>
		</p>
	<?php endif; ?>
</div>

<div class="awdcm-grid">
	<div class="awdcm-card">
		<h2>Recipients</h2>
		<table class="widefat striped">
			<thead><tr><th>Name</th><th>Email</th><th>Result</th><th>Details</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<td><?php echo esc_html( trim( $r->first_name . ' ' . $r->last_name ) ); ?></td>
					<td><?php echo esc_html( $r->email ); ?></td>
					<td><?php echo AWDCM_Admin::status_badge( $r->status, $q_labels ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td class="awdcm-muted"><?php echo esc_html( $r->error ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="awdcm-muted">Bounces and unsubscribes reported by SMTP2GO show on each contact's activity log.</p>
	</div>
	<div class="awdcm-card">
		<h2>Email preview</h2>
		<p class="awdcm-muted"><?php echo esc_html( 'plain' === $campaign->style ? 'Plain style' : 'Branded style' ); ?>. Shown with sample personalization.</p>
		<iframe class="awdcm-preview" sandbox="" srcdoc="<?php echo esc_attr( $preview['html'] ); ?>" title="Email preview"></iframe>
	</div>
</div>
<?php
AWDCM_Admin::footer();
