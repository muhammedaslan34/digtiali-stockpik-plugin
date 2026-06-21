<?php

declare(strict_types=1);

$helpers_path = dirname(__DIR__) . '/includes/helpers.php';
if (file_exists($helpers_path)) {
    require_once $helpers_path;
}

function assert_same_stockpik($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true)
        );
    }
}

function assert_true_stockpik(bool $value, string $message): void
{
    assert_same_stockpik(true, $value, $message);
}

assert_true_stockpik(
    function_exists('digtiali_stockpik_sanitize_settings'),
    'Helper file should register the Stockpik settings sanitization function.'
);

$sanitized = digtiali_stockpik_sanitize_settings(
    [
        'api_base_url' => ' https://www.stockpik.net/ ',
        'api_key' => '  secret-key  ',
        'enable_logging' => '1',
        'ssl_verify' => '0',
    ]
);

assert_same_stockpik('https://www.stockpik.net/', $sanitized['api_base_url'], 'API base URL should be normalized.');
assert_same_stockpik('secret-key', $sanitized['api_key'], 'API key should be trimmed.');
assert_same_stockpik(true, $sanitized['enable_logging'], 'Enable logging should normalize to boolean true.');
assert_same_stockpik(false, $sanitized['ssl_verify'], 'SSL verify should normalize to boolean false.');

$api_url = digtiali_stockpik_build_api_url(
    [
        'api_base_url' => 'https://www.stockpik.net/',
    ]
);

assert_same_stockpik('https://www.stockpik.net/wapi/order', $api_url, 'API URL should append /wapi/order exactly once.');

assert_same_stockpik('Copy', digtiali_stockpik_t('copy', 'en_US'), 'English translations should be available.');
assert_same_stockpik('Copied!', digtiali_stockpik_t('copied', 'en_US'), 'English copied label should be available.');
assert_same_stockpik('نسخ', digtiali_stockpik_t('copy', 'ar'), 'Arabic translations should be available.');
assert_same_stockpik('Kopyala', digtiali_stockpik_t('copy', 'tr_TR'), 'Turkish translations should be available.');
assert_same_stockpik('Copy to clipboard', digtiali_stockpik_t('copy_aria', 'en_US'), 'English copy aria label should be available.');
assert_same_stockpik('نسخ إلى الحافظة', digtiali_stockpik_t('copy_aria', 'ar'), 'Arabic copy aria label should be available.');

assert_same_stockpik('Stockpik access', digtiali_stockpik_t('stockpik_access', 'en_US'), 'English eyebrow should be available.');
assert_same_stockpik('الوصول إلى Stockpik', digtiali_stockpik_t('stockpik_access', 'ar'), 'Arabic eyebrow should be available.');
assert_same_stockpik('Stockpik erişimi', digtiali_stockpik_t('stockpik_access', 'tr_TR'), 'Turkish eyebrow should be available.');
assert_same_stockpik('Access ready', digtiali_stockpik_t('status_ready', 'en_US'), 'English status ready should be available.');
assert_same_stockpik('جاهز للوصول', digtiali_stockpik_t('status_ready', 'ar'), 'Arabic status ready should be available.');
assert_same_stockpik('Erişim hazır', digtiali_stockpik_t('status_ready', 'tr_TR'), 'Turkish status ready should be available.');

assert_same_stockpik(
    'Activation key:',
    digtiali_stockpik_get_key_label(false, 'en_US'),
    'New users should get the activation-key label.'
);

assert_same_stockpik('success', digtiali_stockpik_get_status_tone('completed'), 'Completed orders should use the success tone.');
assert_same_stockpik('warning', digtiali_stockpik_get_status_tone('processing'), 'Processing orders should use the warning tone.');
assert_same_stockpik('danger', digtiali_stockpik_get_status_tone('cancelled'), 'Cancelled orders should use the danger tone.');
assert_same_stockpik('neutral', digtiali_stockpik_get_status_tone('unknown-status'), 'Unknown statuses should fall back to the neutral tone.');

$dashboard_cards = digtiali_stockpik_build_dashboard_cards(
    [
        'total' => '$499.00',
        'payment_method_title' => 'Stripe',
        'billing_email' => 'customer@example.com',
        'item_count' => 3,
    ],
    'en_US'
);

assert_same_stockpik('Total', $dashboard_cards[0]['label'], 'Dashboard cards should label the order total.');
assert_same_stockpik('$499.00', $dashboard_cards[0]['value'], 'Dashboard cards should keep the formatted total.');
assert_same_stockpik('Payment method', $dashboard_cards[1]['label'], 'Dashboard cards should include the payment label.');
assert_same_stockpik('Stripe', $dashboard_cards[1]['value'], 'Dashboard cards should include the payment value.');
assert_same_stockpik('Contact email', $dashboard_cards[2]['label'], 'Dashboard cards should include the contact email label.');
assert_same_stockpik('customer@example.com', $dashboard_cards[2]['value'], 'Dashboard cards should include the contact email value.');
assert_same_stockpik('Items', $dashboard_cards[3]['label'], 'Dashboard cards should include the item count label.');
assert_same_stockpik('3', $dashboard_cards[3]['value'], 'Dashboard cards should normalize item counts as strings.');

$success_response = digtiali_stockpik_normalize_api_response(
    200,
    '{"success":true,"key":"ABC-123","service":"Starter","expires_at":"2026-12-31","panel_url":"https://panel.stockpik.net","password":"generated-password","email":"customer@example.com"}'
);

assert_same_stockpik(true, $success_response['ok'], 'Successful JSON response should normalize as ok.');
assert_same_stockpik('ABC-123', $success_response['data']['key'], 'Successful response should keep the activation key.');

$error_response = digtiali_stockpik_normalize_api_response(
    422,
    '{"success":false,"message":"Product not found"}'
);

assert_same_stockpik(false, $error_response['ok'], 'Non-200 response should not normalize as ok.');
assert_same_stockpik('Product not found', $error_response['message'], 'Error response should surface the API message.');

echo "All Stockpik helper tests passed.\n";
