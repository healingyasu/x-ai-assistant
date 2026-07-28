<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Budget {
	const OPTION_NAME       = 'xaia_monthly_usage';
	const LOCK_OPTION       = 'xaia_budget_lock';
	const MAX_MILLIUSD      = 4500;
	const URL_POST_COST     = 200;
	const TEXT_POST_COST    = 15;
	const INTERACTION_COST  = 15;
	const POST_READ_COST    = 5;
	const USER_READ_COST    = 10;
	const OWNED_READ_COST   = 1;
	const CATEGORY_LIMITS   = array(
		'posts'        => 4000,
		'candidates'   => 250,
		'mentions'     => 100,
		'interactions' => 130,
		'identity'     => 20,
	);

	public static function status() {
		$month = wp_date( 'Y-m', null, wp_timezone() );
		$usage = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $usage ) || empty( $usage['month'] ) || $month !== $usage['month'] ) {
			$posts = XAIA_Logger::count_successes_since( self::month_start_utc() );
			$usage = array(
				'month'          => $month,
				'spent_milliusd' => $posts * self::URL_POST_COST,
				'breakdown'      => array( 'posts' => $posts * self::URL_POST_COST ),
			);
			update_option( self::OPTION_NAME, $usage, false );
		} elseif ( ! isset( $usage['spent_milliusd'] ) ) {
			$legacy_used = absint( $usage['used'] ?? 0 );
			$usage = array(
				'month'          => $month,
				'spent_milliusd' => $legacy_used * self::URL_POST_COST,
				'breakdown'      => array( 'posts' => $legacy_used * self::URL_POST_COST ),
			);
			update_option( self::OPTION_NAME, $usage, false );
		}

		$spent     = min( self::MAX_MILLIUSD, absint( $usage['spent_milliusd'] ?? 0 ) );
		$breakdown = isset( $usage['breakdown'] ) && is_array( $usage['breakdown'] ) ? array_map( 'absint', $usage['breakdown'] ) : array();
		return array(
			'month'              => $month,
			'spent_milliusd'     => $spent,
			'limit_milliusd'     => self::MAX_MILLIUSD,
			'remaining_milliusd' => max( 0, self::MAX_MILLIUSD - $spent ),
			'breakdown'          => $breakdown,
		);
	}

	public static function reserve( $milliusd, $category ) {
		$milliusd = absint( $milliusd );
		$category = sanitize_key( $category );
		if ( 0 === $milliusd || '' === $category ) {
			return new WP_Error( 'xaia_invalid_budget', __( 'API費用の計算に失敗したため、送信を停止しました。', 'x-ai-assistant' ) );
		}

		$token = wp_generate_uuid4();
		if ( ! self::acquire_lock( $token ) ) {
			return new WP_Error( 'xaia_budget_busy', __( '月間API予算の確認処理中です。少し待ってから再実行してください。', 'x-ai-assistant' ) );
		}

		$status = self::status();
		if ( $milliusd > $status['remaining_milliusd'] ) {
			self::release_lock( $token );
			return new WP_Error( 'xaia_monthly_limit', __( '月間4.50米ドル相当のAPI予算上限に達するため、X APIへの通信を停止しました。翌月に自動で再開します。', 'x-ai-assistant' ) );
		}
		$category_spent = absint( $status['breakdown'][ $category ] ?? 0 );
		if ( isset( self::CATEGORY_LIMITS[ $category ] ) && self::CATEGORY_LIMITS[ $category ] < $category_spent + $milliusd ) {
			self::release_lock( $token );
			return new WP_Error( 'xaia_category_limit', __( 'この機能の月間API予算枠に達したため、X APIへの通信を停止しました。翌月に自動で再開します。', 'x-ai-assistant' ) );
		}

		$status['spent_milliusd'] += $milliusd;
		$status['breakdown'][ $category ] = $category_spent + $milliusd;
		$saved = update_option(
			self::OPTION_NAME,
			array(
				'month'          => $status['month'],
				'spent_milliusd' => $status['spent_milliusd'],
				'breakdown'      => $status['breakdown'],
			),
			false
		);
		self::release_lock( $token );
		if ( ! $saved ) {
			return new WP_Error( 'xaia_budget_save_failed', __( '月間利用数を保存できなかったため、安全のためXへの送信を停止しました。', 'x-ai-assistant' ) );
		}

		return $status;
	}

	public static function refund( $milliusd, $category ) {
		$milliusd = absint( $milliusd );
		$category = sanitize_key( $category );
		$token    = wp_generate_uuid4();
		if ( 0 === $milliusd || '' === $category || ! self::acquire_lock( $token ) ) {
			return false;
		}

		$status         = self::status();
		$category_spent = absint( $status['breakdown'][ $category ] ?? 0 );
		$refund         = min( $milliusd, $category_spent, $status['spent_milliusd'] );
		$status['spent_milliusd'] -= $refund;
		$status['breakdown'][ $category ] = $category_spent - $refund;
		$saved = update_option(
			self::OPTION_NAME,
			array(
				'month'          => $status['month'],
				'spent_milliusd' => $status['spent_milliusd'],
				'breakdown'      => $status['breakdown'],
			),
			false
		);
		self::release_lock( $token );

		return $saved;
	}

	public static function dollars( $milliusd ) {
		return number_format_i18n( absint( $milliusd ) / 1000, 2 );
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
