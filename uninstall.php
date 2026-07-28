<?php
/**
 * アンインストール処理。
 *
 * 投稿履歴を保持するため、初期設定ではデータを削除しません。
 * 削除する場合は、アンインストール前にXAIA_DELETE_DATA_ON_UNINSTALLをtrueとして定義します。
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'XAIA_DELETE_DATA_ON_UNINSTALL' ) || ! XAIA_DELETE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;
delete_option( 'xaia_settings' );
delete_option( 'xaia_version' );
delete_option( 'xaia_monthly_usage' );
delete_option( 'xaia_budget_lock' );
delete_option( 'xaia_interaction_state' );
delete_option( 'xaia_mentions' );
delete_option( 'xaia_candidates' );
delete_option( 'xaia_x_authenticated_user' );
wp_clear_scheduled_hook( 'xaia_scheduled_publish' );
wp_clear_scheduled_hook( 'xaia_check_mentions' );
wp_clear_scheduled_hook( 'xaia_fetch_candidates' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'xaia_logs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_x_post_id' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_posted_at' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_publish_lock' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_post_enabled' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_post_template' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_scheduled_timestamp' ), array( '%s' ) );
