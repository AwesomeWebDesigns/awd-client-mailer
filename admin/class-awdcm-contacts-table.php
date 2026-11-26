<?php
/**
 * Contacts list table.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AWDCM_Contacts_Table extends WP_List_Table {

	private $tag_map = array();

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'contact',
				'plural'   => 'contacts',
				'ajax'     => false,
			)
		);
	}

	private function req( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => 'Name',
			'email'      => 'Email',
			'tags'       => 'Tags',
			'status'     => 'Status',
			'consent'    => 'Consent',
			'created_at' => 'Added',
		);
	}

	protected function get_sortable_columns() {
		return array(
			'name'       => array( 'first_name', false ),
			'email'      => array( 'email', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	public function prepare_items() {
		$per_page              = 50;
		list( $items, $total ) = AWDCM_Contacts::query(
			array(
				'search'   => $this->req( 's' ),
				'status'   => $this->req( 'status' ),
				'tag'      => absint( $this->req( 'tag' ) ),
				'orderby'  => $this->req( 'orderby' ) ? $this->req( 'orderby' ) : 'created_at',
				'order'    => $this->req( 'order' ) ? $this->req( 'order' ) : 'DESC',
				'per_page' => $per_page,
				'paged'    => $this->get_pagenum(),
			)
		);
		$this->items           = $items;
		$this->tag_map         = AWDCM_Contacts::tags_for_many( wp_list_pluck( $items, 'id' ) );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
			)
		);
	}

	protected function get_views() {
		$counts  = AWDCM_Contacts::status_counts();
		$current = $this->req( 'status' );
		$views   = array();
		$all     = array( '' => 'All' ) + AWDCM_Contacts::STATUSES;
		foreach ( $all as $key => $label ) {
			$url           = $key ? AWDCM_Admin::url( 'awdcm-contacts', array( 'status' => $key ) ) : AWDCM_Admin::url( 'awdcm-contacts' );
			$n             = $key ? $counts[ $key ] : $counts['all'];
			$views[ $key ? $key : 'all' ] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', esc_url( $url ), $current === $key ? 'current' : '', esc_html( $label ), $n );
		}
		return $views;
	}

	public function bulk_action_keys() {
		return $this->get_bulk_actions();
	}

	protected function get_bulk_actions() {
		return array(
			'add_tag'    => 'Add tag',
			'remove_tag' => 'Remove tag',
			'inactive'   => 'Mark inactive',
			'active'     => 'Mark active',
			'delete'     => 'Delete',
		);
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$tags    = AWDCM_Contacts::all_tags();
		$current = absint( $this->req( 'tag' ) );
		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="awdcm-bulk-tag">Tag for bulk action</label>';
		echo '<select name="bulk_tag" id="awdcm-bulk-tag"><option value="">Tag for bulk action</option>';
		foreach ( $tags as $t ) {
			printf( '<option value="%d">%s</option>', (int) $t->id, esc_html( $t->name ) );
		}
		echo '</select></div>';

		echo '<div class="alignleft actions">';
		echo '<label class="screen-reader-text" for="awdcm-filter-tag">Filter by tag</label>';
		echo '<select name="tag" id="awdcm-filter-tag"><option value="">All tags</option>';
		foreach ( $tags as $t ) {
			printf( '<option value="%d" %s>%s (%d)</option>', (int) $t->id, selected( $current, (int) $t->id, false ), esc_html( $t->name ), (int) $t->total );
		}
		echo '</select>';
		submit_button( 'Filter', '', 'filter_action', false );
		echo '</div>';
	}

	public function no_items() {
		echo 'No contacts yet. <a href="' . esc_url( AWDCM_Admin::url( 'awdcm-contacts', array( 'tab' => 'import' ) ) ) . '">Import a CSV</a> or <a href="' . esc_url( AWDCM_Admin::url( 'awdcm-contacts', array( 'tab' => 'new' ) ) ) . '">add one</a>.';
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="contact[]" value="' . (int) $item->id . '" />';
	}

	protected function column_name( $item ) {
		$edit    = AWDCM_Admin::url( 'awdcm-contacts', array( 'tab' => 'edit', 'id' => $item->id ) );
		$delete  = wp_nonce_url( admin_url( 'admin-post.php?action=awdcm_delete_contact&id=' . (int) $item->id ), 'awdcm_delete_contact' );
		$name    = AWDCM_Contacts::full_name( $item );
		$actions = array(
			'edit'   => '<a href="' . esc_url( $edit ) . '">Edit</a>',
			'delete' => '<a href="' . esc_url( $delete ) . '" class="awdcm-danger" data-awdcm-confirm="Delete this contact?">Delete</a>',
		);
		return '<strong><a class="row-title" href="' . esc_url( $edit ) . '">' . esc_html( '' !== $name ? $name : '(no name)' ) . '</a></strong>' . $this->row_actions( $actions );
	}

	protected function column_email( $item ) {
		return esc_html( $item->email );
	}

	protected function column_tags( $item ) {
		$tags = $this->tag_map[ (int) $item->id ] ?? array();
		if ( ! $tags ) {
			return '<span class="awdcm-muted">None</span>';
		}
		return implode( ' ', array_map( fn( $t ) => '<span class="awdcm-tag">' . esc_html( $t ) . '</span>', $tags ) );
	}

	protected function column_status( $item ) {
		$out                 = AWDCM_Admin::status_badge( $item->status, AWDCM_Contacts::STATUSES );
		list( $ok, $reason ) = AWDCM_Contacts::eligibility( $item );
		if ( 'active' === $item->status && ! $ok && 'expired' === $reason ) {
			$out .= '<br><span class="awdcm-warn">Consent expired</span>';
		}
		return $out;
	}

	protected function column_consent( $item ) {
		$label = AWDCM_Contacts::CONSENT_TYPES[ $item->consent_type ] ?? 'Not recorded';
		$out   = esc_html( $label );
		if ( AWDCM_Settings::get( 'track_expiry' ) ) {
			$expiry = AWDCM_Contacts::implied_expiry( $item );
			if ( $expiry ) {
				$out .= '<br><span class="awdcm-muted">until ' . esc_html( $expiry ) . '</span>';
			} elseif ( 'implied' === $item->consent_type ) {
				$out .= '<br><span class="awdcm-muted">no date on file</span>';
			}
		}
		return $out;
	}

	protected function column_created_at( $item ) {
		return esc_html( mysql2date( get_option( 'date_format' ), $item->created_at ) );
	}
}
