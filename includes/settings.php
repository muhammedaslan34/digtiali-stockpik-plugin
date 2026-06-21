<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'digtiali_stockpik_register_settings_page');
add_action('admin_init', 'digtiali_stockpik_register_settings');
add_action('admin_post_digtiali_stockpik_connectivity_test', 'digtiali_stockpik_handle_connectivity_test');
add_filter('plugin_action_links_' . DIGTIALI_STOCKPIK_PLUGIN_BASENAME, 'digtiali_stockpik_add_plugin_action_links');

function digtiali_stockpik_register_settings_page(): void
{
    add_options_page(
        __('Digtiali Stockpik', 'digtiali-stockpik'),
        __('Digtiali Stockpik', 'digtiali-stockpik'),
        'manage_options',
        'digtiali-stockpik',
        'digtiali_stockpik_render_settings_page'
    );
}

function digtiali_stockpik_register_settings(): void
{
    register_setting(
        'digtiali_stockpik',
        DIGTIALI_STOCKPIK_OPTION,
        [
            'type' => 'array',
            'sanitize_callback' => 'digtiali_stockpik_sanitize_settings',
            'default' => digtiali_stockpik_default_settings(),
        ]
    );

    add_settings_section(
        'digtiali_stockpik_main',
        __('Connection Settings', 'digtiali-stockpik'),
        'digtiali_stockpik_render_settings_section',
        'digtiali-stockpik'
    );

    add_settings_field(
        'digtiali_stockpik_api_base_url',
        __('API Base URL', 'digtiali-stockpik'),
        'digtiali_stockpik_render_api_base_url_field',
        'digtiali-stockpik',
        'digtiali_stockpik_main'
    );

    add_settings_field(
        'digtiali_stockpik_api_key',
        __('API Key', 'digtiali-stockpik'),
        'digtiali_stockpik_render_api_key_field',
        'digtiali-stockpik',
        'digtiali_stockpik_main'
    );

    add_settings_field(
        'digtiali_stockpik_enable_logging',
        __('Enable Logging', 'digtiali-stockpik'),
        'digtiali_stockpik_render_logging_field',
        'digtiali-stockpik',
        'digtiali_stockpik_main'
    );

    add_settings_field(
        'digtiali_stockpik_ssl_verify',
        __('Verify SSL', 'digtiali-stockpik'),
        'digtiali_stockpik_render_ssl_verify_field',
        'digtiali-stockpik',
        'digtiali_stockpik_main'
    );
}

function digtiali_stockpik_add_plugin_action_links(array $links): array
{
    array_unshift(
        $links,
        sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=digtiali-stockpik')),
            esc_html__('Settings', 'digtiali-stockpik')
        )
    );

    return $links;
}

function digtiali_stockpik_render_settings_section(): void
{
    echo '<p>' . esc_html__('Configure the Stockpik API connection used for WooCommerce order activation.', 'digtiali-stockpik') . '</p>';
}

function digtiali_stockpik_render_api_base_url_field(): void
{
    $settings = digtiali_stockpik_get_settings();
    ?>
    <input
        type="url"
        class="regular-text code"
        name="<?php echo esc_attr(DIGTIALI_STOCKPIK_OPTION); ?>[api_base_url]"
        value="<?php echo esc_attr($settings['api_base_url']); ?>"
        placeholder="https://www.stockpik.net/"
    />
    <p class="description"><?php echo esc_html__('Base URL for the Stockpik site. The plugin appends /wapi/order automatically.', 'digtiali-stockpik'); ?></p>
    <?php
}

function digtiali_stockpik_render_api_key_field(): void
{
    $settings = digtiali_stockpik_get_settings();
    ?>
    <input
        type="text"
        class="regular-text code"
        name="<?php echo esc_attr(DIGTIALI_STOCKPIK_OPTION); ?>[api_key]"
        value="<?php echo esc_attr($settings['api_key']); ?>"
        autocomplete="off"
    />
    <?php
}

function digtiali_stockpik_render_logging_field(): void
{
    $settings = digtiali_stockpik_get_settings();
    ?>
    <input type="hidden" name="<?php echo esc_attr(DIGTIALI_STOCKPIK_OPTION); ?>[enable_logging]" value="0" />
    <label for="digtiali-stockpik-enable-logging">
        <input
            id="digtiali-stockpik-enable-logging"
            type="checkbox"
            name="<?php echo esc_attr(DIGTIALI_STOCKPIK_OPTION); ?>[enable_logging]"
            value="1"
            <?php checked(true, (bool) $settings['enable_logging']); ?>
        />
        <?php echo esc_html__('Write Stockpik debug messages to the PHP error log.', 'digtiali-stockpik'); ?>
    </label>
    <?php
}

