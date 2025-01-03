<?php
class ALCS_Country_Handler {
    private static $supported_countries = array(
        'AE' => array('name' => 'UAE', 'currency' => 'AED'),
        'SA' => array('name' => 'Saudi Arabia', 'currency' => 'SAR'),
        'QA' => array('name' => 'Qatar', 'currency' => 'QAR'),
        'KW' => array('name' => 'Kuwait', 'currency' => 'KWD'),
        'OM' => array('name' => 'Oman', 'currency' => 'OMR')
    );

    public static function get_current_country() {
        return isset($_COOKIE['selected_country']) ? 
               sanitize_text_field($_COOKIE['selected_country']) : 'AE';
    }

    public static function render_switcher($atts) {
        // Check if WooCommerce Price Based on Country is active
        if (!class_exists('WCPBC_Pricing_Zones')) {
            return 'WooCommerce Price Based on Country plugin is required.';
        }

        $current_country = self::get_current_country();
        $pricing_zones = WCPBC_Pricing_Zones::get_zones();
        
        ob_start();
        ?>
        <div class="alcs-country-switcher">
            <select class="alcs-select" data-type="country" onchange="switchCountry(this.value)">
                <?php foreach ($pricing_zones as $zone) : 
                    $countries = $zone->get_countries();
                    foreach ($countries as $country_code) :
                        if (isset(self::$supported_countries[$country_code])) :
                    ?>
                        <option value="<?php echo esc_attr($country_code); ?>" 
                                <?php selected($current_country, $country_code); ?>>
                            <?php echo esc_html(self::$supported_countries[$country_code]['name']); ?>
                        </option>
                    <?php 
                        endif;
                    endforeach;
                endforeach; ?>
            </select>
        </div>
        <script>
        function switchCountry(country) {
            document.cookie = "selected_country=" + country + ";path=/;max-age=31536000";
            jQuery.ajax({
                url: alcsData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'alcs_switch_country',
                    country: country,
                    nonce: alcsData.nonce
                },
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    }
                }
            });
        }
        </script>
        <?php
        return ob_get_clean();
    }
}

// Add AJAX handler for country switching
add_action('wp_ajax_alcs_switch_country', 'alcs_switch_country_callback');
add_action('wp_ajax_nopriv_alcs_switch_country', 'alcs_switch_country_callback');

function alcs_switch_country_callback() {
    check_ajax_referer('alcs-nonce', 'nonce');
    
    $country = sanitize_text_field($_POST['country']);
    
    // Update WooCommerce Price Based on Country session
    if (class_exists('WCPBC_Pricing_Zones')) {
        $pricing_zones = WCPBC_Pricing_Zones::get_zones();
        foreach ($pricing_zones as $zone) {
            if (in_array($country, $zone->get_countries())) {
                // Set the pricing zone
                update_option('wcpbc_current_zone', $zone->get_id());
                WC()->session->set('wcpbc_customer_country', $country);
                break;
            }
        }
    }
    
    wp_send_json_success();
}