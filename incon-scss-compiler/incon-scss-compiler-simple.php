<?php
/**
 * Plugin Name: Incon SCSS Compiler (Simple)
 * Plugin URI: https://github.com/incon/incon-scss-compiler
 * Description: Advanced SCSS compiler for WordPress - Simplified version
 * Version: 1.0.0
 * Author: Incon Development
 * Author URI: https://incon.dev
 * License: GPLv3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('INCON_SCSS_VERSION', '1.0.0');
define('INCON_SCSS_PLUGIN_FILE', __FILE__);
define('INCON_SCSS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('INCON_SCSS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Simple activation function
function incon_scss_simple_activate() {
    // Create cache directory
    $cache_dir = WP_CONTENT_DIR . '/cache/incon-scss/';
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    
    // Save default settings
    $defaults = array(
        'base_dir' => get_stylesheet_directory(),
        'scss_dir' => '/scss/',
        'css_dir' => '/css/',
        'cache_dir' => $cache_dir,
        'output_style' => 'compressed',
        'source_maps' => false
    );
    
    add_option('incon_scss_settings', $defaults);
}

// Simple deactivation function
function incon_scss_simple_deactivate() {
    // Clean up scheduled events if any
    wp_clear_scheduled_hook('incon_scss_cleanup');
}

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'incon_scss_simple_activate');
register_deactivation_hook(__FILE__, 'incon_scss_simple_deactivate');

// Initialize plugin
add_action('init', function() {
    // Load ScssPhp if available
    if (file_exists(INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php')) {
        require_once INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php';
    }
    
    // Add admin menu
    if (is_admin()) {
        add_action('admin_menu', function() {
            add_menu_page(
                'SCSS Compiler',
                'SCSS Compiler',
                'manage_options',
                'incon-scss-simple',
                'incon_scss_simple_admin_page',
                'dashicons-editor-code',
                100
            );
        });
    }
});

// Simple admin page
function incon_scss_simple_admin_page() {
    $settings = get_option('incon_scss_settings', array());
    ?>
    <div class="wrap">
        <h1>SCSS Compiler (Simple)</h1>
        <p>This is a simplified version to test basic functionality.</p>
        
        <?php if (class_exists('ScssPhp\\ScssPhp\\Compiler')): ?>
            <div class="notice notice-success">
                <p>✓ ScssPhp library is loaded and ready.</p>
            </div>
            
            <h2>Quick Compile Test</h2>
            <form method="post">
                <?php wp_nonce_field('incon_scss_compile'); ?>
                <textarea name="scss_input" rows="10" cols="50" style="font-family: monospace;">
$primary: #007cba;
$secondary: #555;

body {
    color: $primary;
    
    h1 {
        color: $secondary;
    }
}</textarea>
                <br><br>
                <input type="submit" name="compile_test" value="Compile SCSS" class="button button-primary">
            </form>
            
            <?php
            if (isset($_POST['compile_test']) && wp_verify_nonce($_POST['_wpnonce'], 'incon_scss_compile')) {
                $scss_input = stripslashes($_POST['scss_input']);
                
                try {
                    $compiler = new ScssPhp\ScssPhp\Compiler();
                    $compiler->setOutputStyle(ScssPhp\ScssPhp\OutputStyle::EXPANDED);
                    
                    $result = $compiler->compileString($scss_input);
                    $css = $result->getCss();
                    
                    echo '<h3>Compiled CSS:</h3>';
                    echo '<pre style="background: #f0f0f0; padding: 10px; border: 1px solid #ddd;">';
                    echo htmlspecialchars($css);
                    echo '</pre>';
                } catch (Exception $e) {
                    echo '<div class="notice notice-error"><p>Compilation error: ' . esc_html($e->getMessage()) . '</p></div>';
                }
            }
            ?>
        <?php else: ?>
            <div class="notice notice-warning">
                <p>ScssPhp library not found. Please check installation.</p>
            </div>
        <?php endif; ?>
        
        <h2>Current Settings</h2>
        <table class="form-table">
            <tr>
                <th>Base Directory:</th>
                <td><?php echo esc_html($settings['base_dir'] ?? 'Not set'); ?></td>
            </tr>
            <tr>
                <th>SCSS Directory:</th>
                <td><?php echo esc_html($settings['scss_dir'] ?? 'Not set'); ?></td>
            </tr>
            <tr>
                <th>CSS Directory:</th>
                <td><?php echo esc_html($settings['css_dir'] ?? 'Not set'); ?></td>
            </tr>
            <tr>
                <th>Cache Directory:</th>
                <td><?php echo esc_html($settings['cache_dir'] ?? 'Not set'); ?></td>
            </tr>
        </table>
    </div>
    <?php
}