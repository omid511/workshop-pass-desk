<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPD_Service {
	private static function now(): string {
		return current_time( 'mysql' );
	}

	private static function code_hash( string $code ): string {
		return hash_hmac( 'sha256', strtoupper( trim( $code ) ), wp_salt( 'auth' ) );
	}

	private static function encrypt_code( string $code ): string {
		$key = hash( 'sha256', wp_salt( 'secure_auth' ), true );
		$iv  = random_bytes( 16 );
		$cipher = openssl_encrypt( $code, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $cipher );
	}

	private static function decrypt_code( string $payload ): string {
		$key = hash( 'sha256', wp_salt( 'secure_auth' ), true );
		$raw = base64_decode( $payload, true );
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		return (string) openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
	}

	public static function save_workshop( array $data, int $id = 0 ): int|WP_Error {
		if ( ! current_user_can( 'manage_workshop_passes' ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage workshops.', 'workshop-pass-desk' ) );
		}
		$start = self::normalize_datetime( sanitize_text_field( $data['starts_at'] ?? '' ) );
		$end = self::normalize_datetime( sanitize_text_field( $data['ends_at'] ?? '' ) );
		$capacity = max( 1, absint( $data['capacity'] ?? 1 ) );
		if ( ! $start || ! $end || strtotime( $end ) <= strtotime( $start ) ) {
			return new WP_Error( 'invalid_dates', __( 'End time must be after start time.', 'workshop-pass-desk' ) );
		}
		$status = in_array( $data['status'] ?? 'draft', array( 'draft', 'active', 'cancelled' ), true ) ? $data['status'] : 'draft';
		$title = sanitize_text_field( $data['title'] ?? '' );
		if ( '' === $title ) {
			return new WP_Error( 'missing_title', __( 'A workshop title is required.', 'workshop-pass-desk' ) );
		}
		global $wpdb;
		$table = WPD_DB::workshops_table();
		$values = array(
			'owner_id' => get_current_user_id(), 'title' => $title, 'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'starts_at' => $start, 'ends_at' => $end, 'capacity' => $capacity, 'status' => $status,
			'product_id' => absint( $data['product_id'] ?? 0 ), 'updated_at' => self::now(),
		);
		if ( $id ) {
			$owned = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d AND owner_id = %d", $id, get_current_user_id() ) );
			if ( ! $owned ) {
				return new WP_Error( 'not_found', __( 'Workshop not found.', 'workshop-pass-desk' ) );
			}
			$wpdb->update( $table, $values, array( 'id' => $id ), array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' ), array( '%d' ) );
			self::log_event( $id, 0, 'workshop_updated', array( 'title' => $title, 'status' => $status ) );
			return $id;
		}
		$values['created_at'] = self::now();
		if ( ! $wpdb->insert( $table, $values, array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' ) ) ) {
			return new WP_Error( 'save_failed', __( 'The workshop could not be saved.', 'workshop-pass-desk' ) );
		}
		$workshop_id = (int) $wpdb->insert_id;
		self::save_session( $workshop_id, array( 'title' => __( 'Main session', 'workshop-pass-desk' ), 'starts_at' => $start, 'ends_at' => $end ) );
		self::log_event( $workshop_id, 0, 'workshop_created', array( 'title' => $title, 'status' => $status ) );
		return $workshop_id;
	}

	private static function normalize_datetime( string $value ): string {
		$timestamp = strtotime( $value );
		return $timestamp ? wp_date( 'Y-m-d H:i:s', $timestamp ) : '';
	}

	public static function log_event( int $workshop_id, int $pass_id, string $type, array|string $details, int $actor_id = 0 ): void {
		global $wpdb;
		$wpdb->insert( WPD_DB::events_table(), array(
			'workshop_id' => $workshop_id,
			'pass_id' => $pass_id ?: null,
			'actor_id' => $actor_id ?: get_current_user_id(),
			'event_type' => sanitize_key( $type ),
			'details' => is_array( $details ) ? wp_json_encode( $details ) : sanitize_textarea_field( $details ),
			'created_at' => self::now(),
		), array( '%d', '%d', '%d', '%s', '%s', '%s' ) );
	}

	public static function sessions( int $workshop_id, int $owner_id = 0 ): array {
		global $wpdb;
		$sql = 'SELECT s.* FROM ' . WPD_DB::sessions_table() . ' s INNER JOIN ' . WPD_DB::workshops_table() . ' w ON w.id = s.workshop_id WHERE s.workshop_id = %d';
		$args = array( $workshop_id );
		if ( $owner_id ) {
			$sql .= ' AND w.owner_id = %d';
			$args[] = $owner_id;
		}
		$sql .= ' ORDER BY s.starts_at ASC';
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	public static function save_session( int $workshop_id, array $data, int $id = 0 ): int|WP_Error {
		global $wpdb;
		$owner_id = get_current_user_id();
		if ( ! current_user_can( 'manage_workshop_passes' ) || ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . WPD_DB::workshops_table() . ' WHERE id = %d AND owner_id = %d', $workshop_id, $owner_id ) ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot manage this workshop.', 'workshop-pass-desk' ) );
		}
		$title = sanitize_text_field( $data['title'] ?? '' );
		$start = self::normalize_datetime( sanitize_text_field( $data['starts_at'] ?? '' ) );
		$end = self::normalize_datetime( sanitize_text_field( $data['ends_at'] ?? '' ) );
		if ( '' === $title || ! $start || ! $end || strtotime( $end ) <= strtotime( $start ) ) {
			return new WP_Error( 'invalid_session', __( 'A session needs a title and an end after its start.', 'workshop-pass-desk' ) );
		}
		$values = array( 'workshop_id' => $workshop_id, 'title' => $title, 'starts_at' => $start, 'ends_at' => $end, 'updated_at' => self::now() );
		if ( $id ) {
			$owned = $wpdb->get_var( $wpdb->prepare( 'SELECT s.id FROM ' . WPD_DB::sessions_table() . ' s INNER JOIN ' . WPD_DB::workshops_table() . ' w ON w.id = s.workshop_id WHERE s.id = %d AND w.owner_id = %d', $id, $owner_id ) );
			if ( ! $owned ) {
				return new WP_Error( 'not_found', __( 'Session not found.', 'workshop-pass-desk' ) );
			}
			$wpdb->update( WPD_DB::sessions_table(), $values, array( 'id' => $id ), array( '%d', '%s', '%s', '%s', '%s' ), array( '%d' ) );
			self::log_event( $workshop_id, 0, 'session_updated', array( 'session_id' => $id, 'title' => $title ), $owner_id );
			return $id;
		}
		$values['created_at'] = self::now();
		$wpdb->insert( WPD_DB::sessions_table(), $values, array( '%d', '%s', '%s', '%s', '%s', '%s' ) );
		$session_id = (int) $wpdb->insert_id;
		self::log_event( $workshop_id, 0, 'session_created', array( 'session_id' => $session_id, 'title' => $title ), $owner_id );
		return $session_id;
	}

	private static function is_in_active_window( int $workshop_id, string $now ): bool {
		global $wpdb;
		$session_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . WPD_DB::sessions_table() . ' WHERE workshop_id = %d', $workshop_id ) );
		if ( $session_count > 0 ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . WPD_DB::sessions_table() . ' WHERE workshop_id = %d AND starts_at <= %s AND ends_at >= %s LIMIT 1', $workshop_id, $now, $now ) );
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . WPD_DB::workshops_table() . " WHERE id = %d AND starts_at <= %s AND ends_at >= %s", $workshop_id, $now, $now ) );
	}

	public static function issue_for_order( int $order_id ): array|WP_Error {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return new WP_Error( 'woocommerce_missing', __( 'WooCommerce is required.', 'workshop-pass-desk' ) );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			return array();
		}
		global $wpdb;
		$created = array();
		$passes = WPD_DB::passes_table();
		$workshops = WPD_DB::workshops_table();
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$product_id = $item->get_product_id();
			$workshop_id = absint( get_post_meta( $product_id, '_wpd_workshop_id', true ) );
			if ( ! $workshop_id ) {
				continue;
			}
			$workshop = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$workshops} WHERE id = %d", $workshop_id ) );
			if ( ! $workshop || 'active' !== $workshop->status ) {
				continue;
			}
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$passes} WHERE order_id = %d AND order_item_id = %d", $order_id, $item_id ) );
			if ( $existing ) {
				continue;
			}
			$wpdb->query( 'START TRANSACTION' );
			$locked = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$workshops} WHERE id = %d FOR UPDATE", $workshop_id ) );
			$issued = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$passes} WHERE workshop_id = %d AND status = 'active'", $workshop_id ) );
			if ( ! $locked || $issued >= (int) $locked->capacity ) {
				$wpdb->query( 'ROLLBACK' );
				continue;
			}
			$code = strtoupper( substr( bin2hex( random_bytes( 12 ) ), 0, 16 ) );
			$inserted = $wpdb->insert( $passes, array(
				'workshop_id' => $workshop_id, 'order_id' => $order_id, 'order_item_id' => $item_id,
				'customer_id' => (int) $order->get_user_id(), 'code_hash' => self::code_hash( $code ),
				'code_ciphertext' => self::encrypt_code( $code ), 'status' => 'active', 'valid_from' => $locked->starts_at,
				'valid_until' => $locked->ends_at, 'created_at' => self::now(), 'updated_at' => self::now(),
			), array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
			if ( $inserted ) {
				$wpdb->query( 'COMMIT' );
				$pass_id = (int) $wpdb->insert_id;
				$created[] = array( 'pass_id' => $pass_id, 'code' => $code, 'workshop_id' => $workshop_id );
				self::log_event( $workshop_id, $pass_id, 'pass_issued', array( 'order_id' => $order_id, 'order_item_id' => (int) $item_id ), (int) $order->get_user_id() );
			} else {
				$wpdb->query( 'ROLLBACK' );
			}
		}
		return $created;
	}

	public static function check_in( string $code, int $actor_id ): array {
		if ( ! current_user_can( 'manage_workshop_passes' ) ) {
			return array( 'status' => 'forbidden', 'message' => __( 'Organizer access is required.', 'workshop-pass-desk' ) );
		}
		$code = strtoupper( trim( $code ) );
		if ( '' === $code ) {
			return array( 'status' => 'invalid', 'message' => __( 'Enter a pass code.', 'workshop-pass-desk' ) );
		}
		$rate_key = 'wpd_check_' . md5( (string) $actor_id . '|' . ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
		$attempts = (int) get_transient( $rate_key );
		if ( $attempts >= 30 ) {
			return array( 'status' => 'rate_limited', 'message' => __( 'Too many attempts. Try again in a minute.', 'workshop-pass-desk' ) );
		}
		set_transient( $rate_key, $attempts + 1, MINUTE_IN_SECONDS );
		global $wpdb;
		$passes = WPD_DB::passes_table();
		$workshops = WPD_DB::workshops_table();
		$attendance = WPD_DB::attendance_table();
		$pass = $wpdb->get_row( $wpdb->prepare( "SELECT p.*, w.title, w.status AS workshop_status FROM {$passes} p INNER JOIN {$workshops} w ON w.id = p.workshop_id WHERE p.code_hash = %s LIMIT 1", self::code_hash( $code ) ) );
		if ( ! $pass ) {
			return array( 'status' => 'invalid', 'message' => __( 'Pass code is not recognized.', 'workshop-pass-desk' ) );
		}
		if ( 'cancelled' === $pass->status || 'cancelled' === $pass->workshop_status ) {
			return array( 'status' => 'cancelled', 'message' => __( 'This pass has been cancelled.', 'workshop-pass-desk' ) );
		}
		if ( $pass->checked_in_at ) {
			return array( 'status' => 'already_checked_in', 'message' => sprintf( __( 'Already checked in at %s.', 'workshop-pass-desk' ), wp_date( get_option( 'time_format' ), strtotime( $pass->checked_in_at ) ) ), 'pass' => $pass );
		}
		$now = current_time( 'mysql' );
		if ( ! self::is_in_active_window( (int) $pass->workshop_id, $now ) ) {
			return array( 'status' => 'out_of_window', 'message' => sprintf( __( 'Check-in opens %s and closes %s.', 'workshop-pass-desk' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $pass->valid_from ) ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $pass->valid_until ) ) ), 'pass' => $pass );
		}
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$passes} SET checked_in_at = %s, checked_in_by = %d, updated_at = %s WHERE id = %d AND status = 'active' AND checked_in_at IS NULL", $now, $actor_id, $now, $pass->id ) );
		if ( 1 !== (int) $changed ) {
			return array( 'status' => 'already_checked_in', 'message' => __( 'This pass was checked in by another device.', 'workshop-pass-desk' ), 'pass' => $pass );
		}
		$wpdb->insert( $attendance, array( 'pass_id' => $pass->id, 'workshop_id' => $pass->workshop_id, 'actor_id' => $actor_id, 'checked_in_at' => $now ), array( '%d', '%d', '%d', '%s' ) );
		self::log_event( (int) $pass->workshop_id, (int) $pass->id, 'pass_checked_in', array( 'actor_id' => $actor_id ), $actor_id );
		return array( 'status' => 'valid', 'message' => __( 'Pass accepted. Attendee checked in.', 'workshop-pass-desk' ), 'pass' => $pass );
	}

	public static function cancel_order( int $order_id ): int {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, workshop_id FROM ' . WPD_DB::passes_table() . ' WHERE order_id = %d AND checked_in_at IS NULL AND status = %s', $order_id, 'active' ) );
		if ( ! $rows ) {
			return 0;
		}
		$changed = $wpdb->query( $wpdb->prepare( "UPDATE " . WPD_DB::passes_table() . " SET status = 'cancelled', updated_at = %s WHERE order_id = %d AND checked_in_at IS NULL AND status = 'active'", self::now(), $order_id ) );
		foreach ( $rows as $row ) {
			self::log_event( (int) $row->workshop_id, (int) $row->id, 'pass_cancelled', array( 'order_id' => $order_id ) );
			self::promote_waitlist( (int) $row->workshop_id );
		}
		return (int) $changed;
	}

	public static function join_waitlist( int $workshop_id, string $email, string $name = '' ): int|WP_Error {
		global $wpdb;
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Enter a valid email address.', 'workshop-pass-desk' ) );
		}
		$workshop = $wpdb->get_row( $wpdb->prepare( 'SELECT id, title, status FROM ' . WPD_DB::workshops_table() . ' WHERE id = %d', $workshop_id ) );
		if ( ! $workshop || 'cancelled' === $workshop->status ) {
			return new WP_Error( 'not_found', __( 'That workshop is not accepting waitlist entries.', 'workshop-pass-desk' ) );
		}
		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . WPD_DB::waitlist_table() . ' WHERE workshop_id = %d AND email = %s', $workshop_id, strtolower( $email ) ) );
		if ( $existing ) {
			return (int) $existing;
		}
		$wpdb->insert( WPD_DB::waitlist_table(), array(
			'workshop_id' => $workshop_id, 'user_id' => get_current_user_id(), 'email' => strtolower( $email ),
			'name' => sanitize_text_field( $name ), 'status' => 'pending', 'created_at' => self::now(), 'updated_at' => self::now(),
		), array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' ) );
		$entry_id = (int) $wpdb->insert_id;
		self::log_event( $workshop_id, 0, 'waitlist_joined', array( 'waitlist_id' => $entry_id, 'email' => strtolower( $email ) ) );
		return $entry_id;
	}

	public static function promote_waitlist( int $workshop_id ): ?object {
		global $wpdb;
		$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . WPD_DB::waitlist_table() . " WHERE workshop_id = %d AND status = 'pending' ORDER BY created_at ASC LIMIT 1", $workshop_id ) );
		if ( ! $entry ) {
			return null;
		}
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE " . WPD_DB::waitlist_table() . " SET status = 'invited', updated_at = %s WHERE id = %d AND status = 'pending'", self::now(), $entry->id ) );
		if ( 1 !== (int) $updated ) {
			return null;
		}
		$workshop = $wpdb->get_row( $wpdb->prepare( 'SELECT title, starts_at FROM ' . WPD_DB::workshops_table() . ' WHERE id = %d', $workshop_id ) );
		if ( $workshop && function_exists( 'wp_mail' ) ) {
			wp_mail( $entry->email, sprintf( __( 'A place opened for %s', 'workshop-pass-desk' ), $workshop->title ), sprintf( __( 'A place is now available for %s. Please complete your purchase before the organizer releases it.', 'workshop-pass-desk' ), $workshop->title ) );
		}
		self::log_event( $workshop_id, 0, 'waitlist_invited', array( 'waitlist_id' => (int) $entry->id, 'email' => $entry->email ) );
		return $entry;
	}

	public static function waitlist( int $workshop_id, int $owner_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT l.* FROM ' . WPD_DB::waitlist_table() . ' l INNER JOIN ' . WPD_DB::workshops_table() . ' w ON w.id = l.workshop_id WHERE l.workshop_id = %d AND w.owner_id = %d ORDER BY l.created_at ASC', $workshop_id, $owner_id ) );
	}

	public static function events( int $workshop_id, int $owner_id, int $limit = 100 ): array {
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT e.* FROM ' . WPD_DB::events_table() . ' e INNER JOIN ' . WPD_DB::workshops_table() . ' w ON w.id = e.workshop_id WHERE e.workshop_id = %d AND w.owner_id = %d ORDER BY e.created_at DESC LIMIT %d', $workshop_id, $owner_id, $limit ) );
	}

	public static function customer_passes( int $user_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT p.*, w.title FROM " . WPD_DB::passes_table() . " p INNER JOIN " . WPD_DB::workshops_table() . " w ON w.id = p.workshop_id WHERE p.customer_id = %d ORDER BY p.created_at DESC", $user_id ) );
		foreach ( $rows as $row ) {
			$row->code = self::decrypt_code( $row->code_ciphertext );
		}
		return $rows;
	}

	public static function summary( int $workshop_id, int $owner_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT w.*, (SELECT COUNT(*) FROM " . WPD_DB::passes_table() . " p WHERE p.workshop_id = w.id AND p.status = 'active') AS issued, (SELECT COUNT(*) FROM " . WPD_DB::passes_table() . " p WHERE p.workshop_id = w.id AND p.checked_in_at IS NOT NULL) AS checked_in FROM " . WPD_DB::workshops_table() . " w WHERE w.id = %d AND w.owner_id = %d", $workshop_id, $owner_id ) );
	}
}
