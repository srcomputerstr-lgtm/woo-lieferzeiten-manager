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
