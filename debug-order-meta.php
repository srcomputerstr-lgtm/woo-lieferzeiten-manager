<?php
/**
 * Debug script to check order meta data
 * 
 * Usage: Add order ID as URL parameter
 * Example: https://deine-domain.de/wp-content/plugins/woo-lieferzeiten-manager/debug-order-meta.php?order_id=10023
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator.');
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    die('Please provide an order ID in the URL: ?order_id=10023');
}

// Get order
$order = wc_get_order($order_id);

if (!$order) {
    die('Order not found: ' . $order_id);
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WLM Order Meta Debug</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        h2 {
            color: #2271b1;
            margin-top: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
        }
        .success {
            color: #00a32a;
            font-weight: bold;
        }
        .error {
            color: #d63638;
            font-weight: bold;
        }
        .warning {
            color: #dba617;
            font-weight: bold;
        }
        code {
            background: #f6f7f7;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
        }
        .meta-box {
            background: #f6f7f7;
            padding: 15px;
            border-left: 4px solid #2271b1;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 WLM Order Meta Debug</h1>
        
        <h2>Order Information</h2>
        <table>
            <tr>
                <th>Order ID</th>
                <td><?php echo $order->get_id(); ?></td>
            </tr>
            <tr>
                <th>Order Number</th>
                <td><?php echo $order->get_order_number(); ?></td>
            </tr>
            <tr>
                <th>Order Status</th>
                <td><?php echo $order->get_status(); ?></td>
            </tr>
            <tr>
                <th>Order Date</th>
                <td><?php echo $order->get_date_created()->format('Y-m-d H:i:s'); ?></td>
            </tr>
        </table>
        
        <h2>Shipping Method</h2>
        <?php
        $shipping_methods = $order->get_shipping_methods();
        if (!empty($shipping_methods)) {
            $shipping_method = reset($shipping_methods);
            ?>
            <table>
                <tr>
                    <th>Method ID</th>
                    <td><code><?php echo $shipping_method->get_method_id(); ?></code></td>
                </tr>
                <tr>
                    <th>Method Name</th>
                    <td><?php echo $shipping_method->get_name(); ?></td>
                </tr>
                <tr>
                    <th>Is WLM Method?</th>
                    <td>
                        <?php 
                        if (strpos($shipping_method->get_method_id(), 'wlm_method_') === 0) {
                            echo '<span class="success">✅ YES</span>';
                        } else {
                            echo '<span class="error">❌ NO (This is why delivery timeframe was not saved!)</span>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
            <?php
        } else {
            echo '<p class="error">❌ No shipping method found!</p>';
        }
        ?>
        
        <h2>WLM Order Meta Fields</h2>
        <?php
        $earliest = $order->get_meta('_wlm_earliest_delivery');
        $latest = $order->get_meta('_wlm_latest_delivery');
        $window = $order->get_meta('_wlm_delivery_window');
        $method_name = $order->get_meta('_wlm_shipping_method_name');
        
        $has_data = !empty($earliest) || !empty($latest);
        ?>
        
        <table>
            <tr>
                <th>Meta Key</th>
                <th>Value</th>
                <th>Status</th>
            </tr>
            <tr>
                <td><code>_wlm_earliest_delivery</code></td>
                <td><?php echo $earliest ? '<code>' . esc_html($earliest) . '</code>' : '<em>empty</em>'; ?></td>
                <td><?php echo $earliest ? '<span class="success">✅</span>' : '<span class="error">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><code>_wlm_latest_delivery</code></td>
                <td><?php echo $latest ? '<code>' . esc_html($latest) . '</code>' : '<em>empty</em>'; ?></td>
                <td><?php echo $latest ? '<span class="success">✅</span>' : '<span class="error">❌</span>'; ?></td>
            </tr>
            <tr>
                <td><code>_wlm_delivery_window</code></td>
                <td><?php echo $window ? esc_html($window) : '<em>empty</em>'; ?></td>
                <td><?php echo $window ? '<span class="success">✅</span>' : '<span class="warning">⚠️</span>'; ?></td>
            </tr>
            <tr>
                <td><code>_wlm_shipping_method_name</code></td>
                <td><?php echo $method_name ? esc_html($method_name) : '<em>empty</em>'; ?></td>
                <td><?php echo $method_name ? '<span class="success">✅</span>' : '<span class="warning">⚠️</span>'; ?></td>
            </tr>
        </table>
        
        <?php if (!$has_data): ?>
        <div class="meta-box" style="border-left-color: #d63638;">
            <h3 style="margin-top: 0; color: #d63638;">❌ No WLM delivery timeframe data found!</h3>
            <p><strong>Possible reasons:</strong></p>
            <ul>
                <li>Order was created with a non-WLM shipping method</li>
                <li>Order was created before plugin version 1.24.0</li>
                <li>Hook <code>woocommerce_checkout_create_order</code> was not triggered</li>
                <li>Error during delivery window calculation</li>
            </ul>
            <p><strong>Check Debug Log:</strong></p>
            <p>Enable Debug Mode in WooCommerce → Lieferzeiten → Zeiten and check the PHP error log for <code>[WLM]</code> entries.</p>
        </div>
        <?php else: ?>
        <div class="meta-box" style="border-left-color: #00a32a;">
            <h3 style="margin-top: 0; color: #00a32a;">✅ WLM delivery timeframe data found!</h3>
            <p>The data should be displayed in the order details page after the shipping address.</p>
        </div>
        <?php endif; ?>
        
        <h2>All Order Meta (for debugging)</h2>
        <details>
            <summary style="cursor: pointer; padding: 10px; background: #f6f7f7; border-radius: 3px;">Click to show all order meta fields</summary>
            <table style="margin-top: 10px;">
                <tr>
                    <th>Meta Key</th>
                    <th>Meta Value</th>
                </tr>
                <?php
                $all_meta = $order->get_meta_data();
                foreach ($all_meta as $meta) {
                    $key = $meta->key;
                    $value = $meta->value;
                    
                    // Highlight WLM fields
                    $is_wlm = strpos($key, '_wlm_') === 0;
                    $style = $is_wlm ? 'background: #fffbcc;' : '';
                    
                    echo '<tr style="' . $style . '">';
                    echo '<td><code>' . esc_html($key) . '</code></td>';
                    echo '<td>';
                    if (is_array($value) || is_object($value)) {
                        echo '<pre>' . esc_html(print_r($value, true)) . '</pre>';
                    } else {
                        echo esc_html($value);
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                ?>
            </table>
        </details>
        
        <h2>Actions</h2>
        <p>
            <a href="<?php echo admin_url('post.php?post=' . $order->get_id() . '&action=edit'); ?>" style="display: inline-block; padding: 10px 15px; background: #2271b1; color: white; text-decoration: none; border-radius: 3px;">
                ← Back to Order Details
            </a>
        </p>
    </div>
</body>
</html>
