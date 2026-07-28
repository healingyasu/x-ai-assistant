<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_X_Client {
	const ENDPOINT = 'https://api.x.com/2/tweets';
	const API_BASE = 'https://api.x.com/2';

	private $credentials;

	public function __construct( array $credentials ) {
		$this->credentials = $credentials;
	}

	public function create_post( $text, $reply_to = '' ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return new WP_Error( 'xaia_empty_text', __( 'Xへ投稿する文章が空です。', 'x-ai-assistant' ) );
		}

		$body = array( 'text' => $text );
		if ( '' !== $reply_to && preg_match( '/^[0-9]{1,19}$/', $reply_to ) ) {
			$body['reply']        = array( 'in_reply_to_tweet_id' => $reply_to );
			$body['made_with_ai'] = true;
		}

		$cost     = preg_match( '#https?://#i', $text ) ? XAIA_Budget::URL_POST_COST : XAIA_Budget::TEXT_POST_COST;
		$category = isset( $body['reply'] ) ? 'interactions' : 'posts';
		$result   = $this->paid_request( $cost, $category, 'POST', self::ENDPOINT, array(), $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( empty( $result['data']['id'] ) ) {
			return new WP_Error( 'xaia_x_api_error', __( 'X APIから投稿IDが返されませんでした。', 'x-ai-assistant' ) );
		}

		return array(
			'id'   => sanitize_text_field( $result['data']['id'] ),
			'text' => isset( $result['data']['text'] ) ? sanitize_textarea_field( $result['data']['text'] ) : $text,
		);
	}

	public function authenticated_user() {
		$result = $this->paid_request( XAIA_Budget::USER_READ_COST, 'identity', 'GET', self::API_BASE . '/users/me' );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( empty( $result['data']['id'] ) ) {
			return new WP_Error( 'xaia_x_user_missing', __( '認証中のXユーザー情報を取得できませんでした。', 'x-ai-assistant' ) );
		}

		return array(
			'id'       => sanitize_text_field( $result['data']['id'] ),
			'name'     => sanitize_text_field( $result['data']['name'] ?? '' ),
			'username' => sanitize_text_field( $result['data']['username'] ?? '' ),
		);
	}

	public function mentions( $user_id, $since_id = '' ) {
		$query = array(
			'max_results'  => 10,
			'tweet.fields' => 'author_id,created_at',
		);
		if ( preg_match( '/^[0-9]{1,19}$/', $since_id ) ) {
			$query['since_id'] = $since_id;
		}

		$reserved = 10 * XAIA_Budget::OWNED_READ_COST;
		$result   = $this->paid_request( $reserved, 'mentions', 'GET', self::API_BASE . '/users/' . rawurlencode( $user_id ) . '/mentions', $query );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$actual = count( $result['data'] ?? array() ) * XAIA_Budget::OWNED_READ_COST;
		if ( $reserved > $actual ) {
			XAIA_Budget::refund( $reserved - $actual, 'mentions' );
		}

		return $result;
	}

	public function search_recent( $query_text ) {
		$query = array(
			'query'        => $query_text,
			'max_results'  => 10,
			'tweet.fields' => 'author_id,created_at',
		);
		$reserved = 10 * XAIA_Budget::POST_READ_COST;
		$result   = $this->paid_request( $reserved, 'candidates', 'GET', self::API_BASE . '/tweets/search/recent', $query );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$actual = count( $result['data'] ?? array() ) * XAIA_Budget::POST_READ_COST;
		if ( $reserved > $actual ) {
			XAIA_Budget::refund( $reserved - $actual, 'candidates' );
		}

		return $result;
	}

	public function like_post( $user_id, $post_id ) {
		return $this->paid_request(
			XAIA_Budget::INTERACTION_COST,
			'interactions',
			'POST',
			self::API_BASE . '/users/' . rawurlencode( $user_id ) . '/likes',
			array(),
			array( 'tweet_id' => $post_id )
		);
	}

	public function follow_user( $user_id, $target_user_id ) {
		return $this->paid_request(
			XAIA_Budget::INTERACTION_COST,
			'interactions',
			'POST',
			self::API_BASE . '/users/' . rawurlencode( $user_id ) . '/following',
			array(),
			array( 'target_user_id' => $target_user_id )
		);
	}

	private function paid_request( $cost, $category, $method, $endpoint, array $query = array(), array $body = array() ) {
		foreach ( XAIA_Settings::secret_keys() as $key ) {
			if ( empty( $this->credentials[ $key ] ) ) {
				return new WP_Error( 'xaia_missing_credentials', __( 'X APIの認証情報がすべて入力されていません。', 'x-ai-assistant' ) );
			}
		}

		$budget = XAIA_Budget::reserve( $cost, $category );
		if ( is_wp_error( $budget ) ) {
			return $budget;
		}

		$result = $this->request( $method, $endpoint, $query, $body );
		if ( is_wp_error( $result ) && $result->get_error_data( 'xaia_x_api_error' ) ) {
			XAIA_Budget::refund( $cost, $category );
		}

		return $result;
	}

	private function request( $method, $endpoint, array $query = array(), array $body = array() ) {
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
		$oauth['oauth_signature'] = $this->signature( $method, $endpoint, $oauth, $query );
		$url = empty( $query ) ? $endpoint : add_query_arg( $query, $endpoint );
		$args = array(
			'method'      => strtoupper( $method ),
			'timeout'     => 20,
			'redirection' => 0,
			'headers'     => array(
				'Authorization' => $this->authorization_header( $oauth ),
				'Accept'        => 'application/json',
			),
		);
		if ( ! empty( $body ) ) {
			$args['headers']['Content-Type'] = 'application/json; charset=utf-8';
			$args['body'] = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}

		$response = wp_safe_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = is_array( $decoded ) ? ( $decoded['detail'] ?? $decoded['title'] ?? __( 'X APIから予期しない応答が返されました。', 'x-ai-assistant' ) ) : __( 'X APIから予期しない応答が返されました。', 'x-ai-assistant' );
			if ( ! empty( $decoded['errors'][0]['message'] ) ) {
				$message = $decoded['errors'][0]['message'];
			}
			return new WP_Error( 'xaia_x_api_error', sanitize_text_field( $message ), array( 'status' => $status ) );
		}

		return is_array( $decoded ) ? $decoded : array();
	}

	private function signature( $method, $url, array $oauth, array $query = array() ) {
		$parameters = array_merge( $query, $oauth );
		ksort( $parameters, SORT_STRING );
		$pairs = array();
		foreach ( $parameters as $key => $value ) {
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
