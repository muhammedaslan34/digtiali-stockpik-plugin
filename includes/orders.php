<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'digtiali_stockpik_bootstrap_orders');
add_action('admin_notices', 'digtiali_stockpik_maybe_render_legacy_notice');

function digtiali_stockpik_bootstrap_orders(): void
{
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'digtiali_stockpik_render_missing_woocommerce_notice');
        return;
    }

    add_action('woocommerce_order_status_processing', 'digtiali_stockpik_queue_order_processing', 10, 2);
    add_action('woocommerce_order_status_completed', 'digtiali_stockpik_queue_order_processing', 10, 2);
    add_action('woocommerce_order_status_pending', 'digtiali_stockpik_queue_order_processing', 10, 2);
    add_action('woocommerce_order_status_on-hold', 'digtiali_stockpik_queue_order_processing', 10, 2);
    add_action('woocommerce_thankyou', 'digtiali_stockpik_queue_order_processing_from_thankyou', 1, 1);
    add_action('digtiali_stockpik_process_order_async', 'digtiali_stockpik_run_async_order_processing', 10, 1);

    add_action('woocommerce_created_customer', 'digtiali_stockpik_stash_checkout_password', 10, 3);
    add_action('woocommerce_checkout_update_order_meta', 'digtiali_stockpik_save_checkout_password_to_order', 20, 1);
    add_action('woocommerce_checkout_order_processed', 'digtiali_stockpik_save_checkout_password_to_order', 5, 1);

    add_filter('woocommerce_payment_complete_order_status', 'digtiali_stockpik_payment_complete_order_status_for_stripe_card_or_link', 20, 3);
}

/** @var string|null Last plaintext password generated during checkout (request-scoped). */
$GLOBALS['digtiali_stockpik_checkout_password_buffer'] = null;

/**
 * Keep the auto-generated WooCommerce checkout password so it can be shown on thank-you.
 *
 * @param int                  $customer_id        New customer ID.
 * @param array<string, mixed> $new_customer_data  Customer data passed to wp_insert_user().
 * @param bool                 $password_generated Whether WooCommerce generated the password.
 */
function digtiali_stockpik_stash_checkout_password($customer_id, $new_customer_data, $password_generated): void
{
    unset($customer_id, $password_generated);

    $password = isset($new_customer_data['user_pass']) ? (string) $new_customer_data['user_pass'] : '';
    if ('' === digtiali_stockpik_trim_string($password)) {
        return;
    }

    $GLOBALS['digtiali_stockpik_checkout_password_buffer'] = $password;

    if (function_exists('WC') && WC()->session) {
        WC()->session->set('digtiali_stockpik_checkout_password', $password);
    }
}

function digtiali_stockpik_save_checkout_password_to_order(int $order_id): void
{
    if ($order_id < 1) {
        return;
    }

    $password = '';

    if (function_exists('WC') && WC()->session) {
        $session_password = WC()->session->get('digtiali_stockpik_checkout_password');
        if (is_string($session_password) && '' !== digtiali_stockpik_trim_string($session_password)) {
            $password = $session_password;
        }
    }

    if ('' === $password && !empty($GLOBALS['digtiali_stockpik_checkout_password_buffer'])) {
        $password = (string) $GLOBALS['digtiali_stockpik_checkout_password_buffer'];
    }

    if ('' === digtiali_stockpik_trim_string($password)) {
        return;
    }

    update_post_meta($order_id, '_wc_checkout_account_password', sanitize_text_field($password));

    if (function_exists('WC') && WC()->session) {
        WC()->session->set('digtiali_stockpik_checkout_password', null);
    }

    $GLOBALS['digtiali_stockpik_checkout_password_buffer'] = null;
}

/**
 * Queue Stockpik provisioning in the background so checkout/thank-you are not blocked.
 */
