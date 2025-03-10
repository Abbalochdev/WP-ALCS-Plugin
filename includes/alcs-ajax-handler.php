 <?php

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('ALCS_Rate_Limiter')) {
    require_once plugin_dir_path(__FILE__) . 'alcs-rate-limiter.php';
}

class ALCS_Ajax_Handler extends ALCS_Base_Handler {
    private $rate_limiter;
    private $last_error;

    public function __construct($cookie_handler = null) {
        $this->rate_limiter = new ALCS_Rate_Limiter();
        parent::__construct($cookie_handler);
    }

    protected function init_hooks() {
        if (!wp_doing_ajax()) {
            return;
        }
        add_action('wp_ajax_alcs_switch_locale', [$this, 'switch_locale']);
        add_action('wp_ajax_nopriv_alcs_switch_locale', [$this, 'switch_locale']);
        
        add_action('woocommerce_init', [$this, 'initialize_customer_country']);

    }

    public function switch_locale() {
        try {
            $this->rate_limiter->check_rate_limit(get_current_user_id() ?: $_SERVER['REMOTE_ADDR']);
            
            if (!check_ajax_referer('alcs-nonce', 'nonce', false)) {
                throw new Exception('Invalid security token', 403);
            }

            if (empty($_POST['country']) || empty($_POST['lang'])) {
                throw new Exception('Missing required parameters', 400);
            }

            $locale = $this->sanitize_locale_params($_POST['country'], $_POST['lang']);
            
            $current_path = sanitize_text_field($_POST['current_path'] ?? '/');

            if (!$this->update_locale_settings($locale['country'], $locale['lang'])) {
                throw new Exception('Failed to update locale settings', 500);
            }

            $clean_path = $this->clean_url_path($current_path);
            $redirect_url = home_url("/{$locale['country']}-{$locale['lang']}" . $clean_path);
            
            // Set session flag to prevent double reload
            if (!headers_sent() && !session_id()) {
                session_start();
            }
            $_SESSION['alcs_locale_updated'] = true;

            wp_send_json_success([
                'redirect' => $redirect_url,
                'sync_complete' => true,
                'timestamp' => $this->current_utc_datetime,
                'user' => $this->current_user_login,
                'debug' => $this->get_debug_info($locale)
            ]);
    
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'debug' => [
                    'country' => $_POST['country'] ?? null,
                    'lang' => $_POST['lang'] ?? null,
                    'woocommerce_active' => class_exists('WooCommerce')
                ]
            ], $e->getCode() ?: 400);
        }
    }

    public function switch_locale_via_url($country, $lang) {
        try {
            $locale = $this->sanitize_locale_params($country, $lang);
            $country = strtolower($locale['country']);
            // return $this->update_locale_settings($country, $locale['lang']);
            
             return $this->update_locale_settings($country, $locale['lang']);
        
        } catch (Exception $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }
    
    private function update_locale_settings($country, $lang) {
        try {
            global $wpdb;
            $wpdb->query('START TRANSACTION');
            
            $this->log_debug("Starting locale update transaction");


            $this->cookie_handler->set_selected_country($country);
            $this->cookie_handler->set_preferred_language($lang);
            $this->apply_country_to_woocommerce(strtoupper($country));

            $this->set_cached_locale(['country' => $country, 'lang' => $lang]);
            
             // Clear relevant caches
            $this->clear_caches();

            // Trigger custom hooks
             do_action('alcs_locale_updated', $country, $lang);

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            $this->log_error($e);
            return false;
        }
    }
    
    // Sync WooCommerce settings based on cookies or URL
    public function initialize_customer_country() {
        $country = $this->cookie_handler->get_selected_country();
        
        if (isset($country)) {
            $country = strtoupper(sanitize_text_field($country));
            if (in_array($country, $this->allowed_countries)) {
                $this->apply_country_to_woocommerce($country);
            }
        }
    }

    private function apply_country_to_woocommerce($country) {
        
        if (WC()->customer) {
            if (WC()->customer->get_billing_country() !== $country) {
                
                $current_billing_country = WC()->customer->get_billing_country();
                $current_shipping_country = WC()->customer->get_shipping_country();
    
                // Only update if the country in the cookie is different from the current billing or shipping country
                if ($current_billing_country !== $country || $current_shipping_country !== $country) {
                    WC()->customer->set_billing_country($country);
                    WC()->customer->set_shipping_country($country);
                    WC()->customer->save();
                }
            }

            if (WC()->session) {
                WC()->session->set('customer_country', $country);
                WC()->session->set_customer_session_cookie(true);
            }
        }
    }

    private function clear_caches() {
        wp_cache_delete('locale_' . get_current_user_id(), 'alcs');
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }

    private function get_debug_info($locale) {
        return [
            'country' => $locale['country'],
            'lang' => $locale['lang'],
            'cookies_set' => true,
            'woocommerce_active' => class_exists('WooCommerce'),
            'session_active' => session_id() ? true : false,
            'user' => $this->current_user_login,
            'timestamp_utc' => $this->current_utc_datetime
        ];
    }

    private function handle_error($exception) {
        $this->last_error = $exception->getMessage();
        $this->log_debug("Error: " . $exception->getMessage());
        
        wp_send_json_error([
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'timestamp' => $this->current_utc_datetime,
            'user' => $this->current_user_login,
            'debug' => [
                'error_trace' => $exception->getTraceAsString(),
                'woocommerce_active' => class_exists('WooCommerce'),
                'session_active' => session_id() ? true : false
            ]
        ], $exception->getCode() ?: 400);
    }

    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[ALCS Debug] [%s] [User: %s] %s',
                $this->current_utc_datetime,
                $this->current_user_login,
                $message
            ));
        }
    }

    public function get_last_error() {
        return $this->last_error;
    }
}