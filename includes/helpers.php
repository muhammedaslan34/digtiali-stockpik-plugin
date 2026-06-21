<?php

declare(strict_types=1);

if (!function_exists('digtiali_stockpik_trim_string')) {
    function digtiali_stockpik_trim_string($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        return '';
    }
}

if (!function_exists('digtiali_stockpik_default_settings')) {
    function digtiali_stockpik_default_settings(): array
    {
        return [
            'api_base_url' => 'https://www.stockpik.net/',
            'api_key' => '',
            'enable_logging' => false,
            'ssl_verify' => true,
        ];
    }
}

if (!function_exists('digtiali_stockpik_normalize_url')) {
    function digtiali_stockpik_normalize_url(string $url): string
    {
        $url = digtiali_stockpik_trim_string($url);
        if ('' === $url) {
            return '';
        }

        if (function_exists('esc_url_raw')) {
            $url = (string) esc_url_raw($url);
        } elseif (false === filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        if ('' === $url) {
            return '';
        }

        return rtrim($url, '/') . '/';
    }
}

if (!function_exists('digtiali_stockpik_sanitize_settings')) {
    function digtiali_stockpik_sanitize_settings(array $input): array
    {
        $defaults = digtiali_stockpik_default_settings();

        return [
            'api_base_url' => digtiali_stockpik_normalize_url((string) ($input['api_base_url'] ?? $defaults['api_base_url'])),
            'api_key' => digtiali_stockpik_trim_string($input['api_key'] ?? ''),
            'enable_logging' => in_array($input['enable_logging'] ?? false, [true, 1, '1', 'yes', 'on'], true),
            'ssl_verify' => in_array($input['ssl_verify'] ?? $defaults['ssl_verify'], [true, 1, '1', 'yes', 'on'], true),
        ];
    }
}

if (!function_exists('digtiali_stockpik_get_settings')) {
    function digtiali_stockpik_get_settings(): array
    {
        $defaults = digtiali_stockpik_default_settings();
        if (!function_exists('get_option')) {
            return $defaults;
        }

        $settings = get_option('digtiali_stockpik_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        return array_merge($defaults, digtiali_stockpik_sanitize_settings($settings));
    }
}

if (!function_exists('digtiali_stockpik_translations')) {
    function digtiali_stockpik_translations(): array
    {
        return [
            'en' => [
                'stockpik_access' => 'Stockpik access',
                'summary_ready' => 'Everything needed for your Stockpik service is organized here.',
                'summary_pending' => 'Login details and activation data will appear here as soon as processing finishes.',
                'status_ready' => 'Access ready',
                'status_pending' => 'Preparing access',
                'stockpik_service' => 'Stockpik service',
                'account_activation' => 'Your STOCKPIK account & activation',
                'account_details' => 'Account details (panel login)',
                'email' => 'Email:',
                'password_generated' => 'Password (generated):',
                'password_hint' => 'Use this email and password to log in to the panel. You can change the password after login.',
                'existing_user_hint' => 'Your subscription has been added to your account. Log in with your existing email and password.',
                'go_to_panel' => 'Go to panel login →',
                'services_and_keys' => 'Your service(s) and activation key(s)',
                'services_and_keys_colon' => 'Your service(s) and activation key(s):',
                'activation_key' => 'Activation key:',
                'expires' => 'Expires:',
                'panel_login' => 'Panel login:',
                'copy' => 'Copy',
                'copied' => 'Copied!',
                'copy_aria' => 'Copy to clipboard',
                'show_password' => 'Show',
                'hide_password' => 'Hide',
                'support_title' => 'Need help?',
                'support_sub' => 'Our support team is ready to assist you anytime.',
                'support_btn' => 'Contact support',
            ],
            'ar' => [
                'stockpik_access' => 'الوصول إلى Stockpik',
                'summary_ready' => 'كل ما تحتاجه لخدمة Stockpik منظم هنا في مكان واحد.',
                'summary_pending' => 'ستظهر بيانات تسجيل الدخول والتفعيل هنا بمجرد انتهاء المعالجة.',
                'status_ready' => 'جاهز للوصول',
                'status_pending' => 'جاري تجهيز الوصول',
                'stockpik_service' => 'خدمة Stockpik',
                'account_activation' => 'حسابك STOCKPIK وتفعيل الاشتراك',
                'account_details' => 'بيانات الحساب (تسجيل الدخول للوحة)',
                'email' => 'البريد الإلكتروني:',
                'password_generated' => 'كلمة المرور (المولدة):',
                'password_hint' => 'استخدم هذا البريد وكلمة المرور لتسجيل الدخول إلى اللوحة. يمكنك تغيير كلمة المرور بعد الدخول.',
                'existing_user_hint' => 'تمت إضافة اشتراكك إلى حسابك. سجّل الدخول باستخدام بريدك وكلمة المرور الحالية.',
                'go_to_panel' => 'الذهاب لتسجيل الدخول للوحة ←',
                'services_and_keys' => 'خدماتك ومفاتيح التفعيل',
                'services_and_keys_colon' => 'خدماتك ومفاتيح التفعيل:',
                'activation_key' => 'مفتاح التفعيل:',
                'expires' => 'ينتهي:',
                'panel_login' => 'تسجيل الدخول للوحة:',
                'copy' => 'نسخ',
                'copied' => 'تم النسخ!',
                'copy_aria' => 'نسخ إلى الحافظة',
                'show_password' => 'إظهار',
                'hide_password' => 'إخفاء',
                'support_title' => 'تحتاج مساعدة؟',
                'support_sub' => 'فريق الدعم لدينا جاهز لمساعدتك في أي وقت.',
                'support_btn' => 'تواصل مع الدعم',
            ],
            'tr' => [
                'stockpik_access' => 'Stockpik erişimi',
                'summary_ready' => 'Stockpik hizmetiniz için gereken her şey burada düzenlendi.',
                'summary_pending' => 'İşlem tamamlandığında giriş bilgileri ve aktivasyon verileri burada görünecek.',
                'status_ready' => 'Erişim hazır',
                'status_pending' => 'Erişim hazırlanıyor',
                'stockpik_service' => 'Stockpik hizmeti',
                'account_activation' => 'STOCKPIK hesabınız ve aktivasyon',
                'account_details' => 'Hesap bilgileri (panel girişi)',
                'email' => 'E-posta:',
                'password_generated' => 'Şifre (oluşturulan):',
                'password_hint' => 'Panele giriş yapmak için bu e-posta ve şifreyi kullanın. Girişten sonra şifreyi değiştirebilirsiniz.',
                'existing_user_hint' => 'Aboneliğiniz hesabınıza eklendi. Mevcut e-posta ve şifrenizle giriş yapın.',
                'go_to_panel' => 'Panel girişine git →',
                'services_and_keys' => 'Hizmet(ler)iniz ve aktivasyon anahtar(lar)ınız',
                'services_and_keys_colon' => 'Hizmet(ler)iniz ve aktivasyon anahtar(lar)ınız:',
                'activation_key' => 'Aktivasyon anahtarı:',
                'expires' => 'Bitiş:',
                'panel_login' => 'Panel girişi:',
                'copy' => 'Kopyala',
                'copied' => 'Kopyalandı!',
                'copy_aria' => 'Panoya kopyala',
                'show_password' => 'Göster',
                'hide_password' => 'Gizle',
                'support_title' => 'Yardıma mı ihtiyacınız var?',
                'support_sub' => 'Destek ekibimiz her an yardımcı olmaya hazır.',
                'support_btn' => 'Destek ile iletişime geçin',
            ],
        ];
    }
}

if (!function_exists('digtiali_stockpik_detect_language')) {
    function digtiali_stockpik_detect_language(?string $locale = null): string
    {
        if (null === $locale) {
            $locale = function_exists('get_locale') ? (string) get_locale() : 'en_US';
        }

        if (0 === strpos($locale, 'ar')) {
            return 'ar';
        }

        if (0 === strpos($locale, 'tr')) {
            return 'tr';
        }

        return 'en';
    }
}

if (!function_exists('digtiali_stockpik_t')) {
    function digtiali_stockpik_t(string $key, ?string $locale = null): string
    {
        $translations = digtiali_stockpik_translations();
        $language = digtiali_stockpik_detect_language($locale);

        if (isset($translations[$language][$key])) {
            return $translations[$language][$key];
        }

        if (isset($translations['en'][$key])) {
            return $translations['en'][$key];
        }

        return $key;
    }
}

if (!function_exists('digtiali_stockpik_get_key_label')) {
    function digtiali_stockpik_get_key_label(bool $is_existing_user, ?string $locale = null): string
    {
        return digtiali_stockpik_t('activation_key', $locale);
    }
}

if (!function_exists('digtiali_stockpik_order_has_keys')) {
    function digtiali_stockpik_order_has_keys($order): bool
    {
        $keys = $order->get_meta('_stockpik_activation_keys');

        return is_array($keys) && [] !== $keys;
    }
}

if (!function_exists('digtiali_stockpik_get_excluded_product_categories')) {
    /**
     * Product categories that use other fulfillment flows (not Stockpik panel).
     *
     * @return string[]
     */
    function digtiali_stockpik_get_excluded_product_categories(): array
    {
        $categories = [
            'graphic-design-tools',
            'wordpress-plugins',
            'wordpress-themes',
            'coming-soon',
        ];

        return array_values(
            array_filter(
                array_map('strval', (array) apply_filters('digtiali_stockpik_excluded_product_categories', $categories))
            )
        );
    }
}

if (!function_exists('digtiali_stockpik_product_is_eligible')) {
    /**
     * True when a WooCommerce product should trigger Stockpik account/key provisioning.
     */
    function digtiali_stockpik_product_is_eligible($product): bool
    {
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }

        $product_id = (int) $product->get_id();
        $parent_id  = (int) $product->get_parent_id();
        $catalog_id = $parent_id > 0 ? $parent_id : $product_id;

        $enabled = digtiali_stockpik_trim_string((string) get_post_meta($catalog_id, '_stockpik_enabled', true));
        if (in_array(strtolower($enabled), ['yes', '1', 'true', 'on'], true)) {
            return (bool) apply_filters('digtiali_stockpik_product_is_eligible', true, $product);
        }
        if (in_array(strtolower($enabled), ['no', '0', 'false', 'off'], true)) {
            return (bool) apply_filters('digtiali_stockpik_product_is_eligible', false, $product);
        }

        if (function_exists('digtiali_fse_product_is_graphic_design_tool')
            && digtiali_fse_product_is_graphic_design_tool($catalog_id)
        ) {
            return (bool) apply_filters('digtiali_stockpik_product_is_eligible', false, $product);
        }

        if (function_exists('digtiali_fse_product_needs_activation')
            && digtiali_fse_product_needs_activation($catalog_id)
        ) {
            return (bool) apply_filters('digtiali_stockpik_product_is_eligible', false, $product);
        }

        if (function_exists('digtiali_rp_product_is_preorder')
            && digtiali_rp_product_is_preorder($catalog_id)
        ) {
            return (bool) apply_filters('digtiali_stockpik_product_is_eligible', false, $product);
        }

        if ('1' === (string) get_post_meta($catalog_id, '_is_preorder', true)) {
            return (bool) apply_filters('digtiali_stockpik_product_is_eligible', false, $product);
        }

        foreach (digtiali_stockpik_get_excluded_product_categories() as $category_slug) {
            if ('' !== $category_slug && has_term($category_slug, 'product_cat', $catalog_id)) {
                return (bool) apply_filters('digtiali_stockpik_product_is_eligible', false, $product);
            }
        }

        $sku = digtiali_stockpik_trim_string($product->get_sku());
        if ($product_id < 1 && '' === $sku) {
            return (bool) apply_filters('digtiali_stockpik_product_is_eligible', false, $product);
        }

        return (bool) apply_filters('digtiali_stockpik_product_is_eligible', true, $product);
    }
}

if (!function_exists('digtiali_stockpik_order_has_eligible_items')) {
    /**
     * True when the order contains at least one Stockpik-eligible product.
     */
    function digtiali_stockpik_order_has_eligible_items($order): bool
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }

        foreach ($order->get_items() as $item) {
            if (!is_a($item, 'WC_Order_Item_Product')) {
                continue;
            }

            $product = $item->get_product();
            if ($product && digtiali_stockpik_product_is_eligible($product)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('digtiali_stockpik_get_order_account_email')) {
    function digtiali_stockpik_get_order_account_email($order): string
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return '';
        }

        $email = digtiali_stockpik_trim_string($order->get_meta('_stockpik_account_email'));
        if ('' !== $email) {
            return $email;
        }

        $keys = $order->get_meta('_stockpik_activation_keys');
        if (is_array($keys)) {
            foreach ($keys as $key_data) {
                if (!is_array($key_data)) {
                    continue;
                }

                $email = digtiali_stockpik_trim_string($key_data['email'] ?? '');
                if ('' !== $email) {
                    return $email;
                }
            }
        }

        return digtiali_stockpik_trim_string($order->get_billing_email());
    }
}

