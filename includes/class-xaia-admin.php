<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class XAIA_Admin {
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_xaia_test_post', array( $this, 'test_post' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( XAIA_FILE ), array( $this, 'action_links' ) );
	}

	public function add_menu() {
		add_options_page( __( 'X AI Assistant', 'x-ai-assistant' ), __( 'X AI Assistant', 'x-ai-assistant' ), 'manage_options', 'x-ai-assistant', array( $this, 'render_page' ) );
	}

	public function register_settings() {
		register_setting( 'xaia_settings_group', XAIA_Settings::OPTION_NAME, array( 'sanitize_callback' => array( 'XAIA_Settings', 'sanitize' ) ) );
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=x-ai-assistant' ) ) . '">' . esc_html__( 'Settings', 'x-ai-assistant' ) . '</a>' );
		return $links;
	}

	public function test_post() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'x-ai-assistant' ) );
		}
		check_admin_referer( 'xaia_test_post' );

		$settings = XAIA_Settings::get_all();
		$text     = strtr(
			$settings['template'],
			array(
				/* translators: %s: WordPress site name. */
				'{title}' => sprintf( __( 'X AI Assistant test from %s', 'x-ai-assistant' ), get_bloginfo( 'name' ) ),
				'{url}'   => home_url( '/' ),
			)
		);
		$result = ( new XAIA_X_Client( $settings ) )->create_post( trim( $text ) );
		if ( is_wp_error( $result ) ) {
			XAIA_Logger::add( 0, 'error', $result->get_error_message() );
			$notice = 'error';
		} else {
			XAIA_Logger::add( 0, 'success', __( 'Test post published to X.', 'x-ai-assistant' ), $result['id'] );
			$notice = 'success';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'xaia_notice'       => $notice,
					'xaia_notice_nonce' => wp_create_nonce( 'xaia_test_notice' ),
				),
				admin_url( 'options-general.php?page=x-ai-assistant' )
			)
		);
		exit;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = XAIA_Settings::get_all( false );
		$logs     = XAIA_Logger::latest();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'X AI Assistant', 'x-ai-assistant' ); ?></h1>
			<?php $this->render_notice(); ?>
			<?php if ( ! XAIA_Credentials::encryption_available() ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Sodium or OpenSSL is required to save X API credentials securely.', 'x-ai-assistant' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Automatically publish a templated X post when a WordPress post is first published.', 'x-ai-assistant' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'xaia_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Automatic posting', 'x-ai-assistant' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( XAIA_Settings::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $settings['enabled'], '1' ); ?>> <?php esc_html_e( 'Enable when posts are first published', 'x-ai-assistant' ); ?></label></td></tr>
					<?php foreach ( $this->credential_labels() as $key => $label ) : ?>
					<tr>
						<th scope="row"><label for="xaia-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><input class="regular-text" type="password" autocomplete="new-password" id="xaia-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( XAIA_Settings::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="" placeholder="<?php echo empty( $settings[ $key ] ) ? esc_attr__( 'Not configured', 'x-ai-assistant' ) : esc_attr__( 'Configured — leave blank to keep', 'x-ai-assistant' ); ?>"></td>
					</tr>
					<?php endforeach; ?>
					<tr><th scope="row"><label for="xaia-template"><?php esc_html_e( 'Post template', 'x-ai-assistant' ); ?></label></th><td><textarea class="large-text" rows="5" id="xaia-template" name="<?php echo esc_attr( XAIA_Settings::OPTION_NAME ); ?>[template]"><?php echo esc_textarea( $settings['template'] ); ?></textarea><p class="description"><?php esc_html_e( 'Available placeholders: {title}, {url}', 'x-ai-assistant' ); ?></p></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Test post', 'x-ai-assistant' ); ?></h2>
			<p><?php esc_html_e( 'This sends a real post to X using the saved template and credentials.', 'x-ai-assistant' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="xaia_test_post">
				<?php wp_nonce_field( 'xaia_test_post' ); ?>
				<?php submit_button( __( 'Send test post', 'x-ai-assistant' ), 'secondary', 'submit', false, array( 'onclick' => "return confirm('" . esc_js( __( 'Send a real test post to X?', 'x-ai-assistant' ) ) . "');" ) ); ?>
			</form>

			<h2><?php esc_html_e( 'Recent logs', 'x-ai-assistant' ); ?></h2>
			<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Date (UTC)', 'x-ai-assistant' ); ?></th><th><?php esc_html_e( 'Post', 'x-ai-assistant' ); ?></th><th><?php esc_html_e( 'Status', 'x-ai-assistant' ); ?></th><th><?php esc_html_e( 'X Post ID', 'x-ai-assistant' ); ?></th><th><?php esc_html_e( 'Message', 'x-ai-assistant' ); ?></th></tr></thead><tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No logs yet.', 'x-ai-assistant' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $logs as $log ) : ?>
				<tr><td><?php echo esc_html( $log->created_at ); ?></td><td><?php echo $log->post_id ? '<a href="' . esc_url( get_edit_post_link( $log->post_id ) ) . '">#' . esc_html( $log->post_id ) . '</a>' : esc_html__( 'Test', 'x-ai-assistant' ); ?></td><td><?php echo esc_html( $log->status ); ?></td><td><?php echo esc_html( $log->x_post_id ); ?></td><td><?php echo esc_html( $log->message ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	private function credential_labels() {
		return array(
			'api_key'             => __( 'API Key', 'x-ai-assistant' ),
			'api_secret'          => __( 'API Secret', 'x-ai-assistant' ),
			'access_token'        => __( 'Access Token', 'x-ai-assistant' ),
			'access_token_secret' => __( 'Access Token Secret', 'x-ai-assistant' ),
		);
	}

	private function render_notice() {
		if ( empty( $_GET['xaia_notice'] ) || empty( $_GET['xaia_notice_nonce'] ) ) {
			return;
		}
		$notice_nonce = sanitize_text_field( wp_unslash( $_GET['xaia_notice_nonce'] ) );
		if ( ! wp_verify_nonce( $notice_nonce, 'xaia_test_notice' ) ) {
			return;
		}
		$success = 'success' === sanitize_key( wp_unslash( $_GET['xaia_notice'] ) );
		$message = $success ? __( 'The test post was published successfully.', 'x-ai-assistant' ) : __( 'The test post failed. Check the latest log for details.', 'x-ai-assistant' );
		echo '<div class="notice notice-' . ( $success ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
