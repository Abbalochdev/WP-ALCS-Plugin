<?php
class ALCS_Language_Handler {
    private static $supported_languages = array(
        'en_US' => array(
            'name' => 'English',
            'native_name' => 'English',
            'flag' => 'us.png'
        ),
        'ar' => array(
            'name' => 'Arabic',
            'native_name' => 'العربية',
            'flag' => 'ae.png'
        )
    );

    public static function get_current_language() {
        return isset($_COOKIE['preferredLang']) ? 
               sanitize_text_field($_COOKIE['preferredLang']) : 'en_US';
    }

    public static function render_switcher($atts = array()) {
        $current_lang = self::get_current_language();
        
        ob_start();
        ?>
        <div class="alcs-language-switcher">
            <select class="alcs-select" data-type="language">
                <?php foreach (self::$supported_languages as $code => $lang_data) : ?>
                    <option value="<?php echo esc_attr($code); ?>" 
                            <?php selected($current_lang, $code); ?>>
                        <?php echo esc_html($lang_data['native_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function switch_language($lang) {
        if (array_key_exists($lang, self::$supported_languages)) {
            setcookie(
                'preferredLang',
                $lang,
                time() + YEAR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
            return true;
        }
        return false;
    }

    public static function get_language_name($code) {
        return isset(self::$supported_languages[$code]) ? 
               self::$supported_languages[$code]['name'] : '';
    }

    public static function get_language_native_name($code) {
        return isset(self::$supported_languages[$code]) ? 
               self::$supported_languages[$code]['native_name'] : '';
    }
}

// Add AJAX handler for language switching
add_action('wp_ajax_alcs_switch_language', 'alcs_switch_language_callback');
add_action('wp_ajax_nopriv_alcs_switch_language', 'alcs_switch_language_callback');

function alcs_switch_language_callback() {
    check_ajax_referer('alcs-nonce', 'nonce');
    
    $lang = sanitize_text_field($_POST['lang']);
    if (ALCS_Language_Handler::switch_language($lang)) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Invalid language code');
    }
}