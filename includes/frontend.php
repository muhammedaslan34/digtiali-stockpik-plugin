<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'digtiali_stockpik_bootstrap_frontend');

function digtiali_stockpik_bootstrap_frontend(): void
{
    if (!class_exists('WooCommerce')) {
        return;
    }

    add_action('wp_enqueue_scripts', 'digtiali_stockpik_enqueue_frontend_assets', 55);
    add_action('woocommerce_order_details_after_order_table', 'digtiali_stockpik_show_keys_on_view_order', 10, 1);
}

function digtiali_stockpik_is_view_order_context(): bool
{
    if (!function_exists('is_account_page') || !function_exists('is_wc_endpoint_url')) {
        return false;
    }

    return is_account_page() && is_wc_endpoint_url('view-order');
}

function digtiali_stockpik_is_thankyou_context(): bool
{
    return function_exists('is_order_received_page') && is_order_received_page();
}

function digtiali_stockpik_is_customer_order_context(): bool
{
    return digtiali_stockpik_is_view_order_context() || digtiali_stockpik_is_thankyou_context();
}

function digtiali_stockpik_should_enqueue_frontend_assets(): bool
{
    if (digtiali_stockpik_is_thankyou_context()) {
        $order_id = function_exists('absint') ? absint(get_query_var('order-received')) : 0;
        if ($order_id < 1 || !function_exists('wc_get_order')) {
            return false;
        }

        $order = wc_get_order($order_id);

        return $order && digtiali_stockpik_order_should_show_access_ui($order);
    }

    return function_exists('is_account_page') && is_account_page();
}

function digtiali_stockpik_enqueue_frontend_assets(): void
{
    if (!digtiali_stockpik_should_enqueue_frontend_assets()) {
        return;
    }

    /* CSS + JS both load on all account pages — scoped to .stockpik-wrap, no bleed */
    wp_enqueue_style(
        'digtiali-stockpik-frontend',
        DIGTIALI_STOCKPIK_URL . 'assets/stockpik.css',
        [],
        DIGTIALI_STOCKPIK_VERSION
    );

    wp_enqueue_script(
        'digtiali-stockpik-frontend',
        DIGTIALI_STOCKPIK_URL . 'assets/stockpik.js',
        [],
        DIGTIALI_STOCKPIK_VERSION,
        true
    );

    wp_localize_script(
        'digtiali-stockpik-frontend',
        'digtialiStockpik',
        [
            'copy'   => digtiali_stockpik_t('copy'),
            'copied' => digtiali_stockpik_t('copied'),
            'show'   => digtiali_stockpik_t('show_password'),
            'hide'   => digtiali_stockpik_t('hide_password'),
        ]
    );
}

function digtiali_stockpik_show_keys_on_view_order($order): void
{
    if (!digtiali_stockpik_is_customer_order_context() || !$order || !is_a($order, 'WC_Order')) {
        return;
    }

    // Thank-you page renders Stockpik in its own card via order-details-thankyou.php.
    if (digtiali_stockpik_is_thankyou_context()) {
        return;
    }

    if (!digtiali_stockpik_order_should_show_access_ui($order)) {
        return;
    }

    digtiali_stockpik_render_account_and_keys($order);
}

