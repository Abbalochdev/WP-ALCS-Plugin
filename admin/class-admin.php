<?php
class ALCS_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Language & Country Switcher', 'alcs'),
            __('Lang & Country', 'alcs'),
            'manage_options',
            'alcs-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('alcs_options', 'alcs_default_language');
        register_setting('alcs_options', 'alcs_default_country');
        register_setting('alcs_options', 'alcs_enable_currency_switch');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('alcs_options');
                do_settings_sections('alcs_options');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Default Language', 'alcs'); ?></th>
                        <td>
                            <select name="alcs_default_language">
                                <?php foreach (ALCS_Utility::get_available_languages() as $code => $lang) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" 
                                            <?php selected(get_option('alcs_default_language'), $code); ?>>
                                        <?php echo esc_html($lang['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Default Country', 'alcs'); ?></th>
                        <td>
                            <select name="alcs_default_country">
                                <?php foreach (ALCS_Utility::get_available_countries() as $code => $country) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" 
                                            <?php selected(get_option('alcs_default_country'), $code); ?>>
                                        <?php echo esc_html($country['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Enable Currency Switch', 'alcs'); ?></th>
                        <td>
                            <input type="checkbox" name="alcs_enable_currency_switch" value="1" 
                                   <?php checked(get_option('alcs_enable_currency_switch'), 1); ?>>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize admin
if (is_admin()) {
    new ALCS_Admin();
}