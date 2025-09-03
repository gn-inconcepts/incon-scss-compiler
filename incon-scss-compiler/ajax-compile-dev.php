<?php
/**
 * Development version of AJAX endpoint - with optional nonce bypass for testing
 */

// Kill absolutely everything
@error_reporting(0);
@ini_set('display_errors', '0');
@ini_set('log_errors', '0');

// Clean all buffers
while (@ob_get_level()) {
    @ob_end_clean();
}

// Function to send JSON and die immediately
function send_json_and_die($data) {
    @header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    die();
}

// Start clean buffer
@ob_start();

// Load WordPress silently
if (!defined('DOING_AJAX')) define('DOING_AJAX', true);
if (!defined('WP_USE_THEMES')) define('WP_USE_THEMES', false);

$wp_load = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
if (!file_exists($wp_load)) {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'WordPress not found')
    ));
}

@require_once($wp_load);
@ob_clean();

// Development mode flag (set to false in production)
$dev_mode = true;
$skip_nonce = isset($_POST['skip_nonce']) && $_POST['skip_nonce'] === 'dev_mode_only';

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'Invalid request method')
    ));
}

// Check nonce (unless in dev mode with skip flag)
if (!$skip_nonce || !$dev_mode) {
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (empty($nonce)) {
        send_json_and_die(array(
            'success' => false,
            'data' => array('message' => 'No security token provided')
        ));
    }
    
    $nonce_valid = wp_verify_nonce($nonce, 'incon_scss_nonce');
    if (!$nonce_valid) {
        // Debug info in dev mode
        if ($dev_mode) {
            send_json_and_die(array(
                'success' => false,
                'data' => array(
                    'message' => 'Security check failed',
                    'debug' => array(
                        'provided_nonce' => $nonce,
                        'expected_action' => 'incon_scss_nonce',
                        'user_id' => get_current_user_id(),
                        'user_logged_in' => is_user_logged_in(),
                        'verify_result' => $nonce_valid
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
}

// Check user permissions
if (!current_user_can('manage_options')) {
    send_json_and_die(array(
        'success' => false,
        'data' => array(
            'message' => 'Insufficient permissions',
            'debug' => $dev_mode ? array(
                'user_id' => get_current_user_id(),
                'user_login' => wp_get_current_user()->user_login,
                'capabilities' => wp_get_current_user()->allcaps
            ) : null
        )
    ));
}

// Load plugin files
$plugin_dir = dirname(__FILE__) . '/';

// Load ScssPhp
if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
    $vendor_autoload = $plugin_dir . 'vendor/autoload.php';
    $scss_lib = $plugin_dir . 'scssphp/scss.inc.php';
    
    if (file_exists($vendor_autoload)) {
        @require_once $vendor_autoload;
    } elseif (file_exists($scss_lib)) {
        @require_once $scss_lib;
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
        'data' => array('message' => 'Compiler class not found at: ' . $compiler_file)
    ));
}

@require_once $compiler_file;

if (!class_exists('InconSCSS_Compiler')) {
    send_json_and_die(array(
        'success' => false,
        'data' => array('message' => 'Compiler class not available')
    ));
}

// Get settings
$settings = get_option('incon_scss_settings', array());
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
    @ob_start();
    
    $compiler = new InconSCSS_Compiler($settings);
    
    if ($file) {
        $result = $compiler->compile_file($file);
    } else {
        $result = $compiler->compile_all();
    }
    
    @ob_clean();
    
    // Add debug info in dev mode
    if ($dev_mode && $skip_nonce) {
        $result['debug'] = array(
            'mode' => 'development',
            'nonce_skipped' => true,
            'user' => wp_get_current_user()->user_login,
            'settings' => $settings
        );
    }
    
    send_json_and_die($result);
    
} catch (Exception $e) {
    @ob_clean();
    
    send_json_and_die(array(
        'success' => false,
        'data' => array(
            'message' => 'Compilation error: ' . $e->getMessage(),
            'debug' => $dev_mode ? array(
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ) : null
        )
    ));
}