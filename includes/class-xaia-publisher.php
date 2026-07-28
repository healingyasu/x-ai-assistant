<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Publisher {
	const POSTED_META = '_xaia_x_post_id';
	const LOCK_META   = '_xaia_publish_lock';
	const CRON_HOOK   = 'xaia_scheduled_publish';

	public function register() {
		add_action( 'wp_after_insert_post', array( $this, 'after_insert_post' ), 10, 4 );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_publish' ) );
	}

	public function after_insert_post( $post_id, $post, $update, $post_before ) {
		$old_status = $post_before instanceof WP_Post ? $post_before->post_status : '';
		if ( 'publish' !== $post->post_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
			return;
		}

		$settings = XAIA_Settings::get_all();
		if ( empty( $settings['enabled'] ) || ! XAIA_Post_Editor::is_enabled( $post_id ) ) {
			return;
		}

		$scheduled = absint( get_post_meta( $post_id, XAIA_Post_Editor::SCHEDULE_META, true ) );
		if ( $scheduled > time() + MINUTE_IN_SECONDS ) {
			$this->schedule_post( $post_id, $scheduled );
			return;
		}

		$this->publish_post( $post, $settings );
	}

	public function schedule_post( $post_id, $timestamp ) {
		$next = wp_next_scheduled( self::CRON_HOOK, array( $post_id ) );
		if ( $next && absint( $next ) === absint( $timestamp ) ) {
			return;
		}
		if ( $next ) {
			wp_unschedule_event( $next, self::CRON_HOOK, array( $post_id ) );
		}
		if ( wp_schedule_single_event( $timestamp, self::CRON_HOOK, array( $post_id ) ) ) {
			/* translators: %s: X投稿の予約日時。 */
			XAIA_Logger::add( $post_id, 'scheduled', sprintf( __( 'X投稿を%sに予約しました。', 'x-ai-assistant' ), wp_date( 'Y-m-d H:i', $timestamp, wp_timezone() ) ) );
		} else {
			XAIA_Logger::add( $post_id, 'error', __( 'X投稿の予約登録に失敗しました。', 'x-ai-assistant' ) );
		}
	}

	public function run_scheduled_publish( $post_id ) {
		$post = get_post( absint( $post_id ) );
		$settings = XAIA_Settings::get_all();
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || empty( $settings['enabled'] ) || ! XAIA_Post_Editor::is_enabled( $post->ID ) ) {
			return;
		}

		$this->publish_post( $post, $settings );
	}

	public function publish_post( WP_Post $post, array $settings = array() ) {
		if ( get_post_meta( $post->ID, self::POSTED_META, true ) ) {
			return new WP_Error( 'xaia_already_posted', __( 'この記事はすでにXへ投稿されています。', 'x-ai-assistant' ) );
		}

		$lock = absint( get_post_meta( $post->ID, self::LOCK_META, true ) );
		if ( $lock && $lock > time() - 15 * MINUTE_IN_SECONDS ) {
			return new WP_Error( 'xaia_locked', __( 'Xへの投稿処理を実行中です。', 'x-ai-assistant' ) );
		}
		if ( $lock ) {
			delete_post_meta( $post->ID, self::LOCK_META );
		}
		if ( ! add_post_meta( $post->ID, self::LOCK_META, time(), true ) ) {
			return new WP_Error( 'xaia_lock_failed', __( 'X投稿処理の重複防止ロックを取得できませんでした。', 'x-ai-assistant' ) );
		}

		$settings = wp_parse_args( $settings, XAIA_Settings::get_all() );
		$text     = $this->build_post_text( $post, $settings );
		$result   = ( new XAIA_X_Client( $settings ) )->create_post( $text );

		delete_post_meta( $post->ID, self::LOCK_META );
		if ( is_wp_error( $result ) ) {
			XAIA_Logger::add( $post->ID, 'error', $result->get_error_message() );
			return $result;
		}

		update_post_meta( $post->ID, self::POSTED_META, $result['id'] );
		update_post_meta( $post->ID, '_xaia_posted_at', current_time( 'mysql', true ) );
		XAIA_Logger::add( $post->ID, 'success', __( 'Xへ投稿しました。', 'x-ai-assistant' ), $result['id'] );

		return $result;
	}

	public function render_template( $template, WP_Post $post ) {
		$values = array(
			'{title}'      => wp_strip_all_tags( get_the_title( $post ) ),
			'{excerpt}'    => $this->get_excerpt( $post ),
			'{url}'        => get_permalink( $post ),
			'{hashtags}'   => $this->get_hashtags( $post ),
			'{categories}' => $this->get_categories( $post ),
		);
		$text = trim( strtr( $template, $values ) );
		return preg_replace( "/[ \t]+\n/", "\n", preg_replace( "/\n{3,}/", "\n\n", $text ) );
	}

	public function build_post_text( WP_Post $post, array $settings = array() ) {
		$settings = wp_parse_args( $settings, XAIA_Settings::get_all() );
		$template = get_post_meta( $post->ID, XAIA_Post_Editor::TEMPLATE_META, true );
		if ( '' === trim( (string) $template ) ) {
			$template = $settings['template'];
		}

		return $this->render_template( $template, $post );
	}

	private function get_excerpt( WP_Post $post ) {
		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		return wp_trim_words( $excerpt, 35, '…' );
	}

	private function get_hashtags( WP_Post $post ) {
		$names = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );
		if ( is_wp_error( $names ) ) {
			$names = array();
		}
		if ( count( $names ) < 5 ) {
			$categories = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $categories ) ) {
				$names = array_merge( $names, $categories );
			}
		}

		$hashtags = array();
		foreach ( array_unique( $names ) as $name ) {
			$name = preg_replace( '/[\s#＃]+/u', '', wp_strip_all_tags( $name ) );
			$name = preg_replace( '/[^\p{L}\p{N}_ー-]/u', '', $name );
			if ( '' !== $name ) {
				$hashtags[] = '#' . $name;
			}
			if ( 5 <= count( $hashtags ) ) {
				break;
			}
		}

		return implode( ' ', $hashtags );
	}

	private function get_categories( WP_Post $post ) {
		$names = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
		return is_wp_error( $names ) ? '' : implode( '、', $names );
	}
}
