<?php
/**
 * Plugin Name: Gratis Automation
 * Description: WordPress-native workflow automation. Trigger → Action chains. Replaces Zapier/Make ($20-50/mo).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 * Text Domain: gratis-automation
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';

add_action('plugins_loaded', function () {
    \Gratis\Automation\Engine::init();
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-auto', \Gratis\Automation\CLI\AutoCommand::class);
    }
});
