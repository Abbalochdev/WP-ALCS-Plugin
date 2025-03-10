<?php
if (!defined('ABSPATH')) {
    exit;
}

class ALCS_Currency_Handler {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook after WooCommerce loads pricing zones
        add_filter('woocommerce_currency_symbol', [$this, 'change_currency_symbol'], 20, 2);
    }

    public function change_currency_symbol($currency_symbol, $currency) {
        // Get selected language from cookie
        $selected_lang = isset($_COOKIE['preferredLang']) ? sanitize_text_field($_COOKIE['preferredLang']) : 'en';

        // Define Arabic currency symbols
        $arabic_symbols = [
            'AED' => 'د.إ',  // UAE Dirham
            'SAR' => 'ر.س',  // Saudi Riyal
            'QAR' => 'ر.ق',  // Qatari Riyal
            'KWD' => 'د.ك',  // Kuwaiti Dinar
            'OMR' => 'ر.ع',  // Omani Rial
            'BHD' => 'د.ب'   // Bahraini Dinar
        ];

        // Apply Arabic symbols if Arabic is selected
        if ($selected_lang === 'ar' && isset($arabic_symbols[$currency])) {
            return $arabic_symbols[$currency];
        }

        return $currency_symbol; // Default WooCommerce currency symbol
    }
}

// Initialize the currency handler
ALCS_Currency_Handler::get_instance();