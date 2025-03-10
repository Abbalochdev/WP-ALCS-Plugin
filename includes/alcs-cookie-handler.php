<?php
if (!defined('ABSPATH')) {
    exit;
}

class ALCS_Cookie_Handler {
    public function get_preferred_language() {
        return $_COOKIE['preferredLang'] ?? 'en_US';
    }

    public function get_selected_country() {
        return $_COOKIE['selected_country'] ?? 'AE';
    }

    public function should_update_country($new_country) {
        $current = $this->get_selected_country();
        return $current !== $new_country;
    }

    public function validate_country_cookie() {
        $country = $this->get_selected_country();
        $allowed = ['AE', 'SA', 'QA', 'KW', 'OM', 'BH'];
        
        if (!in_array($country, $allowed)) {
            $this->set_selected_country('AE');
            return 'AE';
        }
        
        return $country;
    }

// public function set_country_cookie($country) {
 public function   set_selected_country($country){

    $country = strtolower($country); // Normalize to uppercase
    // $country = strtoupper($country); // Normalize to uppercase

    setcookie(
        'selected_country',
        $country,
        [
            'expires' => time() + YEAR_IN_SECONDS,
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'lax'
        ]
    );
}

// public function set_language_cookie($lang) {
public function set_preferred_language($lang){

    $lang = strtolower($lang); // Normalize to lowercase
    setcookie(
        'preferredLang',
        $lang,
        [
            'expires' => time() + YEAR_IN_SECONDS,
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'lax'
        ]
    );
}
}