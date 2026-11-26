<?php
/**
 * All emails list.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

$paged                = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
list( $rows, $total ) = AWDCM_Campaigns::all( $paged, 30 );
$tag_names            = AWDCM_Contacts::tag_names_by_id();

AWDCM_Admin::header( 'Emails', array( 'New Email' => AWDCM_Admin::url( 'awdcm-compose' ) ) );
?>
<table class="widefat striped awdcm-table">
	<thead>
		<tr>
			<th>Subject</th>
			<th>Status</th>
			<th>Recipients</th>
			<th>Sent</th>
			<th>Failed</th>
			<th>Date</th>
		</tr>
	</thead>
	<tbody>
	<?php if ( ! $rows ) : ?>
		<tr><td colspan="6">No emails yet. <a href="<?php echo esc_url( AWDCM_Admin::url( 'awdcm-compose' ) ); ?>">Write your first email</a>.</td></tr>
	<?php endif; ?>
	<?php
	foreach ( $rows as $c ) :
		$is_draft = 'draft' === $c->status;
		$link     = $is_draft ? AWDCM_Admin::url( 'awdcm-compose', array( 'campaign' => $c->id ) ) : AWDCM_Admin::url( 'awdcm-emails', array( 'view' => $c->id ) );
		$tags     = array_intersect_key( $tag_names, array_flip( AWDCM_Campaigns::tag_ids( $c ) ) );
		?>
		<tr>
			<td>
				<strong><a class="row-title" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( '' !== $c->subject ? $c->subject : '(no subject)' ); ?></a></strong>
				<div class="row-actions">
					<span><a href="<?php echo esc_url( $link ); ?>"><?php echo $is_draft ? 'Edit' : 'View'; ?></a> | </span>
					<span><a href="<?php echo esc_url( AWDCM_Admin::action_url( $c->id, 'duplicate' ) ); ?>">Duplicate</a></span>
					<?php if ( 'sending' !== $c->status ) : ?>
						<span> | <a class="awdcm-danger" href="<?php echo esc_url( AWDCM_Admin::action_url( $c->id, 'delete' ) ); ?>" data-awdcm-confirm="Delete this email and its sending history?">Delete</a></span>
					<?php endif; ?>
				</div>
			</td>
			<td><?php echo AWDCM_Admin::status_badge( $c->status, AWDCM_Campaigns::STATUSES ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			<td>
				<?php
				if ( $is_draft ) {
					echo esc_html( $tags ? 'Tags: ' . implode( ', ', $tags ) : 'All clients' );
				} else {
					echo (int) $c->total;
				}
				?>
			</td>
			<td><?php echo $is_draft ? '&ndash;' : (int) $c->sent_count; ?></td>
			<td><?php echo $is_draft ? '&ndash;' : (int) $c->failed_count; ?></td>
			<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $c->sent_at ? $c->sent_at : $c->updated_at ) ); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php
$pages = (int) ceil( $total / 30 );
if ( $pages > 1 ) {
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo wp_kses_post(
		paginate_links(
			array(
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
			)
		)
	);
	echo '</div></div>';
}
AWDCM_Admin::footer();
