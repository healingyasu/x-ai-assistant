<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Logger {
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'xaia_logs';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			x_post_id varchar(64) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL,
			message text NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY status (status)
		) {$charset};";
		dbDelta( $sql );
	}

	public static function add( $post_id, $status, $message, $x_post_id = '' ) {
		global $wpdb;
		$wpdb->insert(
			self::table_name(),
			array(
				'post_id'   => absint( $post_id ),
				'x_post_id' => sanitize_text_field( $x_post_id ),
				'status'    => sanitize_key( $status ),
				'message'   => wp_strip_all_tags( $message ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	public static function latest( $limit = 50 ) {
		global $wpdb;
		$limit = min( 100, max( 1, absint( $limit ) ) );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' ORDER BY id DESC LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
