<?php declare(strict_types=1);

namespace Gratis\Automation;

defined('ABSPATH') || exit;

/**
 * Admin screen for Gratis Automation.
 *
 * Lists configured trigger → action automations and provides nonce-gated
 * tools to add a simple automation, toggle it active/inactive, or delete it.
 */
final class Settings {

    private const PAGE = 'gratis-automation';

    private const TRIGGERS = [
        'post_published'  => 'Post published',
        'user_registered' => 'User registered',
        'form_submitted'  => 'Form submitted',
        'comment_posted'  => 'Comment posted',
        'cron'            => 'Scheduled (hourly)',
    ];

    private const ACTIONS = [
        'send_email'  => 'Send email',
        'webhook'     => 'Call webhook',
        'purge_cache' => 'Purge cache',
    ];

    /** Register the Tools submenu page. */
    public static function register_menu(): void {
        add_management_page(
            __('Gratis Automation', 'gratis-automation'),
            __('Gratis Automation', 'gratis-automation'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /** Register the admin-post action handlers. */
    public static function register_actions(): void {
        add_action('admin_post_gratis_auto_add', [__CLASS__, 'handle_add']);
        add_action('admin_post_gratis_auto_toggle', [__CLASS__, 'handle_toggle']);
        add_action('admin_post_gratis_auto_delete', [__CLASS__, 'handle_delete']);
    }

    /** Create a simple automation (nonce + capability gated). */
    public static function handle_add(): void {
        self::guard('gratis_auto_add');

        // Nonce + capability verified in guard(); inputs sanitised before use.
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
        $trigger = isset($_POST['trigger']) ? sanitize_key(wp_unslash($_POST['trigger'])) : '';
        $type    = isset($_POST['action_type']) ? sanitize_key(wp_unslash($_POST['action_type'])) : '';

        if (!isset(self::TRIGGERS[$trigger]) || !isset(self::ACTIONS[$type])) {
            self::redirect('error');
        }

        $action = ['type' => $type];
        if ('send_email' === $type) {
            $action['to']      = isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : get_option('admin_email');
            $action['subject'] = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : 'Automation triggered';
        }
        if ('webhook' === $type) {
            $action['url'] = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        Engine::add(['trigger' => $trigger, 'actions' => [$action]]);
        self::redirect('added');
    }

    /** Toggle an automation active/inactive. */
    public static function handle_toggle(): void {
        self::guard('gratis_auto_toggle');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        if ($id > 0) {
            Engine::toggle($id);
        }
        self::redirect('toggled');
    }

    /** Delete an automation. */
    public static function handle_delete(): void {
        self::guard('gratis_auto_delete');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
        $id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
        if ($id > 0) {
            Engine::remove($id);
        }
        self::redirect('deleted');
    }

    /** Shared nonce + capability gate. */
    private static function guard(string $nonce): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'gratis-automation'));
        }
        check_admin_referer($nonce);
    }

    /** Redirect back to the page with a status flag. */
    private static function redirect(string $status): void {
        wp_safe_redirect(add_query_arg(
            ['page' => self::PAGE, 'gratis_auto_msg' => $status],
            admin_url('tools.php')
        ));
        exit;
    }

