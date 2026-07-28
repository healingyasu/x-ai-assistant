<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Publisher {
	const POSTED_META = '_xaia_x_post_id';
	const LOCK_META   = '_xaia_publish_lock';

	public function register() {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );
	}

	public function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type ) {
			return;
		}

		$settings = XAIA_Settings::get_all();
		if ( empty( $settings['enabled'] ) ) {
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
		$text     = $this->render_template( $settings['template'], $post );
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
			'{title}' => wp_strip_all_tags( get_the_title( $post ) ),
			'{url}'   => get_permalink( $post ),
		);
		return trim( strtr( $template, $values ) );
	}
}
