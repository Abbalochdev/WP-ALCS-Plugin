 <?php

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('ALCS_Base_Handler')) {
    require_once plugin_dir_path(__FILE__) . 'alcs-base-handler.php';
}

class ALCS_URL_Handler extends ALCS_Base_Handler {
    private static $instance = null;
    private $default_country = 'ae';
    private $default_lang = 'en';
    private $ajax_handler;
    private $is_redirecting = false;

    private function __construct() {
        if (!class_exists('ALCS_Cookie_Handler')) {
            require_once plugin_dir_path(__FILE__) . 'alcs-cookie-handler.php';
            require_once plugin_dir_path(__FILE__) . 'alcs-ajax-handler.php';

        }
        
        $this->cookie_handler = new ALCS_Cookie_Handler();
        $this->ajax_handler = new ALCS_Ajax_Handler($this->cookie_handler);
        parent::__construct($this->cookie_handler);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    protected function init_hooks() {
        if (!is_admin()) {
            // add_action('init', [$this, 'setup_rewrite_rules'], 10);
            add_action('template_redirect', [$this, 'handle_language_country_redirect'], 1);
            add_filter('query_vars', [$this, 'register_query_vars']);
            
            // URL filters
            $url_filters = ['home_url', 'page_link', 'post_link', 'term_link'];
            foreach ($url_filters as $filter) {
                add_filter($filter, [$this, 'modify_url'], 10, 2);
            }
        }
    }
    
    
    // public function setup_rewrite_rules() {
    //     global $wp;
        
    //     // Add query vars
    //     $wp->add_query_var('country');
    //     $wp->add_query_var('lang');

    //     // Add rewrite rules with logging
    //     $this->add_rewrite_rules();

    //     // Check if rewrite rules need to be flushed
    //     if (get_option('alcs_rewrite_rules_need_flush', false)) {
    //         flush_rewrite_rules(true);
    //         update_option('alcs_rewrite_rules_need_flush', false);
            
    //     }
    // }
    
    // private function add_rewrite_rules() {
    //     $rules = [
    //         '^([a-z]{2})-(en|ar)/?$' => 'index.php?country=$matches[1]&lang=$matches[2]',
    //         '^([a-z]{2})-(en|ar)/(.+?)/?$' => 'index.php?country=$matches[1]&lang=$matches[2]&pagename=$matches[3]'
    //     ];

    //     foreach ($rules as $regex => $redirect) {
    //         add_rewrite_rule($regex, $redirect, 'top');
    //     }
    // }
    
    public function register_query_vars($vars) {
        $vars[] = 'country';
        $vars[] = 'lang';
        return $vars;
    }

    public function handle_language_country_redirect() {
        // Prevent infinite loops
        if ($this->is_redirecting || wp_doing_ajax() || is_admin()) {
            return;
        }

        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        if (empty($current_url)) {
            return;
        }

        // Get path without query string
        $path = parse_url($current_url, PHP_URL_PATH);
        
        // Check if URL already has locale
        if (preg_match('#^/([a-z]{2})-(en|ar)(?:/|$)#', $path, $matches)) {
            $country = strtolower($matches[1]);
            $lang = strtolower($matches[2]);

            if ($this->validate_locale($country, $lang)) {
                
                // Update cookies and settings if needed
                $this->maybe_update_locale($country, $lang, $current_url);

                return;
            }
        }

        // Get locale from cookies or default
        $locale = $this->get_current_locale();
        
        // Only redirect if we're not already on the correct URL
        if (!$this->is_correct_locale_url($path, $locale)) {
            $this->is_redirecting = true;
            $redirect_url = $this->build_locale_url($path, $locale);
            
            wp_safe_redirect($redirect_url, 302);
            exit;
        }
    }

    private function maybe_update_locale($country, $lang, $current_url) {
        $current = $this->get_current_locale();
        if ($current['country'] !== $country || $current['lang'] !== $lang) {
            $this->ajax_handler->switch_locale_via_url($country, $lang);
            $this->is_redirecting = true;
            header('Location: ' . $current_url);
        }
    }
    
    private function get_current_locale() {
        // Try to get from cookie first
        $country = $this->cookie_handler->get_selected_country();
        $lang = $this->cookie_handler->get_preferred_language();

        // Validate and return cookie values if valid
        if ($this->validate_locale($country, $lang)) {
            return [
                'country' => strtolower($country),
                'lang' => strtolower($lang)
            ];
        }

        // Return defaults
        return [
            'country' => $this->default_country,
            'lang' => $this->default_lang
        ];
    }
    
    public function modify_url($url, $path = '') {
        if (is_admin() || wp_doing_ajax() || $this->is_redirecting) {
            return $url;
        }

        $locale = $this->get_current_locale();
        $url_parts = parse_url($url);
        $path = $url_parts['path'] ?? '/';
        
        // Clean the path and add locale
        $clean_path = $this->clean_url_path($path);
        $url_parts['path'] = "/{$locale['country']}-{$locale['lang']}" . $clean_path;

        return $this->build_url($url_parts);
    }

    private function is_correct_locale_url($path, $locale) {
        return preg_match("#^/{$locale['country']}-{$locale['lang']}(?:/|$)#i", $path);
    }

    private function build_locale_url($path, $locale) {
        $clean_path = $this->clean_url_path($path);
        $locale_prefix = "/{$locale['country']}-{$locale['lang']}";
        
        // Prevent double locale prefixes
        if (strpos($clean_path, $locale_prefix) === 0) {
            return home_url($clean_path);
        }
        
        return home_url($locale_prefix . $clean_path);
    }





    protected function clean_url_path($path) {
        // Remove existing locale pattern
        $path = preg_replace('#^/[a-z]{2}-(en|ar)/#i', '/', $path);
        // Remove multiple slashes
        $path = preg_replace('#/{2,}#', '/', $path);
        // Ensure leading slash
        return '/' . ltrim($path, '/');
    }

    private function build_url($parts) {
        $scheme = isset($parts['scheme']) ? "{$parts['scheme']}://" : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? "?{$parts['query']}" : '';
        $fragment = isset($parts['fragment']) ? "#{$parts['fragment']}" : '';

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    // public static function activate() {
    //     add_option('alcs_rewrite_rules_need_flush', true);
    // }

    // public static function deactivate() {
    //     delete_option('alcs_rewrite_rules_need_flush');
    //     flush_rewrite_rules();
    // }
}

// Initialize the URL Handler
ALCS_URL_Handler::get_instance();