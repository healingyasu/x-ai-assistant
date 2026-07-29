<?php

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['xaia_test_options']    = array();
$GLOBALS['xaia_test_transients'] = array();
$GLOBALS['xaia_test_uuid']       = 0;

final class WP_Error {
	public $code;

	public function __construct( $code ) {
		$this->code = $code;
	}
}

final class XAIA_Test_Wpdb {
	public $options = 'wp_options';

	public function delete( $table, array $where ) {
		if ( $this->options !== $table || ! isset( $GLOBALS['xaia_test_options'][ $where['option_name'] ] ) ) {
			return 0;
		}
		if ( maybe_serialize( $GLOBALS['xaia_test_options'][ $where['option_name'] ] ) !== $where['option_value'] ) {
			return 0;
		}
		unset( $GLOBALS['xaia_test_options'][ $where['option_name'] ] );
		return 1;
	}
}

$GLOBALS['wpdb'] = new XAIA_Test_Wpdb();

function __( $text ) {
	return $text;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function wp_generate_uuid4() {
	++$GLOBALS['xaia_test_uuid'];
	return '00000000-0000-4000-8000-' . str_pad( (string) $GLOBALS['xaia_test_uuid'], 12, '0', STR_PAD_LEFT );
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['xaia_test_options'] ) ? $GLOBALS['xaia_test_options'][ $name ] : $default;
}

function add_option( $name, $value ) {
	if ( array_key_exists( $name, $GLOBALS['xaia_test_options'] ) ) {
		return false;
	}
	$GLOBALS['xaia_test_options'][ $name ] = $value;
	return true;
}

function update_option( $name, $value ) {
	$GLOBALS['xaia_test_options'][ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['xaia_test_options'][ $name ] );
	return true;
}

function delete_transient( $name ) {
	unset( $GLOBALS['xaia_test_transients'][ $name ] );
	return true;
}

function wp_cache_delete() {
	return true;
}

function maybe_serialize( $value ) {
	return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
}

function xaia_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "失敗: {$message}\n" );
		exit( 1 );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-xaia-lock.php';

$nested_result = XAIA_Lock::run(
	'mentions',
	function () {
		return XAIA_Lock::run(
			'mentions',
			function () {
				return true;
			}
		);
	}
);
xaia_test_assert( is_wp_error( $nested_result ) && 'xaia_operation_busy' === $nested_result->code, '同じ範囲の二重実行を拒否すること' );
xaia_test_assert( ! isset( $GLOBALS['xaia_test_options']['xaia_operation_lock_mentions'] ), '処理後にロックを解放すること' );

try {
	XAIA_Lock::run(
		'candidates',
		function () {
			throw new RuntimeException( 'test' );
		}
	);
} catch ( RuntimeException $exception ) {
	xaia_test_assert( 'test' === $exception->getMessage(), 'テスト例外を受け取れること' );
}
xaia_test_assert( ! isset( $GLOBALS['xaia_test_options']['xaia_operation_lock_candidates'] ), '例外発生時もロックを解放すること' );

$GLOBALS['xaia_test_options']['xaia_operation_lock_mentions'] = array(
	'token' => 'stale',
	'time'  => time() - XAIA_Lock::TIMEOUT - 1,
);
$stale_result = XAIA_Lock::run(
	'mentions',
	function () {
		return 'recovered';
	}
);
xaia_test_assert( 'recovered' === $stale_result, '期限切れロックだけを安全に回収すること' );

final class XAIA_Settings {
	public static function get_all() {
		return array( 'retention_days' => 90 );
	}

	public static function retention_days( $value ) {
		return max( 30, min( 365, absint( $value ) ) );
	}
}

final class XAIA_Logger {
	public static $deleted_days = 0;
	public static $cleared      = false;

	public static function delete_older_than( $days ) {
		self::$deleted_days = $days;
		return true;
	}

	public static function clear() {
		self::$cleared = true;
		return true;
	}
}

final class XAIA_Interaction {
	const MENTIONS_OPTION   = 'xaia_mentions';
	const CANDIDATES_OPTION = 'xaia_candidates';
	const STATE_OPTION      = 'xaia_interaction_state';
	const USER_OPTION       = 'xaia_x_authenticated_user';
}

require_once dirname( __DIR__ ) . '/includes/class-xaia-maintenance.php';

$GLOBALS['xaia_test_options']['xaia_mentions'] = array(
	'old' => array( 'created_at' => gmdate( DATE_ATOM, time() - 100 * DAY_IN_SECONDS ) ),
	'new' => array( 'created_at' => gmdate( DATE_ATOM, time() - 10 * DAY_IN_SECONDS ) ),
);
$GLOBALS['xaia_test_options']['xaia_candidates'] = array(
	'broken' => array( 'created_at' => '' ),
	'new'    => array( 'created_at' => gmdate( DATE_ATOM, time() - DAY_IN_SECONDS ) ),
);
( new XAIA_Maintenance() )->cleanup();
xaia_test_assert( 90 === XAIA_Logger::$deleted_days, '投稿ログへ保持期間を適用すること' );
xaia_test_assert( array( 'new' ) === array_keys( $GLOBALS['xaia_test_options']['xaia_mentions'] ), '古いメンションだけを削除すること' );
xaia_test_assert( array( 'new' ) === array_keys( $GLOBALS['xaia_test_options']['xaia_candidates'] ), '日付不明または古い候補を削除すること' );

$GLOBALS['xaia_test_options']['xaia_interaction_state']      = array( 'initialized' => true );
$GLOBALS['xaia_test_options']['xaia_x_authenticated_user']   = array( 'id' => '1' );
$GLOBALS['xaia_test_transients']['xaia_error_mentions']      = '1';
$clear_result = XAIA_Maintenance::clear_activity_data();
xaia_test_assert( true === $clear_result && XAIA_Logger::$cleared, '交流データとログを消去すること' );
xaia_test_assert( ! isset( $GLOBALS['xaia_test_options']['xaia_mentions'], $GLOBALS['xaia_test_options']['xaia_candidates'] ), '交流オプションを消去すること' );

echo "セキュリティ回帰テスト: 成功\n";
