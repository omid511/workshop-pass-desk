<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPD_Admin {
	public static function hooks(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_wpd_save_workshop', array( __CLASS__, 'save_workshop' ) );
		add_action( 'admin_post_wpd_save_session', array( __CLASS__, 'save_session' ) );
		add_action( 'admin_post_wpd_check_in', array( __CLASS__, 'check_in' ) );
		add_action( 'admin_post_wpd_export', array( __CLASS__, 'export_csv' ) );
	}

	public static function assets( string $hook ): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'toplevel_page_wpd-workshops' !== $hook && ! in_array( $page, array( 'wpd-workshops', 'wpd-check-in' ), true ) ) {
			return;
		}
		wp_enqueue_style( 'wpd-admin', plugin_dir_url( WPD_FILE ) . 'assets/style.css', array(), WPD_VERSION );
	}

	public static function menu(): void {
		add_menu_page( __( 'Workshop Pass Desk', 'workshop-pass-desk' ), __( 'Pass Desk', 'workshop-pass-desk' ), 'manage_workshop_passes', 'wpd-workshops', array( __CLASS__, 'workshops_page' ), 'dashicons-tickets-alt' );
		add_submenu_page( 'wpd-workshops', __( 'Check in', 'workshop-pass-desk' ), __( 'Check in', 'workshop-pass-desk' ), 'manage_workshop_passes', 'wpd-check-in', array( __CLASS__, 'checkin_page' ) );
	}

	public static function save_workshop(): void {
		if ( ! current_user_can( 'manage_workshop_passes' ) || ! check_admin_referer( 'wpd_save_workshop' ) ) {
			wp_die( esc_html__( 'Permission check failed.', 'workshop-pass-desk' ), 403 );
		}
		$id = absint( $_POST['id'] ?? 0 );
		$result = WPD_Service::save_workshop( wp_unslash( $_POST ), $id );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 400 );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wpd-workshops&saved=1' ) );
		exit;
	}

	public static function check_in(): void {
		if ( ! current_user_can( 'manage_workshop_passes' ) || ! check_admin_referer( 'wpd_check_in' ) ) {
			wp_die( esc_html__( 'Permission check failed.', 'workshop-pass-desk' ), 403 );
		}
		$result = WPD_Service::check_in( sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ), get_current_user_id() );
		set_transient( 'wpd_result_' . get_current_user_id(), $result, 60 );
		wp_safe_redirect( admin_url( 'admin.php?page=wpd-check-in' ) );
		exit;
	}

	public static function save_session(): void {
		if ( ! current_user_can( 'manage_workshop_passes' ) || ! check_admin_referer( 'wpd_save_session' ) ) {
			wp_die( esc_html__( 'Permission check failed.', 'workshop-pass-desk' ), 403 );
		}
		$result = WPD_Service::save_session( absint( $_POST['workshop_id'] ?? 0 ), wp_unslash( $_POST ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), 400 );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wpd-workshops&saved_session=1' ) );
		exit;
	}

	public static function export_csv(): void {
		if ( ! current_user_can( 'manage_workshop_passes' ) || ! check_admin_referer( 'wpd_export' ) ) {
			wp_die( esc_html__( 'Permission check failed.', 'workshop-pass-desk' ), 403 );
		}
		$workshop_id = absint( $_GET['workshop_id'] ?? 0 );
		$summary = WPD_Service::summary( $workshop_id, get_current_user_id() );
		if ( ! $summary ) {
			wp_die( esc_html__( 'Workshop not found.', 'workshop-pass-desk' ), 404 );
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT p.id, p.order_id, p.customer_id, p.status, p.valid_from, p.valid_until, p.checked_in_at, p.checked_in_by, a.checked_in_at AS attendance_at FROM ' . WPD_DB::passes_table() . ' p LEFT JOIN ' . WPD_DB::attendance_table() . ' a ON a.pass_id = p.id WHERE p.workshop_id = %d ORDER BY p.created_at ASC', $workshop_id ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="workshop-' . $workshop_id . '-attendance.csv"' );
		$out = fopen( 'php://output', 'wb' );
		fputcsv( $out, array( 'workshop', 'pass_id', 'order_id', 'customer_id', 'status', 'valid_from', 'valid_until', 'checked_in_at', 'checked_in_by', 'attendance_event_at' ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, array( $summary->title, $row->id, $row->order_id, $row->customer_id, $row->status, $row->valid_from, $row->valid_until, $row->checked_in_at, $row->checked_in_by, $row->attendance_at ) );
		}
		fclose( $out );
		exit;
	}

	public static function workshops_page(): void {
		if ( ! current_user_can( 'manage_workshop_passes' ) ) {
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . WPD_DB::workshops_table() . ' WHERE owner_id = %d ORDER BY starts_at DESC', get_current_user_id() ) );
		?>
		<div class="wrap wpd-wrap"><h1><?php esc_html_e( 'Workshop Pass Desk', 'workshop-pass-desk' ); ?></h1>
		<p><?php esc_html_e( 'Create workshops, map products in WooCommerce, and watch attendance.', 'workshop-pass-desk' ); ?></p>
		<h2><?php esc_html_e( 'New workshop', 'workshop-pass-desk' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpd-form">
			<input type="hidden" name="action" value="wpd_save_workshop"><?php wp_nonce_field( 'wpd_save_workshop' ); ?>
			<p><label><?php esc_html_e( 'Title', 'workshop-pass-desk' ); ?><br><input required name="title" class="regular-text"></label></p>
			<p><label><?php esc_html_e( 'Description', 'workshop-pass-desk' ); ?><br><textarea name="description" class="large-text"></textarea></label></p>
			<p><label><?php esc_html_e( 'Starts (site timezone)', 'workshop-pass-desk' ); ?><br><input required type="datetime-local" name="starts_at"></label>
			<label><?php esc_html_e( 'Ends', 'workshop-pass-desk' ); ?><br><input required type="datetime-local" name="ends_at"></label>
			<label><?php esc_html_e( 'Capacity', 'workshop-pass-desk' ); ?><br><input required type="number" min="1" name="capacity" value="20"></label></p>
			<p><label><?php esc_html_e( 'Status', 'workshop-pass-desk' ); ?><br><select name="status"><option value="draft"><?php esc_html_e( 'Draft', 'workshop-pass-desk' ); ?></option><option value="active"><?php esc_html_e( 'Active', 'workshop-pass-desk' ); ?></option><option value="cancelled"><?php esc_html_e( 'Cancelled', 'workshop-pass-desk' ); ?></option></select></label></p>
			<p><button class="button button-primary"><?php esc_html_e( 'Create workshop', 'workshop-pass-desk' ); ?></button></p>
		</form>
		<h2><?php esc_html_e( 'Your workshops', 'workshop-pass-desk' ); ?></h2><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Workshop', 'workshop-pass-desk' ); ?></th><th><?php esc_html_e( 'Window', 'workshop-pass-desk' ); ?></th><th><?php esc_html_e( 'Status', 'workshop-pass-desk' ); ?></th><th><?php esc_html_e( 'Capacity', 'workshop-pass-desk' ); ?></th><th><?php esc_html_e( 'Sessions', 'workshop-pass-desk' ); ?></th><th><?php esc_html_e( 'Operations', 'workshop-pass-desk' ); ?></th></tr></thead><tbody>
		<?php foreach ( $rows as $row ) : $summary = WPD_Service::summary( (int) $row->id, get_current_user_id() ); $sessions = WPD_Service::sessions( (int) $row->id, get_current_user_id() ); $waitlist = WPD_Service::waitlist( (int) $row->id, get_current_user_id() ); ?><tr><td><strong><?php echo esc_html( $row->title ); ?></strong><br><small><?php echo $row->product_id ? esc_html( 'Product #' . $row->product_id ) : esc_html__( 'Map a WooCommerce product', 'workshop-pass-desk' ); ?></small></td><td><?php echo esc_html( $row->starts_at . ' – ' . $row->ends_at ); ?></td><td><?php echo esc_html( ucfirst( $row->status ) ); ?></td><td><?php echo esc_html( (int) $summary->issued . ' / ' . (int) $row->capacity . ' issued; ' . (int) $summary->checked_in . ' checked in' ); ?></td><td><?php foreach ( $sessions as $session ) : ?><div><?php echo esc_html( $session->title . ': ' . $session->starts_at . '–' . $session->ends_at ); ?></div><?php endforeach; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wpd_save_session"><input type="hidden" name="workshop_id" value="<?php echo esc_attr( $row->id ); ?>"><?php wp_nonce_field( 'wpd_save_session' ); ?><input required name="title" placeholder="Session title"><input required type="datetime-local" name="starts_at"><input required type="datetime-local" name="ends_at"><button class="button"><?php esc_html_e( 'Add session', 'workshop-pass-desk' ); ?></button></form></td><td><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpd_export&workshop_id=' . (int) $row->id ), 'wpd_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'workshop-pass-desk' ); ?></a><br><?php echo esc_html( count( $waitlist ) . ' waitlist entries' ); ?></td></tr><?php endforeach; ?>
		<?php if ( ! $rows ) : ?><tr><td colspan="6"><?php esc_html_e( 'No workshops yet.', 'workshop-pass-desk' ); ?></td></tr><?php endif; ?></tbody></table></div>
		<?php
	}

	public static function checkin_page(): void {
		$result = get_transient( 'wpd_result_' . get_current_user_id() );
		delete_transient( 'wpd_result_' . get_current_user_id() );
		?><div class="wrap wpd-wrap wpd-checkin"><h1><?php esc_html_e( 'Check in an attendee', 'workshop-pass-desk' ); ?></h1><p><?php esc_html_e( 'Enter the pass code exactly as shown on the attendee receipt.', 'workshop-pass-desk' ); ?></p>
		<?php if ( $result ) : ?><div class="notice <?php echo 'valid' === $result['status'] ? 'notice-success' : 'notice-error'; ?>"><p><strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $result['status'] ) ) ); ?>:</strong> <?php echo esc_html( $result['message'] ); ?></p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpd-code-form"><input type="hidden" name="action" value="wpd_check_in"><?php wp_nonce_field( 'wpd_check_in' ); ?><label for="wpd-code"><?php esc_html_e( 'Pass code', 'workshop-pass-desk' ); ?></label><input id="wpd-code" name="code" required autocomplete="off" autocapitalize="characters" inputmode="text"><button class="button button-primary"><?php esc_html_e( 'Check in', 'workshop-pass-desk' ); ?></button></form></div><?php
	}
}
