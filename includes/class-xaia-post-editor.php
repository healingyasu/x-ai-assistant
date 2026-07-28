<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Post_Editor {
	const ENABLED_META  = '_xaia_post_enabled';
	const TEMPLATE_META = '_xaia_post_template';
	const SCHEDULE_META = '_xaia_scheduled_timestamp';

	public function register() {
		add_action( 'add_meta_boxes_post', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save' ), 10, 2 );
	}

	public function add_meta_box() {
		add_meta_box(
			'xaia-post-settings',
			__( 'X AIアシスタント', 'x-ai-assistant' ),
			array( $this, 'render' ),
			'post',
			'side',
			'high'
		);
	}

	public function render( WP_Post $post ) {
		wp_nonce_field( 'xaia_save_post_settings', 'xaia_post_nonce' );
		$enabled   = self::is_enabled( $post->ID );
		$template  = (string) get_post_meta( $post->ID, self::TEMPLATE_META, true );
		$timestamp = absint( get_post_meta( $post->ID, self::SCHEDULE_META, true ) );
		$scheduled = $timestamp ? wp_date( 'Y-m-d\TH:i', $timestamp, wp_timezone() ) : '';
		$preview   = ( new XAIA_Publisher() )->build_post_text( $post );
		$x_post_id = get_post_meta( $post->ID, XAIA_Publisher::POSTED_META, true );
		?>
		<p><label><input type="checkbox" name="xaia_post_enabled" value="1" <?php checked( $enabled ); ?>> <?php esc_html_e( 'この記事をXへ投稿する', 'x-ai-assistant' ); ?></label></p>
		<p><label for="xaia-post-template"><strong><?php esc_html_e( 'この記事専用の投稿文', 'x-ai-assistant' ); ?></strong></label></p>
		<textarea id="xaia-post-template" name="xaia_post_template" rows="7" style="width:100%" placeholder="<?php esc_attr_e( '空欄の場合は共通テンプレートを使用します。', 'x-ai-assistant' ); ?>"><?php echo esc_textarea( $template ); ?></textarea>
		<p class="description"><?php esc_html_e( '使用可能：{title}、{excerpt}、{url}、{hashtags}', 'x-ai-assistant' ); ?></p>
		<p><label for="xaia-scheduled-at"><strong><?php esc_html_e( 'X投稿予約日時', 'x-ai-assistant' ); ?></strong></label></p>
		<input type="datetime-local" id="xaia-scheduled-at" name="xaia_scheduled_at" value="<?php echo esc_attr( $scheduled ); ?>" style="width:100%">
		<p class="description"><?php esc_html_e( '空欄なら記事公開時に投稿します。記事公開より前の日時は即時投稿になります。', 'x-ai-assistant' ); ?></p>
		<hr>
		<p><strong><?php esc_html_e( '投稿プレビュー', 'x-ai-assistant' ); ?></strong></p>
		<div style="white-space:pre-wrap;background:#f6f7f7;padding:10px;border:1px solid #dcdcde"><?php echo esc_html( $preview ); ?></div>
		<p class="description"><?php esc_html_e( 'タイトル・抜粋・タグを変更した場合は、下書きを保存するとプレビューが更新されます。', 'x-ai-assistant' ); ?></p>
		<?php if ( $x_post_id ) : ?>
			<p><strong><?php esc_html_e( '投稿済み', 'x-ai-assistant' ); ?></strong><br><?php echo esc_html( $x_post_id ); ?></p>
		<?php endif; ?>
		<?php
	}

	public function save( $post_id, WP_Post $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( empty( $_POST['xaia_post_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['xaia_post_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'xaia_save_post_settings' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, self::ENABLED_META, empty( $_POST['xaia_post_enabled'] ) ? '0' : '1' );

		$template = isset( $_POST['xaia_post_template'] ) ? sanitize_textarea_field( wp_unslash( $_POST['xaia_post_template'] ) ) : '';
		if ( '' === trim( $template ) ) {
			delete_post_meta( $post_id, self::TEMPLATE_META );
		} else {
			update_post_meta( $post_id, self::TEMPLATE_META, $template );
		}

		$timestamp = $this->parse_schedule( isset( $_POST['xaia_scheduled_at'] ) ? wp_unslash( $_POST['xaia_scheduled_at'] ) : '' );
		if ( $timestamp ) {
			update_post_meta( $post_id, self::SCHEDULE_META, $timestamp );
		} else {
			delete_post_meta( $post_id, self::SCHEDULE_META );
		}

		$next = wp_next_scheduled( XAIA_Publisher::CRON_HOOK, array( $post_id ) );
		if ( $next && ( ! self::is_enabled( $post_id ) || $timestamp <= time() + MINUTE_IN_SECONDS || get_post_meta( $post_id, XAIA_Publisher::POSTED_META, true ) ) ) {
			wp_unschedule_event( $next, XAIA_Publisher::CRON_HOOK, array( $post_id ) );
		} elseif ( 'publish' === $post->post_status && $timestamp > time() + MINUTE_IN_SECONDS && ! get_post_meta( $post_id, XAIA_Publisher::POSTED_META, true ) ) {
			( new XAIA_Publisher() )->schedule_post( $post_id, $timestamp );
		}
	}

	public static function is_enabled( $post_id ) {
		if ( ! metadata_exists( 'post', $post_id, self::ENABLED_META ) ) {
			return true;
		}

		return '1' === get_post_meta( $post_id, self::ENABLED_META, true );
	}

	private function parse_schedule( $value ) {
		$value = sanitize_text_field( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ) {
			return 0;
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $value, wp_timezone() );
		return $date ? $date->getTimestamp() : 0;
	}
}
