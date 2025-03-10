 <?php

if (!defined('ABSPATH')) {
    exit;
}

class ALCS_SEO_Handler {
    private static $instance = null;
    private $default_country = 'ae';
    private $default_lang = 'en';
    private $allowed_countries = ['ae', 'kw', 'sa', 'qa', 'om','bh'];
    private $allowed_languages = ['en', 'ar'];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_filter('rank_math/frontend/canonical', [$this, 'modify_url']);
        add_filter('rank_math/frontend/permalink', [$this, 'modify_url']);
        add_filter('rank_math/sitemap/entry', [$this, 'modify_sitemap_entry']);
        add_filter('rank_math/opengraph/url', [$this, 'modify_url']);
        add_action('wp_head', [$this, 'generate_hreflang_tags']);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_head', [$this, 'debug_url_info'], 1);
        }
    }

    public function modify_url($url) {
        if (empty($url)) {
            return $url;
        }

        $locale = $this->get_current_locale();
        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '';

        $clean_path = $this->remove_locale_prefix($path);
        $new_url = home_url(sprintf('/%s-%s%s', $locale['country'], $locale['lang'], $clean_path));

        if (isset($parsed_url['query'])) {
            $new_url .= '?' . $parsed_url['query'];
        }
        if (isset($parsed_url['fragment'])) {
            $new_url .= '#' . $parsed_url['fragment'];
        }

        return $new_url;
    }

        public function modify_sitemap_entry($entry) {
        // Ensure no output before headers are sent
        ob_start(); // Start output buffering

        // Your existing logic
        if (!empty($entry['loc'])) {
            $entry['loc'] = $this->modify_url($entry['loc']);
            $entry['alternates'] = $this->get_alternate_urls($entry['loc']);
        }

        ob_end_clean(); // Clean (erase) the output buffer and turn off output buffering
        return $entry;
    }

    public function generate_hreflang_tags() {
        $base_url = home_url();
        $languages = [
            'en' => ['ae', 'kw', 'sa', 'qa', 'om','bh'],
            'ar' => ['ae', 'kw', 'sa', 'qa', 'om','bh'],
        ];

        // Strip country-language prefix if present
        $base_urls = preg_replace('#/[a-z]{2}-(en|ar)/?#', '', $base_url);

        foreach ($languages as $lang => $regions) {
            foreach ($regions as $region) {
                echo '<link rel="alternate" href="' . esc_url($base_urls . '/' . $region . '-' . $lang . '/') . '" hreflang="' . esc_attr($lang . '-' . $region) . '">' . PHP_EOL;
            }
        }

        // Default hreflang
        echo '<link rel="alternate" href="' . esc_url($base_url) . '" hreflang="x-default">' . PHP_EOL;
    }

    private function remove_locale_prefix($url) {
        return preg_replace('#/[a-z]{2}-(en|ar)(/|$)#', '/', $url);
    }

    private function get_current_locale() {
        $country = strtolower($_COOKIE['selected_country'] ?? $this->default_country);
        $lang = strtolower($_COOKIE['preferredLang'] ?? $this->default_lang);

        $country = in_array($country, $this->allowed_countries) ? $country : $this->default_country;
        $lang = in_array($lang, $this->allowed_languages) ? $lang : $this->default_lang;

        return ['country' => $country, 'lang' => $lang];
    }

    private function get_alternate_urls($url) {
        $alternate_urls = [];
        $parsed_url = parse_url($url);
        $path = $parsed_url['path'] ?? '';

        $base_url = home_url();

        foreach ($this->allowed_languages as $lang) {
            foreach ($this->allowed_countries as $country) {
                $clean_path = $this->remove_locale_prefix($path);
                $alternate_url = $base_url . sprintf('/%s-%s%s', $country, $lang, $clean_path);
                
                if (isset($parsed_url['query'])) {
                    $alternate_url .= '?' . $parsed_url['query'];
                }
                if (isset($parsed_url['fragment'])) {
                    $alternate_url .= '#' . $parsed_url['fragment'];
                }

                $alternate_urls[] = [
                    'hreflang' => $lang . '-' . $country,
                    'href' => $alternate_url,
                ];
            }
        }

        return $alternate_urls;
    }

    public function debug_url_info() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Current URL: ' . $_SERVER['REQUEST_URI']);
            error_log('Locale: ' . print_r($this->get_current_locale(), true));
        }
    }
}

ALCS_SEO_Handler::get_instance();