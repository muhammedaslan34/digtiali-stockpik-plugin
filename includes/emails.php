<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_email_order_meta_fields', 'digtiali_stockpik_add_keys_to_email', 10, 3);

function digtiali_stockpik_add_keys_to_email($fields, $sent_to_admin, $order)
{
    if (!$order || !is_a($order, 'WC_Order')) {
        return $fields;
    }

    $keys = $order->get_meta('_stockpik_activation_keys');
    if (empty($keys) || !is_array($keys)) {
        return $fields;
    }

    $account_email = digtiali_stockpik_get_order_account_email($order);
    $account_password = digtiali_stockpik_get_order_account_password($order);
    $panel_url = (string) $order->get_meta('_stockpik_panel_url');

    if ('' === $panel_url && !empty($keys[0]['panel_url'])) {
        $panel_url = (string) $keys[0]['panel_url'];
    }

    $lines = [];
    $lines[] = digtiali_stockpik_t('account_details');
    $lines[] = digtiali_stockpik_t('email') . ' ' . $account_email;

    if ('' !== $account_password) {
        $lines[] = digtiali_stockpik_t('password_generated') . ' ' . $account_password;
    }

    if ('' !== $panel_url) {
        $lines[] = digtiali_stockpik_t('panel_login') . ' ' . $panel_url;
    }

    $lines[] = '';
    $lines[] = digtiali_stockpik_t('services_and_keys_colon');

    foreach ($keys as $key_data) {
        if (!is_array($key_data)) {
            continue;
        }

        $service = (string) ($key_data['service'] ?? '');
        $key = (string) ($key_data['key'] ?? '');
        $expires = (string) ($key_data['expires_at'] ?? '');
        $lines[] = $service . ' - ' . $key . ' (' . digtiali_stockpik_t('expires') . ' ' . $expires . ')';
    }

    $fields['stockpik_activation'] = [
        'label' => digtiali_stockpik_t('account_activation'),
        'value' => implode("\n", $lines),
    ];

    return $fields;
}
