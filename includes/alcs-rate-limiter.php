<?php
if (!defined('ABSPATH')) {
    exit;
}

class ALCS_Rate_Limiter {
    private $prefix = 'alcs_rate_';
    private $duration = 2; // seconds

    public function check_rate_limit($identifier) {
        $key = $this->prefix . $identifier;
        if (wp_cache_get($key)) {
            throw new Exception('Too many requests. Please wait a moment.', 429);
        }
        wp_cache_set($key, true, '', $this->duration);
    }
}