if (!function_exists('digtiali_stockpik_extract_password_from_response')) {
    /**
     * Pull a plaintext password from Stockpik API payload shapes.
     */
    function digtiali_stockpik_extract_password_from_response(array $data): string
    {
        $direct_keys = [
            'password',
            'generated_password',
            'plain_password',
            'user_password',
            'temp_password',
            'pass',
        ];

        foreach ($direct_keys as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                $password = digtiali_stockpik_trim_string($data[$key]);
                if ('' !== $password) {
                    return $password;
                }
            }
        }

        foreach (['user', 'account', 'data'] as $nested_key) {
            if (!empty($data[$nested_key]) && is_array($data[$nested_key])) {
                $password = digtiali_stockpik_extract_password_from_response($data[$nested_key]);
                if ('' !== $password) {
                    return $password;
                }
            }
        }

        return '';
    }
}

if (!function_exists('digtiali_stockpik_save_order_account_password')) {
    function digtiali_stockpik_save_order_account_password($order, string $password): void
    {
        if (!$order || !is_a($order, 'WC_Order') || '' === $password) {
            return;
        }

        $order->update_meta_data('_stockpik_account_password', $password);

        $keys = $order->get_meta('_stockpik_activation_keys');
        if (is_array($keys)) {
            foreach ($keys as $index => $key_data) {
                if (!is_array($key_data)) {
                    continue;
                }

                if ('' === digtiali_stockpik_trim_string($key_data['password'] ?? '')) {
                    $keys[$index]['password'] = $password;
                }
            }

            $order->update_meta_data('_stockpik_activation_keys', $keys);
        }

        $email = digtiali_stockpik_get_order_account_email($order);
        if ('' !== $email && function_exists('get_user_by')) {
            $user = get_user_by('email', $email);
            if ($user && isset($user->ID)) {
                update_user_meta((int) $user->ID, '_stockpik_panel_password', $password);
            }
        }

        $order->save();
    }
}

