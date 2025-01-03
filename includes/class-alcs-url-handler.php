<?php
if (!defined('ABSPATH')) {
    exit;
}

class ALCS_URL_Handler {
    private static $instance = null;
    private $default_country = 'ae';
    private $default_lang = 'en';
    private $allowed_countries = ['ae', 'sa', 'qa', 'kw', 'om'];
    private $allowed_languages = ['en', 'ar'];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Rewrite rules and query vars
        add_action('init', array($this, 'setup_rewrite_rules_and_query_vars'));

        // URL modifications
        add_filter('home_url', array($this, 'modify_url'), 10, 2);
        add_filter('page_link', array($this, 'modify_url'), 10, 2);
        add_filter('post_link', array($this, 'modify_url'), 10, 2);
        add_filter('term_link', array($this, 'modify_url'), 10, 2);

        // Handle redirects and locale
        add_action('template_redirect', array($this, 'handle_language_country_redirect'));
    }

    public function setup_rewrite_rules_and_query_vars() {
        // Add rewrite rules
        add_rewrite_rule(
            '^([a-z]{2})-(en|ar)/?$',
            'index.php?country=$matches[1]&lang=$matches[2]',
            'top'
        );

        add_rewrite_rule(
            '^([a-z]{2})-(en|ar)/(.+?)/?$',
            'index.php?country=$matches[1]&lang=$matches[2]&pagename=$matches[3]',
            'top'
        );

        // Register query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'country';
            $vars[] = 'lang';
            return $vars;
        });

        // Flush rewrite rules on activation/deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function modify_url($url, $path = '') {
        if (is_admin() || defined('REST_REQUEST')) {
            return $url;
        }

        // Exclude WooCommerce specific pages
        $woocommerce_pages = array('cart', 'checkout', 'my-account');
        foreach ($woocommerce_pages as $page) {
            if (strpos($url, $page) !== false) {
                return $url;
            }
        }

        $country = $this->get_current_country();
        $lang = $this->get_current_language();

        // Parse URL
        $url_parts = parse_url($url);
        $path = isset($url_parts['path']) ? $url_parts['path'] : '';

        // Remove existing country-lang prefix if exists
        $path = preg_replace('#^/[a-z]{2}-(en|ar)/#', '', $path);

        // Add country-lang prefix
        $new_path = "/{$country}-{$lang}/" . ltrim($path, '/');
        $url_parts['path'] = $new_path;

        return $this->build_url($url_parts);
    }

    public function handle_language_country_redirect() {
        global $wp_query;

        $country = get_query_var('country');
        $lang = get_query_var('lang');

        if ($country && $lang) {
            // Validate country and language
            $country = strtolower($country);
            $lang = strtolower($lang);

            if (!in_array($country, $this->allowed_countries)) {
                $country = $this->default_country;
            }

            if (!in_array($lang, $this->allowed_languages)) {
                $lang = $this->default_lang;
            }

            // Set cookies
            $this->set_cookies($country, $lang);
        }
    }

    private function set_cookies($country, $lang) {
        setcookie(
            'selected_country',
            strtoupper($country),
            time() + YEAR_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );

        setcookie(
            'preferredLang',
            $lang === 'ar' ? 'ar' : 'en_US',
            time() + YEAR_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    private function get_current_country() {
        return isset($_COOKIE['selected_country']) ? 
               strtolower($_COOKIE['selected_country']) : 
               $this->default_country;
    }

    private function get_current_language() {
        $lang = isset($_COOKIE['preferredLang']) ? $_COOKIE['preferredLang'] : 'en_US';
        return $lang === 'ar' ? 'ar' : 'en';
    }

    private function build_url($parts) {
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = isset($parts['host']) ? $parts['host'] : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        // Ensure no duplicate slashes
        $path = preg_replace('#/+#', '/', $path);

        return $scheme . $host . $port . $path . $query . $fragment;
    }

    public static function activate() {
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }
}