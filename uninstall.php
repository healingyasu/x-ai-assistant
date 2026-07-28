<?php
/**
 * Uninstall handler.
 *
 * Data is retained by default to preserve the posting audit trail.
 * Define XAIA_DELETE_DATA_ON_UNINSTALL as true before uninstalling to remove it.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'XAIA_DELETE_DATA_ON_UNINSTALL' ) || ! XAIA_DELETE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;
delete_option( 'xaia_settings' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'xaia_logs' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.NotPrepared
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_x_post_id' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_posted_at' ), array( '%s' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_xaia_publish_lock' ), array( '%s' ) );