function digtiali_stockpik_render_account_and_keys($order): void
{
    digtiali_stockpik_prepare_order_for_display($order);

    $order_id = (int) $order->get_id();
    if ($order_id > 0) {
        $fresh_order = wc_get_order($order_id);
        if ($fresh_order && is_a($fresh_order, 'WC_Order')) {
            $order = $fresh_order;
        }
    }

    $account_email    = digtiali_stockpik_get_order_account_email($order);
    $account_password = digtiali_stockpik_get_order_account_password($order);
    $panel_url        = digtiali_stockpik_get_panel_url($order);
    $has_keys         = digtiali_stockpik_order_has_keys($order);

    $status_label  = esc_html($has_keys ? digtiali_stockpik_t('status_ready') : digtiali_stockpik_t('status_pending'));
    $status_class  = $has_keys ? 'stockpik-status--ready' : 'stockpik-status--pending';
    $summary_text  = esc_html($has_keys ? digtiali_stockpik_t('summary_ready') : digtiali_stockpik_t('summary_pending'));
    $copy_text     = esc_html(digtiali_stockpik_t('copy'));
    $copy_aria     = esc_attr(digtiali_stockpik_t('copy_aria'));
    $password_id   = 'stockpik-password-' . (int) $order->get_id();

    /* SVG snippets */
    $icon_person   = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    $icon_person_lg = '<svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
    $icon_mail     = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
    $icon_lock     = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
    $icon_crown    = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 20h20M5 20V9l7-5 7 5v11"/><path d="M12 4v16"/><path d="M5 9l7 3 7-3"/></svg>';
    $icon_globe    = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
    $icon_copy     = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
    $icon_extlink  = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
    $icon_calendar = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    $icon_headset  = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>';

    /* ── Wrapper open ── */
    $wrap_class = 'stockpik-wrap';
    if (digtiali_stockpik_is_view_order_context()) {
        $wrap_class .= ' stockpik-wrap--view-order';
    }
    echo '<section class="' . esc_attr($wrap_class) . '" data-stockpik-access="true">';

    /* ── Hero header ── */
    echo '<div class="stockpik-hero">';
    echo '<div class="stockpik-hero__lead">';
    echo '<h3 class="stockpik-title">' . esc_html(digtiali_stockpik_t('account_activation')) . '</h3>';
    echo '<p class="stockpik-summary">' . $summary_text . '</p>';
    echo '<span class="stockpik-status ' . esc_attr($status_class) . '">';
    echo '<span class="stockpik-status__dot" aria-hidden="true"></span>';
    echo $status_label;
    echo '</span>';
    echo '</div>';
    echo '<div class="stockpik-hero__avatar" aria-hidden="true">' . $icon_person_lg . '</div>';
    echo '</div>';

    /* ── Account details card ── */
    if ('' !== $account_email || '' !== $account_password || '' !== $panel_url) {
        echo '<article class="stockpik-card stockpik-card--account">';
        echo '<div class="stockpik-card__head">';
        echo '<div class="stockpik-card__icon">' . $icon_person . '</div>';
        echo '<h4 class="stockpik-card__title">' . esc_html(digtiali_stockpik_t('account_details')) . '</h4>';
        echo '</div>';
        echo '<div class="stockpik-card__body">';

        if ('' !== $account_email) {
            echo '<div class="stockpik-row stockpik-row--stack">'
                . '<span class="stockpik-label">' . $icon_mail . esc_html(digtiali_stockpik_t('email')) . '</span>'
                . '<div class="stockpik-keyline">'
                . '<button type="button" class="stockpik-copy" data-copy="' . esc_attr($account_email) . '" aria-label="' . $copy_aria . '">' . $icon_copy . '<span>' . $copy_text . '</span></button>'
                . '<code class="stockpik-code">' . esc_html($account_email) . '</code>'
                . '</div></div>';
        }

        if ('' !== $account_password) {
            echo '<div class="stockpik-row stockpik-row--stack stockpik-row--password">'
                . '<span class="stockpik-label">' . $icon_lock . esc_html(digtiali_stockpik_t('password_generated')) . '</span>'
                . '<div class="stockpik-keyline stockpik-keyline--password">'
                . '<button type="button" class="stockpik-copy" data-copy="' . esc_attr($account_password) . '" aria-label="' . $copy_aria . '">' . $icon_copy . '<span>' . $copy_text . '</span></button>'
                . '<code class="stockpik-code" id="' . esc_attr($password_id) . '">' . esc_html($account_password) . '</code>'
                . '</div></div>';
        } elseif ('' !== $account_email) {
            echo '<p class="stockpik-hint">' . esc_html(digtiali_stockpik_t('existing_user_hint')) . '</p>';
        }

        if ('' !== $panel_url) {
            echo '<a class="stockpik-btn" href="' . esc_url($panel_url) . '" target="_blank" rel="noopener noreferrer">';
            echo $icon_extlink;
            echo '<span>' . esc_html(digtiali_stockpik_t('go_to_panel')) . '</span>';
            echo '</a>';
        }

        echo '</div>';
        echo '</article>';
    }

    /* ── Services / Keys card ── */
    if (false && !empty($keys)) {
        echo '<article class="stockpik-card stockpik-card--services">';
        echo '<div class="stockpik-card__head">';
        echo '<div class="stockpik-card__icon">' . $icon_crown . '</div>';
        echo '<h4 class="stockpik-card__title">' . esc_html(digtiali_stockpik_t('services_and_keys')) . '</h4>';
        echo '</div>';
        echo '<div class="stockpik-card__body">';
        echo '<ul class="stockpik-list">';

        foreach ($keys as $key_data) {
            if (!is_array($key_data)) {
                continue;
            }

            $service        = (string) ($key_data['service'] ?? '');
            $key            = (string) ($key_data['key'] ?? '');
            $expires_at     = (string) ($key_data['expires_at'] ?? '');
            $item_panel_url = (string) ($key_data['panel_url'] ?? '');
            $remaining      = '' !== $expires_at ? digtiali_stockpik_remaining_label($expires_at) : '';

            echo '<li class="stockpik-item">';

            /* Info side */
            echo '<div class="stockpik-item__info">';
            echo '<strong class="stockpik-service"><span class="stockpik-service__dot" aria-hidden="true"></span>' . esc_html($service ?: digtiali_stockpik_t('stockpik_service')) . '</strong>';

            if ('' !== $item_panel_url) {
                echo '<div class="stockpik-item__url">';
                echo $icon_globe;
                echo '<span class="stockpik-label">' . esc_html(digtiali_stockpik_t('panel_login')) . '</span>';
                echo '<span class="stockpik-item__url-text">' . esc_html($item_panel_url) . '</span>';
                echo '</div>';
            }

            echo '</div>';

            /* Calendar side */
            echo '<div class="stockpik-item__calendar" aria-label="' . esc_attr(digtiali_stockpik_t('expires') . ' ' . $expires_at) . '">';
            echo $icon_calendar;
            if ('' !== $expires_at) {
                echo '<span class="stockpik-item__expiry">' . esc_html($expires_at) . '</span>';
            }
            if ('' !== $remaining) {
                echo '<span class="stockpik-item__remaining">' . esc_html($remaining) . '</span>';
            }
            echo '</div>';

            echo '</li>';
        }

        echo '</ul>';
        echo '</div>';
        echo '</article>';
    }

    /* ── Support section ── */
    $support_url = apply_filters('digtiali_stockpik_support_url', '');
    echo '<div class="stockpik-support">';
    echo '<div class="stockpik-support__icon" aria-hidden="true">' . $icon_headset . '</div>';
    echo '<div class="stockpik-support__body">';
    echo '<p class="stockpik-support__title">' . esc_html(digtiali_stockpik_t('support_title')) . '</p>';
    echo '<p class="stockpik-support__sub">' . esc_html(digtiali_stockpik_t('support_sub')) . '</p>';
    echo '</div>';
    if ('' !== $support_url) {
        echo '<a class="stockpik-support__btn" href="' . esc_url($support_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html(digtiali_stockpik_t('support_btn')) . '</a>';
    }
    echo '</div>';

    echo '</section>';
}

function digtiali_stockpik_get_panel_url($order): string
{
    $panel_url = (string) $order->get_meta('_stockpik_panel_url');
    if ('' !== $panel_url) {
        return $panel_url;
    }

    $keys = $order->get_meta('_stockpik_activation_keys');
    if (!is_array($keys) || empty($keys[0]['panel_url'])) {
        return '';
    }

    return (string) $keys[0]['panel_url'];
}