function digtiali_stockpik_queue_order_processing($order_id, $order = null): void
{
    $order_id = absint($order_id);
    if ($order_id < 1) {
        return;
    }

    if (!$order) {
        $order = wc_get_order($order_id);
    }

    if ($order && is_a($order, 'WC_Order') && !digtiali_stockpik_order_has_eligible_items($order)) {
        return;
    }

    if ($order && is_a($order, 'WC_Order') && 'yes' === $order->get_meta('_stockpik_api_processed')) {
        if ('' !== digtiali_stockpik_get_order_account_password($order)) {
            return;
        }
    }

    digtiali_stockpik_enqueue_order_processing($order_id);
}

function digtiali_stockpik_queue_order_processing_from_thankyou($order_id): void
{
    digtiali_stockpik_queue_order_processing($order_id);
}

function digtiali_stockpik_enqueue_order_processing(int $order_id): void
{
    if ($order_id < 1) {
        return;
    }

    $hook  = 'digtiali_stockpik_process_order_async';
    $args  = [$order_id];
    $group = 'digtiali-stockpik';

    if (function_exists('as_has_scheduled_action') && as_has_scheduled_action($hook, $args, $group)) {
        return;
    }

    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action($hook, $args, $group);
        digtiali_stockpik_log('Queued async Stockpik sync for order #' . $order_id . '.');
        return;
    }

    if (wp_next_scheduled($hook, $args)) {
        return;
    }

    wp_schedule_single_event(time() + 1, $hook, $args);
    digtiali_stockpik_log('Scheduled Stockpik sync for order #' . $order_id . '.');
}

function digtiali_stockpik_run_async_order_processing($order_id): void
{
    $order_id = absint($order_id);
    if ($order_id < 1) {
        return;
    }

    digtiali_stockpik_process_paid_order($order_id);
    digtiali_stockpik_refresh_missing_password(wc_get_order($order_id));
}

/**
 * Ensure Stockpik credentials are fetched as soon as the customer reaches thank-you,
 * even when the order is still pending/on-hold (manual payment methods).
 *
 * @deprecated Use digtiali_stockpik_queue_order_processing_from_thankyou().
 */
function digtiali_stockpik_maybe_process_on_thankyou($order_id): void
{
    digtiali_stockpik_queue_order_processing_from_thankyou($order_id);
}

function digtiali_stockpik_render_missing_woocommerce_notice(): void
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    echo '<div class="notice notice-error"><p>' .
        esc_html__('Digtiali Stockpik requires WooCommerce to be active.', 'digtiali-stockpik') .
        '</p></div>';
}

function digtiali_stockpik_maybe_render_legacy_notice(): void
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!digtiali_stockpik_is_legacy_snippet_published()) {
        return;
    }

    echo '<div class="notice notice-warning"><p>' .
        esc_html__('The old Fluent Snippets Stockpik snippet is still published. Disable it after activating this plugin to avoid duplicate behavior.', 'digtiali-stockpik') .
        '</p></div>';
}

/**
 * After payment, set order status to completed when Stripe UPE payment type is card or Link,
 * so physical orders do not stay on "processing" while other Stripe methods keep the default.
 *
 * @param string   $status   Default next status (processing or completed).
 * @param int      $order_id Order ID.
 * @param WC_Order $order    Order object.
 */
function digtiali_stockpik_payment_complete_order_status_for_stripe_card_or_link($status, $order_id, $order)
{
    if (!apply_filters('digtiali_stockpik_enable_autocomplete_stripe_card_link', true, $order_id, $order)) {
        return $status;
    }

    if (!$order || !is_a($order, 'WC_Order')) {
        $order = wc_get_order($order_id);
    }

    if (!$order || !digtiali_stockpik_order_uses_stripe_card_or_link($order)) {
        return $status;
    }

    return 'completed';
}

function digtiali_stockpik_is_legacy_snippet_published(): bool
{
    static $is_published = null;

    if (null !== $is_published) {
        return $is_published;
    }

    $index_path = WP_CONTENT_DIR . '/fluent-snippet-storage/index.php';
    if (!file_exists($index_path)) {
        $is_published = false;
        return $is_published;
    }

    $index = include $index_path;
    if (!is_array($index)) {
        $is_published = false;
        return $is_published;
    }

    $legacy = $index['published']['33-connect-with-stockpik.php'] ?? null;
    $is_published = is_array($legacy) && ('published' === ($legacy['status'] ?? ''));

    return $is_published;
}

