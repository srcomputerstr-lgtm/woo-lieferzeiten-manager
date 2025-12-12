<?php
/**
 * Ship-By-Date Notifications
 *
 * Handles daily email notifications for orders that need to be shipped today.
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Ship_Notifications {
    
    /**
     * Cron hook name
     */
    const CRON_HOOK = 'wlm_daily_ship_notification';
    
    /**
     * Initialize
     */
    public function __construct() {
        // Schedule cron on activation
        add_action('init', array($this, 'schedule_cron'));
        
        // Hook the cron action
        add_action(self::CRON_HOOK, array($this, 'send_daily_notification'));
        
        // Add settings
        add_filter('wlm_settings_fields', array($this, 'add_settings_fields'));
    }
    
    /**
     * Schedule the cron job
     */
    public function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Get configured time (default: 08:00)
            $notification_time = get_option('wlm_ship_notification_time', '08:00');
            list($hour, $minute) = explode(':', $notification_time);
            
            // Schedule for today at configured time
            $timestamp = strtotime("today {$hour}:{$minute}");
            
            // If time has passed today, schedule for tomorrow
            if ($timestamp < current_time('timestamp')) {
                $timestamp = strtotime("tomorrow {$hour}:{$minute}");
            }
            
            wp_schedule_event($timestamp, 'daily', self::CRON_HOOK);
            
            WLM_Core::log('Scheduled ship notification cron for ' . date('Y-m-d H:i:s', $timestamp));
        }
    }
    
    /**
     * Reschedule cron when time setting changes
     */
    public function reschedule_cron() {
        // Clear existing schedule
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        
        // Reschedule
        $this->schedule_cron();
    }
    
    /**
     * Send daily notification email
     */
    public function send_daily_notification() {
        // Check if notifications are enabled
        if (!get_option('wlm_ship_notification_enabled', false)) {
            WLM_Core::log('Ship notifications are disabled, skipping');
            return false;
        }
        
        $today = date('Y-m-d');
        
        WLM_Core::log('Running daily ship notification for ' . $today);
        
        // Get orders that need to be shipped today
        $orders = $this->get_orders_to_ship_today($today);
        
        WLM_Core::log('Found ' . count($orders) . ' orders to ship today');
        
        if (empty($orders)) {
            WLM_Core::log('No orders to ship today');
            
            // Check if we should send email even when no orders
            if (!get_option('wlm_ship_notification_send_empty', false)) {
                WLM_Core::log('send_empty is disabled, not sending email');
                return false;
            }
        }
        
        // Send email
        return $this->send_notification_email($orders, $today);
    }
    
    /**
     * Get orders that need to be shipped today
     *
     * @param string $date Date in Y-m-d format
     * @return array Array of order objects with metadata
     */
    private function get_orders_to_ship_today($date) {
        $args = array(
            'limit' => -1,
            'status' => array('processing', 'on-hold'),
            'meta_query' => array(
                array(
                    'key' => '_wlm_ship_by_date',
                    'value' => $date,
                    'compare' => '<=',
                    'type' => 'DATE',
                ),
            ),
            'orderby' => 'meta_value',
            'meta_key' => '_wlm_ship_by_date',
            'order' => 'ASC',
        );
        
        $wc_orders = wc_get_orders($args);
        
        $orders = array();
        foreach ($wc_orders as $order) {
            $orders[] = array(
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'date' => $order->get_date_created()->date('d.m.Y H:i'),
                'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'total' => $order->get_total(),
                'status' => $order->get_status(),
                'ship_by' => $order->get_meta('_wlm_ship_by_date'),
                'earliest_delivery' => $order->get_meta('_wlm_earliest_delivery'),
                'latest_delivery' => $order->get_meta('_wlm_latest_delivery'),
                'delivery_window' => $order->get_meta('_wlm_delivery_window'),
                'shipping_method' => $order->get_meta('_wlm_shipping_method_name'),
                'items' => $this->get_order_items($order),
            );
        }
        
        return $orders;
    }
    
    /**
     * Get order items summary
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_items($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'name' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'quantity' => $item->get_quantity(),
            );
        }
        return $items;
    }
    
    /**
     * Send notification email
     *
     * @param array $orders Array of orders
     * @param string $date Date in Y-m-d format
     */
    private function send_notification_email($orders, $date) {
        $to = get_option('wlm_ship_notification_email', get_option('admin_email'));
        $subject = sprintf('Versandliste für %s - %d Bestellungen', 
            date_i18n('d.m.Y', strtotime($date)), 
            count($orders)
        );
        
        $message = $this->generate_email_html($orders, $date);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            WLM_Core::log('Ship notification email sent to ' . $to . ' with ' . count($orders) . ' orders');
        } else {
            WLM_Core::log('Failed to send ship notification email to ' . $to);
        }
        
        return $sent;
    }
    
    /**
     * Generate HTML email content
     *
     * @param array $orders Array of orders
     * @param string $date Date in Y-m-d format
     * @return string HTML content
     */
    private function generate_email_html($orders, $date) {
        $date_formatted = date_i18n('d.m.Y', strtotime($date));
        $order_count = count($orders);
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #f5f5f5;
                }
                .email-container {
                    background-color: #ffffff;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    overflow: hidden;
                }
                .email-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .email-header h1 {
                    margin: 0 0 10px 0;
                    font-size: 28px;
                }
                .email-header p {
                    margin: 0;
                    opacity: 0.9;
                    font-size: 16px;
                }
                .email-body {
                    padding: 30px;
                }
                .summary-box {
                    background-color: #f8f9fa;
                    border-left: 4px solid #667eea;
                    padding: 20px;
                    margin-bottom: 30px;
                    border-radius: 4px;
                }
                .summary-box h2 {
                    margin: 0 0 10px 0;
                    color: #667eea;
                    font-size: 20px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                    background-color: white;
                }
                thead {
                    background-color: #667eea;
                    color: white;
                }
                th {
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    font-size: 14px;
                }
                td {
                    padding: 12px;
                    border-bottom: 1px solid #e9ecef;
                    font-size: 14px;
                }
                tr:hover {
                    background-color: #f8f9fa;
                }
                .status-badge {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 12px;
                    font-size: 12px;
                    font-weight: 600;
                }
                .status-processing {
                    background-color: #fff3cd;
                    color: #856404;
                }
                .status-on-hold {
                    background-color: #f8d7da;
                    color: #721c24;
                }
                .order-link {
                    color: #667eea;
                    text-decoration: none;
                    font-weight: 600;
                }
                .order-link:hover {
                    text-decoration: underline;
                }
                .items-list {
                    font-size: 12px;
                    color: #6c757d;
                    margin: 4px 0 0 0;
                }
                .urgent {
                    background-color: #fff3cd !important;
                }
                .email-footer {
                    background-color: #f8f9fa;
                    padding: 20px 30px;
                    text-align: center;
                    color: #6c757d;
                    font-size: 12px;
                }
                .no-orders {
                    text-align: center;
                    padding: 40px;
                    color: #6c757d;
                }
                .no-orders svg {
                    width: 64px;
                    height: 64px;
                    margin-bottom: 20px;
                    opacity: 0.5;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <h1>📦 Versandliste</h1>
                    <p><?php echo esc_html($date_formatted); ?></p>
                </div>
                
                <div class="email-body">
                    <div class="summary-box">
                        <h2>Zusammenfassung</h2>
                        <p><strong><?php echo $order_count; ?></strong> Bestellung(en) müssen heute versendet werden</p>
                        <p style="margin: 5px 0 0 0; color: #6c757d; font-size: 14px;">
                            Ship-By-Date: <?php echo esc_html($date_formatted); ?>
                        </p>
                    </div>
                    
                    <?php if (empty($orders)): ?>
                        <div class="no-orders">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3>Keine Bestellungen für heute</h3>
                            <p>Es müssen heute keine Bestellungen versendet werden.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Bestellung</th>
                                    <th>Kunde</th>
                                    <th>Artikel</th>
                                    <th>Versandart</th>
                                    <th>Lieferzeitraum</th>
                                    <th>Status</th>
                                    <th>Gesamt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $is_urgent = (strtotime($order['ship_by']) < strtotime($date));
                                    $row_class = $is_urgent ? 'urgent' : '';
                                    ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td>
                                            <a href="<?php echo admin_url('post.php?post=' . $order['id'] . '&action=edit'); ?>" class="order-link">
                                                #<?php echo esc_html($order['number']); ?>
                                            </a>
                                            <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">
                                                <?php echo esc_html($order['date']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html($order['customer']); ?></td>
                                        <td>
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="items-list">
                                                    <?php echo esc_html($item['quantity']); ?>x 
                                                    <?php echo esc_html($item['name']); ?>
                                                    <?php if ($item['sku']): ?>
                                                        <span style="color: #adb5bd;">(<?php echo esc_html($item['sku']); ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><?php echo esc_html($order['shipping_method'] ?: '-'); ?></td>
                                        <td>
                                            <?php echo esc_html($order['delivery_window'] ?: '-'); ?>
                                            <?php if ($is_urgent): ?>
                                                <div style="color: #856404; font-size: 12px; margin-top: 4px;">
                                                    ⚠️ Überfällig seit <?php echo date_i18n('d.m.Y', strtotime($order['ship_by'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo esc_attr($order['status']); ?>">
                                                <?php echo esc_html(ucfirst($order['status'])); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo wc_price($order['total']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="email-footer">
                    <p>Diese E-Mail wurde automatisch von <?php echo get_bloginfo('name'); ?> generiert.</p>
                    <p>Woo Lieferzeiten Manager v<?php echo WLM_VERSION; ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add settings fields
     *
     * @param array $fields Existing fields
     * @return array Modified fields
     */
    public function add_settings_fields($fields) {
        $fields['ship_notification_enabled'] = array(
            'type' => 'checkbox',
            'label' => 'Tägliche Versandbenachrichtigung aktivieren',
            'description' => 'Sendet täglich eine E-Mail mit allen Bestellungen, die heute versendet werden müssen.',
            'default' => false,
        );
        
        $fields['ship_notification_email'] = array(
            'type' => 'text',
            'label' => 'E-Mail-Adresse für Versandbenachrichtigungen',
            'description' => 'E-Mail-Adresse, an die die tägliche Versandliste gesendet wird.',
            'default' => get_option('admin_email'),
        );
        
        $fields['ship_notification_time'] = array(
            'type' => 'time',
            'label' => 'Uhrzeit für tägliche Benachrichtigung',
            'description' => 'Uhrzeit, zu der die E-Mail täglich versendet wird.',
            'default' => '08:00',
        );
        
        $fields['ship_notification_send_empty'] = array(
            'type' => 'checkbox',
            'label' => 'E-Mail auch senden, wenn keine Bestellungen vorhanden sind',
            'description' => 'Wenn aktiviert, wird die E-Mail auch gesendet, wenn keine Bestellungen versendet werden müssen.',
            'default' => false,
        );
        
        return $fields;
    }
    
    /**
     * Manual trigger for testing
     */
    public function trigger_manual() {
        WLM_Core::log('[WLM Ship Notifications] Manual trigger called');
        
        // Force enable for testing
        $was_enabled = get_option('wlm_ship_notification_enabled', false);
        update_option('wlm_ship_notification_enabled', true);
        
        $result = $this->send_daily_notification();
        
        // Restore original setting
        update_option('wlm_ship_notification_enabled', $was_enabled);
        
        WLM_Core::log('[WLM Ship Notifications] Manual trigger completed');
        
        return $result;
    }
}
