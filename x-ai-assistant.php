<?php
/**
 * Plugin Name: X AIアシスタント
 * Plugin URI: https://github.com/healingyasu/x-ai-assistant
 * Description: 外部AI APIを使わず、WordPress記事の投稿文作成・予約・Xへの自動投稿を行います。
 * Version: 2.0.0
 * Author: 上田 恭弘
 * Author URI: https://github.com/healingyasu
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: x-ai-assistant
 * GitHub Plugin URI: https://github.com/healingyasu/x-ai-assistant
 * Update URI: https://github.com/healingyasu/x-ai-assistant
 * Primary Branch: main
 * Release Asset: true
 * Requires at least: 6.2
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'XAIA_VERSION', '2.0.0' );
define( 'XAIA_FILE', __FILE__ );
define( 'XAIA_DIR', plugin_dir_path( __FILE__ ) );

require_once XAIA_DIR . 'includes/class-xaia-credentials.php';
require_once XAIA_DIR . 'includes/class-xaia-settings.php';
require_once XAIA_DIR . 'includes/class-xaia-logger.php';
require_once XAIA_DIR . 'includes/class-xaia-x-client.php';
require_once XAIA_DIR . 'includes/class-xaia-publisher.php';
require_once XAIA_DIR . 'includes/class-xaia-post-editor.php';
require_once XAIA_DIR . 'includes/class-xaia-admin.php';
require_once XAIA_DIR . 'includes/class-xaia-plugin.php';

register_activation_hook( __FILE__, array( 'XAIA_Logger', 'install' ) );

XAIA_Plugin::instance()->boot();
