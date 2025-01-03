<?php
class ALCS_Utility {
    /**
     * Get available languages
     *
     * @return array
     */
    public static function get_available_languages() {
        return array(
            'en_US' => array(
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr'
            ),
            'ar' => array(
                'name' => 'Arabic',
                'native_name' => 'العربية',
                'direction' => 'rtl'
            )
        );
    }

    /**
     * Get available countries
     *
     * @return array
     */
    public static function get_available_countries() {
        return array(
            'AE' => array(
                'name' => 'UAE',
                'currency' => 'AED',
                'flag' => 'ae.png'
            ),
            'SA' => array(
                'name' => 'Saudi Arabia',
                'currency' => 'SAR',
                'flag' => 'sa.png'
            ),
            'QA' => array(
                'name' => 'Qatar',
                'currency' => 'QAR',
                'flag' => 'qa.png'
            ),
            'KW' => array(
                'name' => 'Kuwait',
                'currency' => 'KWD',
                'flag' => 'kw.png'
            ),
            'OM' => array(
                'name' => 'Oman',
                'currency' => 'OMR',
                'flag' => 'om.png'
            )
        );
    }

    /**
     * Handle AJAX requests
     */
    public static function handle_ajax_request() {
        check_ajax_referer('alcs-nonce', 'nonce');

        $type = sanitize_text_field($_POST['type']);
        $value = sanitize_text_field($_POST['value']);

        if ($type === 'language') {
            self::set_language($value);
        } elseif ($type === 'country') {
            self::set_country($value);
        }

        wp_send_json_success();
    }

    /**
     * Set language
     *
     * @param string $lang
     */
    private static function set_language($lang) {
        $languages = self::get_available_languages();
        if (isset($languages[$lang])) {
            setcookie(
                'preferredLang',
                $lang,
                time() + YEAR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }
    }

    /**
     * Set country
     *
     * @param string $country
     */
    private static function set_country($country) {
        $countries = self::get_available_countries();
        if (isset($countries[$country])) {
            setcookie(
                'selected_country',
                $country,
                time() + YEAR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );

            if (class_exists('WooCommerce')) {
                WC()->session->set('customer_country', $country);
            }
        }
    }
}

// Register AJAX handlers
add_action('wp_ajax_alcs_update_selection', array('ALCS_Utility', 'handle_ajax_request'));
add_action('wp_ajax_nopriv_alcs_update_selection', array('ALCS_Utility', 'handle_ajax_request'));