if (!function_exists('digtiali_stockpik_get_user_panel_password')) {
    function digtiali_stockpik_get_user_panel_password($order): string
    {
        if (!$order || !is_a($order, 'WC_Order') || !function_exists('get_user_by')) {
            return '';
        }

        $email = digtiali_stockpik_get_order_account_email($order);
        if ('' === $email) {
            return '';
        }

        $user = get_user_by('email', $email);
        if (!$user || !isset($user->ID)) {
            return '';
        }

        return digtiali_stockpik_trim_string(get_user_meta((int) $user->ID, '_stockpik_panel_password', true));
    }
}

if (!function_exists('digtiali_stockpik_get_order_account_password')) {
    function digtiali_stockpik_get_order_account_password($order): string
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return '';
        }

        $password = digtiali_stockpik_trim_string($order->get_meta('_stockpik_account_password'));
        if ('' !== $password) {
            return $password;
        }

        $keys = $order->get_meta('_stockpik_activation_keys');
        if (is_array($keys)) {
            foreach ($keys as $key_data) {
                if (!is_array($key_data)) {
                    continue;
                }

                $password = digtiali_stockpik_trim_string($key_data['password'] ?? '');
                if ('' !== $password) {
                    return $password;
                }
            }
        }

        $password = digtiali_stockpik_trim_string($order->get_meta('customer_password'));
        if ('' !== $password) {
            return $password;
        }

        if (apply_filters('digtiali_stockpik_use_wc_checkout_password_fallback', true, $order)) {
            $password = digtiali_stockpik_trim_string($order->get_meta('_wc_checkout_account_password'));
            if ('' !== $password) {
                return $password;
            }
        }

        return digtiali_stockpik_get_user_panel_password($order);
    }
}

