<?php
/**
 * Admin class for backend settings
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Admin {
    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX handler for saving settings
        add_action('wp_ajax_wlm_save_settings', array($this, 'ajax_save_settings'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Add as tab in WooCommerce Shipping settings
        add_filter('woocommerce_get_sections_shipping', array($this, 'add_shipping_section'));
        add_filter('woocommerce_get_settings_shipping', array($this, 'get_shipping_settings'), 10, 2);
        add_action('woocommerce_settings_shipping', array($this, 'render_shipping_section'));
        add_action('woocommerce_settings_save_shipping', array($this, 'save_shipping_section'));
    }
    
    /**
     * Add MEGA Versandmanager section to shipping settings
     */
    public function add_shipping_section($sections) {
        $sections['wlm'] = __('MEGA Versandmanager', 'woo-lieferzeiten-manager');
        return $sections;
    }
    
    /**
     * Get settings for MEGA Versandmanager section
     */
    public function get_shipping_settings($settings, $current_section) {
        if ($current_section === 'wlm') {
            // Return a dummy field so WooCommerce knows this section has settings
            // This triggers the save_shipping_section hook
            // The actual rendering is done in render_shipping_section()
            return array(
                array(
                    'type' => 'title',
                    'id' => 'wlm_dummy_title',
                ),
                array(
                    'type' => 'sectionend',
                    'id' => 'wlm_dummy_end',
                ),
            );
        }
        return $settings;
    }
    
    /**
     * Render MEGA Versandmanager section
     */
    public function render_shipping_section() {
        global $current_section;
        
        if ($current_section === 'wlm') {
            $this->render_settings_page();
        }
    }
    
    /**
     * Save MEGA Versandmanager section
     * This is called by WooCommerce when the settings are saved
     */
    public function save_shipping_section() {
        global $current_section;
        
        if ($current_section === 'wlm') {
            // Check nonce - WooCommerce uses its own nonce
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'woocommerce-settings')) {
                return;
            }
            
            // Save settings
            if (isset($_POST['wlm_settings'])) {
                update_option('wlm_settings', $_POST['wlm_settings']);
            }
            if (isset($_POST['wlm_shipping_methods'])) {
                update_option('wlm_shipping_methods', $_POST['wlm_shipping_methods']);
                
                // Update zones after saving shipping methods
                $this->update_zones_after_save();
            }
            if (isset($_POST['wlm_surcharges'])) {
                update_option('wlm_surcharges', $_POST['wlm_surcharges']);
            }
            
            // Force shipping methods to re-register
            do_action('woocommerce_load_shipping_methods');
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts($hook) {
        // Load on WooCommerce settings page (shipping section)
        $is_wc_settings = ($hook === 'woocommerce_page_wc-settings' && isset($_GET['tab']) && $_GET['tab'] === 'shipping');
        $is_product_page = ($hook === 'post.php' || $hook === 'post-new.php');
        
        if (!$is_wc_settings && !$is_product_page) {
            return;
        }

        wp_enqueue_style(
            'wlm-admin',
            WLM_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            WLM_VERSION
        );

        wp_enqueue_script(
            'wlm-admin',
            WLM_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'jquery-ui-datepicker', 'jquery-ui-sortable'),
            WLM_VERSION,
            true
        );

        // Get all product attributes
        $attributes = array();
        if (function_exists('wc_get_attribute_taxonomies')) {
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            foreach ($attribute_taxonomies as $tax) {
                $attr_name = wc_attribute_taxonomy_name($tax->attribute_name);
                $attributes[$attr_name] = $tax->attribute_label;
            }
        }
        
        wp_localize_script('wlm-admin', 'wlm_admin_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wlm-admin-nonce'),
            'attributes' => $attributes
        ));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wlm_settings_group', 'wlm_settings');
        register_setting('wlm_settings_group', 'wlm_shipping_methods');
        register_setting('wlm_settings_group', 'wlm_surcharges');
    }
    

    /**
     * AJAX handler to get attribute values
     */
    public function ajax_get_attribute_values() {
        check_ajax_referer('wlm-admin-nonce', 'nonce');
        
        $attribute = isset($_POST['attribute']) ? sanitize_text_field($_POST['attribute']) : '';
        
        if (empty($attribute)) {
            wp_send_json_error('No attribute specified');
        }
        
        $values = array();
        
        // Check if it's a product attribute
        if (strpos($attribute, 'pa_') === 0) {
            $terms = get_terms(array(
                'taxonomy' => $attribute,
                'hide_empty' => false,
            ));
            
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $values[] = array(
                        'value' => $term->slug,
                        'label' => $term->name
                    );
                }
            }
        }
        // Product categories
        elseif ($attribute === 'product_cat') {
            $terms = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
            ));
            
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $values[] = array(
                        'value' => $term->slug,
                        'label' => $term->name
                    );
                }
            }
        }
        // Product tags
        elseif ($attribute === 'product_tag') {
            $terms = get_terms(array(
                'taxonomy' => 'product_tag',
                'hide_empty' => false,
            ));
            
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $values[] = array(
                        'value' => $term->slug,
                        'label' => $term->name
                    );
                }
            }
        }
        
        wp_send_json_success($values);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        global $current_section;
        
        // Determine if we're in WooCommerce settings or standalone page
        $is_wc_settings = isset($_GET['page']) && $_GET['page'] === 'wc-settings';
        
        // Get active tab (use wlm_tab for WooCommerce settings, tab for standalone)
        if ($is_wc_settings && isset($_GET['wlm_tab'])) {
            $active_tab = sanitize_text_field($_GET['wlm_tab']);
        } elseif (isset($_GET['tab']) && $_GET['tab'] !== 'shipping') {
            $active_tab = sanitize_text_field($_GET['tab']);
        } else {
            $active_tab = 'times';
        }
        
        ?>
        <div class="wrap wlm-settings-wrap">
            <?php if (!$is_wc_settings): ?>
            <h1><?php esc_html_e('Woo Lieferzeiten Manager', 'woo-lieferzeiten-manager'); ?></h1>
            <?php endif; ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo $is_wc_settings ? 'admin.php?page=wc-settings&tab=shipping&section=wlm&wlm_tab=times' : '?page=wlm-settings&tab=times'; ?>" class="nav-tab <?php echo $active_tab === 'times' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Zeiten', 'woo-lieferzeiten-manager'); ?>
                </a>
                <a href="<?php echo $is_wc_settings ? 'admin.php?page=wc-settings&tab=shipping&section=wlm&wlm_tab=shipping' : '?page=wlm-settings&tab=shipping'; ?>" class="nav-tab <?php echo $active_tab === 'shipping' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Versandarten', 'woo-lieferzeiten-manager'); ?>
                </a>
                <a href="<?php echo $is_wc_settings ? 'admin.php?page=wc-settings&tab=shipping&section=wlm&wlm_tab=surcharges' : '?page=wlm-settings&tab=surcharges'; ?>" class="nav-tab <?php echo $active_tab === 'surcharges' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Zuschläge', 'woo-lieferzeiten-manager'); ?>
                </a>
            </h2>

            <?php if (!$is_wc_settings): ?>
            <form method="post" action="options.php">
            <?php 
                settings_fields('wlm_settings_group');
            endif;
            
            switch ($active_tab) {
                case 'times':
                    $this->render_times_tab();
                    break;
                case 'shipping':
                    $this->render_shipping_tab();
                    break;
                case 'surcharges':
                    $this->render_surcharges_tab();
                    break;
            }
            
            // Show submit button
            echo '<p class="submit">';
            echo '<button type="button" id="wlm-save-settings" class="button-primary">' . esc_html__('Änderungen speichern', 'woo-lieferzeiten-manager') . '</button>';
            echo '<span class="wlm-save-spinner" style="display:none; margin-left: 10px;">Speichern...</span>';
            echo '</p>';
            
            if (!$is_wc_settings):
            ?>
            </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render times tab
     */
    private function render_times_tab() {
        $settings = get_option('wlm_settings', array());
        require_once WLM_PLUGIN_DIR . 'admin/views/tab-times.php';
    }

    /**
     * Render shipping tab
     */
    private function render_shipping_tab() {
        $shipping_methods = get_option('wlm_shipping_methods', array());
        require_once WLM_PLUGIN_DIR . 'admin/views/tab-shipping.php';
    }

    /**
     * Render surcharges tab
     */
    private function render_surcharges_tab() {
        $surcharges = get_option('wlm_surcharges', array());
        require_once WLM_PLUGIN_DIR . 'admin/views/tab-surcharges.php';
    }

    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        // DEBUG
        error_log('=== WLM AJAX SAVE START ===');
        error_log('$_POST keys: ' . print_r(array_keys($_POST), true));
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wlm-admin-nonce')) {
            error_log('AJAX SAVE: Nonce verification failed');
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            error_log('AJAX SAVE: Permission check failed');
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get data (JSON string from JavaScript)
        $data_json = isset($_POST['data']) ? $_POST['data'] : '';
        error_log('Data JSON received: ' . $data_json);
        
        $data = json_decode(stripslashes($data_json), true);
        if (!$data) {
            $data = array();
        }
        error_log('Data decoded: ' . print_r($data, true));
        
        // Save settings
        if (isset($data['wlm_settings'])) {
            error_log('Saving wlm_settings: ' . print_r($data['wlm_settings'], true));
            update_option('wlm_settings', $data['wlm_settings']);
            error_log('After save, wlm_settings from DB: ' . print_r(get_option('wlm_settings'), true));
        }
        
        // Save shipping methods
        if (isset($data['wlm_shipping_methods'])) {
            // Normalize data: Fix attribute_conditions if it's in wrong format
            foreach ($data['wlm_shipping_methods'] as &$method) {
                // Check if attribute_conditions has wrong format
                // Wrong: ["attribute_conditions[0][attribute]"] => "value"
                // Right: ["attribute_conditions"] => [["attribute" => "value"]]
                $conditions = array();
                foreach ($method as $key => $value) {
                    // Match pattern: attribute_conditions[0][attribute] or attribute_conditions[0][value]
                    if (preg_match('/^attribute_conditions\[(\d+)\]\[(\w+)\]$/', $key, $matches)) {
                        $index = (int)$matches[1];
                        $field = $matches[2];
                        
                        if (!isset($conditions[$index])) {
                            $conditions[$index] = array();
                        }
                        $conditions[$index][$field] = $value;
                        
                        // Remove the wrong key
                        unset($method[$key]);
                    }
                }
                
                // If we found conditions, add them in correct format
                if (!empty($conditions)) {
                    $method['attribute_conditions'] = array_values($conditions);
                }
            }
            unset($method); // Break reference
            
            error_log('Saving wlm_shipping_methods (normalized): ' . print_r($data['wlm_shipping_methods'], true));
            update_option('wlm_shipping_methods', $data['wlm_shipping_methods']);
            error_log('After save, wlm_shipping_methods from DB: ' . print_r(get_option('wlm_shipping_methods'), true));
        }
        
        // Save surcharges
        if (isset($data['wlm_surcharges'])) {
            error_log('Saving wlm_surcharges: ' . print_r($data['wlm_surcharges'], true));
            update_option('wlm_surcharges', $data['wlm_surcharges']);
            error_log('After save, wlm_surcharges from DB: ' . print_r(get_option('wlm_surcharges'), true));
        }
        
        // Force shipping methods to re-register
        do_action('woocommerce_load_shipping_methods');
        
        error_log('=== WLM AJAX SAVE END ===');
        wp_send_json_success('Settings saved');
    }
    
    /**
     * Update shipping zones after saving methods
     * Ensures all configured methods are added to all zones
     */
    private function update_zones_after_save() {
        // Get configured methods
        $configured_methods = get_option('wlm_shipping_methods', array());
        
        if (empty($configured_methods)) {
            return;
        }
        
        // Get all shipping zones
        $zones = WC_Shipping_Zones::get_zones();
        
        // Add "Rest of the World" zone
        $zones[] = array('id' => 0);
        
        foreach ($zones as $zone_data) {
            $zone_id = $zone_data['id'] ?? $zone_data['zone_id'] ?? 0;
            $zone = WC_Shipping_Zones::get_zone($zone_id);
            
            if (!$zone) {
                continue;
            }
            
            // Get existing shipping methods in this zone
            $existing_methods = $zone->get_shipping_methods();
            $existing_method_ids = array();
            
            foreach ($existing_methods as $method) {
                $existing_method_ids[] = $method->id;
            }
            
            // Add our methods if not already present
            foreach ($configured_methods as $method_config) {
                if (empty($method_config['enabled'])) {
                    continue;
                }
                
                $method_id = $method_config['id'];
                
                // Skip if already in zone
                if (in_array($method_id, $existing_method_ids)) {
                    continue;
                }
                
                // Add method to zone
                $zone->add_shipping_method($method_id);
                
                error_log('WLM: Added method ' . $method_id . ' to zone ' . $zone_id);
            }
        }
    }
}
