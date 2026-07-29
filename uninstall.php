<?php
/**
 * アンインストール処理。
 *
 * 初期設定ではデータを保持します。設定画面または定数で完全削除を選べます。
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings    = get_option( 'xaia_settings', array() );
$delete_data = defined( 'XAIA_DELETE_DATA_ON_UNINSTALL' ) && XAIA_DELETE_DATA_ON_UNINSTALL;
$delete_data = $delete_data || ( is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] ) );

wp_clear_scheduled_hook( 'xaia_scheduled_publish' );
wp_clear_scheduled_hook( 'xaia_check_mentions' );
wp_clear_scheduled_hook( 'xaia_fetch_candidates' );
wp_clear_scheduled_hook( 'xaia_cleanup_stored_data' );
delete_option( 'xaia_budget_lock' );
delete_option( 'xaia_operation_lock_mentions' );
delete_option( 'xaia_operation_lock_candidates' );
delete_option( 'xaia_operation_lock_test_post' );

if ( ! $delete_data ) {
	return;
}

global $wpdb;
delete_option( 'xaia_settings' );
delete_option( 'xaia_version' );
delete_option( 'xaia_monthly_usage' );
delete_option( 'xaia_interaction_state' );
delete_option( 'xaia_mentions' );
delete_option( 'xaia_candidates' );
delete_option( 'xaia_x_authenticated_user' );
delete_transient( 'xaia_error_user' );
delete_transient( 'xaia_error_mentions' );
delete_transient( 'xaia_error_mail' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'xaia_logs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_x_post_id' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_posted_at' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_publish_lock' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_post_enabled' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_post_template' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_scheduled_timestamp' ), array( '%s' ) );
