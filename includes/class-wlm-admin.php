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
        
        // AJAX handler for running cronjob manually
        add_action('wp_ajax_wlm_run_cronjob', array($this, 'ajax_run_cronjob'));
        
        // AJAX handler for sending test notification
        add_action('wp_ajax_wlm_send_test_notification', array($this, 'ajax_send_test_notification'));
        
        // AJAX handler for sending test performance report
        add_action('wp_ajax_wlm_send_test_performance_report', array($this, 'ajax_send_test_performance_report'));
        
        // AJAX handler for getting shipping classes
        add_action('wp_ajax_wlm_get_shipping_classes', array($this, 'ajax_get_shipping_classes'));
        
        // AJAX handler for export
        add_action('wp_ajax_wlm_export_settings', array($this, 'ajax_export_settings'));
        
        // AJAX handler for import
        add_action('wp_ajax_wlm_import_settings', array($this, 'ajax_import_settings'));
        
        // AJAX handler for getting Germanized providers
        add_action('wp_ajax_wlm_get_germanized_providers', array($this, 'ajax_get_germanized_providers'));
        
        // Duplicate functionality removed
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
                error_log('[WLM Save] ========== SAVING SHIPPING METHODS ==========');
                error_log('[WLM Save] Total methods in POST: ' . count($_POST['wlm_shipping_methods']));
                
                foreach ($_POST['wlm_shipping_methods'] as $index => $method) {
                    error_log('[WLM Save] Method ' . $index . ': ' . ($method['name'] ?? 'N/A'));
                    if (isset($method['attribute_conditions'])) {
                        error_log('[WLM Save]   - Conditions count: ' . count($method['attribute_conditions']));
                        error_log('[WLM Save]   - Conditions: ' . print_r($method['attribute_conditions'], true));
                    } else {
                        error_log('[WLM Save]   - No conditions');
                    }
                }
                
                update_option('wlm_shipping_methods', $_POST['wlm_shipping_methods']);
                
                error_log('[WLM Save] Saved to database');
                
                // Verify what was saved
                $saved_methods = get_option('wlm_shipping_methods');
                error_log('[WLM Save] Verification - Total methods in DB: ' . count($saved_methods));
                foreach ($saved_methods as $index => $method) {
                    error_log('[WLM Save] DB Method ' . $index . ': ' . ($method['name'] ?? 'N/A'));
                    if (isset($method['attribute_conditions'])) {
                        error_log('[WLM Save]   - Conditions count: ' . count($method['attribute_conditions']));
                    } else {
                        error_log('[WLM Save]   - No conditions');
                    }
                }
                
                // Update zones after saving shipping methods
                $this->update_zones_after_save();
            }
            if (isset($_POST['wlm_surcharges'])) {
                update_option('wlm_surcharges', $_POST['wlm_surcharges']);
            }
            if (isset($_POST['wlm_surcharge_application_strategy'])) {
                update_option('wlm_surcharge_application_strategy', sanitize_text_field($_POST['wlm_surcharge_application_strategy']));
            }
            if (isset($_POST['wlm_shipping_selection_strategy'])) {
                $strategy_value = sanitize_text_field($_POST['wlm_shipping_selection_strategy']);
                WLM_Core::log('[WLM Admin] Saving shipping_selection_strategy: ' . $strategy_value);
                update_option('wlm_shipping_selection_strategy', $strategy_value);
                WLM_Core::log('[WLM Admin] After save, value from DB: ' . get_option('wlm_shipping_selection_strategy'));
            } else {
                WLM_Core::log('[WLM Admin] wlm_shipping_selection_strategy NOT in POST!');
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

        // Enqueue Select2 (WooCommerce includes it)
        wp_enqueue_style('select2');
        wp_enqueue_script('select2');
        
        wp_enqueue_script(
            'wlm-admin',
            WLM_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'jquery-ui-datepicker', 'jquery-ui-sortable', 'select2'),
            WLM_VERSION,
            true
        );
        
        // Enqueue export/import script
        wp_enqueue_script(
            'wlm-export-import',
            WLM_PLUGIN_URL . 'admin/js/export-import.js',
            array('jquery', 'wlm-admin'),
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
        
        // Get shipping classes
        $shipping_classes = array();
        $terms = get_terms(array(
            'taxonomy' => 'product_shipping_class',
            'hide_empty' => false
        ));
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $shipping_classes[$term->slug] = $term->name;
            }
        }
        
        wp_localize_script('wlm-admin', 'wlmAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wlm-admin-nonce'),
            'attributes' => $attributes,
            'shippingClasses' => $shipping_classes,
            'i18n' => array(
                'running' => __('Wird ausgeführt...', 'woo-lieferzeiten-manager'),
                'runNow' => __('Jetzt ausführen', 'woo-lieferzeiten-manager')
            )
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
                <a href="<?php echo $is_wc_settings ? 'admin.php?page=wc-settings&tab=shipping&section=wlm&wlm_tab=notifications' : '?page=wlm-settings&tab=notifications'; ?>" class="nav-tab <?php echo $active_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Benachrichtigungen', 'woo-lieferzeiten-manager'); ?>
                </a>
                <a href="<?php echo $is_wc_settings ? 'admin.php?page=wc-settings&tab=shipping&section=wlm&wlm_tab=export-import' : '?page=wlm-settings&tab=export-import'; ?>" class="nav-tab <?php echo $active_tab === 'export-import' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Export / Import', 'woo-lieferzeiten-manager'); ?>
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
                case 'notifications':
                    $this->render_notifications_tab();
                    break;
                case 'export-import':
                    $this->render_export_import_tab();
                    break;
            }
            
            // Show submit button (normal form submit, not AJAX)
            echo '<p class="submit">';
            if ($is_wc_settings) {
                // In WooCommerce settings, use WooCommerce's submit button
                submit_button(__('Änderungen speichern', 'woo-lieferzeiten-manager'), 'primary', 'save', false);
            } else {
                // In standalone page, use normal submit
                submit_button(__('Änderungen speichern', 'woo-lieferzeiten-manager'));
            }
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
        $shipping_selection_strategy = get_option('wlm_shipping_selection_strategy', 'customer_choice');
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        require_once WLM_PLUGIN_DIR . 'admin/views/tab-shipping.php';
    }

    /**
     * Render surcharges tab
     */
    private function render_surcharges_tab() {
        $surcharges = get_option('wlm_surcharges', array());
        $surcharge_application_strategy = get_option('wlm_surcharge_application_strategy', 'all_charges');
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        require_once WLM_PLUGIN_DIR . 'admin/views/tab-surcharges.php';
    }

    /**
     * Render export/import tab
     */
    private function render_export_import_tab() {
        require_once WLM_PLUGIN_DIR . 'admin/views/tab-export-import.php';
    }

    /**
     * Render notifications tab
     */
    private function render_notifications_tab() {
        require_once WLM_PLUGIN_DIR . 'admin/views/tab-notifications.php';
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
        
        // Get active section from data
        $active_section = isset($data['_active_section']) ? $data['_active_section'] : 'all';
        error_log('[WLM Save] Active section: ' . $active_section);
        
        // Save settings (MERGE with existing to prevent data loss)
        if (isset($data['wlm_settings'])) {
            error_log('Saving wlm_settings: ' . print_r($data['wlm_settings'], true));
            $existing_settings = get_option('wlm_settings', array());
            $merged_settings = array_merge($existing_settings, $data['wlm_settings']);
            update_option('wlm_settings', $merged_settings);
            error_log('After save, wlm_settings from DB: ' . print_r(get_option('wlm_settings'), true));
        }
        
        // Save shipping selection strategy
        if (isset($data['wlm_shipping_selection_strategy'])) {
            update_option('wlm_shipping_selection_strategy', sanitize_text_field($data['wlm_shipping_selection_strategy']));
        }
        
        // Save shipping methods (ONLY when on shipping tab to prevent data loss)
        if ($active_section === 'shipping' || $active_section === 'wlm') {
            if (isset($data['wlm_shipping_methods'])) {
            // Normalize data: Convert flat keys to nested arrays
            foreach ($data['wlm_shipping_methods'] as $method_index => &$method) {
                // Fix flat keys like "attribute_conditions][0][logic" to nested structure
                $conditions = array();
                $keys_to_remove = array();
                
                foreach ($method as $key => $value) {
                    // Match: attribute_conditions][INDEX][FIELD] or attribute_conditions][INDEX][FIELD][]
                    if (preg_match('/^attribute_conditions\]\[(\d+)\]\[([^\]]+)\](\[\])?$/', $key, $matches)) {
                        $index = (int)$matches[1];
                        $field = $matches[2];
                        $is_array = isset($matches[3]) && $matches[3] === '[]';
                        
                        if (!isset($conditions[$index])) {
                            $conditions[$index] = array(
                                'logic' => 'at_least_one',
                                'attribute' => '',
                                'values' => array()
                            );
                        }
                        
                        if ($is_array) {
                            // It's an array field like values[]
                            $conditions[$index][$field] = is_array($value) ? $value : array($value);
                        } else {
                            $conditions[$index][$field] = $value;
                        }
                        
                        $keys_to_remove[] = $key;
                    }
                    // Also fix required_categories][] format
                    elseif ($key === 'required_categories][]' || $key === 'required_categories][') {
                        $method['required_categories'] = is_array($value) ? $value : array();
                        $keys_to_remove[] = $key;
                    }
                }
                
                // Remove flat keys
                foreach ($keys_to_remove as $key) {
                    unset($method[$key]);
                }
                
                // Add normalized conditions if found
                if (!empty($conditions)) {
                    $method['attribute_conditions'] = array_values($conditions);
                    WLM_Core::log('WLM: Normalized flat keys to attribute_conditions for method ' . $method_index . ': ' . print_r($method['attribute_conditions'], true));
                }
                
                // Validate attribute_conditions structure
                if (isset($method['attribute_conditions']) && is_array($method['attribute_conditions'])) {
                    error_log('[WLM Validate] Method ' . $method_index . ' BEFORE validation: ' . print_r($method['attribute_conditions'], true));
                    
                    // Clean up conditions but preserve valid ones
                    foreach ($method['attribute_conditions'] as $cond_index => &$cond) {
                        // Ensure logic field exists
                        if (!isset($cond['logic'])) {
                            $cond['logic'] = 'at_least_one';
                        }
                        
                        // Ensure type field exists
                        if (!isset($cond['type'])) {
                            $cond['type'] = 'attribute';
                        }
                        
                        // Ensure attribute field exists (even if empty)
                        if (!isset($cond['attribute'])) {
                            $cond['attribute'] = '';
                        }
                        
                        // Ensure values is an array
                        if (!isset($cond['values'])) {
                            $cond['values'] = array();
                        } elseif (!is_array($cond['values'])) {
                            $cond['values'] = array($cond['values']);
                        }
                        
                        // Filter out empty values
                        $cond['values'] = array_filter($cond['values'], function($val) {
                            return !empty($val) && $val !== '';
                        });
                        
                        // Re-index values array
                        $cond['values'] = array_values($cond['values']);
                    }
                    unset($cond);
                    
                    // Only remove conditions that are COMPLETELY empty (no attribute AND no values)
                    $method['attribute_conditions'] = array_filter($method['attribute_conditions'], function($cond) {
                        return !empty($cond['attribute']) || !empty($cond['values']);
                    });
                    
                    // Re-index array
                    $method['attribute_conditions'] = array_values($method['attribute_conditions']);
                    
                    error_log('[WLM Validate] Method ' . $method_index . ' AFTER validation: ' . print_r($method['attribute_conditions'], true));
                    WLM_Core::log('WLM: Validated attribute_conditions for method ' . $method_index . ': ' . print_r($method['attribute_conditions'], true));
                }
            }
            unset($method); // Break reference
            
            error_log('Saving wlm_shipping_methods (normalized): ' . print_r($data['wlm_shipping_methods'], true));
            update_option('wlm_shipping_methods', $data['wlm_shipping_methods']);
            error_log('After save, wlm_shipping_methods from DB: ' . print_r(get_option('wlm_shipping_methods'), true));
            } else {
                error_log('[WLM Save] No wlm_shipping_methods in data, keeping existing');
            }
        } else {
            error_log('[WLM Save] Not on shipping tab (' . $active_section . '), skipping wlm_shipping_methods save');
        }
        
        // Save surcharges (ONLY when on surcharges tab to prevent data loss)
        if ($active_section === 'surcharges') {
            if (isset($data['wlm_surcharges'])) {
            error_log('Saving wlm_surcharges: ' . print_r($data['wlm_surcharges'], true));
            
            // Normalize data: Convert flat keys to nested arrays (same as shipping methods)
            foreach ($data['wlm_surcharges'] as $surcharge_index => &$surcharge) {
                // Fix flat keys like "attribute_conditions][0][logic" to nested structure
                $conditions = array();
                $keys_to_remove = array();
                
                foreach ($surcharge as $key => $value) {
                    // Match: attribute_conditions][INDEX][FIELD] or attribute_conditions][INDEX][FIELD][]
                    if (preg_match('/^attribute_conditions\]\[(\d+)\]\[([^\]]+)\](\[\])?$/', $key, $matches)) {
                        $index = (int)$matches[1];
                        $field = $matches[2];
                        $is_array = isset($matches[3]) && $matches[3] === '[]';
                        
                        if (!isset($conditions[$index])) {
                            $conditions[$index] = array(
                                'logic' => 'at_least_one',
                                'type' => 'attribute',
                                'attribute' => '',
                                'values' => array()
                            );
                        }
                        
                        if ($is_array) {
                            // It's an array field like values[]
                            $conditions[$index][$field] = is_array($value) ? $value : array($value);
                        } else {
                            $conditions[$index][$field] = $value;
                        }
                        
                        $keys_to_remove[] = $key;
                    }
                }
                
                // Remove flat keys
                foreach ($keys_to_remove as $key) {
                    unset($surcharge[$key]);
                }
                
                // Add normalized conditions if found
                if (!empty($conditions)) {
                    $surcharge['attribute_conditions'] = array_values($conditions);
                    WLM_Core::log('WLM: Normalized flat keys to attribute_conditions for surcharge ' . $surcharge_index . ': ' . print_r($surcharge['attribute_conditions'], true));
                }
                
                // Validate attribute_conditions structure
                if (isset($surcharge['attribute_conditions']) && is_array($surcharge['attribute_conditions'])) {
                    // Filter out empty or invalid conditions
                    $surcharge['attribute_conditions'] = array_filter($surcharge['attribute_conditions'], function($cond) {
                        // For shipping class: only type and values needed
                        if (isset($cond['type']) && $cond['type'] === 'shipping_class') {
                            return !empty($cond['values']);
                        }
                        // For attribute/taxonomy: must have attribute and at least one value
                        return !empty($cond['attribute']) && !empty($cond['values']);
                    });
                    
                    // Reindex array
                    $surcharge['attribute_conditions'] = array_values($surcharge['attribute_conditions']);
                    
                    // Ensure values is always an array
                    foreach ($surcharge['attribute_conditions'] as &$cond) {
                        if (isset($cond['values']) && !is_array($cond['values'])) {
                            $cond['values'] = array($cond['values']);
                        }
                    }
                }
            }
            
            update_option('wlm_surcharges', $data['wlm_surcharges']);
            error_log('After save, wlm_surcharges from DB: ' . print_r(get_option('wlm_surcharges'), true));
            } else {
                error_log('[WLM Save] No wlm_surcharges in data, keeping existing');
            }
        } else {
            error_log('[WLM Save] Not on surcharges tab (' . $active_section . '), skipping wlm_surcharges save');
        }
        
        // Save performance report settings (always save, not tab-specific)
        if (isset($data['wlm_performance_report_enabled'])) {
            update_option('wlm_performance_report_enabled', (bool) $data['wlm_performance_report_enabled']);
            error_log('[WLM Save] Performance report enabled: ' . ($data['wlm_performance_report_enabled'] ? 'true' : 'false'));
        }
        
        if (isset($data['wlm_performance_report_email'])) {
            update_option('wlm_performance_report_email', sanitize_email($data['wlm_performance_report_email']));
            error_log('[WLM Save] Performance report email: ' . $data['wlm_performance_report_email']);
        }
        
        if (isset($data['wlm_performance_report_send_empty'])) {
            update_option('wlm_performance_report_send_empty', (bool) $data['wlm_performance_report_send_empty']);
            error_log('[WLM Save] Performance report send empty: ' . ($data['wlm_performance_report_send_empty'] ? 'true' : 'false'));
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
                    // Base method exists, but check if Express variant needs to be added
                    if (!empty($method_config['express_enabled'])) {
                        $express_method_id = $method_id . '_express';
                        if (!in_array($express_method_id, $existing_method_ids)) {
                            $zone->add_shipping_method($express_method_id);
                            WLM_Core::log('WLM: Added EXPRESS method ' . $express_method_id . ' to zone ' . $zone_id);
                        }
                    }
                    continue;
                }
                
                // Add base method to zone
                $zone->add_shipping_method($method_id);
                WLM_Core::log('WLM: Added method ' . $method_id . ' to zone ' . $zone_id);
                
                // If Express is enabled, also add Express variant
                if (!empty($method_config['express_enabled'])) {
                    $express_method_id = $method_id . '_express';
                    $zone->add_shipping_method($express_method_id);
                    WLM_Core::log('WLM: Added EXPRESS method ' . $express_method_id . ' to zone ' . $zone_id);
                }
            }
        }
    }
    
    /**
     * AJAX handler for sending test notification
     */
    public function ajax_send_test_notification() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wlm-admin-nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        WLM_Core::log('[WLM AJAX] Sending test notification...');
        
        // Get ship notifications instance
        $notifications = new WLM_Ship_Notifications();
        
        // Trigger manual notification
        $notifications->trigger_manual();
        
        wp_send_json_success(array(
            'message' => 'Test-E-Mail wurde erfolgreich versendet!'
        ));
    }
    
    /**
     * AJAX handler for sending test performance report
     */
    public function ajax_send_test_performance_report() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wlm-admin-nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        WLM_Core::log('[WLM AJAX] Sending test performance report...');
        
        // Get performance report instance
        $report = new WLM_Performance_Report();
        
        // Trigger manual report
        $result = $report->trigger_manual();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Test-Report wurde erfolgreich versendet!'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Fehler beim Senden des Test-Reports. Bitte Debug-Log prüfen.'
            ));
        }
    }
    
    /**
     * AJAX handler for running cronjob manually
     */
    public function ajax_run_cronjob() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wlm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Log start
        WLM_Core::log('[WLM AJAX] Running cronjob manually...');
        
        try {
            // Run the cronjob
            WLM_Core::instance()->update_product_availability();
            
            // Get updated stats
            $last_run = get_option('wlm_cronjob_last_run', 0);
            $last_count = get_option('wlm_cronjob_last_count', 0);
            
            WLM_Core::log('[WLM AJAX] Cronjob completed. Processed: ' . $last_count . ' products');
            
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Cronjob erfolgreich ausgeführt! %d Produkte verarbeitet.', 'woo-lieferzeiten-manager'),
                    $last_count
                ),
                'last_run' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_run),
                'count' => $last_count
            ));
        } catch (Exception $e) {
            WLM_Core::log('[WLM AJAX] Cronjob error: ' . $e->getMessage());
            wp_send_json_error('Fehler beim Ausführen des Cronjobs: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting shipping classes
     */
    public function ajax_get_shipping_classes() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'wlm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get shipping classes
        $shipping_classes = WC()->shipping()->get_shipping_classes();
        $result = array();
        
        foreach ($shipping_classes as $class) {
            $result[] = array(
                'value' => $class->slug,
                'label' => $class->name
            );
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler for export settings
     */
    public function ajax_export_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wlm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $export_settings = isset($_POST['export_settings']) && $_POST['export_settings'] === '1';
        $export_shipping = isset($_POST['export_shipping_methods']) && $_POST['export_shipping_methods'] === '1';
        $export_surcharges = isset($_POST['export_surcharges']) && $_POST['export_surcharges'] === '1';
        
        $export_data = array(
            'version' => WLM_VERSION,
            'exported_at' => current_time('mysql'),
            'site_url' => get_site_url(),
        );
        
        // Export general settings
        if ($export_settings) {
            $settings = get_option('wlm_settings', array());
            $export_data['settings'] = $settings;
        }
        
        // Export shipping methods
        if ($export_shipping) {
            $shipping_methods = get_option('wlm_shipping_methods', array());
            $export_data['shipping_methods'] = $shipping_methods;
        }
        
        // Export surcharges
        if ($export_surcharges) {
            $surcharges = get_option('wlm_surcharges', array());
            $surcharge_strategy = get_option('wlm_surcharge_application_strategy', 'all_charges');
            $export_data['surcharges'] = $surcharges;
            $export_data['surcharge_application_strategy'] = $surcharge_strategy;
        }
        
        wp_send_json_success(array(
            'data' => $export_data,
            'filename' => 'wlm-settings-' . date('Y-m-d-His') . '.json'
        ));
    }
    
    /**
     * AJAX handler for import settings
     */
    public function ajax_import_settings() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wlm-admin-nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        if (!isset($_POST['data'])) {
            wp_send_json_error('No data provided');
            return;
        }
        
        $import_data = json_decode(stripslashes($_POST['data']), true);
        
        if (!$import_data || !is_array($import_data)) {
            wp_send_json_error('Invalid JSON data');
            return;
        }
        
        // Validate version
        if (!isset($import_data['version'])) {
            wp_send_json_error('Invalid export file: missing version');
            return;
        }
        
        $import_settings = isset($_POST['import_settings']) && $_POST['import_settings'] === '1';
        $import_shipping = isset($_POST['import_shipping_methods']) && $_POST['import_shipping_methods'] === '1';
        $import_surcharges = isset($_POST['import_surcharges']) && $_POST['import_surcharges'] === '1';
        
        $imported = array();
        
        // Import general settings
        if ($import_settings && isset($import_data['settings'])) {
            update_option('wlm_settings', $import_data['settings']);
            $imported[] = 'settings';
        }
        
        // Import shipping methods
        if ($import_shipping && isset($import_data['shipping_methods'])) {
            update_option('wlm_shipping_methods', $import_data['shipping_methods']);
            $imported[] = 'shipping_methods';
        }
        
        // Import surcharges
        if ($import_surcharges && isset($import_data['surcharges'])) {
            update_option('wlm_surcharges', $import_data['surcharges']);
            if (isset($import_data['surcharge_application_strategy'])) {
                update_option('wlm_surcharge_application_strategy', $import_data['surcharge_application_strategy']);
            }
            $imported[] = 'surcharges';
        }
        
        if (empty($imported)) {
            wp_send_json_error('Nothing to import');
            return;
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('Import erfolgreich! Importiert: %s', 'woo-lieferzeiten-manager'),
                implode(', ', $imported)
            ),
            'imported' => $imported
        ));
    }
    
    /**
     * AJAX handler to get Germanized/Shiptastic providers
     */
    public function ajax_get_germanized_providers() {
        check_ajax_referer('wlm-admin-nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        global $wpdb;
        $provider_list = array();
        
        // Get all tables in database
        $all_tables = $wpdb->get_col("SHOW TABLES");
        
        // Find Germanized shipping provider table
        $gzd_table = null;
        foreach ($all_tables as $table) {
            if (stripos($table, 'shipping_provider') !== false) {
                $gzd_table = $table;
                break;
            }
        }
        
        if ($gzd_table) {
            // Get all columns from the table
            $columns = $wpdb->get_results("SHOW COLUMNS FROM {$gzd_table}");
            $column_names = array();
            foreach ($columns as $col) {
                $column_names[] = $col->Field;
            }
            
            // Get all providers
            $providers = $wpdb->get_results("SELECT * FROM {$gzd_table}");
            
            if (!empty($providers)) {
                foreach ($providers as $p) {
                    $slug = '';
                    $title = '';
                    
                    // Find slug column (try different names)
                    foreach (array('shipping_provider_name', 'name', 'slug', 'provider_name') as $field) {
                        if (isset($p->$field) && !empty($p->$field)) {
                            $slug = $p->$field;
                            break;
                        }
                    }
                    
                    // Find title column (try different names)
                    foreach (array('shipping_provider_title', 'title', 'label', 'provider_title') as $field) {
                        if (isset($p->$field) && !empty($p->$field)) {
                            $title = $p->$field;
                            break;
                        }
                    }
                    
                    // Use slug as title if title is empty
                    if (empty($title) && !empty($slug)) {
                        $title = ucfirst($slug);
                    }
                    
                    if (!empty($slug)) {
                        $provider_list[] = array(
                            'slug' => $slug,
                            'title' => $title
                        );
                    }
                }
            }
        }
        
        // If still empty, return error
        if (empty($provider_list)) {
            wp_send_json_success(array(
                'providers' => array(),
                'message' => 'Germanized/Shiptastic nicht installiert oder keine Provider gefunden'
            ));
            return;
        }
        
         wp_send_json_success(array('providers' => $provider_list));
    }
    
    // Duplicate functionality removed
}
