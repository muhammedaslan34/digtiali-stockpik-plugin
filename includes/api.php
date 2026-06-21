<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function digtiali_stockpik_request_api(array $body, int $timeout = 30): array
{
    $settings = digtiali_stockpik_get_settings();
    $endpoint = digtiali_stockpik_build_api_url($settings);
    if ('' === $endpoint) {
        return [
            'ok' => false,
            'http_code' => 0,
            'message' => 'Stockpik API URL is not configured.',
            'data' => [],
            'raw_body' => '',
            'endpoint' => '',
        ];
    }

    $api_key = digtiali_stockpik_trim_string($settings['api_key'] ?? '');
    if ('' === $api_key) {
        return [
            'ok' => false,
            'http_code' => 0,
            'message' => 'Stockpik API key is not configured.',
            'data' => [],
            'raw_body' => '',
            'endpoint' => $endpoint,
        ];
    }

    $request_body = array_merge(
        [
            'api_key' => $api_key,
        ],
        $body
    );

    $response = wp_remote_post(
        $endpoint,
        [
            'timeout' => $timeout,
            'sslverify' => !empty($settings['ssl_verify']),
            'headers' => digtiali_stockpik_build_request_headers(),
            'body' => wp_json_encode($request_body),
        ]
    );

    if (is_wp_error($response)) {
        return [
            'ok' => false,
            'http_code' => 0,
            'message' => $response->get_error_message(),
            'data' => [],
            'raw_body' => '',
            'endpoint' => $endpoint,
        ];
    }

    $response_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body = (string) wp_remote_retrieve_body($response);
    $normalized = digtiali_stockpik_normalize_api_response($response_code, $raw_body);
    $normalized['raw_body'] = $raw_body;
    $normalized['endpoint'] = $endpoint;

    return $normalized;
}

function digtiali_stockpik_request_order_key(array $args): array
{
    $body = [
        'email' => (string) ($args['email'] ?? ''),
        'name' => (string) ($args['name'] ?? ''),
        'surname' => (string) ($args['surname'] ?? ''),
        'order_id' => (string) ($args['order_id'] ?? ''),
    ];

    $product_id = isset($args['product_id']) ? (int) $args['product_id'] : 0;
    if ($product_id > 0) {
        $body['product_id'] = $product_id;
    }

    $product_sku = digtiali_stockpik_trim_string($args['product_sku'] ?? '');
    if ('' !== $product_sku) {
        $body['product_sku'] = $product_sku;
    }

    $password = digtiali_stockpik_trim_string($args['password'] ?? '');
    if ('' !== $password && apply_filters('digtiali_stockpik_send_checkout_password_to_api', true, $args)) {
        $body['password'] = $password;
    }

    return digtiali_stockpik_request_api($body, 30);
}

function digtiali_stockpik_run_connectivity_test(int $product_id = 1): array
{
    $product_id = max(1, $product_id);

    return digtiali_stockpik_request_api(
        [
            'email' => 'test@example.com',
            'name' => 'Test',
            'surname' => 'User',
            'product_id' => $product_id,
        ],
        15
    );
}