if (!function_exists('digtiali_stockpik_get_checkout_password_for_order')) {
    function digtiali_stockpik_get_checkout_password_for_order($order): string
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return '';
        }

        $password = digtiali_stockpik_trim_string($order->get_meta('_wc_checkout_account_password'));
        if ('' !== $password) {
            return $password;
        }

        if (!empty($GLOBALS['digtiali_stockpik_checkout_password_buffer'])) {
            $password = digtiali_stockpik_trim_string((string) $GLOBALS['digtiali_stockpik_checkout_password_buffer']);
            if ('' !== $password) {
                return $password;
            }
        }

        if (function_exists('WC') && WC()->session) {
            $session_password = WC()->session->get('digtiali_stockpik_checkout_password');
            if (is_string($session_password)) {
                $password = digtiali_stockpik_trim_string($session_password);
                if ('' !== $password) {
                    return $password;
                }
            }
        }

        return '';
    }
}

if (!function_exists('digtiali_stockpik_sync_order_display_password')) {
    /**
     * Ensure Stockpik order meta has the best available generated password for display.
     */
    function digtiali_stockpik_sync_order_display_password($order): string
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return '';
        }

        $stored = digtiali_stockpik_trim_string($order->get_meta('_stockpik_account_password'));
        if ('' !== $stored) {
            return $stored;
        }

        $candidates = [];

        $keys = $order->get_meta('_stockpik_activation_keys');
        if (is_array($keys)) {
            foreach ($keys as $key_data) {
                if (!is_array($key_data)) {
                    continue;
                }

                $candidates[] = digtiali_stockpik_trim_string($key_data['password'] ?? '');
            }
        }

        $candidates[] = digtiali_stockpik_trim_string($order->get_meta('customer_password'));

        if (apply_filters('digtiali_stockpik_use_wc_checkout_password_fallback', true, $order)) {
            $candidates[] = digtiali_stockpik_get_checkout_password_for_order($order);
        }

        $candidates[] = digtiali_stockpik_get_user_panel_password($order);

        foreach ($candidates as $password) {
            if ('' === $password) {
                continue;
            }

            digtiali_stockpik_save_order_account_password($order, $password);

            return $password;
        }

        return '';
    }
}

