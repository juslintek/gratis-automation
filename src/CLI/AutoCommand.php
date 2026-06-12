<?php declare(strict_types=1);
namespace Gratis\Automation\CLI;

use Gratis\Automation\Engine;

final class AutoCommand {

    /**
     * List automations.
     * @when after_wp_load
     */
    public function list($args, $assoc_args): void {
        unset($args, $assoc_args);
        $all = Engine::all();
        if (empty($all)) { \WP_CLI::log('No automations. Create one with: wp gratis-auto add'); return; }
        $rows = array_map(fn($a) => [
            'ID' => $a['id'] ?? '?', 'Trigger' => $a['trigger'] ?? '?',
            'Actions' => count($a['actions'] ?? []), 'Active' => ($a['active'] ?? true) ? 'yes' : 'no',
        ], $all);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Trigger', 'Actions', 'Active']);
    }

    /**
     * Run an automation manually.
     * <id> : Automation ID.
     * @when after_wp_load
     */
    public function run($args, $assoc_args): void {
        unset($assoc_args);
        $all = Engine::all();
        $auto = null;
        foreach ($all as $a) { if (($a['id'] ?? 0) === (int) $args[0]) { $auto = $a; break; } }
        if (!$auto) { \WP_CLI::error("Automation #{$args[0]} not found."); }
        $results = Engine::run($auto);
        \WP_CLI::success('Ran ' . count($results) . ' action(s).');
    }

    /**
     * Add a simple automation.
     * <trigger> : Trigger hook (post_published, form_submitted, cron, etc).
     * <action_type> : Action type (send_email, webhook, purge_cache).
     * [--to=<email>] : Email recipient.
     * [--url=<url>] : Webhook URL.
     * @when after_wp_load
     */
    public function add($args, $assoc_args): void {
        $action = ['type' => $args[1]];
        if (!empty($assoc_args['to'])) $action['to'] = $assoc_args['to'];
        if (!empty($assoc_args['url'])) $action['url'] = $assoc_args['url'];

        $id = Engine::add(['trigger' => $args[0], 'actions' => [$action]]);
        \WP_CLI::success("Automation #{$id} created: {$args[0]} → {$args[1]}");
    }
}
