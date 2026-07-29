<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Settings {
	const OPTION_NAME = 'xaia_settings';

	public static function defaults() {
		return array(
			'enabled'                  => '1',
			'interaction_enabled'      => '1',
			'email_notifications'      => '1',
			'notification_email'       => sanitize_email( get_option( 'admin_email', '' ) ),
			'retention_days'           => 90,
			'delete_data_on_uninstall' => '0',
			'api_key'                  => '',
			'api_secret'               => '',
			'access_token'             => '',
			'access_token_secret'      => '',
			'template'                 => "ブログ更新しました。\nご興味ある方は、読んでみてください。\n\n{title}\n\n{url}\n\n{hashtags}",
			'schema_version'           => XAIA_VERSION,
		);
	}

	public static function maybe_upgrade() {
		$stored_version = (string) get_option( 'xaia_version', '1.0.0' );
		if ( version_compare( $stored_version, XAIA_VERSION, '>=' ) ) {
			return;
		}

		$settings = get_option( self::OPTION_NAME, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$old_defaults = array(
			"{title}\n{url}",
			"{title}\n\n{excerpt}\n\n{url}\n\n{hashtags}",
			"{title}\n\n{url}\n\n{hashtags}",
		);
		if ( isset( $settings['template'] ) && in_array( $settings['template'], $old_defaults, true ) ) {
			$settings['template'] = self::defaults()['template'];
		}
		$settings['schema_version'] = XAIA_VERSION;
		update_option( self::OPTION_NAME, $settings, false );
		update_option( 'xaia_version', XAIA_VERSION, false );
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

	public static function retention_days( $value ) {
		return max( 30, min( 365, absint( $value ) ) );
	}

	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = self::get_all( false );
		$output  = array(
			'enabled'                  => empty( $input['enabled'] ) ? '0' : '1',
			'interaction_enabled'      => empty( $input['interaction_enabled'] ) ? '0' : '1',
			'email_notifications'      => empty( $input['email_notifications'] ) ? '0' : '1',
			'notification_email'       => isset( $input['notification_email'] ) ? sanitize_email( wp_unslash( $input['notification_email'] ) ) : self::defaults()['notification_email'],
			'retention_days'           => self::retention_days( $input['retention_days'] ?? self::defaults()['retention_days'] ),
			'delete_data_on_uninstall' => empty( $input['delete_data_on_uninstall'] ) ? '0' : '1',
			'template'                 => isset( $input['template'] ) ? sanitize_textarea_field( $input['template'] ) : self::defaults()['template'],
			'schema_version'           => XAIA_VERSION,
		);

		if ( '' === trim( $output['template'] ) ) {
			$output['template'] = self::defaults()['template'];
		}

		$credentials_changed = false;
		foreach ( self::secret_keys() as $key ) {
			$value = isset( $input[ $key ] ) ? trim( wp_unslash( $input[ $key ] ) ) : '';
			$credentials_changed = $credentials_changed || '' !== $value;
			$output[ $key ] = '' !== $value ? XAIA_Credentials::encrypt( $value ) : ( $current[ $key ] ?? '' );
		}
		if ( $credentials_changed ) {
			delete_option( XAIA_Interaction::USER_OPTION );
		}

		return $output;
	}
}
