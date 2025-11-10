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
            // Return empty to prevent default settings rendering
            return array();
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
     */
    public function save_shipping_section() {
        global $current_section;
        
        if ($current_section === 'wlm' && isset($_POST['wlm_settings'])) {
            // Settings are saved via register_settings
            // Just trigger the save action
            if (isset($_POST['wlm_settings'])) {
                update_option('wlm_settings', $_POST['wlm_settings']);
            }
            if (isset($_POST['wlm_shipping_methods'])) {
                update_option('wlm_shipping_methods', $_POST['wlm_shipping_methods']);
            }
            if (isset($_POST['wlm_surcharges'])) {
                update_option('wlm_surcharges', $_POST['wlm_surcharges']);
            }
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
        
        wp_localize_script('wlm-admin', 'wlm_admin', array(
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
        
        // Handle form submission for WooCommerce settings
        if ($is_wc_settings && isset($_POST['save']) && $current_section === 'wlm') {
            check_admin_referer('wlm-settings');
            
            if (isset($_POST['wlm_settings'])) {
                update_option('wlm_settings', $_POST['wlm_settings']);
            }
            if (isset($_POST['wlm_shipping_methods'])) {
                update_option('wlm_shipping_methods', $_POST['wlm_shipping_methods']);
            }
            if (isset($_POST['wlm_surcharges'])) {
                update_option('wlm_surcharges', $_POST['wlm_surcharges']);
            }
            
            // Redirect to avoid resubmission
            wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=shipping&section=wlm&saved=1'));
            exit;
        }
        
        // Show success message
        if (isset($_GET['saved'])) {
            echo '<div class="updated"><p>' . esc_html__('Einstellungen gespeichert.', 'woo-lieferzeiten-manager') . '</p></div>';
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

            <form method="post" action="<?php echo $is_wc_settings ? '' : 'options.php'; ?>">
                <?php
                if ($is_wc_settings) {
                    wp_nonce_field('wlm-settings');
                } else {
                    settings_fields('wlm_settings_group');
                }
                
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
