<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Plugin {
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		( new XAIA_Publisher() )->register();

		if ( is_admin() ) {
			( new XAIA_Admin() )->register();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'x-ai-assistant', false, dirname( plugin_basename( XAIA_FILE ) ) . '/languages' );
	}
}
