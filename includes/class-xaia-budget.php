<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Budget {
	const OPTION_NAME          = 'xaia_monthly_usage';
	const LOCK_OPTION          = 'xaia_budget_lock';
	const MAX_MONTHLY_REQUESTS = 20;

	public static function status() {
		$month = wp_date( 'Y-m', null, wp_timezone() );
		$usage = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $usage ) || empty( $usage['month'] ) || $month !== $usage['month'] ) {
			$usage = array(
				'month' => $month,
				'used'  => XAIA_Logger::count_successes_since( self::month_start_utc() ),
			);
			update_option( self::OPTION_NAME, $usage, false );
		}

		$used = min( self::MAX_MONTHLY_REQUESTS, absint( $usage['used'] ?? 0 ) );
		return array(
			'month'     => $month,
			'used'      => $used,
			'limit'     => self::MAX_MONTHLY_REQUESTS,
			'remaining' => max( 0, self::MAX_MONTHLY_REQUESTS - $used ),
		);
	}

	public static function reserve_post() {
		$token = wp_generate_uuid4();
		if ( ! self::acquire_lock( $token ) ) {
			return new WP_Error( 'xaia_budget_busy', __( '月間利用数の確認処理中です。少し待ってから再実行してください。', 'x-ai-assistant' ) );
		}

		$status = self::status();
		if ( $status['used'] >= $status['limit'] ) {
			self::release_lock( $token );
			return new WP_Error( 'xaia_monthly_limit', __( '月間20回のX API投稿上限に達したため、送信を停止しました。翌月に自動で再開します。', 'x-ai-assistant' ) );
		}

		++$status['used'];
		$status['remaining'] = max( 0, $status['limit'] - $status['used'] );
		$saved = update_option(
			self::OPTION_NAME,
			array(
				'month' => $status['month'],
				'used'  => $status['used'],
			),
			false
		);
		self::release_lock( $token );
		if ( ! $saved ) {
			return new WP_Error( 'xaia_budget_save_failed', __( '月間利用数を保存できなかったため、安全のためXへの送信を停止しました。', 'x-ai-assistant' ) );
		}

		return $status;
	}

	private static function acquire_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( ! is_array( $lock ) || empty( $lock['time'] ) || absint( $lock['time'] ) < time() - 30 ) {
			delete_option( self::LOCK_OPTION );
		}

		return add_option(
			self::LOCK_OPTION,
			array(
				'token' => $token,
				'time'  => time(),
			),
			'',
			false
		);
	}

	private static function release_lock( $token ) {
		$lock = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $lock ) && isset( $lock['token'] ) && hash_equals( (string) $lock['token'], (string) $token ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}

	private static function month_start_utc() {
		$local_start = wp_date( 'Y-m-01 00:00:00', null, wp_timezone() );
		return get_gmt_from_date( $local_start );
	}
}