    /** Render the admin screen. */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-automation'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gratis Automation', 'gratis-automation') . '</h1>';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        if (isset($_GET['gratis_auto_msg'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
            $msg = sanitize_key(wp_unslash($_GET['gratis_auto_msg']));
            $map = [
                'added'   => __('Automation created.', 'gratis-automation'),
                'toggled' => __('Automation updated.', 'gratis-automation'),
                'deleted' => __('Automation deleted.', 'gratis-automation'),
                'error'   => __('Invalid trigger or action.', 'gratis-automation'),
            ];
            if (isset($map[$msg])) {
                $class = 'error' === $msg ? 'notice-error' : 'notice-success';
                echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($map[$msg]) . '</p></div>';
            }
        }

        self::render_list();
        self::render_add_form();

        echo '</div>';
    }

    /** Existing automations list with toggle/delete actions. */
    private static function render_list(): void {
        $all = Engine::all();
        echo '<h2>' . esc_html__('Automations', 'gratis-automation') . '</h2>';

        if (empty($all)) {
            echo '<p>' . esc_html__('No automations yet. Create one below.', 'gratis-automation') . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('ID', 'gratis-automation') . '</th>';
        echo '<th>' . esc_html__('Trigger', 'gratis-automation') . '</th>';
        echo '<th>' . esc_html__('Actions', 'gratis-automation') . '</th>';
        echo '<th>' . esc_html__('Active', 'gratis-automation') . '</th>';
        echo '<th>' . esc_html__('Manage', 'gratis-automation') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($all as $auto) {
            $id      = absint($auto['id'] ?? 0);
            $trigger = (string) ($auto['trigger'] ?? '');
            $label   = self::TRIGGERS[$trigger] ?? $trigger;
            $active  = (bool) ($auto['active'] ?? true);
            $types   = array_map(static fn($a) => (string) ($a['type'] ?? ''), $auto['actions'] ?? []);

            echo '<tr>';
            echo '<td>' . absint($id) . '</td>';
            echo '<td>' . esc_html($label) . '</td>';
            echo '<td>' . esc_html(implode(', ', $types)) . '</td>';
            echo '<td>' . ($active ? esc_html__('Yes', 'gratis-automation') : esc_html__('No', 'gratis-automation')) . '</td>';
            echo '<td>';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
            wp_nonce_field('gratis_auto_toggle');
            echo '<input type="hidden" name="action" value="gratis_auto_toggle">';
            echo '<input type="hidden" name="id" value="' . absint($id) . '">';
            submit_button($active ? __('Disable', 'gratis-automation') : __('Enable', 'gratis-automation'), 'secondary small', 'submit', false);
            echo '</form> ';

            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline" onsubmit="return confirm(\'' . esc_js(__('Delete this automation?', 'gratis-automation')) . '\')">';
            wp_nonce_field('gratis_auto_delete');
            echo '<input type="hidden" name="action" value="gratis_auto_delete">';
            echo '<input type="hidden" name="id" value="' . absint($id) . '">';
            submit_button(__('Delete', 'gratis-automation'), 'delete small', 'submit', false);
            echo '</form>';

            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** Add-automation form. */
    private static function render_add_form(): void {
        echo '<h2>' . esc_html__('Add automation', 'gratis-automation') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gratis_auto_add');
        echo '<input type="hidden" name="action" value="gratis_auto_add">';
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="ga_trigger">' . esc_html__('When', 'gratis-automation') . '</label></th><td>';
        echo '<select id="ga_trigger" name="trigger">';
        foreach (self::TRIGGERS as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="ga_action">' . esc_html__('Then', 'gratis-automation') . '</label></th><td>';
        echo '<select id="ga_action" name="action_type">';
        foreach (self::ACTIONS as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label for="ga_to">' . esc_html__('Email recipient', 'gratis-automation') . '</label></th><td>';
        echo '<input type="email" id="ga_to" class="regular-text" name="to" value="' . esc_attr((string) get_option('admin_email')) . '">';
        echo '<p class="description">' . esc_html__('Used by the "Send email" action.', 'gratis-automation') . '</p></td></tr>';

        echo '<tr><th scope="row"><label for="ga_url">' . esc_html__('Webhook URL', 'gratis-automation') . '</label></th><td>';
        echo '<input type="url" id="ga_url" class="regular-text" name="url" value="" placeholder="https://">';
        echo '<p class="description">' . esc_html__('Used by the "Call webhook" action.', 'gratis-automation') . '</p></td></tr>';

        echo '</tbody></table>';
        submit_button(__('Create automation', 'gratis-automation'));
        echo '</form>';
    }
}
