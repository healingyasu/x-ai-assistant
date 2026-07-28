<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Credentials {
	const PREFIX = 'xaia:v1:';

	public static function encrypt( $plain_text ) {
		if ( '' === $plain_text ) {
			return '';
		}

		$key = self::key();
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain_text, $nonce, $key );
			return self::PREFIX . 's:' . base64_encode( $nonce . $cipher );
		}

		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = random_bytes( 12 );
			$tag = '';
			$cipher = openssl_encrypt( $plain_text, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( false !== $cipher ) {
				return self::PREFIX . 'o:' . base64_encode( $iv . $tag . $cipher );
			}
		}

		return '';
	}

	public static function decrypt( $stored ) {
		if ( ! is_string( $stored ) || '' === $stored ) {
			return '';
		}

		if ( 0 !== strpos( $stored, self::PREFIX ) ) {
			return $stored;
		}

		$method = substr( $stored, strlen( self::PREFIX ), 2 );
		$data   = base64_decode( substr( $stored, strlen( self::PREFIX ) + 2 ), true );
		if ( false === $data ) {
			return '';
		}

		if ( 's:' === $method && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce = substr( $data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain = sodium_crypto_secretbox_open( substr( $data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, self::key() );
			return false === $plain ? '' : $plain;
		}

		if ( 'o:' === $method && function_exists( 'openssl_decrypt' ) ) {
			$plain = openssl_decrypt( substr( $data, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $data, 0, 12 ), substr( $data, 12, 16 ) );
			return false === $plain ? '' : $plain;
		}

		return '';
	}

	public static function encryption_available() {
		return function_exists( 'sodium_crypto_secretbox' ) || function_exists( 'openssl_encrypt' );
	}

	private static function key() {
		$material = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
		return hash( 'sha256', 'x-ai-assistant|' . $material, true );
	}
}
