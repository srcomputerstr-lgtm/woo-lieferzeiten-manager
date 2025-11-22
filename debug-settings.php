<?php
/**
 * Debug script to display WLM settings
 * 
 * Upload this file to your WordPress root directory and access it via:
 * https://mega-domo.com/debug-settings.php
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You must be logged in as admin to view this page.');
}

// Get settings
$settings = get_option('wlm_settings', array());

?>
<!DOCTYPE html>
<html>
<head>
    <title>WLM Settings Debug</title>
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
            padding: 30px;
            border-radius: 8px;
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
        .setting-row {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .setting-row:hover {
            background: #f6f7f7;
        }
        .setting-key {
            font-weight: 600;
            color: #2271b1;
        }
        .setting-value {
            font-family: 'Courier New', monospace;
            background: #f6f7f7;
            padding: 8px;
            border-radius: 4px;
        }
        .raw-data {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 4px;
            overflow-x: auto;
            margin-top: 20px;
        }
        .raw-data pre {
            margin: 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .highlight {
            background: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .day-names {
            margin-top: 5px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 WLM Settings Debug</h1>
        
        <h2>📋 Key Settings</h2>
        
        <?php
        // Map day numbers to names
        $day_names = array(
            1 => 'Monday',
            2 => 'Tuesday', 
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        );
        
        // Display important settings
        $important_keys = array(
            'business_days' => 'Business Days (Werktage)',
            'cutoff_time' => 'Cutoff Time',
            'processing_days' => 'Processing Days',
            'holidays' => 'Holidays (Feiertage)',
            'debug_mode' => 'Debug Mode'
        );
        
        foreach ($important_keys as $key => $label) {
            $value = isset($settings[$key]) ? $settings[$key] : '<span style="color: #d63638;">NOT SET</span>';
            
            echo '<div class="setting-row">';
            echo '<div class="setting-key">' . esc_html($label) . '</div>';
            echo '<div class="setting-value">';
            
            if ($key === 'business_days' && is_array($value)) {
                echo '<span class="highlight">' . implode(', ', $value) . '</span>';
                echo '<div class="day-names">';
                $names = array_map(function($d) use ($day_names) { 
                    return $day_names[$d] ?? $d; 
                }, $value);
                echo '→ ' . implode(', ', $names);
                echo '</div>';
            } elseif ($key === 'holidays' && is_array($value)) {
                echo empty($value) ? '<em>none</em>' : implode(', ', $value);
            } elseif ($key === 'debug_mode') {
                echo $value ? '<span style="color: #00a32a;">✓ ENABLED</span>' : '<span style="color: #d63638;">✗ DISABLED</span>';
            } else {
                echo is_array($value) ? '<pre>' . print_r($value, true) . '</pre>' : esc_html($value);
            }
            
            echo '</div>';
            echo '</div>';
        }
        ?>
        
        <h2>📦 All Settings (Raw Data)</h2>
        <div class="raw-data">
            <pre><?php print_r($settings); ?></pre>
        </div>
        
        <h2>ℹ️ Database Info</h2>
        <div class="setting-row">
            <div class="setting-key">Option Name</div>
            <div class="setting-value">wlm_settings</div>
        </div>
        <div class="setting-row">
            <div class="setting-key">Data Type</div>
            <div class="setting-value"><?php echo gettype($settings); ?></div>
        </div>
        <div class="setting-row">
            <div class="setting-key">Array Keys Count</div>
            <div class="setting-value"><?php echo is_array($settings) ? count($settings) : 'N/A'; ?></div>
        </div>
    </div>
</body>
</html>
