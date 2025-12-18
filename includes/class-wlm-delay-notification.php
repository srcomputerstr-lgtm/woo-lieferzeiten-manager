<?php
/**
 * Delay Notifications
 *
 * Handles email notifications for orders that are delayed (Ship-By-Date exceeded).
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Delay_Notification {
    
    /**
     * Cron hook name
     */
    const CRON_HOOK = 'wlm_daily_delay_check';
    
    /**
     * Initialize
     */
    public function __construct() {
        // Schedule cron on activation
        add_action('init', array($this, 'schedule_cron'));
        
        // Hook the cron action
        add_action(self::CRON_HOOK, array($this, 'check_delayed_orders'));
        
        // Add settings
        add_filter('wlm_settings_fields', array($this, 'add_settings_fields'));
    }
    
    /**
     * Schedule the cron job
     */
    public function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Get configured time (default: 09:00)
            $check_time = get_option('wlm_delay_check_time', '09:00');
            list($hour, $minute) = explode(':', $check_time);
            
            // Schedule for today at configured time
            $timestamp = strtotime("today {$hour}:{$minute}");
            
            // If time has passed today, schedule for tomorrow
            if ($timestamp < current_time('timestamp')) {
                $timestamp = strtotime("tomorrow {$hour}:{$minute}");
            }
            
            wp_schedule_event($timestamp, 'daily', self::CRON_HOOK);
            
            WLM_Core::log('Scheduled delay check cron for ' . date('Y-m-d H:i:s', $timestamp));
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
     * Check for delayed orders and send notifications
     */
    public function check_delayed_orders() {
        // Check if notifications are enabled
        if (!get_option('wlm_delay_notification_enabled', false)) {
            WLM_Core::log('Delay notifications are disabled, skipping');
            return false;
        }
        
        $today = date('Y-m-d');
        $delay_days = (int) get_option('wlm_delay_notification_days', 1);
        
        WLM_Core::log('Running daily delay check for ' . $today . ' (delay threshold: ' . $delay_days . ' days)');
        
        // Get delayed orders
        $orders = $this->get_delayed_orders($delay_days);
        
        WLM_Core::log('Found ' . count($orders) . ' delayed orders');
        
        if (empty($orders)) {
            return false;
        }
        
        $sent_count = 0;
        
        foreach ($orders as $order) {
            if ($this->should_send_notification($order)) {
                if ($this->send_delay_notification($order)) {
                    $sent_count++;
                }
            }
        }
        
        WLM_Core::log('Sent ' . $sent_count . ' delay notifications');
        
        return true;
    }
    
    /**
     * Get delayed orders
     */
    private function get_delayed_orders($delay_days) {
        global $wpdb;
        
        $today = date('Y-m-d');
        $threshold_date = date('Y-m-d', strtotime("-{$delay_days} days"));
        
        // Get orders with ship_by_date < threshold and status = processing or packed
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT pm.post_id
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_wlm_ship_by_date'
            AND pm.meta_value < %s
            AND p.post_type = 'shop_order'
            AND p.post_status IN ('wc-processing', 'wc-packed')
            ORDER BY pm.meta_value ASC
        ", $threshold_date));
        
        $orders = array();
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $orders[] = $order;
            }
        }
        
        return $orders;
    }
    
    /**
     * Check if notification should be sent for this order
     */
    private function should_send_notification($order) {
        $notification_count = (int) $order->get_meta('_wlm_delay_notification_count');
        $last_notification = $order->get_meta('_wlm_last_delay_notification');
        
        // Check if already sent
        if ($notification_count > 0 && !get_option('wlm_delay_notification_repeat', false)) {
            return false;
        }
        
        // Check max count
        $max_count = (int) get_option('wlm_delay_notification_max_count', 2);
        if ($notification_count >= $max_count) {
            return false;
        }
        
        // Check interval
        if ($last_notification) {
            $interval_days = (int) get_option('wlm_delay_notification_interval', 2);
            $next_send_date = date('Y-m-d', strtotime($last_notification . " +{$interval_days} days"));
            
            if (date('Y-m-d') < $next_send_date) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Send delay notification email
     */
    private function send_delay_notification($order) {
        $notification_count = (int) $order->get_meta('_wlm_delay_notification_count');
        $template_number = min($notification_count + 1, 3); // 1, 2, or 3
        
        // Get email settings
        $subject = get_option("wlm_delay_notification_subject_{$template_number}", 
            'Ihre Bestellung {order_number} - Wir sind etwas später dran');
        $message = get_option("wlm_delay_notification_message_{$template_number}", 
            $this->get_default_message($template_number));
        
        // Replace placeholders
        $subject = $this->replace_placeholders($subject, $order);
        $message = $this->replace_placeholders($message, $order);
        
        // Get customer email
        $to = $order->get_billing_email();
        
        // Send email using WooCommerce email template
        $email_heading = $subject;
        $email_content = wpautop($message);
        
        // Use WooCommerce email template
        ob_start();
        wc_get_template('emails/email-header.php', array('email_heading' => $email_heading));
        echo $email_content;
        wc_get_template('emails/email-footer.php');
        $email_body = ob_get_clean();
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $sent = wp_mail($to, $subject, $email_body, $headers);
        
        if ($sent) {
            // Update meta
            $order->update_meta_data('_wlm_delay_notification_count', $notification_count + 1);
            $order->update_meta_data('_wlm_last_delay_notification', date('Y-m-d'));
            $order->save();
            
            WLM_Core::log('Sent delay notification #' . $template_number . ' to ' . $to . ' for order #' . $order->get_id());
        } else {
            WLM_Core::log('Failed to send delay notification to ' . $to . ' for order #' . $order->get_id());
        }
        
        return $sent;
    }
    
    /**
     * Replace placeholders in text
     */
    private function replace_placeholders($text, $order) {
        $replacements = array(
            '{order_number}' => $order->get_order_number(),
            '{customer_first_name}' => $order->get_billing_first_name(),
            '{customer_last_name}' => $order->get_billing_last_name(),
            '{order_date}' => $order->get_date_created()->format('d.m.Y'),
            '{order_total}' => $order->get_formatted_order_total(),
            '{site_title}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url'),
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * Get default message template
     */
    private function get_default_message($template_number) {
        $messages = array(
            1 => "Hallo {customer_first_name},\n\nleider müssen wir Ihnen mitteilen, dass sich die Bearbeitung Ihrer Bestellung {order_number} etwas verzögert hat.\n\nWir setzen alles daran, Ihre Bestellung schnellstmöglich zu verpacken und zu versenden. Sobald Ihre Bestellung unser Lager verlässt, erhalten Sie automatisch eine Versandbestätigung mit der Sendungsverfolgung und dem voraussichtlichen Zustellfenster.\n\nWir entschuldigen uns für die Unannehmlichkeiten und danken Ihnen für Ihre Geduld.\n\nMit freundlichen Grüßen\nIhr {site_title} Team",
            
            2 => "Hallo {customer_first_name},\n\nwir möchten Sie darüber informieren, dass sich die Bearbeitung Ihrer Bestellung {order_number} weiterhin verzögert.\n\nWir arbeiten mit Hochdruck daran, Ihre Bestellung fertigzustellen und schnellstmöglich zu versenden. Wir danken Ihnen für Ihr Verständnis und Ihre Geduld.\n\nSobald Ihre Bestellung versendet wurde, erhalten Sie umgehend eine Benachrichtigung.\n\nMit freundlichen Grüßen\nIhr {site_title} Team",
            
            3 => "Hallo {customer_first_name},\n\nwir möchten uns nochmals für die Verzögerung Ihrer Bestellung {order_number} entschuldigen.\n\nWir tun unser Bestes, um Ihre Bestellung so schnell wie möglich zu versenden. Falls Sie Fragen haben, zögern Sie bitte nicht, uns zu kontaktieren.\n\nVielen Dank für Ihre Geduld und Ihr Verständnis.\n\nMit freundlichen Grüßen\nIhr {site_title} Team"
        );
        
        return isset($messages[$template_number]) ? $messages[$template_number] : $messages[1];
    }
    
    /**
     * Add settings fields
     */
    public function add_settings_fields($fields) {
        $fields['delay_notifications'] = array(
            'title' => __('Verzögerungs-Benachrichtigungen', 'woo-lieferzeiten-manager'),
            'fields' => array(
                'wlm_delay_notification_enabled' => array(
                    'label' => __('Benachrichtigungen aktivieren', 'woo-lieferzeiten-manager'),
                    'type' => 'checkbox',
                    'default' => false,
                    'description' => __('Aktiviert automatische E-Mail-Benachrichtigungen bei verzögerten Bestellungen', 'woo-lieferzeiten-manager')
                ),
                'wlm_delay_check_time' => array(
                    'label' => __('Prüfzeit', 'woo-lieferzeiten-manager'),
                    'type' => 'time',
                    'default' => '09:00',
                    'description' => __('Uhrzeit für die tägliche Prüfung verzögerter Bestellungen', 'woo-lieferzeiten-manager')
                ),
                'wlm_delay_notification_days' => array(
                    'label' => __('Verzögerung in Tagen', 'woo-lieferzeiten-manager'),
                    'type' => 'number',
                    'default' => 1,
                    'description' => __('Anzahl Tage nach Überschreitung des Ship-By-Date, bevor Benachrichtigung gesendet wird', 'woo-lieferzeiten-manager')
                ),
                'wlm_delay_notification_repeat' => array(
                    'label' => __('Mehrfachversand erlauben', 'woo-lieferzeiten-manager'),
                    'type' => 'checkbox',
                    'default' => false,
                    'description' => __('Erlaubt das mehrfache Versenden von Verzögerungs-Benachrichtigungen', 'woo-lieferzeiten-manager')
                ),
                'wlm_delay_notification_max_count' => array(
                    'label' => __('Maximale Anzahl', 'woo-lieferzeiten-manager'),
                    'type' => 'select',
                    'options' => array(
                        '1' => '1',
                        '2' => '2',
                        '3' => '3'
                    ),
                    'default' => '2',
                    'description' => __('Maximale Anzahl von Benachrichtigungen pro Bestellung', 'woo-lieferzeiten-manager')
                ),
                'wlm_delay_notification_interval' => array(
                    'label' => __('Abstand in Tagen', 'woo-lieferzeiten-manager'),
                    'type' => 'number',
                    'default' => 2,
                    'description' => __('Anzahl Tage zwischen wiederholten Benachrichtigungen', 'woo-lieferzeiten-manager')
                ),
                // Template 1
                'wlm_delay_notification_subject_1' => array(
                    'label' => __('Betreff (1. Benachrichtigung)', 'woo-lieferzeiten-manager'),
                    'type' => 'text',
                    'default' => 'Ihre Bestellung {order_number} - Wir sind etwas später dran',
                    'description' => __('Verfügbare Platzhalter: {order_number}, {customer_first_name}, {customer_last_name}, {order_date}, {site_title}', 'woo-lieferzeiten-manager')
                ),
                'wlm_delay_notification_message_1' => array(
                    'label' => __('Nachricht (1. Benachrichtigung)', 'woo-lieferzeiten-manager'),
                    'type' => 'textarea',
                    'default' => "Hallo {customer_first_name},\n\nleider müssen wir Ihnen mitteilen, dass sich die Bearbeitung Ihrer Bestellung {order_number} etwas verzögert hat.\n\nWir setzen alles daran, Ihre Bestellung schnellstmöglich zu verpacken und zu versenden. Sobald Ihre Bestellung unser Lager verlässt, erhalten Sie automatisch eine Versandbestätigung mit der Sendungsverfolgung und dem voraussichtlichen Zustellfenster.\n\nWir entschuldigen uns für die Unannehmlichkeiten und danken Ihnen für Ihre Geduld.\n\nMit freundlichen Grüßen\nIhr {site_title} Team",
                    'description' => __('Verfügbare Platzhalter: {order_number}, {customer_first_name}, {customer_last_name}, {order_date}, {order_total}, {site_title}, {site_url}', 'woo-lieferzeiten-manager')
                ),
                // Template 2
                'wlm_delay_notification_subject_2' => array(
                    'label' => __('Betreff (2. Benachrichtigung)', 'woo-lieferzeiten-manager'),
                    'type' => 'text',
                    'default' => 'Ihre Bestellung {order_number} - Update zur Verzögerung',
                    'description' => __('Nur relevant wenn Mehrfachversand aktiviert ist', 'woo-lieferzeiten-manager')
                ),
                'wlm_delay_notification_message_2' => array(
                    'label' => __('Nachricht (2. Benachrichtigung)', 'woo-lieferzeiten-manager'),
                    'type' => 'textarea',
                    'default' => "Hallo {customer_first_name},\n\nwir möchten Sie darüber informieren, dass sich die Bearbeitung Ihrer Bestellung {order_number} weiterhin verzögert.\n\nWir arbeiten mit Hochdruck daran, Ihre Bestellung fertigzustellen und schnellstmöglich zu versenden. Wir danken Ihnen für Ihr Verständnis und Ihre Geduld.\n\nSobald Ihre Bestellung versendet wurde, erhalten Sie umgehend eine Benachrichtigung.\n\nMit freundlichen Grüßen\nIhr {site_title} Team",
                    'description' => __('Nur relevant wenn Mehrfachversand aktiviert ist', 'woo-lieferzeiten-manager')
                ),
                // Template 3
                'wlm_delay_notification_subject_3' => array(
                    'label' => __('Betreff (3. Benachrichtigung)', 'woo-lieferzeiten-manager'),
                    'type' => 'text',
                    'default' => 'Ihre Bestellung {order_number} - Weiteres Update',
                    'description' => __('Nur relevant wenn Mehrfachversand aktiviert und max. 3 Benachrichtigungen eingestellt', 'woo-lieferzeiten-manager')
                ),
                'wlm_delay_notification_message_3' => array(
                    'label' => __('Nachricht (3. Benachrichtigung)', 'woo-lieferzeiten-manager'),
                    'type' => 'textarea',
                    'default' => "Hallo {customer_first_name},\n\nwir möchten uns nochmals für die Verzögerung Ihrer Bestellung {order_number} entschuldigen.\n\nWir tun unser Bestes, um Ihre Bestellung so schnell wie möglich zu versenden. Falls Sie Fragen haben, zögern Sie bitte nicht, uns zu kontaktieren.\n\nVielen Dank für Ihre Geduld und Ihr Verständnis.\n\nMit freundlichen Grüßen\nIhr {site_title} Team",
                    'description' => __('Nur relevant wenn Mehrfachversand aktiviert und max. 3 Benachrichtigungen eingestellt', 'woo-lieferzeiten-manager')
                ),
            )
        );
        
        return $fields;
    }
    
    /**
     * Send test notification
     */
    public function send_test_notification($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return false;
        }
        
        return $this->send_delay_notification($order);
    }
}
