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
		XAIA_Settings::maybe_upgrade();
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		( new XAIA_Publisher() )->register();
		( new XAIA_Post_Editor() )->register();
		( new XAIA_Interaction() )->register();
		( new XAIA_Maintenance() )->register();

		if ( is_admin() ) {
			( new XAIA_Admin() )->register();
			( new XAIA_Interaction_Admin() )->register();
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'x-ai-assistant', false, dirname( plugin_basename( XAIA_FILE ) ) . '/languages' );
	}
}
