<?php declare(strict_types=1);
namespace Gratis\Automation;

final class Engine {

    private const OPTION = 'gratis_automations';

    public static function init(): void {
        $automations = self::all();
        foreach ($automations as $auto) {
            if (!($auto['active'] ?? true)) continue;
            self::registerTrigger($auto);
        }
    }

    public static function all(): array {
 return get_option(self::OPTION, []); }

    public static function add(array $automation): int {
        $all = self::all();
        $automation['id'] = count($all) + 1;
        $automation['created'] = gmdate('c');
        $automation['active'] = true;
        $all[] = $automation;
        update_option(self::OPTION, $all, false);
        return $automation['id'];
    }

    public static function remove(int $id): bool {
        $all = self::all();
        $all = array_filter($all, fn($a) => ($a['id'] ?? 0) !== $id);
        update_option(self::OPTION, array_values($all), false);
        return true;
    }

    public static function toggle(int $id, ?bool $active = null): bool {
        $all = self::all();
        foreach ($all as &$a) {
            if (($a['id'] ?? 0) === $id) {
                $a['active'] = $active ?? !($a['active'] ?? true);
            }
        }
        unset($a);
        update_option(self::OPTION, array_values($all), false);
        return true;
    }

    public static function run(array $automation, array $context = []): array {
        $results = [];
        foreach ($automation['actions'] ?? [] as $action) {
            $results[] = self::executeAction($action, $context);
        }
        return $results;
    }

    private static function registerTrigger(array $auto): void {
        $trigger = $auto['trigger'] ?? '';
        $hook = match ($trigger) {
            'post_published'   => 'publish_post',
            'user_registered'  => 'user_register',
            'form_submitted'   => 'gratis_form_submitted',
            'comment_posted'   => 'comment_post',
            'cron'             => 'gratis_auto_cron_' . ($auto['id'] ?? 0),
            default            => $trigger, // Allow custom hooks
        };

        if ($trigger === 'cron' && !wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $auto['schedule'] ?? 'hourly', $hook);
        }

        add_action($hook, function () use ($auto) {
            self::run($auto, ['trigger_args' => func_get_args()]);
        }, 99);
    }

    private static function executeAction(array $action, array $context): array {
        $type = $action['type'] ?? '';
        return match ($type) {
            'send_email'  => self::actionEmail($action, $context),
            'webhook'     => self::actionWebhook($action, $context),
            'purge_cache' => self::actionPurgeCache(),
            'create_post' => self::actionCreatePost($action, $context),
            'update_meta' => self::actionUpdateMeta($action, $context),
            default       => ['success' => false, 'error' => "Unknown action: {$type}"],
        };
    }

    private static function actionEmail(array $action, array $context): array {
        $to = $action['to'] ?? get_option('admin_email');
        $subject = $action['subject'] ?? 'Automation triggered';
        $body = $action['body'] ?? wp_json_encode($context, JSON_PRETTY_PRINT);
        return ['success' => wp_mail($to, $subject, $body)];
    }

    private static function actionWebhook(array $action, array $context): array {
        $url = $action['url'] ?? '';
        if (!$url) return ['success' => false, 'error' => 'No URL'];
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($context),
            'timeout' => 10,
        ]);
        return ['success' => !is_wp_error($response), 'code' => wp_remote_retrieve_response_code($response)];
    }

    private static function actionPurgeCache(): array {
        do_action('gratis_cache_purge_all', 'automation');
        return ['success' => true];
    }

    private static function actionCreatePost(array $action, array $context): array {
        unset($context);
        $id = wp_insert_post([
            'post_title'  => $action['title'] ?? 'Auto-generated',
            'post_status' => $action['status'] ?? 'draft',
            'post_type'   => $action['post_type'] ?? 'post',
        ]);
        return ['success' => !is_wp_error($id), 'post_id' => $id];
    }

    private static function actionUpdateMeta(array $action, array $context): array {
        $post_id = $action['post_id'] ?? ($context['trigger_args'][0] ?? 0);
        if (!$post_id) return ['success' => false];
        update_post_meta((int) $post_id, $action['key'] ?? '', $action['value'] ?? '');
        return ['success' => true];
    }
}
