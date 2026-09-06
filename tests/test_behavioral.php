<?php
// Behavioral suite for Workshop Pass Desk. Runs with plain PHP, no WordPress:
//   php tests/test_behavioral.php
// Uses an in-memory SQLite database behind a minimal wpdb-compatible fake.

error_reporting( E_ALL );
define( 'ABSPATH', '/tmp/wpd-test/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );
if ( ! is_dir( ABSPATH . 'wp-admin/includes' ) ) {
	mkdir( ABSPATH . 'wp-admin/includes', 0777, true );
}
define( 'WPD_DB_VERSION', '1.2.0' );
file_put_contents( ABSPATH . 'wp-admin/includes/upgrade.php', "<?php\nfunction dbDelta( \$sql ) {}\n" );

$GLOBALS['wpd_options']     = array();
$GLOBALS['wpd_transients']  = array();
$GLOBALS['wpd_postmeta']    = array();
$GLOBALS['wpd_orders']      = array();
$GLOBALS['wpd_maillog']     = array();
$GLOBALS['wpd_mail_ok']     = true;
$GLOBALS['wpd_can']         = true;
$GLOBALS['wpd_uid']         = 1;
$GLOBALS['wpd_actions']     = array();
$GLOBALS['wpd_no_crypto']   = false;
$GLOBALS['wpd_network']     = false;
$GLOBALS['wpd_rewrites']    = 0;
$GLOBALS['wpd_roles']       = array();

// ---------- WP function stubs ----------
class WP_Error {
	public $code; public $msg;
	public function __construct( $code = '', $msg = '' ) { $this->code = $code; $this->msg = $msg; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->msg; }
}
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_html( $s ) { return $s; }
function esc_attr( $s ) { return $s; }
function esc_url( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return strtolower( trim( (string) $s ) ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
function absint( $v ) { return abs( (int) $v ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function load_plugin_textdomain( $d, $d2 = false, $p = '' ) {}
function wp_get_current_user() { return new class() { public $user_email = ''; public $display_name = ''; public function exists() { return false; } }; }
function is_user_logged_in() { return false; }
function current_user_can( $c, ...$a ) { return $GLOBALS['wpd_can']; }
function get_current_user_id() { return $GLOBALS['wpd_uid']; }
function current_time( $t ) { return date( 'Y-m-d H:i:s' ); }
function wp_date( $f, $ts = null ) { return date( $f, null === $ts ? time() : $ts ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['wpd_options'] ) ? $GLOBALS['wpd_options'][ $k ] : $d; }
function update_option( $k, $v ) { $GLOBALS['wpd_options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['wpd_options'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['wpd_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['wpd_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['wpd_transients'][ $k ] ); return true; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function apply_filters( $h, $v ) { return $GLOBALS['wpd_filters'][ $h ] ?? $v; }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function wp_verify_nonce( $n, $a ) { return 'valid-nonce' === $n; }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function wp_create_nonce( $a ) { return 'valid-nonce'; }
function check_admin_referer( $a ) { return true; }
function wp_nonce_field( $a ) {}
function admin_url( $p = '' ) { return 'http://example.test/wp-admin/' . $p; }
function wp_safe_redirect( $u ) {}
function wp_die( $m = '', $t = '', $a = array() ) { throw new Exception( 'wp_die: ' . $m ); }
function get_post_meta( $id, $k, $s = false ) { return $GLOBALS['wpd_postmeta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['wpd_postmeta'][ $id ][ $k ] = $v; return true; }
function wp_mail( $to, $sub, $msg ) { $GLOBALS['wpd_maillog'][] = array( $to, $sub ); return $GLOBALS['wpd_mail_ok']; }
function wc_get_order( $id ) { return $GLOBALS['wpd_orders'][ $id ] ?? false; }
function add_action( $h, $c, $p = 10, $a = 1 ) { $GLOBALS['wpd_actions'][] = array( $h, $c ); }
function add_shortcode( $t, $c ) {}
function shortcode_atts( $d, $a, $s = '' ) { return array_merge( $d, (array) $a ); }
function flush_rewrite_rules() { $GLOBALS['wpd_rewrites']++; }
function get_role( $r ) {
	if ( ! isset( $GLOBALS['wpd_roles'][ $r ] ) ) {
		$GLOBALS['wpd_roles'][ $r ] = new class() {
			public $caps = array();
			public function add_cap( $c ) { $this->caps[ $c ] = true; }
			public function remove_cap( $c ) { unset( $this->caps[ $c ] ); }
			public function has_cap( $c ) { return isset( $this->caps[ $c ] ); }
		};
	}
	return $GLOBALS['wpd_roles'][ $r ];
}
function is_plugin_active_for_network( $p ) { return $GLOBALS['wpd_network']; }
function plugin_basename( $f ) { return basename( $f ); }

// ---------- Fake wpdb over SQLite ----------
class FakeWPDB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $rows_affected = 0;
	public $queries = array();
	private $pdo;
	public function __construct() {
		$this->pdo = new PDO( 'sqlite::memory:' );
		$this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}
	public function get_charset_collate() { return ''; }
	public function prepare( $q, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$i = 0;
		return preg_replace_callback(
			'/%([dfs%])/',
			function ( $m ) use ( &$args, &$i ) {
				if ( '%' === $m[1] ) {
					return '%';
				}
				$v = $args[ $i++ ] ?? null;
				if ( 'd' === $m[1] ) {
					return (string) (int) $v;
				}
				if ( 'f' === $m[1] ) {
					return (string) (float) $v;
				}
				return "'" . str_replace( "'", "''", (string) $v ) . "'";
			},
			$q
		);
	}
	public function sql( $sql ) {
		return (string) preg_replace( '/\s+FOR UPDATE\s*$/i', '', $sql );
	}
	public function query( $sql ) {
		$this->queries[] = $sql;
		$sql = $this->sql( $sql );
		$up = strtoupper( ltrim( $sql ) );
		if ( str_starts_with( $up, 'START TRANSACTION' ) ) {
			$this->pdo->beginTransaction();
			return true;
		}
		if ( str_starts_with( $up, 'COMMIT' ) ) {
			$this->pdo->commit();
			return true;
		}
		if ( str_starts_with( $up, 'ROLLBACK' ) ) {
			$this->pdo->rollBack();
			return true;
		}
		if ( str_starts_with( $up, 'SHOW ' ) || str_starts_with( $up, 'ALTER TABLE' ) ) {
			return true; // MySQL-isms: no-op under SQLite.
		}
		$r = $this->pdo->exec( $sql );
		$this->rows_affected = (int) $r;
		return $r;
	}
	public function get_col( $sql ) {
		$sql = $this->sql( $sql );
		$this->queries[] = $sql;
		if ( str_starts_with( strtoupper( ltrim( $sql ) ), 'SHOW ' ) ) {
			return array();
		}
		return $this->pdo->query( $sql )->fetchAll( PDO::FETCH_COLUMN );
	}
	public function get_row( $sql ) {
		$sql = $this->sql( $sql );
		$this->queries[] = $sql;
		if ( str_starts_with( strtoupper( ltrim( $sql ) ), 'SHOW ' ) ) {
			return null;
		}
		$r = $this->pdo->query( $sql )->fetch( PDO::FETCH_OBJ );
		return $r ? $r : null;
	}
	public function get_var( $sql ) {
		$sql = $this->sql( $sql );
		$this->queries[] = $sql;
		if ( str_starts_with( strtoupper( ltrim( $sql ) ), 'SHOW ' ) ) {
			return null;
		}
		$r = $this->pdo->query( $sql )->fetch( PDO::FETCH_NUM );
		return $r ? $r[0] : null;
	}
	public function get_results( $sql, $out = null ) {
		$sql = $this->sql( $sql );
		$this->queries[] = $sql;
		if ( str_starts_with( strtoupper( ltrim( $sql ) ), 'SHOW ' ) ) {
			return array();
		}
		$mode = ( 'ARRAY_A' === $out ) ? PDO::FETCH_ASSOC : PDO::FETCH_OBJ;
		return $this->pdo->query( $sql )->fetchAll( $mode );
	}
	public function insert( $table, $data, $format = null ) {
		$cols = implode( ', ', array_keys( $data ) );
		$vals = implode( ', ', array_map( fn( $v ) => null === $v ? 'NULL' : "'" . str_replace( "'", "''", (string) $v ) . "'", array_values( $data ) ) );
		$sql  = "INSERT INTO {$table} ({$cols}) VALUES ({$vals})";
		$this->queries[] = $sql;
		$r = $this->pdo->exec( $sql );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		$this->rows_affected = (int) $r;
		return $r;
	}
	public function update( $table, $data, $where, $f1 = null, $f2 = null ) {
		$set = implode( ', ', array_map( fn( $k, $v ) => "{$k} = " . ( null === $v ? 'NULL' : "'" . str_replace( "'", "''", (string) $v ) . "'" ), array_keys( $data ), array_values( $data ) ) );
		$w = implode( ' AND ', array_map( fn( $k, $v ) => "{$k} = '" . str_replace( "'", "''", (string) $v ) . "'", array_keys( $where ), array_values( $where ) ) );
		$sql = "UPDATE {$table} SET {$set} WHERE {$w}";
		$this->queries[] = $sql;
		$r = $this->pdo->exec( $sql );
		$this->rows_affected = (int) $r;
		return $r;
	}
	public function schema() {
		$p = $this->prefix;
		$this->pdo->exec( "CREATE TABLE {$p}wpd_workshops (id INTEGER PRIMARY KEY AUTOINCREMENT, owner_id INTEGER NOT NULL DEFAULT 0, title VARCHAR(190) NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '', starts_at TEXT NOT NULL DEFAULT '', ends_at TEXT NOT NULL DEFAULT '', capacity INTEGER NOT NULL DEFAULT 1, status VARCHAR(20) NOT NULL DEFAULT 'draft', product_id INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL DEFAULT '')" );
		$this->pdo->exec( "CREATE TABLE {$p}wpd_passes (id INTEGER PRIMARY KEY AUTOINCREMENT, workshop_id INTEGER NOT NULL DEFAULT 0, order_id INTEGER NOT NULL DEFAULT 0, order_item_id INTEGER NOT NULL DEFAULT 0, item_index INTEGER NOT NULL DEFAULT 0, customer_id INTEGER NOT NULL DEFAULT 0, code_hash VARCHAR(64) NOT NULL DEFAULT '', code_ciphertext TEXT NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'active', valid_from TEXT NOT NULL DEFAULT '', valid_until TEXT NOT NULL DEFAULT '', checked_in_at TEXT NULL, checked_in_by INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL DEFAULT '', UNIQUE(order_id, order_item_id, item_index))" );
		$this->pdo->exec( "CREATE TABLE {$p}wpd_attendance (id INTEGER PRIMARY KEY AUTOINCREMENT, pass_id INTEGER NOT NULL DEFAULT 0, workshop_id INTEGER NOT NULL DEFAULT 0, actor_id INTEGER NOT NULL DEFAULT 0, checked_in_at TEXT NOT NULL DEFAULT '')" );
		$this->pdo->exec( "CREATE TABLE {$p}wpd_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, workshop_id INTEGER NOT NULL DEFAULT 0, title VARCHAR(190) NOT NULL DEFAULT '', starts_at TEXT NOT NULL DEFAULT '', ends_at TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL DEFAULT '')" );
		$this->pdo->exec( "CREATE TABLE {$p}wpd_waitlist (id INTEGER PRIMARY KEY AUTOINCREMENT, workshop_id INTEGER NOT NULL DEFAULT 0, user_id INTEGER NOT NULL DEFAULT 0, email VARCHAR(190) NOT NULL DEFAULT '', name VARCHAR(190) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at TEXT NOT NULL DEFAULT '', updated_at TEXT NOT NULL DEFAULT '')" );
		$this->pdo->exec( "CREATE TABLE {$p}wpd_events (id INTEGER PRIMARY KEY AUTOINCREMENT, workshop_id INTEGER NOT NULL DEFAULT 0, pass_id INTEGER NULL, actor_id INTEGER NOT NULL DEFAULT 0, event_type VARCHAR(40) NOT NULL DEFAULT '', details TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL DEFAULT '')" );
	}
}

// ---------- Fake Woo order/item ----------
class FakeItem {
	public function __construct( private $product, private $variation, private $qty, private $meta = array() ) {}
	public function get_product_id() { return $this->product; }
	public function get_variation_id() { return $this->variation; }
	public function get_quantity() { return $this->qty; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
}
class FakeOrder {
	public function __construct( private $status, private $user, private $items, private $meta = array(), private $key = 'orderkey123' ) {}
	public function get_status() { return $this->status; }
	public function get_user_id() { return $this->user; }
	public function get_items( $t = '' ) { return $this->items; }
	public function get_order_key() { return $this->key; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
}

$WPD_DIR = dirname( __DIR__ ) . '/';
define( 'WPD_FILE', $WPD_DIR . 'workshop-pass-desk.php' );
require $WPD_DIR . 'includes/class-wpd-db.php';
require $WPD_DIR . 'includes/class-wpd-service.php';
require $WPD_DIR . 'includes/class-wpd-product.php';
require $WPD_DIR . 'includes/class-wpd-admin.php';
require $WPD_DIR . 'includes/class-wpd-plugin.php';

// ---------- Harness ----------
$GLOBALS['wpd_pass'] = 0;
$GLOBALS['wpd_fail'] = 0;
function ok( $cond, $msg ) {
	if ( $cond ) {
		$GLOBALS['wpd_pass']++;
	} else {
		$GLOBALS['wpd_fail']++;
		echo "FAIL: {$msg}\n";
	}
}
function fresh_db() {
	$GLOBALS['wpdb'] = new FakeWPDB();
	$GLOBALS['wpdb']->schema();
	$GLOBALS['wpd_options'] = array();
	$GLOBALS['wpd_transients'] = array();
	$GLOBALS['wpd_postmeta'] = array();
	$GLOBALS['wpd_orders'] = array();
	$GLOBALS['wpd_maillog'] = array();
	$GLOBALS['wpd_mail_ok'] = true;
	$GLOBALS['wpd_can'] = true;
	$GLOBALS['wpd_uid'] = 1;
	$GLOBALS['wpd_actions'] = array();
	$GLOBALS['wpd_filters'] = array();
	$GLOBALS['wpd_network'] = false;
	$GLOBALS['wpd_rewrites'] = 0;
	$GLOBALS['wpd_roles'] = array();
	$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
	unset( $_POST['_wpd_workshop_id'], $_POST['woocommerce_meta_nonce'] );
}
function make_workshop( $overrides = array() ) {
	$data = array_merge(
		array(
			'title' => 'T', 'starts_at' => date( 'Y-m-d H:i:s', time() - 3600 ),
			'ends_at' => date( 'Y-m-d H:i:s', time() + 3600 ), 'capacity' => 10, 'status' => 'active',
		),
		$overrides
	);
	return WPD_Service::save_workshop( $data );
}
function map_product( $product_id, $workshop_id ) {
	$GLOBALS['wpd_postmeta'][ $product_id ]['_wpd_workshop_id'] = $workshop_id;
}
function make_order( $id, $items, $status = 'processing', $user = 7, $meta = array() ) {
	$GLOBALS['wpd_orders'][ $id ] = new FakeOrder( $status, $user, $items, $meta );
}

// T1a: partial refund revokes exactly the refunded units.
fresh_db();
$w = make_workshop();
map_product( 50, $w );
make_order( 5, array( 101 => new FakeItem( 50, 0, 3 ) ) );
WPD_Service::issue_for_order( 5 );
ok( 3 === count( WPD_Service::order_passes( 5 ) ), 'setup issues 3 passes' );
$GLOBALS['wpd_orders'][9] = new FakeOrder( 'refunded', 7, array( 201 => new FakeItem( 50, 0, -1, array( '_refunded_item_id' => 101 ) ) ) );
WPD_Service::revoke_refunded_units( 5, 9 );
$active = array_filter( WPD_Service::order_passes( 5 ), fn( $p ) => 'active' === $p->status );
ok( 2 === count( $active ), 'partial refund leaves 2 active passes' );

// T1b: issuance fails closed without crypto.
fresh_db();
$w = make_workshop();
map_product( 50, $w );
make_order( 5, array( 101 => new FakeItem( 50, 0, 1 ) ) );
$GLOBALS['wpd_filters']['wpd_crypto_available'] = false;
$r = WPD_Service::issue_for_order( 5 );
ok( is_wp_error( $r ) && 'crypto_unavailable' === $r->get_error_code(), 'no-crypto issuance fails closed' );
ok( 0 === count( WPD_Service::order_passes( 5 ) ), 'no-crypto issues zero passes' );

// T1c: dedicated secret survives rotation.
fresh_db();
WPD_DB::ensure_secret();
$s1 = get_option( 'wpd_secret' );
ok( is_string( $s1 ) && 64 === strlen( $s1 ), 'activation secret is 64 hex chars' );
$w = make_workshop();
map_product( 50, $w );
make_order( 5, array( 101 => new FakeItem( 50, 0, 1 ) ) );
$created = WPD_Service::issue_for_order( 5 );
WPD_Service::rotate_secret();
ok( get_option( 'wpd_secret' ) !== $s1, 'rotation issues a new secret' );
$check = WPD_Service::check_in( $created[0]['code'], 1 );
ok( 'valid' === $check['status'], 'pre-rotation pass still checks in after rotation' );
make_order( 6, array( 102 => new FakeItem( 50, 0, 1 ) ) );
$created2 = WPD_Service::issue_for_order( 6 );
$again = WPD_Service::customer_passes( 7 );
$codes = array_map( fn( $p ) => $p->code, $again );
ok( in_array( $created2[0]['code'], $codes, true ), 'post-rotation pass decrypts under new secret' );

// T1d: variation save requires nonce.
fresh_db();
$_POST['_wpd_workshop_id'] = array( 77 => '5' );
WPD_Product::save_variation( 77, 0 );
ok( '' === get_post_meta( 77, '_wpd_workshop_id', true ), 'variation mapping without nonce is rejected' );
$_POST['woocommerce_meta_nonce'] = 'valid-nonce';
WPD_Product::save_variation( 77, 0 );
ok( '5' == get_post_meta( 77, '_wpd_workshop_id', true ), 'variation mapping with nonce is saved' );

// T1e: waitlist invite only on delivered mail.
fresh_db();
$w = make_workshop();
$GLOBALS['wpd_mail_ok'] = false;
WPD_Service::join_waitlist( $w, 'a@example.test' );
WPD_Service::promote_waitlist( $w );
$st = $GLOBALS['wpdb']->get_var( "SELECT status FROM wp_wpd_waitlist WHERE email = 'a@example.test'" );
ok( 'pending' === $st, 'failed mail keeps entry pending' );
$ev = $GLOBALS['wpdb']->get_var( "SELECT event_type FROM wp_wpd_events WHERE event_type = 'mail_failed'" );
ok( 'mail_failed' === $ev, 'failed mail logs mail_failed event' );
$GLOBALS['wpd_mail_ok'] = true;
WPD_Service::promote_waitlist( $w );
$st = $GLOBALS['wpdb']->get_var( "SELECT status FROM wp_wpd_waitlist WHERE email = 'a@example.test'" );
ok( 'invited' === $st, 'delivered mail marks entry invited' );

// T1f: subscription renewals do not mint passes.
fresh_db();
$w = make_workshop();
map_product( 50, $w );
make_order( 5, array( 101 => new FakeItem( 50, 0, 1 ) ), 'processing', 7, array( '_subscription_renewal' => '42' ) );
ok( array() === WPD_Service::issue_for_order( 5 ), 'renewal order issues nothing' );

// T2a: CSV cells escape formula triggers.
ok( "'=cmd" === WPD_Admin::csv_cell( '=cmd' ), 'leading = is escaped' );
ok( "'+1" === WPD_Admin::csv_cell( '+1' ), 'leading + is escaped' );
ok( "'-2" === WPD_Admin::csv_cell( '-2' ), 'leading - is escaped' );
ok( "'@x" === WPD_Admin::csv_cell( '@x' ), 'leading @ is escaped' );
ok( 'plain' === WPD_Admin::csv_cell( 'plain' ), 'plain cells untouched' );

// T2b: check-in is atomic.
fresh_db();
$w = make_workshop();
map_product( 50, $w );
make_order( 5, array( 101 => new FakeItem( 50, 0, 1 ) ) );
$created = WPD_Service::issue_for_order( 5 );
$GLOBALS['wpdb']->queries = array();
WPD_Service::check_in( $created[0]['code'], 1 );
$log = implode( "\n", $GLOBALS['wpdb']->queries );
$start = strpos( $log, 'START TRANSACTION' );
$upd = strpos( $log, 'UPDATE wp_wpd_passes SET checked_in_at' );
$ins = strpos( $log, 'INSERT INTO wp_wpd_attendance' );
$com = strpos( $log, 'COMMIT' );
ok( false !== $start && $start < $upd && $upd < $ins && $ins < $com, 'check-in wraps UPDATE+INSERT in one transaction' );

// T2c: waitlist join is rate limited.
fresh_db();
$w = make_workshop();
$n = 0;
for ( $i = 0; $i < 6; $i++ ) {
	$r = WPD_Service::join_waitlist( $w, "user{$i}@example.test" );
	if ( ! is_wp_error( $r ) ) {
		$n++;
	}
}
ok( 5 === $n, '6th rapid join from one IP is rate limited' );

// T2d: network activation is guarded.
fresh_db();
$GLOBALS['wpd_network'] = true;
WPD_Plugin::boot();
$found = false;
foreach ( $GLOBALS['wpd_actions'] as $a ) {
	if ( 'admin_notices' === $a[0] && is_callable( $a[1] ) ) {
		ob_start();
		$a[1]();
		$out = (string) ob_get_clean();
		if ( str_contains( $out, 'etwork' ) ) {
			$found = true;
		}
	}
}
ok( $found, 'network-wide activation registers a multisite admin notice' );

// T2e: capacity cannot drop below issued; mapping errors surface.
fresh_db();
$w = make_workshop( array( 'capacity' => 2 ) );
map_product( 50, $w );
make_order( 5, array( 101 => new FakeItem( 50, 0, 2 ) ) );
WPD_Service::issue_for_order( 5 );
$r = WPD_Service::save_workshop( array( 'title' => 'T', 'starts_at' => date( 'Y-m-d H:i:s', time() - 3600 ), 'ends_at' => date( 'Y-m-d H:i:s', time() + 3600 ), 'capacity' => 1, 'status' => 'active' ), $w );
ok( is_wp_error( $r ) && 'capacity_below_issued' === $r->get_error_code(), 'capacity cut below issued is rejected' );
$_POST['woocommerce_meta_nonce'] = 'valid-nonce';
$_POST['_wpd_workshop_id'] = '9999';
$GLOBALS['wpd_uid'] = 2; // different owner: workshop belongs to uid 1.
$r = WPD_Product::save( 50 );
ok( is_wp_error( $r ), 'mapping to an unowned workshop surfaces an error' );
$GLOBALS['wpd_uid'] = 1;

// T3a: upgrades are locked off public views.
fresh_db();
update_option( 'wpd_db_version', '0.0.0' );
WPD_DB::maybe_upgrade();
ok( false !== get_transient( 'wpd_upgrade_lock' ), 'upgrade sets a lock' );
update_option( 'wpd_db_version', '0.0.0' );
WPD_DB::maybe_upgrade();
ok( '0.0.0' === get_option( 'wpd_db_version' ), 'locked upgrade does not re-run' );

// T3b: uninstall honors the erase setting.
fresh_db();
WPD_DB::uninstall( false );
try {
	$left = $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_wpd_workshops' );
	ok( '0' == (string) $left, 'uninstall with erase off keeps tables' );
} catch ( Exception $e ) {
	ok( false, 'uninstall with erase off keeps tables' );
}
WPD_DB::uninstall( true );
try {
	$GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM wp_wpd_workshops' );
	$gone = false;
} catch ( Exception $e ) {
	$gone = true;
}
ok( $gone, 'uninstall with erase on drops tables' );

echo "\nbehavioral: {$GLOBALS['wpd_pass']} passed, {$GLOBALS['wpd_fail']} failed\n";
exit( $GLOBALS['wpd_fail'] ? 1 : 0 );
