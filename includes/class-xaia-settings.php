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
			'template'            => "{title}\n{url}",
		);
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
