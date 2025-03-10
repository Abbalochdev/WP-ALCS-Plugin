<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class ALCS_Base_Handler {
    protected $allowed_countries = ['AE', 'SA', 'QA', 'KW', 'OM', 'BH'];
    protected $allowed_languages = ['en', 'ar'];
    protected $cookie_handler;
    protected $cache_duration = 3600; // 1 hour
    protected $locale_cache_key = 'alcs_locale_';

    public function __construct($cookie_handler = null) {
        $this->cookie_handler = $cookie_handler;
        $this->init_hooks();
    }

    abstract protected function init_hooks();

    protected function validate_locale($country, $lang) {
        return in_array(strtoupper($country), $this->allowed_countries, true) && 
               in_array(strtolower($lang), $this->allowed_languages, true);
    }

    protected function clean_url_path($path) {
        $path = preg_replace('#^/[a-z]{2}-(en|ar)/#i', '/', $path);
        return '/' . ltrim(preg_replace('#/{2,}#', '/', $path), '/');
    }

    protected function get_cached_locale() {
        return wp_cache_get($this->locale_cache_key . get_current_user_id());
    }

    protected function set_cached_locale($locale) {
        wp_cache_set(
            $this->locale_cache_key . get_current_user_id(),
            $locale,
            '',
            $this->cache_duration
        );
    }

    protected function log_error($exception) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[ALCS] Error: %s in %s:%d',
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));
        }
    }

    protected function sanitize_locale_params($country, $lang) {
        return [
            'country' => strtolower(substr(preg_replace('/[^a-zA-Z]/', '', $country), 0, 2)),
            'lang' => in_array(strtolower($lang), ['en', 'ar']) ? strtolower($lang) : 'en'
        ];
    }

}