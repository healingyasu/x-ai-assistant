<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_X_Client {
	const ENDPOINT = 'https://api.x.com/2/tweets';

	private $credentials;

	public function __construct( array $credentials ) {
		$this->credentials = $credentials;
	}

	public function create_post( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return new WP_Error( 'xaia_empty_text', __( 'Xへ投稿する文章が空です。', 'x-ai-assistant' ) );
		}

		foreach ( XAIA_Settings::secret_keys() as $key ) {
			if ( empty( $this->credentials[ $key ] ) ) {
				return new WP_Error( 'xaia_missing_credentials', __( 'X APIの認証情報がすべて入力されていません。', 'x-ai-assistant' ) );
			}
		}

		$oauth = array(
			'oauth_consumer_key'     => $this->credentials['api_key'],
			'oauth_nonce'            => wp_generate_password( 32, false, false ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => (string) time(),
			'oauth_token'            => $this->credentials['access_token'],
			'oauth_version'          => '1.0',
		);
		$oauth['oauth_signature'] = $this->signature( 'POST', self::ENDPOINT, $oauth );

		$response = wp_safe_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => $this->authorization_header( $oauth ),
					'Content-Type'  => 'application/json; charset=utf-8',
					'Accept'        => 'application/json',
				),
				'body'        => wp_json_encode( array( 'text' => $text ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || empty( $body['data']['id'] ) ) {
			$message = $body['detail'] ?? $body['title'] ?? __( 'X APIから予期しない応答が返されました。', 'x-ai-assistant' );
			if ( ! empty( $body['errors'][0]['message'] ) ) {
				$message = $body['errors'][0]['message'];
			}
			return new WP_Error( 'xaia_x_api_error', sanitize_text_field( $message ), array( 'status' => $status ) );
		}

		return array(
			'id'   => sanitize_text_field( $body['data']['id'] ),
			'text' => isset( $body['data']['text'] ) ? sanitize_textarea_field( $body['data']['text'] ) : $text,
		);
	}

	private function signature( $method, $url, array $oauth ) {
		ksort( $oauth );
		$pairs = array();
		foreach ( $oauth as $key => $value ) {
			$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}

		$base = strtoupper( $method ) . '&' . rawurlencode( $url ) . '&' . rawurlencode( implode( '&', $pairs ) );
		$key  = rawurlencode( $this->credentials['api_secret'] ) . '&' . rawurlencode( $this->credentials['access_token_secret'] );

		return base64_encode( hash_hmac( 'sha1', $base, $key, true ) );
	}

	private function authorization_header( array $oauth ) {
		ksort( $oauth );
		$parts = array();
		foreach ( $oauth as $key => $value ) {
			$parts[] = rawurlencode( $key ) . '="' . rawurlencode( $value ) . '"';
		}

		return 'OAuth ' . implode( ', ', $parts );
	}
}