function digtiali_stockpik_render_ssl_verify_field(): void
{
    $settings = digtiali_stockpik_get_settings();
    ?>
    <input type="hidden" name="<?php echo esc_attr(DIGTIALI_STOCKPIK_OPTION); ?>[ssl_verify]" value="0" />
    <label for="digtiali-stockpik-ssl-verify">
        <input
            id="digtiali-stockpik-ssl-verify"
            type="checkbox"
            name="<?php echo esc_attr(DIGTIALI_STOCKPIK_OPTION); ?>[ssl_verify]"
            value="1"
            <?php checked(true, (bool) $settings['ssl_verify']); ?>
        />
        <?php echo esc_html__('Enable SSL certificate verification for Stockpik requests.', 'digtiali-stockpik'); ?>
    </label>
    <?php
}

function digtiali_stockpik_get_test_result_transient_key(?int $user_id = null): string
{
    if (null === $user_id) {
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
    }

    return 'digtiali_stockpik_test_result_' . (int) $user_id;
}

function digtiali_stockpik_handle_connectivity_test(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to run the Stockpik connectivity test.', 'digtiali-stockpik'));
    }

    check_admin_referer('digtiali_stockpik_connectivity_test');

    $product_id = isset($_POST['product_id']) ? max(1, absint($_POST['product_id'])) : 1;
    $result = digtiali_stockpik_run_connectivity_test($product_id);
    set_transient(digtiali_stockpik_get_test_result_transient_key(), $result, MINUTE_IN_SECONDS);

    wp_safe_redirect(admin_url('options-general.php?page=digtiali-stockpik'));
    exit;
}

function digtiali_stockpik_pull_connectivity_result(): ?array
{
    $transient_key = digtiali_stockpik_get_test_result_transient_key();
    $result = get_transient($transient_key);
    if (false === $result || !is_array($result)) {
        return null;
    }

    delete_transient($transient_key);

    return $result;
}

function digtiali_stockpik_render_connectivity_notice(?array $result): void
{
    if (null === $result) {
        return;
    }

    $class = !empty($result['ok']) ? 'notice notice-success' : 'notice notice-error';
    $message = !empty($result['ok'])
        ? sprintf(
            /* translators: %d is an HTTP status code. */
            __('Connectivity test succeeded (HTTP %d).', 'digtiali-stockpik'),
            (int) ($result['http_code'] ?? 0)
        )
        : sprintf(
            /* translators: 1: HTTP status code, 2: error message. */
            __('Connectivity test failed (HTTP %1$d): %2$s', 'digtiali-stockpik'),
            (int) ($result['http_code'] ?? 0),
            (string) ($result['message'] ?? __('Unknown error', 'digtiali-stockpik'))
        );

    echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
}

function digtiali_stockpik_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $result = digtiali_stockpik_pull_connectivity_result();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Digtiali Stockpik', 'digtiali-stockpik'); ?></h1>
        <?php digtiali_stockpik_render_connectivity_notice($result); ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('digtiali_stockpik');
            do_settings_sections('digtiali-stockpik');
            submit_button(__('Save Settings', 'digtiali-stockpik'));
            ?>
        </form>

        <hr />

        <h2><?php echo esc_html__('Connectivity Test', 'digtiali-stockpik'); ?></h2>
        <p><?php echo esc_html__('Run a test request against the configured Stockpik API. This may create or validate activation data for the product ID you provide.', 'digtiali-stockpik'); ?></p>

        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
            <?php wp_nonce_field('digtiali_stockpik_connectivity_test'); ?>
            <input type="hidden" name="action" value="digtiali_stockpik_connectivity_test" />
            <label for="digtiali-stockpik-test-product-id"><?php echo esc_html__('Test Product ID', 'digtiali-stockpik'); ?></label>
            <input
                id="digtiali-stockpik-test-product-id"
                type="number"
                min="1"
                name="product_id"
                value="1"
                class="small-text"
            />
            <?php submit_button(__('Run Connectivity Test', 'digtiali-stockpik'), 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
}
