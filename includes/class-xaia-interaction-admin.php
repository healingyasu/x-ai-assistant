<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Interaction_Admin {
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_xaia_interaction_action', array( $this, 'handle_action' ) );
	}

	public function add_menu() {
		add_options_page( __( 'X交流支援', 'x-ai-assistant' ), __( 'X交流支援', 'x-ai-assistant' ), 'manage_options', 'x-ai-assistant-interaction', array( $this, 'render_page' ) );
	}

	public function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'この操作を実行する権限がありません。', 'x-ai-assistant' ) );
		}

		$action  = isset( $_POST['xaia_do'] ) ? sanitize_key( wp_unslash( $_POST['xaia_do'] ) ) : '';
		$post_id = isset( $_POST['x_post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['x_post_id'] ) ) : '';
		check_admin_referer( 'xaia_interaction_' . $action . '_' . $post_id );

		$interaction = new XAIA_Interaction();
		$result      = true;
		if ( 'check_mentions' === $action ) {
			$result = $interaction->check_mentions();
		} elseif ( 'like' === $action ) {
			$result = $interaction->like_candidate( $post_id );
		} elseif ( 'follow' === $action ) {
			$result = $interaction->follow_candidate( $post_id );
		} elseif ( 'reply' === $action ) {
			$text   = isset( $_POST['reply_text'] ) ? wp_unslash( $_POST['reply_text'] ) : '';
			$result = $interaction->reply_mention( $post_id, $text );
		} elseif ( 'dismiss_mention' === $action ) {
			$result = $interaction->dismiss( 'mention', $post_id );
		} elseif ( 'dismiss_candidate' === $action ) {
			$result = $interaction->dismiss( 'candidate', $post_id );
		} else {
			$result = new WP_Error( 'xaia_invalid_action', __( '不明な操作です。', 'x-ai-assistant' ) );
		}

		$message = is_wp_error( $result ) ? $result->get_error_message() : $this->success_message( $action );
		$type    = is_wp_error( $result ) ? 'error' : 'success';
		wp_safe_redirect(
			add_query_arg(
				array(
					'xaia_interaction_notice' => $type,
					'xaia_message'            => $message,
					'xaia_notice_nonce'       => wp_create_nonce( 'xaia_interaction_notice' ),
				),
				admin_url( 'options-general.php?page=x-ai-assistant-interaction' )
			)
		);
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$mentions   = array_reverse( XAIA_Interaction::mentions(), true );
		$candidates = array_reverse( XAIA_Interaction::candidates(), true );
		$budget     = XAIA_Budget::status();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'X交流支援', 'x-ai-assistant' ); ?></h1>
			<?php $this->render_notice(); ?>
			<p><?php esc_html_e( 'AI APIは使用しません。候補を確認し、人間が選択した操作だけをX APIへ送信します。返信案はChatGPTへコピーして作成できます。', 'x-ai-assistant' ); ?></p>
			<div class="notice notice-info inline"><p>
				<?php
				/* translators: 1: 使用額、2: 上限額、3: 残額。 */
				echo esc_html( sprintf( __( '今月のAPI予算：%1$s／%2$s米ドル相当、残り%3$s米ドル相当', 'x-ai-assistant' ), XAIA_Budget::dollars( $budget['spent_milliusd'] ), XAIA_Budget::dollars( $budget['limit_milliusd'] ), XAIA_Budget::dollars( $budget['remaining_milliusd'] ) ) );
				?>
			</p></div>

			<h2><?php esc_html_e( 'メンション', 'x-ai-assistant' ); ?></h2>
			<p><?php esc_html_e( '1時間ごとに新着を確認します。初回確認では過去分をメール通知せず、一覧へ取り込みます。', 'x-ai-assistant' ); ?></p>
			<?php $this->action_form( 'check_mentions', '', __( '今すぐ確認', 'x-ai-assistant' ), false ); ?>
			<table class="widefat striped" style="margin-top:12px"><thead><tr><th><?php esc_html_e( '日時', 'x-ai-assistant' ); ?></th><th><?php esc_html_e( '内容', 'x-ai-assistant' ); ?></th><th><?php esc_html_e( '返信支援', 'x-ai-assistant' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $mentions ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'メンションはまだありません。', 'x-ai-assistant' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $mentions as $mention ) : ?>
				<?php
				if ( 'dismissed' === ( $mention['status'] ?? '' ) ) {
					continue;
				}
				?>
				<tr>
					<td><?php echo esc_html( $mention['created_at'] ); ?></td>
					<td><small><?php esc_html_e( '投稿者ID：', 'x-ai-assistant' ); ?><?php echo esc_html( $mention['author_id'] ); ?></small><div style="white-space:pre-wrap"><?php echo esc_html( $mention['text'] ); ?></div><p><a href="<?php echo esc_url( $this->x_url( $mention['id'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Xで開く', 'x-ai-assistant' ); ?></a></p></td>
					<td><?php $this->render_mention_actions( $mention ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>

			<h2><?php esc_html_e( '交流候補', 'x-ai-assistant' ); ?></h2>
			<p><?php esc_html_e( '記事をXへ投稿した後、その週に未取得の場合だけ記事タグ・カテゴリーに関連する候補を最大5件表示します。いいね・フォローは自動実行しません。', 'x-ai-assistant' ); ?></p>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( '日時', 'x-ai-assistant' ); ?></th><th><?php esc_html_e( '候補投稿', 'x-ai-assistant' ); ?></th><th><?php esc_html_e( '操作', 'x-ai-assistant' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $candidates ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( '交流候補はまだありません。次の記事投稿後に取得します。', 'x-ai-assistant' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $candidates as $candidate ) : ?>
				<?php
				if ( ! empty( $candidate['dismissed'] ) ) {
					continue;
				}
				?>
				<tr>
					<td><?php echo esc_html( $candidate['created_at'] ); ?></td>
					<td><small><?php esc_html_e( '投稿者ID：', 'x-ai-assistant' ); ?><?php echo esc_html( $candidate['author_id'] ); ?></small><div style="white-space:pre-wrap"><?php echo esc_html( $candidate['text'] ); ?></div><p><a href="<?php echo esc_url( $this->x_url( $candidate['id'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Xで確認する', 'x-ai-assistant' ); ?></a></p></td>
					<td>
						<?php if ( empty( $candidate['liked'] ) ) : ?>
							<?php $this->action_form( 'like', $candidate['id'], __( 'いいね', 'x-ai-assistant' ), true ); ?>
						<?php else : ?>
							<?php esc_html_e( 'いいね済み', 'x-ai-assistant' ); ?>
						<?php endif; ?>
						<?php if ( empty( $candidate['followed'] ) ) : ?>
							<?php $this->action_form( 'follow', $candidate['id'], __( 'フォロー', 'x-ai-assistant' ), true ); ?>
						<?php else : ?>
							<br><?php esc_html_e( 'フォロー済み', 'x-ai-assistant' ); ?>
						<?php endif; ?>
						<?php $this->action_form( 'dismiss_candidate', $candidate['id'], __( '非表示', 'x-ai-assistant' ), false ); ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<script>
		document.querySelectorAll('.xaia-copy-prompt').forEach(function (button) {
			button.addEventListener('click', function () {
				var field = document.getElementById(button.getAttribute('data-target'));
				if (field && navigator.clipboard) {
					navigator.clipboard.writeText(field.value);
					button.textContent = '<?php echo esc_js( __( 'コピーしました', 'x-ai-assistant' ) ); ?>';
				}
			});
		});
		document.querySelectorAll('.xaia-protected-form').forEach(function (form) {
			form.addEventListener('submit', function (event) {
				if (form.dataset.submitted === '1') {
					event.preventDefault();
					return;
				}
				form.dataset.submitted = '1';
				form.querySelectorAll('button, input[type="submit"]').forEach(function (button) {
					button.disabled = true;
				});
			});
		});
		</script>
		<?php
	}

	private function render_mention_actions( array $mention ) {
		if ( 'replied' === ( $mention['status'] ?? '' ) ) {
			esc_html_e( '返信済み', 'x-ai-assistant' );
			return;
		}
		$id     = 'xaia-prompt-' . sanitize_html_class( $mention['id'] );
		$prompt = __( "次のXメンションに対する自然で丁寧な返信案を作ってください。事実や私の体験を推測で追加せず、返信文だけを提示してください。\n\n", 'x-ai-assistant' ) . $mention['text'];
		?>
		<textarea id="<?php echo esc_attr( $id ); ?>" class="large-text" rows="5" readonly><?php echo esc_textarea( $prompt ); ?></textarea>
		<p><button type="button" class="button xaia-copy-prompt" data-target="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'ChatGPT用にコピー', 'x-ai-assistant' ); ?></button> <a class="button" href="https://chatgpt.com/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'ChatGPTを開く', 'x-ai-assistant' ); ?></a></p>
		<form class="xaia-protected-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="xaia_interaction_action">
			<input type="hidden" name="xaia_do" value="reply">
			<input type="hidden" name="x_post_id" value="<?php echo esc_attr( $mention['id'] ); ?>">
			<?php wp_nonce_field( 'xaia_interaction_reply_' . $mention['id'] ); ?>
			<textarea name="reply_text" class="large-text" rows="4" required placeholder="<?php esc_attr_e( 'ChatGPTで作成した返信案を確認・修正して貼り付けます。', 'x-ai-assistant' ); ?>"></textarea>
			<p class="description"><?php esc_html_e( '費用を抑えるため、返信文にURLは含められません。', 'x-ai-assistant' ); ?></p>
			<?php submit_button( __( '確認して返信', 'x-ai-assistant' ), 'primary', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'この内容でXへ返信しますか？', 'x-ai-assistant' ) ) . "');" ) ); ?>
		</form>
		<?php $this->action_form( 'dismiss_mention', $mention['id'], __( '非表示', 'x-ai-assistant' ), false ); ?>
		<?php
	}

	private function action_form( $action, $post_id, $label, $confirm ) {
		$attributes = array();
		if ( $confirm ) {
			$attributes['onclick'] = "return confirm('" . esc_js( __( 'Xでこの操作を実行しますか？', 'x-ai-assistant' ) ) . "');";
		}
		?>
		<form class="xaia-protected-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:2px">
			<input type="hidden" name="action" value="xaia_interaction_action">
			<input type="hidden" name="xaia_do" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="x_post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<?php wp_nonce_field( 'xaia_interaction_' . $action . '_' . $post_id ); ?>
			<?php submit_button( $label, 'secondary', 'submit', false, $attributes ); ?>
		</form>
		<?php
	}

	private function success_message( $action ) {
		$messages = array(
			'check_mentions'    => __( 'メンションを確認しました。', 'x-ai-assistant' ),
			'like'              => __( 'いいねしました。', 'x-ai-assistant' ),
			'follow'            => __( 'フォローしました。', 'x-ai-assistant' ),
			'reply'             => __( '返信しました。', 'x-ai-assistant' ),
			'dismiss_mention'   => __( 'メンションを非表示にしました。', 'x-ai-assistant' ),
			'dismiss_candidate' => __( '候補を非表示にしました。', 'x-ai-assistant' ),
		);
		return $messages[ $action ] ?? __( '操作を完了しました。', 'x-ai-assistant' );
	}

	private function x_url( $post_id ) {
		return 'https://x.com/i/web/status/' . rawurlencode( $post_id );
	}

	private function render_notice() {
		if ( empty( $_GET['xaia_interaction_notice'] ) || empty( $_GET['xaia_message'] ) || empty( $_GET['xaia_notice_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_GET['xaia_notice_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'xaia_interaction_notice' ) ) {
			return;
		}
		$type    = 'success' === sanitize_key( wp_unslash( $_GET['xaia_interaction_notice'] ) ) ? 'success' : 'error';
		$message = sanitize_text_field( wp_unslash( $_GET['xaia_message'] ) );
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
