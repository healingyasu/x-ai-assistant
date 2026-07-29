<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * X API操作と保存処理の重複実行を防止します。
 */
final class XAIA_Lock {
	const OPTION_PREFIX = 'xaia_operation_lock_';
	const TIMEOUT       = 120;

	/**
	 * ロック中に処理を実行します。
	 *
	 * @param string   $scope    ロック範囲。
	 * @param callable $callback 実行する処理。
	 * @return mixed|WP_Error
	 */
	public static function run( $scope, callable $callback ) {
		$token = self::acquire( $scope );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		try {
			return call_user_func( $callback );
		} finally {
			self::release( $scope, $token );
		}
	}

	/**
	 * ロック用オプション名を返します。
	 *
	 * @param string $scope ロック範囲。
	 * @return string
	 */
	public static function option_name( $scope ) {
		return self::OPTION_PREFIX . sanitize_key( $scope );
	}

	/**
	 * ロックを取得します。
	 *
	 * @param string $scope ロック範囲。
	 * @return string|WP_Error
	 */
	private static function acquire( $scope ) {
		$option = self::option_name( $scope );
		$lock   = get_option( $option, array() );
		if ( is_array( $lock ) && ! empty( $lock['time'] ) && absint( $lock['time'] ) < time() - self::TIMEOUT ) {
			self::delete_if_matches( $option, $lock );
		}

		$token = wp_generate_uuid4();
		$added = add_option(
			$option,
			array(
				'token' => $token,
				'time'  => time(),
			),
			'',
			false
		);
		if ( ! $added ) {
			return new WP_Error( 'xaia_operation_busy', __( '同じ種類の操作を処理中です。少し待ってから再実行してください。', 'x-ai-assistant' ) );
		}

		return $token;
	}

	/**
	 * 自分が取得したロックだけを解放します。
	 *
	 * @param string $scope ロック範囲。
	 * @param string $token ロックトークン。
	 */
	private static function release( $scope, $token ) {
		$option = self::option_name( $scope );
		$lock   = get_option( $option, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			self::delete_if_matches( $option, $lock );
		}
	}

	/**
	 * 読み取った値と一致するロックだけを原子的に削除します。
	 *
	 * @param string $option ロック用オプション名。
	 * @param array  $lock   読み取ったロック値。
	 * @return bool
	 */
	private static function delete_if_matches( $option, array $lock ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- 値が一致する期限切れロックだけを原子的に削除します。
		$deleted = $wpdb->delete(
			$wpdb->options,
			array(
				'option_name'  => $option,
				'option_value' => maybe_serialize( $lock ),
			),
			array( '%s', '%s' )
		);
		if ( $deleted ) {
			wp_cache_delete( $option, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		}

		return (bool) $deleted;
	}
}