if (!function_exists('digtiali_stockpik_prepare_order_for_display')) {
    function digtiali_stockpik_prepare_order_for_display($order): void
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        if (!digtiali_stockpik_order_should_show_access_ui($order)) {
            return;
        }

        digtiali_stockpik_refresh_missing_password($order);
        digtiali_stockpik_sync_order_display_password($order);
    }
}

if (!function_exists('digtiali_stockpik_order_should_show_access_ui')) {
    /**
     * Show when Stockpik keys exist or eligible products are still being provisioned.
     */
    function digtiali_stockpik_order_should_show_access_ui($order): bool
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }

        if (digtiali_stockpik_order_has_keys($order)) {
            if (!digtiali_stockpik_order_has_eligible_items($order)) {
                return (bool) apply_filters('digtiali_stockpik_order_should_show_access_ui', false, $order);
            }

            return (bool) apply_filters('digtiali_stockpik_order_should_show_access_ui', true, $order);
        }

        if (!digtiali_stockpik_order_has_eligible_items($order)) {
            return (bool) apply_filters('digtiali_stockpik_order_should_show_access_ui', false, $order);
        }

        if ('yes' === $order->get_meta('_stockpik_api_attempted')) {
            return (bool) apply_filters('digtiali_stockpik_order_should_show_access_ui', false, $order);
        }

        if ('yes' === $order->get_meta('_stockpik_api_processed')) {
            return (bool) apply_filters('digtiali_stockpik_order_should_show_access_ui', false, $order);
        }

        return (bool) apply_filters('digtiali_stockpik_order_should_show_access_ui', true, $order);
    }
}

if (!function_exists('digtiali_stockpik_get_status_tone')) {
    function digtiali_stockpik_get_status_tone(string $status): string
    {
        if (function_exists('sanitize_key')) {
            $status = sanitize_key($status);
        } else {
            $status = strtolower(preg_replace('/[^a-z0-9_-]+/', '', $status));
        }

        if (in_array($status, ['completed'], true)) {
            return 'success';
        }

        if (in_array($status, ['processing', 'on-hold', 'pending'], true)) {
            return 'warning';
        }

        if (in_array($status, ['cancelled', 'failed', 'refunded'], true)) {
            return 'danger';
        }

        return 'neutral';
    }
}

if (!function_exists('digtiali_stockpik_build_dashboard_cards')) {
    function digtiali_stockpik_build_dashboard_cards(array $snapshot, ?string $locale = null): array
    {
        $language = digtiali_stockpik_detect_language($locale);

        return [
            [
                'label' => 'en' === $language ? 'Total' : ('ar' === $language ? 'الإجمالي' : 'Toplam'),
                'value' => (string) ($snapshot['total'] ?? ''),
            ],
            [
                'label' => 'en' === $language ? 'Payment method' : ('ar' === $language ? 'طريقة الدفع' : 'Ödeme yöntemi'),
                'value' => (string) ($snapshot['payment_method_title'] ?? ''),
            ],
            [
                'label' => 'en' === $language ? 'Contact email' : ('ar' === $language ? 'بريد التواصل' : 'İletişim e-postası'),
                'value' => (string) ($snapshot['billing_email'] ?? ''),
            ],
            [
                'label' => 'en' === $language ? 'Items' : ('ar' === $language ? 'العناصر' : 'Ürünler'),
                'value' => (string) ($snapshot['item_count'] ?? '0'),
            ],
        ];
    }
}

