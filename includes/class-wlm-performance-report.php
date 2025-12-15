<?php
/**
 * Weekly Performance Report
 *
 * Sends weekly KPI email about shipping performance
 *
 * @package WooLieferzeitenManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class WLM_Performance_Report {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook for manual trigger
        add_action('wlm_send_performance_report', array($this, 'send_weekly_report'));
    }
    
    /**
     * Send weekly performance report
     *
     * @return bool Success status
     */
    public function send_weekly_report() {
        if (!get_option('wlm_performance_report_enabled', false)) {
            WLM_Core::log('[WLM Performance Report] Performance reports are disabled');
            return false;
        }
        
        $to = get_option('wlm_performance_report_email', get_option('admin_email'));
        
        WLM_Core::log('[WLM Performance Report] Generating weekly performance report for ' . $to);
        
        // Get last 7 days of data
        $stats = $this->get_weekly_stats();
        
        if ($stats['total_orders'] === 0) {
            WLM_Core::log('[WLM Performance Report] No completed orders in the last 7 days');
            
            // Check if we should send empty reports
            if (!get_option('wlm_performance_report_send_empty', false)) {
                return false;
            }
        }
        
        $subject = sprintf(
            '📊 Wöchentlicher Performance Report - KW %s',
            date('W')
        );
        
        $message = $this->generate_email_html($stats);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            WLM_Core::log('Performance report sent to ' . $to);
        } else {
            WLM_Core::log('Failed to send performance report to ' . $to);
        }
        
        return $sent;
    }
    
    /**
     * Get weekly statistics
     *
     * @return array Statistics
     */
    private function get_weekly_stats() {
        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-7 days', current_time('timestamp')));
        
        // Get all completed orders from last 7 days (by ORDER date, not completion date)
        $args = array(
            'limit' => -1,
            'status' => array('completed'),
            'date_created' => $start_date . '...' . $end_date,
        );
        
        $orders = wc_get_orders($args);
        
        $total_orders = 0;
        $on_time = 0;
        $overdue = 0;
        $total_processing_days = 0;
        $total_overtime_days = 0;
        $orders_with_processing_data = 0;
        $order_details = array();
        
        foreach ($orders as $order) {
            $ship_by_date = $order->get_meta('_wlm_ship_by_date');
            $date_completed = $order->get_date_completed();
            
            if (!$ship_by_date || !$date_completed) {
                continue;
            }
            
            $total_orders++;
            
            $ship_by_timestamp = strtotime($ship_by_date);
            $completed_timestamp = $date_completed->getTimestamp();
            
            // Check if shipped on time
            $is_on_time = $completed_timestamp <= $ship_by_timestamp;
            if ($is_on_time) {
                $on_time++;
            } else {
                $overdue++;
            }
            
            // Calculate actual processing time
            $order_date = $order->get_date_created();
            $processing_days = 0;
            if ($order_date) {
                $processing_days = ($completed_timestamp - $order_date->getTimestamp()) / (60 * 60 * 24);
                $total_processing_days += $processing_days;
                $orders_with_processing_data++;
            }
            
            // Calculate overtime (days late)
            $overtime_days = ($completed_timestamp - $ship_by_timestamp) / (60 * 60 * 24);
            $total_overtime_days += $overtime_days;
            
            // Collect order details for table
            $order_details[] = array(
                'order_number' => $order->get_order_number(),
                'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'order_date' => $order_date ? $order_date->date('Y-m-d') : '',
                'ship_by_date' => $ship_by_date,
                'completed_date' => $date_completed->date('Y-m-d'),
                'processing_days' => round($processing_days, 1),
                'overtime_days' => round($overtime_days, 1),
                'is_on_time' => $is_on_time,
            );
        }
        
        $on_time_percentage = $total_orders > 0 ? round(($on_time / $total_orders) * 100, 1) : 0;
        $overdue_percentage = $total_orders > 0 ? round(($overdue / $total_orders) * 100, 1) : 0;
        $avg_processing_days = $orders_with_processing_data > 0 ? round($total_processing_days / $orders_with_processing_data, 1) : 0;
        $avg_overtime_days = $total_orders > 0 ? round($total_overtime_days / $total_orders, 1) : 0;
        
        // Get target processing days from settings
        $target_processing_days = (float) get_option('wlm_processing_days', 1);
        
        // Get target on-time rate (default 90%)
        $target_on_time_rate = (float) get_option('wlm_target_on_time_rate', 90);
        
        return array(
            'period_start' => $start_date,
            'period_end' => $end_date,
            'total_orders' => $total_orders,
            'on_time' => $on_time,
            'overdue' => $overdue,
            'on_time_percentage' => $on_time_percentage,
            'overdue_percentage' => $overdue_percentage,
            'avg_processing_days' => $avg_processing_days,
            'avg_overtime_days' => $avg_overtime_days,
            'target_processing_days' => $target_processing_days,
            'target_on_time_rate' => $target_on_time_rate,
            'order_details' => $order_details,
        );
    }
    
    /**
     * Generate HTML email content
     *
     * @param array $stats Statistics
     * @return string HTML content
     */
    private function generate_email_html($stats) {
        $period_start_formatted = date_i18n('d.m.Y', strtotime($stats['period_start']));
        $period_end_formatted = date_i18n('d.m.Y', strtotime($stats['period_end']));
        
        // Determine performance colors
        $on_time_color = $stats['on_time_percentage'] >= 90 ? '#28a745' : ($stats['on_time_percentage'] >= 75 ? '#ffc107' : '#dc3545');
        $processing_color = $stats['avg_processing_days'] <= $stats['target_processing_days'] ? '#28a745' : ($stats['avg_processing_days'] <= $stats['target_processing_days'] * 1.5 ? '#ffc107' : '#dc3545');
        
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
                    max-width: 800px;
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
                    background: linear-gradient(135deg, #F39200 0%, #000000 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .email-header img {
                    max-width: 128px;
                    margin-bottom: 15px;
                }
                .email-header h1 {
                    margin: 0 0 10px 0;
                    font-size: 28px;
                    color: white;
                    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
                }
                .email-header p {
                    margin: 0;
                    color: white;
                    opacity: 0.95;
                    font-size: 16px;
                    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
                }
                .email-body {
                    padding: 30px;
                }
                .period-info {
                    text-align: center;
                    margin-bottom: 30px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 6px;
                }
                .period-info strong {
                    color: #F39200;
                    font-size: 18px;
                }
                .kpi-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                    margin-bottom: 30px;
                }
                .kpi-card {
                    background: #fff;
                    border-radius: 8px;
                    padding: 25px;
                    text-align: center;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    border-top: 4px solid;
                }
                .kpi-card.success {
                    border-top-color: #28a745;
                }
                .kpi-card.warning {
                    border-top-color: #ffc107;
                }
                .kpi-card.danger {
                    border-top-color: #dc3545;
                }
                .kpi-value {
                    font-size: 48px;
                    font-weight: bold;
                    margin: 10px 0;
                    line-height: 1;
                }
                .kpi-label {
                    font-size: 14px;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 5px;
                }
                .kpi-subtitle {
                    font-size: 16px;
                    color: #999;
                    margin-top: 10px;
                }
                .summary-box {
                    background: #f8f9fa;
                    border-left: 4px solid #F39200;
                    padding: 20px;
                    margin-top: 30px;
                    border-radius: 4px;
                }
                .summary-box h3 {
                    margin: 0 0 15px 0;
                    color: #F39200;
                    font-size: 18px;
                }
                .summary-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #e0e0e0;
                }
                .summary-item:last-child {
                    border-bottom: none;
                }
                .summary-label {
                    color: #666;
                }
                .summary-value {
                    font-weight: 600;
                    color: #333;
                }
                .email-footer {
                    background: #f8f9fa;
                    padding: 20px;
                    text-align: center;
                    color: #666;
                    font-size: 12px;
                }
                @media only screen and (max-width: 600px) {
                    .kpi-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <img src="https://mega-holz.de/wp-content/uploads/2020/10/mega-holz-logo-128.png" alt="MEGA Holz" style="max-width: 128px; margin-bottom: 15px;">
                    <h1 style="margin: 0 0 10px 0; font-size: 28px; color: #000000 !important;">📊 Performance Report</h1>
                    <p style="margin: 0; font-size: 16px; color: #000000 !important;">Wöchentliche Versandleistung KW <?php echo date('W'); ?></p>
                </div>
                
                <div class="email-body">
                    <div class="period-info">
                        <strong>Zeitraum:</strong> <?php echo esc_html($period_start_formatted); ?> - <?php echo esc_html($period_end_formatted); ?>
                    </div>
                    
                    <?php if ($stats['total_orders'] > 0): ?>
                    
                    <div class="kpi-grid">
                        <!-- On-Time Performance -->
                        <div class="kpi-card <?php echo $stats['on_time_percentage'] >= 90 ? 'success' : ($stats['on_time_percentage'] >= 75 ? 'warning' : 'danger'); ?>">
                            <div class="kpi-label">Pünktlichkeit</div>
                            <div class="kpi-value" style="color: <?php echo esc_attr($on_time_color); ?>">
                                <?php echo esc_html($stats['on_time_percentage']); ?>%
                            </div>
                            <div class="kpi-subtitle">
                                <?php echo esc_html($stats['on_time']); ?> von <?php echo esc_html($stats['total_orders']); ?> pünktlich
                            </div>
                        </div>
                        
                        <!-- Overdue Orders -->
                        <div class="kpi-card <?php echo $stats['overdue'] === 0 ? 'success' : ($stats['overdue_percentage'] <= 10 ? 'warning' : 'danger'); ?>">
                            <div class="kpi-label">Überfällig</div>
                            <div class="kpi-value" style="color: <?php echo $stats['overdue'] === 0 ? '#28a745' : '#dc3545'; ?>">
                                <?php echo esc_html($stats['overdue']); ?>
                            </div>
                            <div class="kpi-subtitle">
                                <?php echo esc_html($stats['overdue_percentage']); ?>% zu spät versendet
                            </div>
                        </div>
                        
                        <!-- Average Processing Time -->
                        <div class="kpi-card <?php echo $stats['avg_processing_days'] <= $stats['target_processing_days'] ? 'success' : ($stats['avg_processing_days'] <= $stats['target_processing_days'] * 1.5 ? 'warning' : 'danger'); ?>">
                            <div class="kpi-label">Ø Processing-Time</div>
                            <div class="kpi-value" style="color: <?php echo esc_attr($processing_color); ?>">
                                <?php echo esc_html($stats['avg_processing_days']); ?>
                            </div>
                            <div class="kpi-subtitle">
                                Tage (Soll: <?php echo esc_html($stats['target_processing_days']); ?>)
                            </div>
                        </div>
                        
                        <!-- Total Orders -->
                        <div class="kpi-card success">
                            <div class="kpi-label">Bestellungen</div>
                            <div class="kpi-value" style="color: #F39200;">
                                <?php echo esc_html($stats['total_orders']); ?>
                            </div>
                            <div class="kpi-subtitle">
                                Letzte 7 Tage
                            </div>
                        </div>
                    </div>
                    
                    <div class="summary-box">
                        <h3>📈 Zusammenfassung</h3>
                        <div class="summary-item">
                            <span class="summary-label">Zeitraum</span>
                            <span class="summary-value"><?php echo esc_html($period_start_formatted); ?> - <?php echo esc_html($period_end_formatted); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Versendete Bestellungen</span>
                            <span class="summary-value"><?php echo esc_html($stats['total_orders']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Pünktlich versendet</span>
                            <span class="summary-value" style="color: #28a745;"><?php echo esc_html($stats['on_time']); ?> (<?php echo esc_html($stats['on_time_percentage']); ?>%)</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Zu spät versendet</span>
                            <span class="summary-value" style="color: #dc3545;"><?php echo esc_html($stats['overdue']); ?> (<?php echo esc_html($stats['overdue_percentage']); ?>%)</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Durchschnittliche Processing-Time</span>
                            <span class="summary-value" style="color: <?php echo esc_attr($processing_color); ?>">
                                <?php echo esc_html($stats['avg_processing_days']); ?> Tage
                            </span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Ziel Processing-Time</span>
                            <span class="summary-value"><?php echo esc_html($stats['target_processing_days']); ?> Tage</span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Durchschnittliche Overtime</span>
                            <span class="summary-value" style="color: <?php echo $stats['avg_overtime_days'] > 0 ? '#dc3545' : '#28a745'; ?>">
                                <?php echo $stats['avg_overtime_days'] > 0 ? '+' : ''; ?><?php echo esc_html($stats['avg_overtime_days']); ?> Tage
                            </span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Performance vs. Ziel</span>
                            <span class="summary-value" style="color: <?php echo esc_attr($on_time_color); ?>">
                                <?php echo esc_html($stats['on_time_percentage']); ?>% pünktlich
                                <?php if ($stats['on_time_percentage'] >= $stats['target_on_time_rate']): ?>
                                    ✅ Ziel erreicht! (Ziel: ≥<?php echo esc_html($stats['target_on_time_rate']); ?>%)
                                <?php elseif ($stats['on_time_percentage'] >= 75): ?>
                                    ⚠️ Unter Ziel (Ziel: ≥<?php echo esc_html($stats['target_on_time_rate']); ?>%)
                                <?php else: ?>
                                    ❌ Ziel verfehlt (Ziel: ≥<?php echo esc_html($stats['target_on_time_rate']); ?>%)
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    
                    <div style="text-align: center; padding: 40px; color: #999;">
                        <p style="font-size: 18px; margin: 0;">Keine abgeschlossenen Bestellungen in diesem Zeitraum.</p>
                    </div>
                    
                    <?php endif; ?>
                    
                    <?php if (!empty($stats['order_details'])): ?>
                    <!-- Order Details Table -->
                    <div style="margin-top: 40px;">
                        <h2 style="color: #333; font-size: 20px; margin-bottom: 20px; border-bottom: 2px solid #F39200; padding-bottom: 10px;">
                            📋 Bestelldetails (<?php echo count($stats['order_details']); ?> Bestellungen)
                        </h2>
                        
                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: #F39200; color: white;">
                                    <th style="padding: 12px 8px; text-align: left; border: 1px solid #ddd;">Bestellung</th>
                                    <th style="padding: 12px 8px; text-align: left; border: 1px solid #ddd;">Kunde</th>
                                    <th style="padding: 12px 8px; text-align: left; border: 1px solid #ddd;">Bestelldatum</th>
                                    <th style="padding: 12px 8px; text-align: left; border: 1px solid #ddd;">Ship-By-Date</th>
                                    <th style="padding: 12px 8px; text-align: left; border: 1px solid #ddd;">Versanddatum</th>
                                    <th style="padding: 12px 8px; text-align: right; border: 1px solid #ddd;">Tats. Processing</th>
                                    <th style="padding: 12px 8px; text-align: right; border: 1px solid #ddd;">Overtime</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['order_details'] as $detail): ?>
                                <tr style="background: <?php echo $detail['is_on_time'] ? '#f0f9ff' : '#fff8f0'; ?>;">
                                    <td style="padding: 10px 8px; border: 1px solid #ddd;">
                                        <strong style="color: #F39200;">#<?php echo esc_html($detail['order_number']); ?></strong>
                                    </td>
                                    <td style="padding: 10px 8px; border: 1px solid #ddd;">
                                        <?php echo esc_html($detail['customer']); ?>
                                    </td>
                                    <td style="padding: 10px 8px; border: 1px solid #ddd;">
                                        <?php echo date_i18n('d.m.Y', strtotime($detail['order_date'])); ?>
                                    </td>
                                    <td style="padding: 10px 8px; border: 1px solid #ddd;">
                                        <?php echo date_i18n('d.m.Y', strtotime($detail['ship_by_date'])); ?>
                                    </td>
                                    <td style="padding: 10px 8px; border: 1px solid #ddd;">
                                        <?php echo date_i18n('d.m.Y', strtotime($detail['completed_date'])); ?>
                                    </td>
                                    <td style="padding: 10px 8px; border: 1px solid #ddd; text-align: right;">
                                        <?php echo esc_html($detail['processing_days']); ?> Tage
                                    </td>
                                    <td style="padding: 10px 8px; border: 1px solid #ddd; text-align: right;">
                                        <?php if ($detail['overtime_days'] > 0): ?>
                                            <span style="color: #dc3545; font-weight: bold;">+<?php echo esc_html($detail['overtime_days']); ?> Tage</span>
                                        <?php elseif ($detail['overtime_days'] < 0): ?>
                                            <span style="color: #28a745; font-weight: bold;"><?php echo esc_html($detail['overtime_days']); ?> Tage</span>
                                        <?php else: ?>
                                            <span style="color: #28a745; font-weight: bold;">✓ Pünktlich</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="email-footer">
                    <p>Dieser Report wird automatisch jeden Montag generiert.</p>
                    <p>MEGA Holz - Versandmanagement</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Trigger manual report (for testing)
     *
     * @return bool Success status
     */
    public function trigger_manual() {
        WLM_Core::log('[WLM Performance Report] trigger_manual() called');
        WLM_Core::log('[WLM Performance Report] Enabled setting: ' . (get_option('wlm_performance_report_enabled', false) ? 'true' : 'false'));
        
        // Temporarily enable for testing
        $was_enabled = get_option('wlm_performance_report_enabled', false);
        if (!$was_enabled) {
            WLM_Core::log('[WLM Performance Report] Temporarily enabling for test');
            update_option('wlm_performance_report_enabled', true);
        }
        
        $result = $this->send_weekly_report();
        
        // Restore original setting
        if (!$was_enabled) {
            update_option('wlm_performance_report_enabled', false);
        }
        
        WLM_Core::log('[WLM Performance Report] trigger_manual() result: ' . ($result ? 'true' : 'false'));
        return $result;
    }
}
