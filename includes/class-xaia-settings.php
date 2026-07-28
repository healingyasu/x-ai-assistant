<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Settings {
	const OPTION_NAME = 'xaia_settings';

	public static function defaults() {
		return array(
			'enabled'             => '1',
			'api_key'             => '',
			'api_secret'          => '',
			'access_token'        => '',
			'access_token_secret' => '',
			'template'            => "{title}\n\n{excerpt}\n\n{url}\n\n{hashtags}",
		);
	}

	public static function maybe_upgrade() {
		$stored_version = (string) get_option( 'xaia_version', '1.0.0' );
		if ( version_compare( $stored_version, XAIA_VERSION, '>=' ) ) {
			return;
		}

		$settings = get_option( self::OPTION_NAME, array() );
		if ( isset( $settings['template'] ) && "{title}\n{url}" === $settings['template'] ) {
			$settings['template'] = self::defaults()['template'];
			update_option( self::OPTION_NAME, $settings );
		}
		update_option( 'xaia_version', XAIA_VERSION );
	}

	public static function get_all( $decrypt = true ) {
		$settings = wp_parse_args( get_option( self::OPTION_NAME, array() ), self::defaults() );

		if ( $decrypt ) {
			foreach ( self::secret_keys() as $key ) {
				$settings[ $key ] = XAIA_Credentials::decrypt( $settings[ $key ] );
			}
		}

		return $settings;
	}

	public static function secret_keys() {
		return array( 'api_key', 'api_secret', 'access_token', 'access_token_secret' );
	}

	public static function credentials_complete() {
		$settings = self::get_all();
		foreach ( self::secret_keys() as $key ) {
			if ( empty( $settings[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = self::get_all( false );
		$output  = array(
			'enabled'  => empty( $input['enabled'] ) ? '0' : '1',
			'template' => isset( $input['template'] ) ? sanitize_textarea_field( $input['template'] ) : self::defaults()['template'],
		);

		if ( '' === trim( $output['template'] ) ) {
			$output['template'] = self::defaults()['template'];
		}

		foreach ( self::secret_keys() as $key ) {
			$value = isset( $input[ $key ] ) ? trim( wp_unslash( $input[ $key ] ) ) : '';
			$output[ $key ] = '' !== $value ? XAIA_Credentials::encrypt( $value ) : ( $current[ $key ] ?? '' );
		}

		return $output;
	}
}