function digtiali_stockpik_process_paid_order($order_id, $order = null): void
{
    if (!$order) {
        $order = wc_get_order($order_id);
    }

    if (!$order || !is_a($order, 'WC_Order')) {
        return;
    }

    if (!digtiali_stockpik_order_has_eligible_items($order)) {
        return;
    }

    if ('yes' === $order->get_meta('_stockpik_api_processed')) {
        if ('' !== digtiali_stockpik_get_order_account_password($order)) {
            digtiali_stockpik_log('Order #' . $order->get_id() . ' already processed.');
            return;
        }

        digtiali_stockpik_refresh_missing_password($order);
        return;
    }

    $settings = digtiali_stockpik_get_settings();
    $endpoint = digtiali_stockpik_build_api_url($settings);
    if ('' === $endpoint) {
        $order->add_order_note('[STOCKPIK] API URL is not configured.');
        return;
    }

    if ('' === digtiali_stockpik_trim_string($settings['api_key'] ?? '')) {
        $order->add_order_note('[STOCKPIK] API key is not configured.');
        return;
    }

    $customer_email = $order->get_billing_email();
    $customer_name = $order->get_billing_first_name();
    $customer_surname = $order->get_billing_last_name();

    $keys_sent = [];
    $items_checked = 0;
    $items_skipped = 0;

    $order->add_order_note('[STOCKPIK] Order ' . $order->get_status() . ' - checking items. API: ' . $endpoint);

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) {
            $items_skipped++;
            continue;
        }

        if (!digtiali_stockpik_product_is_eligible($product)) {
            $items_skipped++;
            continue;
        }

        $product_id = (int) $item->get_product_id();
        $sku = (string) $product->get_sku();

        if ($product_id < 1 && '' === $sku) {
            $order->add_order_note('[STOCKPIK] Skipped item "' . $item->get_name() . '" - no Product ID or SKU.');
            $items_skipped++;
            continue;
        }

        $items_checked++;
        $quantity = max(1, (int) $item->get_quantity());

        for ($index = 0; $index < $quantity; $index++) {
            $checkout_password = digtiali_stockpik_get_checkout_password_for_order($order);

            $response = digtiali_stockpik_request_order_key(
                [
                    'email' => $customer_email,
                    'name' => $customer_name,
                    'surname' => $customer_surname,
                    'order_id' => (string) $order->get_id(),
                    'product_id' => $product_id,
                    'product_sku' => $sku,
                    'password' => $checkout_password,
                ]
            );

            if (empty($response['ok'])) {
                $message = (string) ($response['message'] ?? 'Unknown error');
                $http_code = (int) ($response['http_code'] ?? 0);
                $suffix = $http_code > 0 ? ' (HTTP ' . $http_code . ')' : '';
                $order->add_order_note('[STOCKPIK] API error: ' . esc_html($message) . $suffix);
                digtiali_stockpik_log('API error for order #' . $order->get_id() . ': ' . $message);
                continue;
            }

            $data = is_array($response['data'] ?? null) ? $response['data'] : [];
            $keys_sent[] = [
                'key' => (string) ($data['key'] ?? ''),
                'service' => isset($data['service']) ? (string) $data['service'] : $sku,
                'expires_at' => isset($data['expires_at']) ? (string) $data['expires_at'] : '',
                'panel_url' => isset($data['panel_url']) ? (string) $data['panel_url'] : '',
                'password' => digtiali_stockpik_extract_password_from_response($data),
                'email' => isset($data['email']) ? (string) $data['email'] : $customer_email,
            ];
        }
    }

    if (empty($keys_sent)) {
        $order->add_order_note('[STOCKPIK] No keys generated. Checked: ' . $items_checked . ', skipped: ' . $items_skipped . '.');
        $order->update_meta_data('_stockpik_api_attempted', 'yes');
        $order->save();
        return;
    }

    $first = $keys_sent[0];
    $account_password = (string) ($first['password'] ?? '');

    if ('' === $account_password && apply_filters('digtiali_stockpik_use_wc_checkout_password_fallback', true, $order)) {
        $account_password = digtiali_stockpik_trim_string($order->get_meta('_wc_checkout_account_password'));
    }

    $order->update_meta_data('_stockpik_api_processed', 'yes');
    $order->update_meta_data('_stockpik_activation_keys', $keys_sent);
    $order->update_meta_data('_stockpik_account_email', (string) ($first['email'] ?? $customer_email));
    $order->update_meta_data('_stockpik_account_password', $account_password);
    $order->update_meta_data('_stockpik_panel_url', (string) ($first['panel_url'] ?? ''));
    $order->save();

    digtiali_stockpik_sync_order_display_password($order);

    digtiali_stockpik_log('Order #' . $order->get_id() . ' processed successfully with ' . count($keys_sent) . ' activation key(s).');
}

