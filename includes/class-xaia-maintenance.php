<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 保存データの保持期間と消去処理を管理します。
 */
final class XAIA_Maintenance {
	const CLEANUP_HOOK = 'xaia_cleanup_stored_data';

	public function register() {
		add_action( 'init', array( $this, 'ensure_schedule' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup' ) );
	}

	public static function activate() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	public function ensure_schedule() {
		self::activate();
	}

	public function cleanup() {
		$settings = XAIA_Settings::get_all( false );
		$days     = XAIA_Settings::retention_days( $settings['retention_days'] ?? 90 );
		XAIA_Logger::delete_older_than( $days );

		return XAIA_Lock::run(
			'mentions',
			function () use ( $days ) {
				$this->prune_option( XAIA_Interaction::MENTIONS_OPTION, $days );
				return XAIA_Lock::run(
					'candidates',
					function () use ( $days ) {
						$this->prune_option( XAIA_Interaction::CANDIDATES_OPTION, $days );
						return true;
					}
				);
			}
		);
	}

	public static function clear_activity_data() {
		return XAIA_Lock::run(
			'mentions',
			function () {
				return XAIA_Lock::run(
					'candidates',
					function () {
						delete_option( XAIA_Interaction::MENTIONS_OPTION );
						delete_option( XAIA_Interaction::CANDIDATES_OPTION );
						delete_option( XAIA_Interaction::STATE_OPTION );
						delete_option( XAIA_Interaction::USER_OPTION );
						delete_transient( 'xaia_error_user' );
						delete_transient( 'xaia_error_mentions' );
						delete_transient( 'xaia_error_mail' );
						return XAIA_Logger::clear() ? true : new WP_Error( 'xaia_clear_failed', __( '投稿ログを消去できませんでした。', 'x-ai-assistant' ) );
					}
				);
			}
		);
	}

	private function prune_option( $option, $days ) {
		$items  = get_option( $option, array() );
		$cutoff = time() - absint( $days ) * DAY_IN_SECONDS;
		if ( ! is_array( $items ) ) {
			delete_option( $option );
			return;
		}

		$kept = array();
		foreach ( $items as $key => $item ) {
			$created = is_array( $item ) ? strtotime( (string) ( $item['created_at'] ?? '' ) ) : false;
			if ( false !== $created && $created >= $cutoff ) {
				$kept[ $key ] = $item;
			}
		}
		if ( count( $kept ) !== count( $items ) ) {
			update_option( $option, $kept, false );
		}
	}
}
