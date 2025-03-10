<?php
/**
 * Plugin Name: AB ALCS Plugin
 * Plugin URI: https://github.com/Abbalochdev/WP-ALCS-Plugin
 * Description: Advanced language and country switcher with WooCommerce integration
 * Version: 1.6.0
 * Author: Abbalochdev
 * Text Domain: alcs
 * Domain Path: /languages
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ALCS_VERSION', '1.6.0');
define('ALCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ALCS_PLUGIN_BASENAME', plugin_basename(__FILE__));


class ALCS_Plugin {
    private static $instance = null;
    private $cookie_handler;
    private $ajax_handler;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
        private function __construct() {
        // Load required files in correct order
        $this->load_dependencies();
        
        // Initialize handlers
        $this->init_handlers();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        $required_files = [
            'base-handler',
            'rate-limiter',
            'cookie-handler',
            'ajax-handler',
            'url-handler',
            'seo-handler',
            'currency-handler'
        ];

        foreach ($required_files as $file) {
            $file_path = ALCS_PLUGIN_DIR . 'includes/alcs-' . $file . '.php';
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                error_log("ALCS Plugin: Missing required file - alcs-{$file}.php");
            }
        }
    }
    
    
        private function init_handlers() {
        // Initialize handlers in correct order
        $this->cookie_handler = new ALCS_Cookie_Handler();
        $this->ajax_handler = new ALCS_Ajax_Handler($this->cookie_handler);
        ALCS_URL_Handler::get_instance();
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'load_plugin_textdomain']);
        // add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('locale', [$this, 'set_preferred_language']);
        add_action('init', [$this, 'reload_translations']);
        
        
        add_shortcode('alcs_language', array($this, 'language_switcher_shortcode'));
        add_shortcode('alcs_country', array($this, 'country_switcher_shortcode'));
        

    }

    public function enqueue_assets() {
        wp_enqueue_style('alcs-style', ALCS_PLUGIN_URL . 'assets/css/style.css', [], ALCS_VERSION);
        wp_enqueue_script('alcs-script', ALCS_PLUGIN_URL . 'assets/js/script.js', ['jquery'], ALCS_VERSION, true);

        wp_localize_script('alcs-script', 'alcsData', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('alcs-nonce'),
            'currentLang' => $this->cookie_handler->get_preferred_language(),
            'currentCountry' => $this->cookie_handler->get_selected_country()
        ]);
    }
    
        public function set_preferred_language($locale) {
        $preferredLang = $_COOKIE['preferredLang'] ?? 'en_US';
        $supportedLocales = ['ar' => 'ar', 'en_US' => 'en_US'];

        return $supportedLocales[$preferredLang] ?? 'en_US';
    }

    public function reload_translations() {
        $locale = apply_filters('locale', get_locale());
        $moFilePath = WP_LANG_DIR . "/$locale.mo";

        if (file_exists($moFilePath)) {
            load_textdomain('default', $moFilePath);
        }
    }

    public function language_switcher_shortcode($atts) {
    $current_lang = $this->cookie_handler->get_preferred_language();

        ob_start();
        ?>
        <span id="desktop-lang-switch" class="lang-switch" data-lang="<?= $current_lang === 'ar' ? 'en_US' : 'ar'; ?>">
            <?= $current_lang === 'ar' ? 'English' : 'العربية'; ?>
        </span>
        <span id="mobile-lang-switch" class="lang-switch mobile" data-lang="<?= $current_lang === 'ar' ? 'en_US' : 'ar'; ?>">
            <?= $current_lang === 'ar' ? 'English' : 'العربية'; ?>
        </span>
        <?php
        return ob_get_clean();
    }
    
    public function country_switcher_shortcode($atts) {
        $current_country = $this->cookie_handler->get_selected_country();
        $countries = [
            'AE' => ['name' => 'UAE', 'currency' => 'AED'],
            'SA' => ['name' => 'Saudi', 'currency' => 'SAR'],
            'QA' => ['name' => 'Qatar', 'currency' => 'QAR'],
            'KW' => ['name' => 'Kuwait', 'currency' => 'KWD'],
            'OM' => ['name' => 'Oman', 'currency' => 'OMR'],
            'BH' => ['name' => 'Bahrain', 'currency' => 'BHD']
        ];

        ob_start();
        ?>
        <div class="custom-dropdown">
            <span class="dropdown-btn">
                <span class="delivery-text">Deliver to:</span>
                <span id="selected-country-label">
                    <img src="<?php echo ALCS_PLUGIN_URL . 'assets/images/flags/' . strtolower($current_country) . '.png'; ?>" 
                         alt="<?php echo esc_attr($countries[$current_country]['name']); ?> Flag" 
                         class="flag-icon">
                    <?= esc_html($countries[$current_country]['name']); ?>
                </span>
            </span>
            <div class="dropdown-content">
                <?php foreach ($countries as $code => $data) : ?>
                    <div class="dropdown-item" data-country="<?php echo esc_attr($code); ?>">
                        <img src="<?php echo ALCS_PLUGIN_URL . 'assets/images/flags/' . strtolower($code) . '.png'; ?>" 
                             alt="<?php echo esc_attr($data['name']); ?> Flag" 
                             class="flag-icon">
                        <?= esc_html($data['name']); ?>
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
    
     public static function activate() {
        if (!get_option('alcs_version')) {
            add_option('alcs_version', ALCS_VERSION);
            add_option('alcs_rewrite_rules_need_flush', true);
        }

        if (method_exists('ALCS_URL_Handler', 'activate')) {
            ALCS_URL_Handler::activate();
        }
    }

    public static function deactivate() {
        if (method_exists('ALCS_URL_Handler', 'deactivate')) {
            ALCS_URL_Handler::deactivate();
        }
        delete_option('alcs_rewrite_rules_need_flush');
        flush_rewrite_rules();
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    ALCS_Plugin::get_instance();
});

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('ALCS_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('ALCS_Plugin', 'deactivate'));

