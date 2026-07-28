<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Interaction {
	const MENTION_HOOK      = 'xaia_check_mentions';
	const CANDIDATE_HOOK    = 'xaia_fetch_candidates';
	const STATE_OPTION      = 'xaia_interaction_state';
	const MENTIONS_OPTION   = 'xaia_mentions';
	const CANDIDATES_OPTION = 'xaia_candidates';
	const USER_OPTION       = 'xaia_x_authenticated_user';

	public function register() {
		add_action( 'init', array( $this, 'ensure_schedule' ) );
		add_action( self::MENTION_HOOK, array( $this, 'check_mentions' ) );
		add_action( self::CANDIDATE_HOOK, array( $this, 'fetch_candidates' ) );
		add_action( 'xaia_post_published_to_x', array( $this, 'schedule_candidates' ) );
	}

	public function ensure_schedule() {
		if ( ! wp_next_scheduled( self::MENTION_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::MENTION_HOOK );
		}
	}

	public function check_mentions() {
		$settings = XAIA_Settings::get_all();
		if ( empty( $settings['interaction_enabled'] ) ) {
			return new WP_Error( 'xaia_interaction_disabled', __( 'X交流支援が無効です。', 'x-ai-assistant' ) );
		}

		$user = $this->authenticated_user( $settings );
		if ( is_wp_error( $user ) ) {
			$this->log_error_once( 'user', $user->get_error_message() );
			return $user;
		}

		$state   = $this->state();
		$initial = empty( $state['mentions_initialized'] );
		$result  = ( new XAIA_X_Client( $settings ) )->mentions( $user['id'], $state['mentions_since_id'] ?? '' );
		if ( is_wp_error( $result ) ) {
			$this->log_error_once( 'mentions', $result->get_error_message() );
			return $result;
		}

		$mentions = self::mentions();
		$new      = array();
		foreach ( $result['data'] ?? array() as $item ) {
			if ( empty( $item['id'] ) || isset( $mentions[ $item['id'] ] ) ) {
				continue;
			}
			$id = sanitize_text_field( $item['id'] );
			$mentions[ $id ] = array(
				'id'         => $id,
				'text'       => sanitize_textarea_field( $item['text'] ?? '' ),
				'author_id'  => sanitize_text_field( $item['author_id'] ?? '' ),
				'created_at' => sanitize_text_field( $item['created_at'] ?? '' ),
				'status'     => 'new',
				'reply_id'   => '',
			);
			$new[] = $mentions[ $id ];
		}
		self::save_items( self::MENTIONS_OPTION, $mentions, 100 );

		if ( ! empty( $result['meta']['newest_id'] ) ) {
			$state['mentions_since_id'] = sanitize_text_field( $result['meta']['newest_id'] );
		}
		$state['mentions_initialized'] = '1';
		update_option( self::STATE_OPTION, $state, false );

		if ( ! $initial && ! empty( $new ) && ! empty( $settings['email_notifications'] ) ) {
			$this->send_mention_email( $new, $settings['notification_email'] );
		}

		return true;
	}

	public function schedule_candidates( $post_id ) {
		$settings = XAIA_Settings::get_all();
		if ( empty( $settings['interaction_enabled'] ) ) {
			return;
		}

		$week  = wp_date( 'o-W', null, wp_timezone() );
		$state = $this->state();
		if ( ( $state['candidate_week'] ?? '' ) === $week || ( $state['candidate_queued_week'] ?? '' ) === $week ) {
			return;
		}

		if ( wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CANDIDATE_HOOK, array( absint( $post_id ) ) ) ) {
			$state['candidate_queued_week'] = $week;
			update_option( self::STATE_OPTION, $state, false );
		} else {
			XAIA_Logger::add( $post_id, 'error', __( '交流候補の取得予約に失敗しました。', 'x-ai-assistant' ) );
		}
	}

	public function fetch_candidates( $post_id ) {
		$settings = XAIA_Settings::get_all();
		$state    = $this->state();
		if ( empty( $settings['interaction_enabled'] ) ) {
			unset( $state['candidate_queued_week'] );
			update_option( self::STATE_OPTION, $state, false );
			return;
		}

		$query = $this->candidate_query( absint( $post_id ) );
		if ( '' === $query ) {
			unset( $state['candidate_queued_week'] );
			update_option( self::STATE_OPTION, $state, false );
			return;
		}

		$user = $this->authenticated_user( $settings );
		if ( is_wp_error( $user ) ) {
			$this->candidate_failed( $state, $post_id, $user->get_error_message() );
			return;
		}
		$result = ( new XAIA_X_Client( $settings ) )->search_recent( $query );
		if ( is_wp_error( $result ) ) {
			$this->candidate_failed( $state, $post_id, $result->get_error_message() );
			return;
		}

		$candidates = self::candidates();
		$added      = 0;
		foreach ( $result['data'] ?? array() as $item ) {
			if ( 5 <= $added || empty( $item['id'] ) || ( $item['author_id'] ?? '' ) === $user['id'] ) {
				continue;
			}
			$id = sanitize_text_field( $item['id'] );
			if ( isset( $candidates[ $id ] ) ) {
				continue;
			}
			$candidates[ $id ] = array(
				'id'             => $id,
				'text'           => sanitize_textarea_field( $item['text'] ?? '' ),
				'author_id'      => sanitize_text_field( $item['author_id'] ?? '' ),
				'created_at'     => sanitize_text_field( $item['created_at'] ?? '' ),
				'source_post_id' => absint( $post_id ),
				'liked'          => false,
				'followed'       => false,
				'dismissed'      => false,
			);
			++$added;
		}
		self::save_items( self::CANDIDATES_OPTION, $candidates, 50 );

		$state['candidate_week'] = wp_date( 'o-W', null, wp_timezone() );
		unset( $state['candidate_queued_week'] );
		update_option( self::STATE_OPTION, $state, false );
		/* translators: %d: 取得した交流候補数。 */
		XAIA_Logger::add( $post_id, 'info', sprintf( __( '交流候補を%d件取得しました。', 'x-ai-assistant' ), $added ) );
	}

	public function like_candidate( $post_id ) {
		$candidates = self::candidates();
		if ( empty( $candidates[ $post_id ] ) || ! empty( $candidates[ $post_id ]['liked'] ) ) {
			return new WP_Error( 'xaia_candidate_missing', __( '対象の候補が見つからないか、すでにいいね済みです。', 'x-ai-assistant' ) );
		}

		$settings = XAIA_Settings::get_all();
		$user     = $this->authenticated_user( $settings );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$result = ( new XAIA_X_Client( $settings ) )->like_post( $user['id'], $post_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$candidates[ $post_id ]['liked'] = true;
		self::save_items( self::CANDIDATES_OPTION, $candidates, 50 );
		return true;
	}

	public function follow_candidate( $post_id ) {
		$candidates = self::candidates();
		if ( empty( $candidates[ $post_id ] ) || empty( $candidates[ $post_id ]['author_id'] ) || ! empty( $candidates[ $post_id ]['followed'] ) ) {
			return new WP_Error( 'xaia_candidate_missing', __( '対象の候補が見つからないか、すでにフォロー済みです。', 'x-ai-assistant' ) );
		}

		$settings = XAIA_Settings::get_all();
		$user     = $this->authenticated_user( $settings );
		if ( is_wp_error( $user ) ) {
			return $user;
		}
		$result = ( new XAIA_X_Client( $settings ) )->follow_user( $user['id'], $candidates[ $post_id ]['author_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$candidates[ $post_id ]['followed'] = true;
		self::save_items( self::CANDIDATES_OPTION, $candidates, 50 );
		return true;
	}

	public function reply_mention( $post_id, $text ) {
		$mentions = self::mentions();
		$text     = sanitize_textarea_field( $text );
		if ( empty( $mentions[ $post_id ] ) || 'replied' === ( $mentions[ $post_id ]['status'] ?? '' ) ) {
			return new WP_Error( 'xaia_mention_missing', __( '対象のメンションが見つからないか、すでに返信済みです。', 'x-ai-assistant' ) );
		}
		if ( '' === trim( $text ) ) {
			return new WP_Error( 'xaia_empty_reply', __( '返信文を入力してください。', 'x-ai-assistant' ) );
		}
		if ( preg_match( '#https?://#i', $text ) ) {
			return new WP_Error( 'xaia_reply_url', __( '月980円以内の予算を守るため、返信文にはURLを含められません。', 'x-ai-assistant' ) );
		}

		$result = ( new XAIA_X_Client( XAIA_Settings::get_all() ) )->create_post( $text, $post_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$mentions[ $post_id ]['status']   = 'replied';
		$mentions[ $post_id ]['reply_id'] = $result['id'];
		self::save_items( self::MENTIONS_OPTION, $mentions, 100 );
		XAIA_Logger::add( 0, 'interaction_success', __( 'メンションへ返信しました。', 'x-ai-assistant' ), $result['id'] );
		return true;
	}

	public function dismiss( $kind, $post_id ) {
		if ( 'mention' === $kind ) {
			$items = self::mentions();
			if ( isset( $items[ $post_id ] ) ) {
				$items[ $post_id ]['status'] = 'dismissed';
				self::save_items( self::MENTIONS_OPTION, $items, 100 );
			}
		} elseif ( 'candidate' === $kind ) {
			$items = self::candidates();
			if ( isset( $items[ $post_id ] ) ) {
				$items[ $post_id ]['dismissed'] = true;
				self::save_items( self::CANDIDATES_OPTION, $items, 50 );
			}
		}
	}

	public static function mentions() {
		$items = get_option( self::MENTIONS_OPTION, array() );
		return is_array( $items ) ? $items : array();
	}

	public static function candidates() {
		$items = get_option( self::CANDIDATES_OPTION, array() );
		return is_array( $items ) ? $items : array();
	}

	private function authenticated_user( array $settings ) {
		$user = get_option( self::USER_OPTION, array() );
		if ( is_array( $user ) && ! empty( $user['id'] ) ) {
			return $user;
		}

		$user = ( new XAIA_X_Client( $settings ) )->authenticated_user();
		if ( ! is_wp_error( $user ) ) {
			update_option( self::USER_OPTION, $user, false );
		}
		return $user;
	}

	private function state() {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	private function candidate_query( $post_id ) {
		$names = wp_get_post_terms( $post_id, array( 'post_tag', 'category' ), array( 'fields' => 'names' ) );
		if ( is_wp_error( $names ) ) {
			return '';
		}

		$terms = array();
		foreach ( array_unique( $names ) as $name ) {
			$name = preg_replace( '/[^\p{L}\p{N}_ー\s-]/u', '', wp_strip_all_tags( $name ) );
			if ( '' !== trim( $name ) ) {
				$terms[] = '"' . trim( $name ) . '"';
			}
			if ( 3 <= count( $terms ) ) {
				break;
			}
		}
		return empty( $terms ) ? '' : '(' . implode( ' OR ', $terms ) . ') lang:ja -is:retweet -is:reply';
	}

	private function candidate_failed( array $state, $post_id, $message ) {
		unset( $state['candidate_queued_week'] );
		update_option( self::STATE_OPTION, $state, false );
		/* translators: %s: X APIのエラーメッセージ。 */
		XAIA_Logger::add( $post_id, 'error', sprintf( __( '交流候補を取得できませんでした：%s', 'x-ai-assistant' ), $message ) );
	}

	private function send_mention_email( array $mentions, $email ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return;
		}

		/* translators: %d: 新着メンション数。 */
		$subject = sprintf( __( '【X AIアシスタント】新しいメンションが%d件あります', 'x-ai-assistant' ), count( $mentions ) );
		$lines   = array( __( 'Xで新しいメンションを受信しました。', 'x-ai-assistant' ), '' );
		foreach ( $mentions as $mention ) {
			/* translators: %s: XユーザーID。 */
			$lines[] = sprintf( __( '投稿者ID：%s', 'x-ai-assistant' ), $mention['author_id'] );
			$lines[] = $mention['text'];
			$lines[] = 'https://x.com/i/web/status/' . $mention['id'];
			$lines[] = '';
		}
		$lines[] = admin_url( 'options-general.php?page=x-ai-assistant-interaction' );
		if ( ! wp_mail( $email, $subject, implode( "\n", $lines ) ) ) {
			$this->log_error_once( 'mail', __( 'メンション通知メールを送信できませんでした。', 'x-ai-assistant' ) );
		}
	}

	private function log_error_once( $key, $message ) {
		$transient = 'xaia_error_' . sanitize_key( $key );
		if ( get_transient( $transient ) ) {
			return;
		}
		XAIA_Logger::add( 0, 'error', $message );
		set_transient( $transient, '1', 12 * HOUR_IN_SECONDS );
	}

	private static function save_items( $option, array $items, $limit ) {
		$items = array_slice( array_reverse( $items, true ), 0, absint( $limit ), true );
		$items = array_reverse( $items, true );
		update_option( $option, $items, false );
	}
}
