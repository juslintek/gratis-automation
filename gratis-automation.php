<?php
/**
 * Plugin Name: Gratis Automation
 * Plugin URI: https://github.com/juslintek/gratis-automation
 * Description: WordPress-native workflow automation. Trigger → Action chains. Replaces Zapier/Make ($20-50/mo).
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-automation
 */

defined('ABSPATH') || exit;

define('GRATIS_AUTOMATION_VERSION', '1.0.0');
define('GRATIS_AUTOMATION_FILE', __FILE__);
define('GRATIS_AUTOMATION_DIR', plugin_dir_path(__FILE__));
define('GRATIS_AUTOMATION_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('plugins_loaded', function () {
    \Gratis\Automation\Engine::init();
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-auto', \Gratis\Automation\CLI\AutoCommand::class);
    }
});

// Admin settings page + add/toggle/delete actions.
add_action('admin_menu', [\Gratis\Automation\Settings::class, 'register_menu']);
\Gratis\Automation\Settings::register_actions();
