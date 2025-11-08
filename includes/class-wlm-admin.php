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
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Lieferzeiten Manager', 'woo-lieferzeiten-manager'),
            __('Lieferzeiten', 'woo-lieferzeiten-manager'),
            'manage_woocommerce',
            'wlm-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wlm-settings') === false && $hook !== 'post.php' && $hook !== 'post-new.php') {
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

        wp_localize_script('wlm-admin', 'wlm_admin_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wlm-admin-nonce')
        ));
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wlm_settings_group', 'wlm_settings');
        register_setting('wlm_settings_group', 'wlm_shipping_methods', array(
            'sanitize_callback' => array($this, 'sanitize_shipping_methods')
        ));
        register_setting('wlm_settings_group', 'wlm_surcharges');
    }
    
    /**
     * Sanitize shipping methods before saving
     *
     * @param array $input Raw input data.
     * @return array Sanitized data.
     */
    public function sanitize_shipping_methods($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $sanitized = array();
        
        foreach ($input as $index => $method) {
            if (!is_array($method)) {
                continue;
            }
            
            // Convert attribute_conditions array to string format for backward compatibility
            if (isset($method['attribute_conditions']) && is_array($method['attribute_conditions'])) {
                $conditions_string = '';
                foreach ($method['attribute_conditions'] as $condition) {
                    if (!empty($condition['attribute']) && isset($condition['value'])) {
                        $conditions_string .= sanitize_text_field($condition['attribute']) . '=' . sanitize_text_field($condition['value']) . "\n";
                    }
                }
                $method['required_attributes'] = trim($conditions_string);
                unset($method['attribute_conditions']);
            }
            
            // Sanitize all fields
            $sanitized[$index] = array(
                'id' => sanitize_text_field($method['id'] ?? ''),
                'name' => sanitize_text_field($method['name'] ?? ''),
                'enabled' => !empty($method['enabled']),
                'priority' => intval($method['priority'] ?? 10),
                'cost_type' => sanitize_text_field($method['cost_type'] ?? 'flat'),
                'cost' => floatval($method['cost'] ?? 0),
                'weight_min' => !empty($method['weight_min']) ? floatval($method['weight_min']) : '',
                'weight_max' => !empty($method['weight_max']) ? floatval($method['weight_max']) : '',
                'cart_total_min' => !empty($method['cart_total_min']) ? floatval($method['cart_total_min']) : '',
                'cart_total_max' => !empty($method['cart_total_max']) ? floatval($method['cart_total_max']) : '',
                'required_attributes' => sanitize_textarea_field($method['required_attributes'] ?? ''),
                'required_categories' => isset($method['required_categories']) && is_array($method['required_categories']) 
                    ? array_map('intval', $method['required_categories']) 
                    : array(),
                'transit_min' => intval($method['transit_min'] ?? 1),
                'transit_max' => intval($method['transit_max'] ?? 3),
                'express_enabled' => !empty($method['express_enabled']),
                'express_cost' => floatval($method['express_cost'] ?? 0),
                'express_cutoff' => sanitize_text_field($method['express_cutoff'] ?? '14:00'),
                'express_transit_min' => intval($method['express_transit_min'] ?? 0),
                'express_transit_max' => intval($method['express_transit_max'] ?? 1),
            );
        }
        
        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'times';
        
        ?>
        <div class="wrap wlm-settings-wrap">
            <h1><?php esc_html_e('Woo Lieferzeiten Manager', 'woo-lieferzeiten-manager'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=wlm-settings&tab=times" class="nav-tab <?php echo $active_tab === 'times' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Zeiten', 'woo-lieferzeiten-manager'); ?>
                </a>
                <a href="?page=wlm-settings&tab=shipping" class="nav-tab <?php echo $active_tab === 'shipping' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Versandarten', 'woo-lieferzeiten-manager'); ?>
                </a>
                <a href="?page=wlm-settings&tab=surcharges" class="nav-tab <?php echo $active_tab === 'surcharges' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Zuschläge', 'woo-lieferzeiten-manager'); ?>
                </a>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields('wlm_settings_group');
                
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
                
                submit_button();
                ?>
            </form>
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
}