/**
 * Re-call Stockpik once when keys exist but the password was never stored.
 */
function digtiali_stockpik_refresh_missing_password($order): bool
{
    if (!$order || !is_a($order, 'WC_Order')) {
        return false;
    }

    if ('' !== digtiali_stockpik_get_order_account_password($order)) {
        return false;
    }

    if ('yes' === $order->get_meta('_stockpik_password_refreshed')) {
        return false;
    }

    if (!digtiali_stockpik_order_has_keys($order) && 'yes' !== $order->get_meta('_stockpik_api_processed')) {
        return false;
    }

    if (!digtiali_stockpik_order_has_eligible_items($order)) {
        return false;
    }

    $settings = digtiali_stockpik_get_settings();
    if ('' === digtiali_stockpik_build_api_url($settings) || '' === digtiali_stockpik_trim_string($settings['api_key'] ?? '')) {
        return false;
    }

    $customer_email = $order->get_billing_email();
    $customer_name = $order->get_billing_first_name();
    $customer_surname = $order->get_billing_last_name();
    $password = '';

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) {
            continue;
        }

        if (!digtiali_stockpik_product_is_eligible($product)) {
            continue;
        }

        $product_id = (int) $item->get_product_id();
        $sku = (string) $product->get_sku();

        if ($product_id < 1 && '' === $sku) {
            continue;
        }

        $response = digtiali_stockpik_request_order_key(
            [
                'email' => $customer_email,
                'name' => $customer_name,
                'surname' => $customer_surname,
                'order_id' => (string) $order->get_id(),
                'product_id' => $product_id,
                'product_sku' => $sku,
                'password' => digtiali_stockpik_get_checkout_password_for_order($order),
            ]
        );

        if (empty($response['ok'])) {
            continue;
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $password = digtiali_stockpik_extract_password_from_response($data);
        if ('' !== $password) {
            break;
        }
    }

    $order->update_meta_data('_stockpik_password_refreshed', 'yes');

    if ('' !== $password) {
        digtiali_stockpik_save_order_account_password($order, $password);
        $order->update_meta_data('_stockpik_password_refreshed', 'yes');
        $order->add_order_note('[STOCKPIK] Stored missing account password from API refresh.');
        digtiali_stockpik_log('Order #' . $order->get_id() . ' password refreshed from API.');
        return true;
    }

    if ('' === digtiali_stockpik_get_order_account_password($order)
        && apply_filters('digtiali_stockpik_use_wc_checkout_password_fallback', true, $order)
    ) {
        $wc_password = digtiali_stockpik_trim_string($order->get_meta('_wc_checkout_account_password'));
        if ('' !== $wc_password) {
            digtiali_stockpik_save_order_account_password($order, $wc_password);
            $order->update_meta_data('_stockpik_password_refreshed', 'yes');
            return true;
        }
    }

    $stored_panel_password = digtiali_stockpik_get_user_panel_password($order);
    if ('' !== $stored_panel_password) {
        digtiali_stockpik_save_order_account_password($order, $stored_panel_password);
        return true;
    }

    $order->save();
    return false;
}