if (!function_exists('digtiali_stockpik_build_api_url')) {
    function digtiali_stockpik_build_api_url(array $settings): string
    {
        $base_url = digtiali_stockpik_normalize_url((string) ($settings['api_base_url'] ?? ''));
        if ('' === $base_url) {
            return '';
        }

        return rtrim($base_url, '/') . '/wapi/order';
    }
}

if (!function_exists('digtiali_stockpik_build_request_headers')) {
    function digtiali_stockpik_build_request_headers(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'ngrok-skip-browser-warning' => '1',
        ];
    }
}

if (!function_exists('digtiali_stockpik_normalize_api_response')) {
    function digtiali_stockpik_normalize_api_response(int $response_code, string $body_raw): array
    {
        $decoded = json_decode($body_raw, true);
        $message = '';

        if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
            $message = $decoded['message'];
        } elseif ('' !== trim($body_raw)) {
            $message = trim(strip_tags($body_raw));
        } else {
            $message = 'Empty or non-JSON response.';
        }

        $ok = 200 === $response_code
            && is_array($decoded)
            && !empty($decoded['success'])
            && !empty($decoded['key']);

        return [
            'ok' => $ok,
            'http_code' => $response_code,
            'message' => $ok ? '' : $message,
            'data' => is_array($decoded) ? $decoded : [],
        ];
    }
}

if (!function_exists('digtiali_stockpik_log')) {
    function digtiali_stockpik_log(string $message): void
    {
        $settings = digtiali_stockpik_get_settings();
        if (empty($settings['enable_logging'])) {
            return;
        }

        error_log('[Digtiali Stockpik] ' . $message);
    }
}

if (!function_exists('digtiali_stockpik_remaining_label')) {
    function digtiali_stockpik_remaining_label(string $expires_at, ?string $locale = null): string
    {
        if ('' === $expires_at) {
            return '';
        }

        $lang = digtiali_stockpik_detect_language($locale);
        $ts   = false;

        // Support DD-MM-YYYY
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $expires_at, $m)) {
            $ts = mktime(0, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3]);
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $expires_at, $m)) {
            // Support YYYY-MM-DD
            $ts = mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
        }

        if (false === $ts || $ts < time()) {
            return '';
        }

        $diff_days = (int) ceil(($ts - time()) / 86400);

        if ($diff_days >= 365) {
            $years = round($diff_days / 365, 1);
            $count = ($years == (int) $years) ? (int) $years : $years;
            if ('ar' === $lang) {
                return 'متبقي ' . $count . ' سنة';
            }
            if ('tr' === $lang) {
                return $count . ' yıl kaldı';
            }
            return $count . ' ' . (1 === $count ? 'year' : 'years') . ' left';
        }

        if ($diff_days >= 30) {
            $months = (int) round($diff_days / 30);
            if ('ar' === $lang) {
                return 'متبقي ' . $months . ' شهر';
            }
            if ('tr' === $lang) {
                return $months . ' ay kaldı';
            }
            return $months . ' ' . (1 === $months ? 'month' : 'months') . ' left';
        }

        if ('ar' === $lang) {
            return 'متبقي ' . $diff_days . ' يوم';
        }
        if ('tr' === $lang) {
            return $diff_days . ' gün kaldı';
        }
        return $diff_days . ' ' . (1 === $diff_days ? 'day' : 'days') . ' left';
    }
}

/**
 * True when the order is paid with WooCommerce Stripe (gateway id "stripe") using
 * Credit/Debit Card or Link (UPE types "card" and "link" on _stripe_upe_payment_type).
 */
if (!function_exists('digtiali_stockpik_order_uses_stripe_card_or_link')) {
    function digtiali_stockpik_order_uses_stripe_card_or_link($order): bool
    {
        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }

        if ('stripe' !== $order->get_payment_method()) {
            return false;
        }

        $upe_type = $order->get_meta('_stripe_upe_payment_type', true);

        return is_string($upe_type) && in_array($upe_type, ['card', 'link'], true);
    }
}
