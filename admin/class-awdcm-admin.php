<?php
/**
 * Admin screens and form handlers.
 *
 * @package AWD_Client_Mailer
 */

defined( 'ABSPATH' ) || exit;

class AWDCM_Admin {

	const CONTACT_URL = 'https://awddigital.ca/?utm_source=awd-client-mailer&utm_medium=plugin&utm_campaign=upgrade';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AWDCM_FILE ), array( __CLASS__, 'action_links' ) );

		$actions = array( 'save_contact', 'delete_contact', 'import', 'export', 'save_tag', 'delete_tag', 'save_campaign', 'send_campaign', 'campaign_action', 'save_settings' );
		foreach ( $actions as $a ) {
			add_action( 'admin_post_awdcm_' . $a, array( __CLASS__, 'handle_' . $a ) );
		}
	}

	public static function menu() {
		add_menu_page( 'Client Mailer', 'Client Mailer', AWDCM_CAP, 'awdcm-emails', array( __CLASS__, 'page_emails' ), 'dashicons-email-alt', 26 );
		add_submenu_page( 'awdcm-emails', 'Emails', 'All Emails', AWDCM_CAP, 'awdcm-emails', array( __CLASS__, 'page_emails' ) );
		$compose  = add_submenu_page( 'awdcm-emails', 'New Email', 'New Email', AWDCM_CAP, 'awdcm-compose', array( __CLASS__, 'page_compose' ) );
		$contacts = add_submenu_page( 'awdcm-emails', 'Contacts', 'Contacts', AWDCM_CAP, 'awdcm-contacts', array( __CLASS__, 'page_contacts' ) );
		add_submenu_page( 'awdcm-emails', 'Client Mailer Settings', 'Settings', AWDCM_CAP, 'awdcm-settings', array( __CLASS__, 'page_settings' ) );
		add_action( 'load-' . $contacts, array( __CLASS__, 'contacts_bulk' ) );
		add_action( 'load-' . $compose, array( __CLASS__, 'compose_load' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'awdcm' ) ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'awdcm-admin', AWDCM_URL . 'assets/admin.css', array(), AWDCM_VERSION );
		wp_enqueue_script( 'awdcm-admin', AWDCM_URL . 'assets/admin.js', array( 'jquery', 'wp-color-picker' ), AWDCM_VERSION, true );
		wp_localize_script(
			'awdcm-admin',
			'AWDCM',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'awdcm_process' ),
			)
		);
		if ( false !== strpos( $hook, 'awdcm-settings' ) ) {
			wp_enqueue_media();
		}
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( 'awdcm-settings' ) ) . '">Settings</a>' );
		return $links;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	public static function url( $page, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	public static function redirect( $page, array $args = array() ) {
		wp_safe_redirect( self::url( $page, $args ) );
		exit;
	}

	private static function guard( $nonce_action ) {
		if ( ! current_user_can( AWDCM_CAP ) ) {
			wp_die( 'You do not have permission to do this.', 403 );
		}
		check_admin_referer( $nonce_action );
	}

	public static function notice( $message, $type = 'success' ) {
		$key   = 'awdcm_notice_' . get_current_user_id();
		$queue = get_transient( $key );
		$queue = is_array( $queue ) ? $queue : array();
		$queue[] = array( $type, $message );
		set_transient( $key, $queue, 120 );
	}

	private static function render_notices() {
		$key   = 'awdcm_notice_' . get_current_user_id();
		$queue = get_transient( $key );
		if ( ! is_array( $queue ) ) {
			return;
		}
		delete_transient( $key );
		foreach ( $queue as $n ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $n[0] ), wp_kses_post( $n[1] ) );
		}
	}

	private static function post( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- callers verify the nonce first.
		return isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
	}

	private static function get( $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
	}

	/** Page header shared by every screen. */
	public static function header( $title, $actions = array() ) {
		echo '<div class="wrap awdcm">';
		echo '<h1 class="wp-heading-inline">' . esc_html( $title ) . '</h1>';
		foreach ( $actions as $label => $href ) {
			echo ' <a href="' . esc_url( $href ) . '" class="page-title-action">' . esc_html( $label ) . '</a>';
		}
		echo '<hr class="wp-header-end">';
		self::upgrade_banner();
		self::render_notices();

		$issues = AWDCM_Settings::setup_issues();
		if ( $issues && self::get( 'page' ) !== 'awdcm-settings' ) {
			echo '<div class="notice notice-warning"><p><strong>Finish the setup before sending:</strong> ' . esc_html( implode( ' ', $issues ) ) . ' <a href="' . esc_url( self::url( 'awdcm-settings' ) ) . '">Go to Settings</a></p></div>';
		}
	}

	public static function footer() {
		echo '</div>';
	}

	public static function upgrade_banner() {
		/** Hide the AWD Digital banner with add_filter( 'awdcm_show_upgrade_banner', '__return_false' ). */
		if ( ! apply_filters( 'awdcm_show_upgrade_banner', true ) ) {
			return;
		}
		?>
		<div class="awdcm-upgrade">
			<div class="awdcm-upgrade-text">
				<span class="awdcm-upgrade-label">AWD Digital</span>
				<strong>Looking to add more features?</strong>
				<span>Scheduled sends, automations, reports, CRM and booking integrations: we can build what your business needs.</span>
			</div>
			<a class="awdcm-pill" href="<?php echo esc_url( apply_filters( 'awdcm_contact_url', self::CONTACT_URL ) ); ?>" target="_blank" rel="noopener">contact us</a>
		</div>
		<?php
	}

	public static function status_badge( $status, $labels ) {
		$label = $labels[ $status ] ?? ucfirst( $status );
		return '<span class="awdcm-badge awdcm-badge-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
	}

	/** Explains plain vs branded. Used on the compose and settings screens. */
	public static function style_help( $open = false ) {
		?>
		<details class="awdcm-style-help" <?php echo $open ? 'open' : ''; ?>>
			<summary>Plain or branded: which should I use?</summary>
			<div class="awdcm-cols">
				<div>
					<h4>Plain</h4>
					<p>Looks like a personal email written to each client.</p>
					<ul class="awdcm-pros">
						<li>Best chance of landing in the main inbox</li>
						<li>Feels personal, so clients are more likely to read and reply</li>
						<li>Looks the same in every email app</li>
					</ul>
					<ul class="awdcm-cons">
						<li>No logo or brand colours</li>
						<li>Long announcements can feel like a wall of text</li>
					</ul>
				</div>
				<div>
					<h4>Branded</h4>
					<p>Your logo, brand colour and a clean one-column layout.</p>
					<ul class="awdcm-pros">
						<li>Looks professional and instantly recognizable</li>
						<li>Better for visual news, like a new service with a photo</li>
					</ul>
					<ul class="awdcm-cons">
						<li>More likely to land in the Promotions tab or junk, especially with large images</li>
						<li>Many email apps hide images until the reader allows them</li>
						<li>Reads like a newsletter, so some clients only skim it</li>
					</ul>
				</div>
			</div>
			<p><strong>Tip:</strong> use plain for personal updates and short news, and branded for announcements with a photo. Keep images small and always include text.</p>
		</details>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Pages
	 * ------------------------------------------------------------------- */

	public static function page_emails() {
		$view = absint( self::get( 'view' ) );
		if ( $view ) {
			$campaign = AWDCM_Campaigns::get( $view );
			if ( $campaign ) {
				include AWDCM_DIR . 'admin/views/email-view.php';
				return;
			}
		}
		include AWDCM_DIR . 'admin/views/emails.php';
	}

	/** Sent emails cannot be edited: send them to their results page before output starts. */
	public static function compose_load() {
		$id       = absint( self::get( 'campaign' ) );
		$campaign = $id ? AWDCM_Campaigns::get( $id ) : null;
		if ( $campaign && 'draft' !== $campaign->status ) {
			self::redirect( 'awdcm-emails', array( 'view' => $campaign->id ) );
		}
	}

	public static function page_compose() {
		$id       = absint( self::get( 'campaign' ) );
		$campaign = $id ? AWDCM_Campaigns::get( $id ) : null;
		if ( $campaign && 'review' === self::get( 'step' ) ) {
			include AWDCM_DIR . 'admin/views/review.php';
			return;
		}
		include AWDCM_DIR . 'admin/views/compose.php';
	}

	public static function page_contacts() {
		$action = self::get( 'tab' );
		if ( 'edit' === $action || 'new' === $action ) {
			$contact = 'edit' === $action ? AWDCM_Contacts::get( absint( self::get( 'id' ) ) ) : null;
			include AWDCM_DIR . 'admin/views/contact-edit.php';
			return;
		}
		if ( 'import' === $action ) {
			include AWDCM_DIR . 'admin/views/import.php';
			return;
		}
		if ( 'tags' === $action ) {
			include AWDCM_DIR . 'admin/views/tags.php';
			return;
		}
		require_once AWDCM_DIR . 'admin/class-awdcm-contacts-table.php';
		$table = new AWDCM_Contacts_Table();
		$table->prepare_items();
		include AWDCM_DIR . 'admin/views/contacts.php';
	}

	public static function page_settings() {
		include AWDCM_DIR . 'admin/views/settings.php';
	}

	public static function contacts_tabs( $current ) {
		$tabs = array(
			''       => 'All Contacts',
			'new'    => 'Add Contact',
			'import' => 'Import',
			'tags'   => 'Tags',
		);
		echo '<nav class="nav-tab-wrapper awdcm-tabs">';
		foreach ( $tabs as $key => $label ) {
			$url = $key ? self::url( 'awdcm-contacts', array( 'tab' => $key ) ) : self::url( 'awdcm-contacts' );
			printf( '<a href="%s" class="nav-tab %s">%s</a>', esc_url( $url ), $current === $key ? 'nav-tab-active' : '', esc_html( $label ) );
		}
		$export = wp_nonce_url( admin_url( 'admin-post.php?action=awdcm_export' ), 'awdcm_export' );
		printf( '<a href="%s" class="nav-tab">Export CSV</a>', esc_url( $export ) );
		echo '</nav>';
	}

	/* ---------------------------------------------------------------------
	 * Contacts handlers
	 * ------------------------------------------------------------------- */

	public static function handle_save_contact() {
		self::guard( 'awdcm_save_contact' );
		$id   = absint( self::post( 'id' ) );
		$data = array(
			'email'            => self::post( 'email' ),
			'first_name'       => self::post( 'first_name' ),
			'last_name'        => self::post( 'last_name' ),
			'consent_type'     => self::post( 'consent_type' ),
			'consent_date'     => self::post( 'consent_date' ),
			'last_transaction' => self::post( 'last_transaction' ),
			'notes'            => self::post( 'notes' ),
		);
		$status = sanitize_key( self::post( 'status' ) );
		if ( $status ) {
			$data['status'] = $status;
		}
		$data['reconsent'] = (bool) self::post( 'reconsent' );

		$result = $id ? AWDCM_Contacts::update( $id, $data ) : AWDCM_Contacts::insert( $data );
		if ( is_wp_error( $result ) ) {
			self::notice( esc_html( $result->get_error_message() ), 'error' );
			self::redirect( 'awdcm-contacts', $id ? array( 'tab' => 'edit', 'id' => $id ) : array( 'tab' => 'new' ) );
		}
		$contact_id = $id ? $id : (int) $result;

		$tag_ids = array_map( 'absint', (array) self::post( 'tag_ids', array() ) );
		foreach ( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( self::post( 'new_tags' ) ) ) ) ) as $name ) {
			$tag_ids[] = AWDCM_Contacts::create_tag( $name );
		}
		AWDCM_Contacts::set_tags( $contact_id, array_filter( $tag_ids ) );

		$contact = AWDCM_Contacts::get( $contact_id );
		if ( ! $id && $contact && 'active' !== $contact->status ) {
			self::notice( 'Contact added, but kept as <strong>' . esc_html( $contact->status ) . '</strong> because this address unsubscribed or bounced before. See the activity log below.', 'warning' );
		} else {
			self::notice( $id ? 'Contact updated.' : 'Contact added.' );
		}
		self::redirect( 'awdcm-contacts', array( 'tab' => 'edit', 'id' => $contact_id ) );
	}

	public static function handle_delete_contact() {
		self::guard( 'awdcm_delete_contact' );
		AWDCM_Contacts::delete( array( absint( self::get( 'id' ) ) ) );
		self::notice( 'Contact deleted. Their unsubscribe history is kept, so they will not be emailed by mistake if added again.' );
		self::redirect( 'awdcm-contacts' );
	}

	/** Bulk actions from the contacts list (runs before the page renders). */
	public static function contacts_bulk() {
		require_once AWDCM_DIR . 'admin/class-awdcm-contacts-table.php';
		$table  = new AWDCM_Contacts_Table();
		$action = $table->current_action();
		if ( ! $action || ! array_key_exists( $action, $table->bulk_action_keys() ) ) {
			return;
		}
		if ( ! current_user_can( AWDCM_CAP ) ) {
			wp_die( 'You do not have permission to do this.', 403 );
		}
		check_admin_referer( 'bulk-contacts' );

		// phpcs:ignore WordPress.Security.NonceVerification
		$ids = isset( $_REQUEST['contact'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['contact'] ) ) : array();
		$ids = array_filter( $ids );
		$tag = absint( self::get( 'bulk_tag' ) );

		if ( ! $ids ) {
			self::notice( 'Select at least one contact first.', 'warning' );
		} else {
			switch ( $action ) {
				case 'add_tag':
				case 'remove_tag':
					if ( ! $tag ) {
						self::notice( 'Choose a tag in the "Tag for bulk action" menu first.', 'warning' );
						break;
					}
					'add_tag' === $action ? AWDCM_Contacts::add_tag( $ids, $tag ) : AWDCM_Contacts::remove_tag( $ids, $tag );
					self::notice( sprintf( 'Tag %s %d contact(s).', 'add_tag' === $action ? 'added to' : 'removed from', count( $ids ) ) );
					break;
				case 'inactive':
				case 'active':
					$done    = 0;
					$skipped = 0;
					foreach ( $ids as $id ) {
						$r = AWDCM_Contacts::update( $id, array( 'status' => $action ) );
						is_wp_error( $r ) ? ++$skipped : ++$done;
					}
					$msg = sprintf( '%d contact(s) marked %s.', $done, $action );
					if ( $skipped ) {
						$msg .= sprintf( ' %d unsubscribed or bounced contact(s) were left unchanged: open each one to reactivate it after they give consent again.', $skipped );
					}
					self::notice( $msg, $skipped ? 'warning' : 'success' );
					break;
				case 'delete':
					$n = AWDCM_Contacts::delete( $ids );
					self::notice( sprintf( '%d contact(s) deleted.', $n ) );
					break;
			}
		}
		$keep = array();
		foreach ( array( 'status', 'tag', 's', 'paged' ) as $k ) {
			if ( '' !== self::get( $k ) ) {
				$keep[ $k ] = self::get( $k );
			}
		}
		self::redirect( 'awdcm-contacts', $keep );
	}

	public static function handle_save_tag() {
		self::guard( 'awdcm_save_tag' );
		$id = AWDCM_Contacts::create_tag( self::post( 'name' ) );
		self::notice( $id ? 'Tag saved.' : 'Enter a tag name.', $id ? 'success' : 'warning' );
		self::redirect( 'awdcm-contacts', array( 'tab' => 'tags' ) );
	}

	public static function handle_delete_tag() {
		self::guard( 'awdcm_delete_tag' );
		AWDCM_Contacts::delete_tag( absint( self::get( 'id' ) ) );
		self::notice( 'Tag deleted. The contacts themselves were not changed.' );
		self::redirect( 'awdcm-contacts', array( 'tab' => 'tags' ) );
	}

	/* ---------------------------------------------------------------------
	 * Import / export
	 * ------------------------------------------------------------------- */

	const HEADER_ALIASES = array(
		'email'            => array( 'email', 'e-mail', 'email_address', 'emailaddress', 'mail' ),
		'first_name'       => array( 'first_name', 'firstname', 'first', 'given_name', 'name' ),
		'last_name'        => array( 'last_name', 'lastname', 'last', 'surname', 'family_name' ),
		'tags'             => array( 'tags', 'tag', 'groups', 'group' ),
		'consent_type'     => array( 'consent_type', 'consent' ),
		'consent_date'     => array( 'consent_date' ),
		'last_transaction' => array( 'last_transaction', 'last_purchase', 'last_visit', 'last_lesson', 'last_service' ),
		'status'           => array( 'status' ),
		'notes'            => array( 'notes', 'note' ),
	);

	private static function map_headers( array $headers ) {
		$map = array();
		foreach ( $headers as $i => $h ) {
			$key = strtolower( trim( preg_replace( '/[\s\-]+/', '_', (string) $h ) ) );
			foreach ( self::HEADER_ALIASES as $field => $aliases ) {
				if ( in_array( $key, $aliases, true ) && ! isset( $map[ $field ] ) ) {
					$map[ $field ] = $i;
				}
			}
		}
		return $map;
	}

	public static function handle_import() {
		self::guard( 'awdcm_import' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- tmp path checked with is_uploaded_file.
		$file = isset( $_FILES['csv'] ) ? $_FILES['csv'] : null;
		if ( ! $file || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::notice( 'Please choose a CSV file to upload.', 'error' );
			self::redirect( 'awdcm-contacts', array( 'tab' => 'import' ) );
		}
		$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
			self::notice( 'The file must be a .csv file. In Excel or Google Sheets, use "Save as" or "Download" and choose CSV.', 'error' );
			self::redirect( 'awdcm-contacts', array( 'tab' => 'import' ) );
		}

		$update_existing = (bool) self::post( 'update_existing' );
		$default_consent = sanitize_key( self::post( 'default_consent' ) );
		$bulk_tags       = array_filter( array_map( 'absint', (array) self::post( 'tag_ids', array() ) ) );
		foreach ( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( self::post( 'new_tags' ) ) ) ) ) as $name ) {
			$bulk_tags[] = AWDCM_Contacts::create_tag( $name );
		}

		$fh    = fopen( $file['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$first = (string) fgets( $fh );
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$delim = ',';
		foreach ( array( ';', "\t" ) as $d ) {
			if ( substr_count( $first, $d ) > substr_count( $first, $delim ) ) {
				$delim = $d;
			}
		}
		$header = str_getcsv( trim( $first ), $delim );
		$map    = self::map_headers( $header );
		$rows   = array();

		if ( ! isset( $map['email'] ) ) {
			if ( isset( $header[0] ) && is_email( trim( $header[0] ) ) ) {
				// No header row: assume email, first name, last name.
				$map    = array(
					'email'      => 0,
					'first_name' => 1,
					'last_name'  => 2,
				);
				$rows[] = $header;
			} else {
				fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				self::notice( 'We could not find an "email" column. Make sure the first row has column names, for example: email, first_name, last_name.', 'error' );
				self::redirect( 'awdcm-contacts', array( 'tab' => 'import' ) );
			}
		}

		while ( ( $r = fgetcsv( $fh, 0, $delim ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition
			$rows[] = $r;
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$tag_sep = ';' === $delim ? '/[|,]/' : '/[|;]/';
		$counts  = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'invalid' => 0,
			'held'    => 0,
		);

		foreach ( $rows as $r ) {
			$val = function ( $field ) use ( $r, $map ) {
				return isset( $map[ $field ], $r[ $map[ $field ] ] ) ? trim( (string) $r[ $map[ $field ] ] ) : '';
			};
			$email = strtolower( $val( 'email' ) );
			if ( '' === $email && '' === implode( '', $r ) ) {
				continue;
			}
			if ( ! is_email( $email ) ) {
				++$counts['invalid'];
				continue;
			}

			$data = array();
			foreach ( array( 'first_name', 'last_name', 'consent_date', 'last_transaction', 'notes' ) as $f ) {
				if ( '' !== $val( $f ) ) {
					$data[ $f ] = $val( $f );
				}
			}
			$consent = strtolower( $val( 'consent_type' ) );
			if ( isset( AWDCM_Contacts::CONSENT_TYPES[ $consent ] ) && '' !== $consent ) {
				$data['consent_type'] = $consent;
			}
			$status = strtolower( $val( 'status' ) );
			if ( in_array( $status, array( 'active', 'inactive', 'unsubscribed' ), true ) ) {
				$data['status'] = $status;
			}

			$tags = $bulk_tags;
			foreach ( array_filter( array_map( 'trim', preg_split( $tag_sep, $val( 'tags' ) ) ) ) as $name ) {
				$tags[] = AWDCM_Contacts::create_tag( $name );
			}

			$existing = AWDCM_Contacts::get_by_email( $email );
			if ( $existing ) {
				if ( ! $update_existing ) {
					++$counts['skipped'];
					continue;
				}
				if ( isset( $data['status'] ) && 'active' === $data['status'] && in_array( $existing->status, array( 'unsubscribed', 'bounced' ), true ) ) {
					unset( $data['status'] ); // Never re-subscribe through an import.
				}
				if ( $data ) {
					AWDCM_Contacts::update( $existing->id, $data );
				}
				AWDCM_Contacts::add_tag( array( $existing->id ), $tags );
				++$counts['updated'];
				continue;
			}

			if ( ! isset( $data['consent_type'] ) && isset( AWDCM_Contacts::CONSENT_TYPES[ $default_consent ] ) ) {
				$data['consent_type'] = $default_consent;
			}
			$data['email'] = $email;
			$new_id        = AWDCM_Contacts::insert( $data, 'imported from CSV (' . sanitize_file_name( $file['name'] ) . ')' );
			if ( is_wp_error( $new_id ) ) {
				++$counts['invalid'];
				continue;
			}
			AWDCM_Contacts::add_tag( array( $new_id ), $tags );
			++$counts['created'];
			$c = AWDCM_Contacts::get( $new_id );
			if ( $c && 'active' !== $c->status && ( ! isset( $data['status'] ) || 'active' === $data['status'] ) ) {
				++$counts['held'];
			}
		}

		$msg = sprintf( 'Import finished: %d added, %d updated, %d already existed and were skipped, %d rows with an invalid email.', $counts['created'], $counts['updated'], $counts['skipped'], $counts['invalid'] );
		if ( $counts['held'] ) {
			$msg .= sprintf( ' %d new contact(s) were kept unsubscribed because they had unsubscribed or bounced before.', $counts['held'] );
		}
		self::notice( $msg, $counts['invalid'] || $counts['held'] ? 'warning' : 'success' );
		self::redirect( 'awdcm-contacts' );
	}

	/** Spreadsheet apps treat cells starting with these characters as formulas. */
	private static function csv_safe( $value ) {
		$value = (string) $value;
		return ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $value : $value;
	}

	public static function handle_export() {
		if ( ! current_user_can( AWDCM_CAP ) ) {
			wp_die( 'You do not have permission to do this.', 403 );
		}
		check_admin_referer( 'awdcm_export' );
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . AWDCM_Contacts::table() . ' ORDER BY email ASC' );
		$tags = AWDCM_Contacts::tags_for_many( wp_list_pluck( $rows, 'id' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=client-contacts-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'email', 'first_name', 'last_name', 'status', 'tags', 'consent_type', 'consent_date', 'last_transaction', 'notes', 'created_at' ) );
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array_map(
					array( __CLASS__, 'csv_safe' ),
					array( $r->email, $r->first_name, $r->last_name, $r->status, implode( '|', $tags[ (int) $r->id ] ?? array() ), $r->consent_type, $r->consent_date, $r->last_transaction, $r->notes, $r->created_at )
				)
			);
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Email handlers
	 * ------------------------------------------------------------------- */

	public static function handle_save_campaign() {
		self::guard( 'awdcm_save_campaign' );
		$id   = absint( self::post( 'campaign' ) );
		$mode = sanitize_key( self::post( 'recipients_mode' ) );
		$data = array(
			'subject' => self::post( 'subject' ),
			'body'    => self::post( 'body' ),
			'style'   => sanitize_key( self::post( 'style' ) ),
			'tag_ids' => 'tags' === $mode ? array_map( 'absint', (array) self::post( 'tag_ids', array() ) ) : array(),
		);
		$result = AWDCM_Campaigns::save_draft( $data, $id );
		if ( is_wp_error( $result ) ) {
			self::notice( esc_html( $result->get_error_message() ), 'error' );
			self::redirect( 'awdcm-emails' );
		}
		$id = (int) $result;
		$do = sanitize_key( self::post( 'do' ) );

		if ( 'tags' === $mode && ! $data['tag_ids'] && 'draft' !== $do ) {
			self::notice( 'You chose "Clients with these tags" but did not tick any tags.', 'warning' );
			self::redirect( 'awdcm-compose', array( 'campaign' => $id ) );
		}

		if ( 'test' === $do ) {
			$to = sanitize_email( self::post( 'test_to' ) );
			if ( ! is_email( $to ) ) {
				self::notice( 'Enter a valid email address for the test.', 'error' );
				self::redirect( 'awdcm-compose', array( 'campaign' => $id ) );
			}
			AWDCM_Settings::update( array( 'test_email' => $to ) );
			$campaign = AWDCM_Campaigns::get( $id );
			$sample   = AWDCM_Renderer::sample_contact();
			$msg      = AWDCM_Renderer::render( $campaign, $sample );
			$result   = AWDCM_SMTP2GO::send( $to, '', '[TEST] ' . $msg['subject'], $msg['html'], $msg['text'], $msg['headers'] );
			if ( $result['ok'] ) {
				self::notice( 'Test email sent to ' . esc_html( $to ) . '. Check your inbox (and the junk folder, just in case).' );
			} else {
				self::notice( 'The test email could not be sent: ' . esc_html( $result['error'] ), 'error' );
			}
			self::redirect( 'awdcm-compose', array( 'campaign' => $id ) );
		}

		if ( 'review' === $do ) {
			self::redirect( 'awdcm-compose', array( 'campaign' => $id, 'step' => 'review' ) );
		}

		self::notice( 'Draft saved.' );
		self::redirect( 'awdcm-compose', array( 'campaign' => $id ) );
	}

	public static function handle_send_campaign() {
		self::guard( 'awdcm_send_campaign' );
		$id = absint( self::post( 'campaign' ) );
		if ( ! self::post( 'confirm_consent' ) ) {
			self::notice( 'Please tick the confirmation box before sending.', 'warning' );
			self::redirect( 'awdcm-compose', array( 'campaign' => $id, 'step' => 'review' ) );
		}
		$result = AWDCM_Campaigns::start( $id );
		if ( is_wp_error( $result ) ) {
			self::notice( esc_html( $result->get_error_message() ), 'error' );
			self::redirect( 'awdcm-compose', array( 'campaign' => $id, 'step' => 'review' ) );
		}
		self::notice( sprintf( 'Sending to %d client(s). You can leave this page; sending continues in the background.', $result ) );
		self::redirect( 'awdcm-emails', array( 'view' => $id ) );
	}

	public static function handle_campaign_action() {
		self::guard( 'awdcm_campaign_action' );
		$id = absint( self::get( 'campaign' ) );
		$do = sanitize_key( self::get( 'do' ) );
		$c  = AWDCM_Campaigns::get( $id );
		if ( ! $c ) {
			self::redirect( 'awdcm-emails' );
		}
		switch ( $do ) {
			case 'duplicate':
				$new = AWDCM_Campaigns::duplicate( $id );
				self::notice( 'Copied into a new draft.' );
				self::redirect( 'awdcm-compose', array( 'campaign' => $new ) );
				break;
			case 'delete':
				AWDCM_Campaigns::delete( $id );
				self::notice( 'Email deleted.' );
				self::redirect( 'awdcm-emails' );
				break;
			case 'cancel':
				AWDCM_Campaigns::cancel( $id );
				self::notice( 'Sending stopped. Clients who had not received it yet will not get it.' );
				break;
			case 'pause':
				AWDCM_Campaigns::pause( $id, '' );
				self::notice( 'Sending paused.' );
				break;
			case 'resume':
				AWDCM_Campaigns::resume( $id );
				self::notice( 'Sending resumed.' );
				break;
			case 'retry':
				global $wpdb;
				$n = $wpdb->update(
					AWDCM_Campaigns::queue_table(),
					array(
						'status'   => 'pending',
						'attempts' => 0,
					),
					array(
						'campaign_id' => $id,
						'status'      => 'failed',
					)
				);
				if ( $n ) {
					AWDCM_Campaigns::set_status( $id, 'sending', '' );
					AWDCM_Queue::schedule();
					self::notice( sprintf( 'Retrying %d failed email(s).', $n ) );
				}
				break;
		}
		self::redirect( 'awdcm-emails', array( 'view' => $id ) );
	}

	public static function action_url( $campaign_id, $do ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=awdcm_campaign_action&campaign=' . (int) $campaign_id . '&do=' . $do ), 'awdcm_campaign_action' );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	public static function handle_save_settings() {
		self::guard( 'awdcm_save_settings' );

		if ( self::post( 'regenerate_secret' ) ) {
			AWDCM_Settings::update( array( 'webhook_secret' => wp_generate_password( 32, false, false ) ) );
			self::notice( 'A new webhook address was created. Update it in SMTP2GO.', 'warning' );
			self::redirect( 'awdcm-settings' );
		}

		$color = sanitize_hex_color( self::post( 'brand_color' ) );
		$v     = array(
			'from_name'           => sanitize_text_field( self::post( 'from_name' ) ),
			'from_email'          => sanitize_email( self::post( 'from_email' ) ),
			'reply_to'            => sanitize_email( self::post( 'reply_to' ) ),
			'batch_size'          => max( 1, min( 100, absint( self::post( 'batch_size' ) ) ) ),
			'business_name'       => sanitize_text_field( self::post( 'business_name' ) ),
			'mailing_address'     => sanitize_textarea_field( self::post( 'mailing_address' ) ),
			'contact_line'        => sanitize_text_field( self::post( 'contact_line' ) ),
			'reason_line'         => sanitize_text_field( self::post( 'reason_line' ) ),
			'default_style'       => 'plain' === self::post( 'default_style' ) ? 'plain' : 'branded',
			'logo_id'             => absint( self::post( 'logo_id' ) ),
			'brand_color'         => $color ? $color : '#3059E3',
			'track_expiry'        => self::post( 'track_expiry' ) ? 1 : 0,
			'delete_on_uninstall' => self::post( 'delete_on_uninstall' ) ? 1 : 0,
		);
		if ( ! AWDCM_Settings::api_key_from_constant() ) {
			$key = trim( sanitize_text_field( self::post( 'api_key' ) ) );
			if ( '' !== $key ) {
				$v['api_key'] = $key;
			} elseif ( self::post( 'remove_api_key' ) ) {
				$v['api_key'] = '';
			}
		}
		AWDCM_Settings::update( $v );

		$warn = array();
		if ( '' === $v['mailing_address'] ) {
			$warn[] = 'CASL requires a mailing address in every commercial email.';
		}
		if ( $v['from_email'] && ! is_email( $v['from_email'] ) ) {
			$warn[] = 'The "From" email address is not valid.';
		}
		self::notice( 'Settings saved.' . ( $warn ? ' ' . esc_html( implode( ' ', $warn ) ) : '' ), $warn ? 'warning' : 'success' );
		self::redirect( 'awdcm-settings' );
	}
}
