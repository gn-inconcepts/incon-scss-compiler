<?php
/**
 * Direct AJAX compilation endpoint - Ultra clean version
 */

// Kill absolutely everything
@error_reporting(0);
@ini_set('display_errors', '0');
@ini_set('log_errors', '0');
@ini_set('display_startup_errors', '0');

// Clean all buffers
while (@ob_get_level()) {
    @ob_end_clean();
}

// Function to send JSON and die immediately
function send_json_and_die($data) {
    // Clean any possible output
    if (@ob_get_level()) {
        @ob_clean();
    }
    
    // Send headers
    @header('Content-Type: application/json; charset=utf-8');
    @header('X-Content-Type-Options: nosniff');
    
    // Send JSON
    echo json_encode($data);
    
    // Multiple ways to ensure we stop
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
    die();
}

// Start clean buffer
@ob_start();

// Load WordPress silently
if (!defined('DOING_AJAX')) define('DOING_AJAX', true);
if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', false);

// CRITICAL: Define WP_ADMIN to ensure proper cookie handling
if (!defined('WP_ADMIN')) define('WP_ADMIN', true);

$wp_load = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load)) {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'WordPress not found')
    ));
}

// Suppress any WordPress output
@ob_start();
@require_once($wp_load);

// Load wp-admin functions for proper authentication
require_once(ABSPATH . 'wp-admin/includes/admin.php');

@ob_clean();

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'Invalid request method')
    ));
}

// Check nonce
$nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
if (empty($nonce)) {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'No security token provided')
    ));
}

// Debug mode - uncomment to see what's happening
$debug = false; // Set to true for debugging

// Use WordPress nonce verification (now that we know NULL was from var_dump)
$nonce_valid = wp_verify_nonce($nonce, 'incon_scss_nonce');
if (!$nonce_valid) {
    if ($debug) {
        send_json_and_die(array(
            'success' => false,
            'data' => array(
                'message' => 'Security check failed',
                'debug' => array(
                    'nonce_provided' => $nonce,
                    'user_id' => get_current_user_id(),
                    'user_login' => wp_get_current_user()->user_login,
                    'is_logged_in' => is_user_logged_in(),
                    'verify_result' => $nonce_valid,
                    'cookies' => array(
                        'auth_cookie' => isset($_COOKIE[AUTH_COOKIE]),
                        'logged_in_cookie' => isset($_COOKIE[LOGGED_IN_COOKIE]),
                        'wp_admin' => defined('WP_ADMIN')
                    )
                )
            )
        ));
    } else {
        send_json_and_die(array(
            'success' => false,
            'data' => array('message' => 'Security check failed')
        ));
    }
}

// Check user permissions
if (!current_user_can('manage_options')) {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'Insufficient permissions')
    ));
}

// Load plugin files
$plugin_dir = dirname(__FILE__) . '/';

// Load ScssPhp
if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
    $vendor_autoload = $plugin_dir . 'vendor/autoload.php';
    $scss_lib = $plugin_dir . 'scssphp/scss.inc.php';
    
    if (file_exists($vendor_autoload)) {
        @ob_start();
        @require_once $vendor_autoload;
        @ob_clean();
    } elseif (file_exists($scss_lib)) {
        @ob_start();
        @require_once $scss_lib;
        @ob_clean();
    } else {
        send_json_and_die(array(
            'success' => false,
            'data' => array('message' => 'ScssPhp library not found')
        ));
    }
}

// Load compiler class
$compiler_file = $plugin_dir . 'includes/class-compiler.php';
if (!file_exists($compiler_file)) {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'Compiler class not found')
    ));
}

@ob_start();
@require_once $compiler_file;
@ob_clean();

if (!class_exists('InconSCSS_Compiler')) {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'Compiler class not available')
    ));
}

// Get settings
$settings = @get_option('incon_scss_settings', array());
$defaults = array(
    'base_dir' => get_stylesheet_directory(),
    'scss_dir' => '/scss/',
    'css_dir' => '/css/',
    'output_style' => 'compressed',
    'source_maps' => false,
    'autoprefixer' => false,
    'minify' => false,
    'cache_enabled' => false,
    'dependency_tracking' => false,
    'statistics' => false,
    'custom_functions' => false
);
$settings = wp_parse_args($settings, $defaults);

// Get file parameter
$file = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : '';

// Perform compilation
try {
    // Capture any output during compilation
    @ob_start();
    
    $compiler = new InconSCSS_Compiler($settings);
    
    if ($file) {
        $result = $compiler->compile_file($file);
    } else {
        $result = $compiler->compile_all();
    }
    
    // Discard any output
    @ob_clean();
    
    // Send result
    send_json_and_die($result);
    
} catch (Exception $e) {
    // Clean any output
    @ob_clean();
    
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'Compilation error: ' . $e->getMessage())
    ));
}

// This should never be reached, but just in case
send_json_and_die(array(
    'success' => false,
    'data' => array('message' => 'Unexpected error')
));