<?php
/**
 * Plugin Name: Advanced Language & Country Switcher
 * Plugin URI: https://yourwebsite.com
 * Description: Advanced language and country switcher with WooCommerce integration
 * Version: 1.0.0
 * Author: Abbalochdev
 * Text Domain: alcs
 * Domain Path: /languages
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ALCS_VERSION', '1.0.0');
define('ALCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALCS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load URL Handler
require_once ALCS_PLUGIN_DIR . 'includes/class-alcs-url-handler.php';

class ALCS_Plugin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize URL Handler
        ALCS_URL_Handler::get_instance();

        // Initialize hooks
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('locale', array($this, 'set_preferred_language'));
        add_action('init', array($this, 'reload_translations'));
        add_action('woocommerce_init', array($this, 'update_customer_country'));
    }

    public function init() {
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

        // Add shortcodes
        add_shortcode('alcs_language', array($this, 'language_switcher_shortcode'));
        add_shortcode('alcs_country', array($this, 'country_switcher_shortcode'));
    }

    public function enqueue_assets() {
        wp_enqueue_style(
            'alcs-style',
            ALCS_PLUGIN_URL . 'assets/css/style.css',
            array(),
            ALCS_VERSION
        );

        wp_enqueue_script(
            'alcs-script',
            ALCS_PLUGIN_URL . 'assets/js/script.js',
            array('jquery'),
            ALCS_VERSION,
            true
        );

        wp_localize_script('alcs-script', 'alcsData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alcs-nonce'),
            'currentLang' => isset($_COOKIE['preferredLang']) ? $_COOKIE['preferredLang'] : 'en_US',
            'currentCountry' => isset($_COOKIE['selected_country']) ? $_COOKIE['selected_country'] : 'AE'
        ));
    }

    public function set_preferred_language($locale) {
        if (isset($_COOKIE['preferredLang'])) {
            $preferredLang = sanitize_text_field($_COOKIE['preferredLang']);
            $supportedLocales = [
                'ar' => 'ar',
                'en_US' => 'en_US',
            ];

            if (array_key_exists($preferredLang, $supportedLocales)) {
                return $supportedLocales[$preferredLang];
            }
        }
        return 'en_US';
    }

    public function reload_translations() {
        $locale = apply_filters('locale', get_locale());
        $moFilePath = WP_LANG_DIR . '/' . $locale . '.mo';

        if (file_exists($moFilePath)) {
            load_textdomain('default', $moFilePath);
        }
    }

    public function update_customer_country() {
        if (isset($_POST['selected_country'])) {
            $country = sanitize_text_field($_POST['selected_country']);
            $allowed_countries = array('AE', 'SA', 'QA', 'KW', 'OM');

            if (!in_array($country, $allowed_countries)) {
                return;
            }

            if (WC()->customer) {
                WC()->customer->set_billing_country($country);
                WC()->customer->set_shipping_country($country);
                WC()->customer->save();
            }

            setcookie(
                'selected_country',
                $country,
                time() + YEAR_IN_SECONDS,
                COOKIEPATH,
                COOKIE_DOMAIN,
                is_ssl(),
                true
            );
        }

        if (isset($_COOKIE['selected_country'])) {
            $country = sanitize_text_field($_COOKIE['selected_country']);
            $allowed_countries = array('AE', 'SA', 'QA', 'KW', 'OM');

            if (in_array($country, $allowed_countries) && WC()->customer) {
                WC()->customer->set_billing_country($country);
                WC()->customer->set_shipping_country($country);
                WC()->customer->save();
            }
        }
    }

    public function language_switcher_shortcode($atts) {
        // Get the current language from cookies, default to 'en_US' if not set
        $current_lang = isset($_COOKIE['preferredLang']) ? $_COOKIE['preferredLang'] : 'en_US';

        // Start output buffering
        ob_start();
        ?>
        <!-- Desktop Language Switcher -->
        <span id="desktop-lang-switch" class="lang-switch" 
            data-lang="<?php echo $current_lang === 'ar' ? 'en_US' : 'ar'; ?>">
            <!-- Global Icon -->
            <img src="<?php echo plugins_url('assets/images/global.png', __FILE__); ?>" 
                 alt="Global Icon" class="global-icon">
            
            <!-- Language Text -->
            <?php echo $current_lang === 'ar' ? 'English' : 'العربية'; ?>
        </span>

        <!-- Mobile Language Switcher -->
        <span id="mobile-lang-switch" class="lang-switch mobile" 
            data-lang="<?php echo $current_lang === 'ar' ? 'en_US' : 'ar'; ?>">
            <!-- Global Icon -->
            <img src="<?php echo plugins_url('assets/images/global.png', __FILE__); ?>" 
                 alt="Global Icon" class="global-icon">
            
            <!-- Language Text -->
            <?php echo $current_lang === 'ar' ? 'English' : 'العربية'; ?>
        </span>
        <?php
        // Return the output
        return ob_get_clean();
    }

    public function country_switcher_shortcode($atts) {
        $current_country = isset($_COOKIE['selected_country']) ? $_COOKIE['selected_country'] : 'AE';
        $countries = array(
            'AE' => array('name' => 'UAE', 'currency' => 'AED'),
            'SA' => array('name' => 'Saudi Arabia', 'currency' => 'SAR'),
            'QA' => array('name' => 'Qatar', 'currency' => 'QAR'),
            'KW' => array('name' => 'Kuwait', 'currency' => 'KWD'),
            'OM' => array('name' => 'Oman', 'currency' => 'OMR')
        );

        ob_start();
        ?>
        <div class="custom-dropdown">
            <span class="dropdown-btn">
                <span class="delivery-text">Deliver to:</span>
                <span id="selected-country-label">
                    <img src="<?php echo ALCS_PLUGIN_URL . 'assets/images/flags/' . strtolower($current_country) . '.png'; ?>" 
                         alt="<?php echo esc_attr($countries[$current_country]['name']); ?> Flag" 
                         class="flag-icon">
                    <?php echo esc_html($countries[$current_country]['name']); ?>
                </span>
            </span>
            <div class="dropdown-content">
                <?php foreach ($countries as $code => $data) : ?>
                    <div class="dropdown-item" data-country="<?php echo esc_attr($code); ?>">
                        <img src="<?php echo ALCS_PLUGIN_URL . 'assets/images/flags/' . strtolower($code) . '.png'; ?>" 
                             alt="<?php echo esc_attr($data['name']); ?> Flag" 
                             class="flag-icon">
                        <?php echo esc_html($data['name']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'alcs',
            false,
            dirname(ALCS_PLUGIN_BASENAME) . '/languages/'
        );
    }

    // Static activation method
    public static function activate() {
        flush_rewrite_rules();
    }

    // Static deactivation method
    public static function deactivate() {
        flush_rewrite_rules();
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('ALCS_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('ALCS_Plugin', 'deactivate'));

// AJAX handlers
function alcs_switch_language() {
    check_ajax_referer('alcs-nonce', 'nonce');
    $lang = sanitize_text_field($_POST['lang']);
    setcookie('preferredLang', $lang, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
    wp_send_json_success();
}
add_action('wp_ajax_alcs_switch_language', 'alcs_switch_language');
add_action('wp_ajax_nopriv_alcs_switch_language', 'alcs_switch_language');

function alcs_switch_country() {
    check_ajax_referer('alcs-nonce', 'nonce');
    $country = sanitize_text_field($_POST['country']);
    
    $allowed_countries = array('AE', 'SA', 'QA', 'KW', 'OM');
    if (!in_array($country, $allowed_countries)) {
        wp_send_json_error('Invalid country');
        return;
    }

    setcookie('selected_country', $country, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);

    // Update WooCommerce customer country
    if (class_exists('WC_Customer') && WC()->customer) {
        WC()->customer->set_billing_country($country);
        WC()->customer->set_shipping_country($country);
        WC()->customer->save();
    }

    // Update WooCommerce Price Based on Country
    if (class_exists('WCPBC_Pricing_Zones')) {
        $zones = WCPBC_Pricing_Zones::get_zones();
        foreach ($zones as $zone) {
            if (in_array($country, $zone->get_countries())) {
                update_option('wcpbc_current_zone', $zone->get_id());
                if (WC()->session) {
                    WC()->session->set('wcpbc_customer_country', $country);
                }
                break;
            }
        }
    }

    wp_send_json_success();
}
add_action('wp_ajax_alcs_switch_country', 'alcs_switch_country');
add_action('wp_ajax_nopriv_alcs_switch_country', 'alcs_switch_country');

// Initialize plugin
function ALCS() {
    return ALCS_Plugin::get_instance();
}
ALCS();