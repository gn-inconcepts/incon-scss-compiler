<?php
/**
 * Plugin Name: Incon SCSS Compiler
 * Plugin URI: https://github.com/incon/incon-scss-compiler
 * Description: Advanced SCSS compiler for WordPress with real-time compilation, autoprefixer, and modern features
 * Version: 1.0.0
 * Author: Incon Development
 * Author URI: https://incon.dev
 * License: GPLv3
 * Text Domain: incon-scss
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('INCON_SCSS_VERSION')) {
    define('INCON_SCSS_VERSION', '1.0.0');
}
if (!defined('INCON_SCSS_PLUGIN_FILE')) {
    define('INCON_SCSS_PLUGIN_FILE', __FILE__);
}
if (!defined('INCON_SCSS_PLUGIN_DIR')) {
    define('INCON_SCSS_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('INCON_SCSS_PLUGIN_URL')) {
    define('INCON_SCSS_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('INCON_SCSS_PLUGIN_BASENAME')) {
    define('INCON_SCSS_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Main plugin class
if (!class_exists('InconSCSSCompiler')) {
class InconSCSSCompiler {
    
    private static $instance = null;
    private $compiler = null;
    private $settings = array();
    private $compile_queue = array();
    private $dependencies = array();
    private $stats = array();
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Load settings first
        $this->set_default_settings();
        // Then load dependencies only if not during activation
        if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
            $this->load_dependencies();
            
            // Initialize REST API
            require_once INCON_SCSS_PLUGIN_DIR . 'includes/class-rest-api.php';
            new InconSCSS_REST_API();
            
            // Initialize updater
            $this->init_updater();
            
            $this->init_hooks();
        }
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        
        add_action('wp_ajax_incon_scss_compile', array($this, 'ajax_compile'));
        add_action('wp_ajax_incon_scss_test', array($this, 'ajax_test'));
        add_action('wp_ajax_incon_scss_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_incon_scss_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_incon_scss_get_file_content', array($this, 'ajax_get_file_content'));
        add_action('wp_ajax_incon_scss_save_file', array($this, 'ajax_save_file'));
        add_action('wp_ajax_incon_scss_get_dependencies', array($this, 'ajax_get_dependencies'));
        add_action('wp_ajax_incon_scss_check_changes', array($this, 'ajax_check_changes'));
        add_action('wp_ajax_incon_scss_save_quick_settings', array($this, 'ajax_save_quick_settings'));
        
        add_filter('plugin_action_links_' . INCON_SCSS_PLUGIN_BASENAME, array($this, 'add_action_links'));
        
        add_action('wp_loaded', array($this, 'check_compilation_needed'));
        
        add_filter('incon_scss_variables', array($this, 'default_variables'));
        add_filter('incon_scss_import_paths', array($this, 'default_import_paths'));
    }
    
    private function load_dependencies() {
        // Check if required classes exist before loading
        $required_files = array(
            'includes/class-settings.php',
            'includes/class-cache.php',
            'includes/class-dependency-tracker.php',
            'includes/class-file-watcher.php',
            'includes/class-postcss.php',
            'includes/class-error-handler.php'
        );
        
        foreach ($required_files as $file) {
            $filepath = INCON_SCSS_PLUGIN_DIR . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            }
        }
        
        // Load compiler class only if dependencies are available
        if (file_exists(INCON_SCSS_PLUGIN_DIR . 'includes/class-compiler.php')) {
            // Check for ScssPhp library first
            if (file_exists(INCON_SCSS_PLUGIN_DIR . 'vendor/autoload.php')) {
                require_once INCON_SCSS_PLUGIN_DIR . 'vendor/autoload.php';
            } else if (file_exists(INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php')) {
                // Fallback to bundled version
                require_once INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php';
            }
            
            if (class_exists('ScssPhp\ScssPhp\Compiler') || file_exists(INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php')) {
                require_once INCON_SCSS_PLUGIN_DIR . 'includes/class-compiler.php';
            }
        }
    }
    
    private function set_default_settings() {
        $defaults = array(
            'base_dir' => get_stylesheet_directory(),
            'scss_dir' => '/scss/',
            'css_dir' => '/css/',
            'cache_dir' => WP_CONTENT_DIR . '/cache/incon-scss/',
            'output_style' => 'compressed',
            'source_maps' => true,
            'source_map_type' => 'file',
            'autoprefixer' => true,
            'browsers_list' => 'last 2 versions',
            'minify' => true,
            'remove_unused_css' => false,
            'css_modules' => false,
            'hot_reload' => false,
            'error_display' => 'admin',
            'enqueue_compiled' => true,
            'watch_enabled' => false,
            'watch_interval' => 1000,
            'preserve_comments' => false,
            'postcss_plugins' => array(),
            'custom_functions' => true,
            'dependency_tracking' => true,
            'import_paths' => array(),
            'variables' => array(),
            'compile_on_save' => true,
            'batch_compile' => false,
            'async_compile' => false,
            'notification_email' => '',
            'max_execution_time' => 60,
            'memory_limit' => '256M',
            'update_source' => 'github',
            'github_username' => 'incon',
            'github_repo' => 'incon-scss-compiler',
            'update_server_url' => '',
            'license_key' => ''
        );
        
        $saved_settings = get_option('incon_scss_settings', array());
        $this->settings = wp_parse_args($saved_settings, $defaults);
    }
    
    private function init_updater() {
        $update_source = get_option('incon_scss_update_source', 'github');
        
        if ($update_source === 'github') {
            if (file_exists(INCON_SCSS_PLUGIN_DIR . 'includes/class-github-updater.php')) {
                require_once INCON_SCSS_PLUGIN_DIR . 'includes/class-github-updater.php';
                
                $config = array(
                    'github_username' => get_option('incon_scss_github_username', 'incon'),
                    'github_repo' => get_option('incon_scss_github_repo', 'incon-scss-compiler'),
                    'github_token' => get_option('incon_scss_github_token', ''),
                );
                
                new Incon_SCSS_GitHub_Updater(INCON_SCSS_PLUGIN_FILE, $config);
            }
        } elseif ($update_source === 'custom') {
            if (file_exists(INCON_SCSS_PLUGIN_DIR . 'includes/class-updater.php')) {
                require_once INCON_SCSS_PLUGIN_DIR . 'includes/class-updater.php';
                new Incon_SCSS_Updater(INCON_SCSS_PLUGIN_FILE);
            }
        }
    }
    
    // These methods are no longer needed as activation is handled outside the class
    // public function activate() { }
    // public function deactivate() { }
    
    private function create_cache_directory() {
        if (empty($this->settings['cache_dir'])) {
            return;
        }
        
        $cache_dir = $this->settings['cache_dir'];
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
            
            // Only create .htaccess if directory was created successfully
            if (file_exists($cache_dir) && is_writable($cache_dir)) {
                $htaccess_content = "Order Deny,Allow\nDeny from all";
                @file_put_contents($cache_dir . '.htaccess', $htaccess_content);
            }
        }
    }
    
    // Database table creation is now handled in the activation function
    // private function create_database_tables() { }
    
    public function init() {
        load_plugin_textdomain('incon-scss', false, dirname(INCON_SCSS_PLUGIN_BASENAME) . '/languages');
        
        // Refresh settings in case they've been updated
        $this->settings = get_option('incon_scss_settings', $this->settings);
        
        // Check for ScssPhp library
        $has_scssphp = class_exists('ScssPhp\ScssPhp\Compiler') || 
                       file_exists(INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php');
        
        if (!$has_scssphp && is_admin()) {
            add_action('admin_notices', array($this, 'show_dependency_notice'));
        }
        
        // Load ScssPhp if not already loaded
        if (!class_exists('ScssPhp\ScssPhp\Compiler') && file_exists(INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php')) {
            require_once INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php';
        }
        
        // Don't initialize compiler here - do it on demand
        
        if ($this->settings['watch_enabled'] && !is_admin() && class_exists('InconSCSS_FileWatcher')) {
            $watcher = new InconSCSS_FileWatcher($this->settings);
            $watcher->init();
        }
    }
    
    public function show_dependency_notice() {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . __('Incon SCSS Compiler:', 'incon-scss') . '</strong> ';
        echo __('The ScssPhp library is bundled for basic functionality. For best performance, run:', 'incon-scss');
        echo '<br><code>cd ' . INCON_SCSS_PLUGIN_DIR . ' && composer require scssphp/scssphp</code></p>';
        echo '</div>';
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('SCSS Compiler', 'incon-scss'),
            __('SCSS Compiler', 'incon-scss'),
            'manage_options',
            'incon-scss-compiler',
            array($this, 'admin_page'),
            'dashicons-editor-code',
            100
        );
        
        add_submenu_page(
            'incon-scss-compiler',
            __('Settings', 'incon-scss'),
            __('Settings', 'incon-scss'),
            'manage_options',
            'incon-scss-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'incon-scss-compiler',
            __('Statistics', 'incon-scss'),
            __('Statistics', 'incon-scss'),
            'manage_options',
            'incon-scss-stats',
            array($this, 'stats_page')
        );
        
        add_submenu_page(
            'incon-scss-compiler',
            __('Dependencies', 'incon-scss'),
            __('Dependencies', 'incon-scss'),
            'manage_options',
            'incon-scss-dependencies',
            array($this, 'dependencies_page')
        );
    }
    
    public function register_settings() {
        register_setting('incon_scss_settings_group', 'incon_scss_settings', array($this, 'sanitize_settings'));
    }
    
    public function sanitize_settings($input) {
        // Get current settings to preserve unsubmitted values
        $current = get_option('incon_scss_settings', array());
        $sanitized = $current;
        
        // Update submitted values
        if (isset($input['base_dir'])) {
            $sanitized['base_dir'] = sanitize_text_field($input['base_dir']);
        }
        if (isset($input['scss_dir'])) {
            $sanitized['scss_dir'] = $this->sanitize_path($input['scss_dir']);
        }
        if (isset($input['css_dir'])) {
            $sanitized['css_dir'] = $this->sanitize_path($input['css_dir']);
        }
        if (isset($input['cache_dir'])) {
            $sanitized['cache_dir'] = sanitize_text_field($input['cache_dir']);
        }
        if (isset($input['output_style'])) {
            $sanitized['output_style'] = in_array($input['output_style'], array('compressed', 'expanded')) 
                ? $input['output_style'] : 'compressed';
        }
        
        // Handle checkboxes (they're only sent if checked)
        $checkboxes = array(
            'source_maps', 'autoprefixer', 'minify', 'remove_unused_css',
            'css_modules', 'hot_reload', 'enqueue_compiled', 'watch_enabled',
            'dependency_tracking', 'custom_functions',
            'compile_on_save'
        );
        
        foreach ($checkboxes as $checkbox) {
            $sanitized[$checkbox] = !empty($input[$checkbox]);
        }
        
        if (isset($input['error_display'])) {
            $sanitized['error_display'] = sanitize_text_field($input['error_display']);
        }
        
        // Preserve other settings
        $sanitized['source_map_type'] = $current['source_map_type'] ?? 'file';
        $sanitized['browsers_list'] = $current['browsers_list'] ?? 'last 2 versions';
        $sanitized['watch_interval'] = $current['watch_interval'] ?? 1000;
        $sanitized['postcss_plugins'] = $current['postcss_plugins'] ?? array();
        $sanitized['import_paths'] = $current['import_paths'] ?? array();
        $sanitized['variables'] = $current['variables'] ?? array();
        
        return $sanitized;
    }
    
    private function sanitize_path($path) {
        $path = sanitize_text_field($path);
        if (!empty($path)) {
            if (substr($path, 0, 1) !== '/') {
                $path = '/' . $path;
            }
            if (substr($path, -1) !== '/') {
                $path .= '/';
            }
        }
        return $path;
    }
    
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'incon-scss') === false) {
            return;
        }
        
        wp_enqueue_style('incon-scss-admin', INCON_SCSS_PLUGIN_URL . 'assets/css/admin.css', array(), INCON_SCSS_VERSION);
        wp_enqueue_script('incon-scss-admin', INCON_SCSS_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'wp-api'), INCON_SCSS_VERSION, true);
        
        wp_localize_script('incon-scss-admin', 'inconScss', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'directAjaxUrl' => plugins_url('ajax-compile.php', __FILE__),
            'pluginUrl' => plugin_dir_url(__FILE__),
            'nonce' => wp_create_nonce('incon_scss_nonce'),
            'strings' => array(
                'compiling' => __('Compiling...', 'incon-scss'),
                'compiled' => __('Compiled successfully!', 'incon-scss'),
                'error' => __('Compilation error', 'incon-scss'),
                'clearCache' => __('Clear Cache', 'incon-scss'),
                'cacheCleared' => __('Cache cleared!', 'incon-scss')
            ),
            'settings' => $this->settings
        ));
        
        if ($hook === 'toplevel_page_incon-scss-compiler') {
            wp_enqueue_code_editor(array('type' => 'text/css'));
            wp_enqueue_style('wp-codemirror');
        }
        
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
    }
    
    public function frontend_enqueue_scripts() {
        if ($this->settings['enqueue_compiled']) {
            $css_dir = $this->settings['base_dir'] . $this->settings['css_dir'];
            $css_url = $this->get_base_url() . $this->settings['css_dir'];
            
            if (is_dir($css_dir)) {
                $files = glob($css_dir . '*.css');
                foreach ($files as $file) {
                    $handle = 'incon-scss-' . basename($file, '.css');
                    $url = $css_url . basename($file);
                    $version = filemtime($file);
                    
                    wp_enqueue_style($handle, $url, array(), $version);
                }
            }
        }
        
        if ($this->settings['hot_reload'] && current_user_can('manage_options')) {
            wp_enqueue_script('incon-scss-hot-reload', INCON_SCSS_PLUGIN_URL . 'assets/js/hot-reload.js', array(), INCON_SCSS_VERSION, true);
            wp_localize_script('incon-scss-hot-reload', 'inconScssHotReload', array(
                'interval' => $this->settings['watch_interval'],
                'files' => $this->get_watched_files()
            ));
        }
    }
    
    private function get_base_url() {
        $base_dir = $this->settings['base_dir'];
        
        if ($base_dir === get_stylesheet_directory()) {
            return get_stylesheet_directory_uri();
        } elseif ($base_dir === get_template_directory()) {
            return get_template_directory_uri();
        } elseif (strpos($base_dir, WP_CONTENT_DIR) === 0) {
            return content_url(str_replace(WP_CONTENT_DIR, '', $base_dir));
        } elseif (strpos($base_dir, WP_PLUGIN_DIR) === 0) {
            return plugins_url(str_replace(WP_PLUGIN_DIR, '', $base_dir));
        }
        
        return '';
    }
    
    private function get_watched_files() {
        $scss_dir = $this->settings['base_dir'] . $this->settings['scss_dir'];
        $files = array();
        
        if (is_dir($scss_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($scss_dir),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'scss') {
                    $files[] = array(
                        'path' => $file->getPathname(),
                        'mtime' => $file->getMTime()
                    );
                }
            }
        }
        
        return $files;
    }
    
    public function check_compilation_needed() {
        // Only run if compile_on_save is explicitly enabled
        if (!isset($this->settings['compile_on_save']) || !$this->settings['compile_on_save']) {
            return;
        }
        
        // Make sure compiler class is available
        if (!class_exists('InconSCSS_Compiler')) {
            $compiler_file = INCON_SCSS_PLUGIN_DIR . 'includes/class-compiler.php';
            if (file_exists($compiler_file)) {
                require_once $compiler_file;
            } else {
                return;
            }
        }
        
        if (!class_exists('InconSCSS_Compiler')) {
            return;
        }
        
        try {
            $compiler = new InconSCSS_Compiler($this->settings);
            if ($compiler->needs_compilation()) {
                $compiler->compile_all();
            }
        } catch (Exception $e) {
            error_log('SCSS auto-compile error: ' . $e->getMessage());
        }
    }
    
    public function ajax_test() {
        // Simple test endpoint
        wp_send_json_success(array(
            'message' => 'AJAX is working!',
            'time' => current_time('mysql'),
            'user' => wp_get_current_user()->user_login
        ));
    }
    
    public function ajax_compile() {
        // Completely bypass WordPress's JSON functions and send raw JSON
        
        // Kill all output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Disable all error reporting
        error_reporting(0);
        @ini_set('display_errors', 0);
        @ini_set('log_errors', 0);
        
        // Set headers
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        
        // Function to send JSON and immediately exit
        $send_json = function($data) {
            echo json_encode($data);
            // Multiple exit strategies to ensure we stop
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            exit(0);
        };
        
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'incon_scss_nonce')) {
            $send_json(array(
                'success' => false,
                'data' => array('message' => 'Security check failed')
            ));
        }
        
        if (!current_user_can('manage_options')) {
            $send_json(array(
                'success' => false,
                'data' => array('message' => 'Insufficient permissions')
            ));
        }
        
        // Load ScssPhp if needed
        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
            $scss_lib = INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php';
            if (file_exists($scss_lib)) {
                @require_once $scss_lib;
            } else {
                $send_json(array(
                    'success' => false,
                    'data' => array('message' => 'ScssPhp library not found')
                ));
            }
        }
        
        // Check if compiler class file exists
        $compiler_file = INCON_SCSS_PLUGIN_DIR . 'includes/class-compiler.php';
        if (!file_exists($compiler_file)) {
            $send_json(array(
                'success' => false,
                'data' => array('message' => 'Compiler file not found')
            ));
        }
        
        // Include the compiler file if class doesn't exist
        if (!class_exists('InconSCSS_Compiler')) {
            @require_once $compiler_file;
        }
        
        if (!class_exists('InconSCSS_Compiler')) {
            $send_json(array(
                'success' => false,
                'data' => array('message' => 'Compiler class not available')
            ));
        }
        
        $file = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : '';
        
        try {
            // Start output buffering to catch any output from compilation
            ob_start();
            
            $compiler = new InconSCSS_Compiler($this->settings);
            
            if ($file) {
                $result = $compiler->compile_file($file);
            } else {
                $result = $compiler->compile_all();
            }
            
            // Discard any output
            ob_end_clean();
            
            // Send the result
            $send_json($result);
            
        } catch (Exception $e) {
            // Discard any output
            @ob_end_clean();
            
            $send_json(array(
                'success' => false,
                'data' => array('message' => 'Compilation error: ' . $e->getMessage())
            ));
        }
    }
    
    public function ajax_get_stats() {
        check_ajax_referer('incon_scss_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'incon-scss'));
        }
        
        if (!class_exists('InconSCSS_Statistics')) {
            wp_send_json_success(array());
            return;
        }
        
        $stats = new InconSCSS_Statistics();
        $data = $stats->get_stats();
        
        wp_send_json_success($data);
    }
    
    public function ajax_clear_cache() {
        check_ajax_referer('incon_scss_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'incon-scss'));
        }
        
        if (!class_exists('InconSCSS_Cache')) {
            wp_send_json_error(array('message' => __('Cache handler not available', 'incon-scss')));
            return;
        }
        
        $cache = new InconSCSS_Cache($this->settings['cache_dir']);
        $result = $cache->clear();
        
        wp_send_json_success(array(
            'message' => __('Cache cleared successfully', 'incon-scss'),
            'cleared' => $result
        ));
    }
    
    public function admin_page() {
        $file = INCON_SCSS_PLUGIN_DIR . 'views/admin-dashboard.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>' . __('SCSS Compiler', 'incon-scss') . '</h1>';
            echo '<p>' . __('Dashboard view not found.', 'incon-scss') . '</p></div>';
        }
    }
    
    public function settings_page() {
        $file = INCON_SCSS_PLUGIN_DIR . 'views/admin-settings.php';
        if (file_exists($file)) {
            include $file;
        } else {
            // Basic settings form
            echo '<div class="wrap">';
            echo '<h1>' . __('SCSS Compiler Settings', 'incon-scss') . '</h1>';
            echo '<form method="post" action="options.php">';
            settings_fields('incon_scss_settings_group');
            echo '<table class="form-table"><tbody>';
            echo '<tr><th>' . __('Base Directory', 'incon-scss') . '</th>';
            echo '<td><input type="text" name="incon_scss_settings[base_dir]" value="' . esc_attr($this->settings['base_dir']) . '" class="regular-text" /></td></tr>';
            echo '<tr><th>' . __('SCSS Directory', 'incon-scss') . '</th>';
            echo '<td><input type="text" name="incon_scss_settings[scss_dir]" value="' . esc_attr($this->settings['scss_dir']) . '" class="regular-text" /></td></tr>';
            echo '<tr><th>' . __('CSS Directory', 'incon-scss') . '</th>';
            echo '<td><input type="text" name="incon_scss_settings[css_dir]" value="' . esc_attr($this->settings['css_dir']) . '" class="regular-text" /></td></tr>';
            echo '</tbody></table>';
            submit_button();
            echo '</form></div>';
        }
    }
    
    public function stats_page() {
        $file = INCON_SCSS_PLUGIN_DIR . 'views/admin-stats.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>' . __('Compilation Statistics', 'incon-scss') . '</h1>';
            echo '<p>' . __('Statistics view not found.', 'incon-scss') . '</p></div>';
        }
    }
    
    public function dependencies_page() {
        $file = INCON_SCSS_PLUGIN_DIR . 'views/admin-dependencies.php';
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>' . __('Dependencies', 'incon-scss') . '</h1>';
            echo '<p>' . __('Dependencies view not found.', 'incon-scss') . '</p></div>';
        }
    }
    
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=incon-scss-settings') . '">' . __('Settings', 'incon-scss') . '</a>';
        $compile_link = '<a href="' . admin_url('admin.php?page=incon-scss-compiler') . '">' . __('Compile', 'incon-scss') . '</a>';
        
        array_unshift($links, $settings_link, $compile_link);
        
        return $links;
    }
    
    public function ajax_get_file_content() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'incon_scss_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $file = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : '';
        
        if (!$file || !file_exists($file)) {
            wp_send_json_error(array('message' => 'File not found'));
            return;
        }
        
        $content = file_get_contents($file);
        wp_send_json_success(array('content' => $content));
    }
    
    public function ajax_save_file() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'incon_scss_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $file = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : '';
        $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        
        if (!$file || !file_exists($file)) {
            wp_send_json_error(array('message' => 'File not found'));
            return;
        }
        
        if (file_put_contents($file, $content) !== false) {
            wp_send_json_success(array('message' => 'File saved successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save file'));
        }
    }
    
    public function ajax_get_dependencies() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'incon_scss_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $file = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : '';
        
        if (class_exists('InconSCSS_DependencyTracker')) {
            $tracker = new InconSCSS_DependencyTracker();
            $deps = $tracker->get_all_dependencies($file);
            wp_send_json_success(array('dependencies' => $deps));
        } else {
            wp_send_json_error(array('message' => 'Dependency tracker not available'));
        }
    }
    
    public function ajax_check_changes() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'incon_scss_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (class_exists('InconSCSS_FileWatcher')) {
            $watcher = new InconSCSS_FileWatcher($this->settings);
            $changed = $watcher->check_for_changes();
            wp_send_json(array('changed' => $changed));
        } else {
            wp_send_json(array('changed' => false));
        }
    }
    
    public function ajax_save_quick_settings() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'incon_scss_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Update quick settings
        $this->settings['source_maps'] = !empty($_POST['source_maps']);
        $this->settings['autoprefixer'] = !empty($_POST['autoprefixer']);
        $this->settings['minify'] = !empty($_POST['minify']);
        $this->settings['output_style'] = sanitize_text_field($_POST['output_style'] ?? 'compressed');
        
        update_option('incon_scss_settings', $this->settings);
        wp_send_json_success(array('message' => 'Settings saved'));
    }
    
    public function default_variables($variables) {
        $defaults = array(
            'primary-color' => get_theme_mod('primary_color', '#007cba'),
            'secondary-color' => get_theme_mod('secondary_color', '#6c757d'),
            'font-family' => get_theme_mod('font_family', '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif'),
            'base-font-size' => get_theme_mod('base_font_size', '16px'),
            'container-width' => get_theme_mod('container_width', '1140px'),
            'breakpoint-xs' => '0',
            'breakpoint-sm' => '576px',
            'breakpoint-md' => '768px',
            'breakpoint-lg' => '992px',
            'breakpoint-xl' => '1200px',
            'breakpoint-xxl' => '1400px'
        );
        
        return wp_parse_args($variables, $defaults);
    }
    
    public function default_import_paths($paths) {
        $default_paths = array(
            $this->settings['base_dir'] . $this->settings['scss_dir'],
            $this->settings['base_dir'] . $this->settings['scss_dir'] . 'vendor/',
            $this->settings['base_dir'] . $this->settings['scss_dir'] . 'components/',
            $this->settings['base_dir'] . $this->settings['scss_dir'] . 'layouts/',
            $this->settings['base_dir'] . $this->settings['scss_dir'] . 'utilities/',
            INCON_SCSS_PLUGIN_DIR . 'scss-library/'
        );
        
        return array_merge($paths, $default_paths);
    }
} // End of class InconSCSSCompiler
} // End of class_exists check

// Helper function to get plugin instance
if (!function_exists('incon_scss_compiler')) {
    function incon_scss_compiler() {
        if (class_exists('InconSCSSCompiler')) {
            return InconSCSSCompiler::get_instance();
        }
        return null;
    }
}

// Simple activation without class dependency
function incon_scss_activate() {
    // Ensure WordPress functions are available
    if (!function_exists('wp_mkdir_p')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    // Create cache directory
    $cache_dir = WP_CONTENT_DIR . '/cache/incon-scss/';
    if (!file_exists($cache_dir)) {
        wp_mkdir_p($cache_dir);
    }
    
    // Set default options
    $defaults = array(
        'base_dir' => get_stylesheet_directory(),
        'scss_dir' => '/scss/',
        'css_dir' => '/css/',
        'cache_dir' => $cache_dir,
        'output_style' => 'compressed',
        'source_maps' => true,
        'source_map_type' => 'file',
        'autoprefixer' => true,
        'browsers_list' => 'last 2 versions',
        'minify' => true,
        'remove_unused_css' => false,
        'css_modules' => false,
        'hot_reload' => false,
        'error_display' => 'admin',
        'enqueue_compiled' => true,
        'watch_enabled' => false,
        'watch_interval' => 1000,
        'preserve_comments' => false,
        'postcss_plugins' => array(),
        'custom_functions' => true,
        'statistics' => true,
        'dependency_tracking' => true,
        'import_paths' => array(),
        'variables' => array(),
        'compile_on_save' => true,
        'batch_compile' => false,
        'async_compile' => false,
        'notification_email' => '',
        'max_execution_time' => 60,
        'memory_limit' => '256M'
    );
    
    add_option('incon_scss_settings', $defaults);
    
    // Try to create database tables
    incon_scss_create_tables();
}

// Simple deactivation
function incon_scss_deactivate() {
    wp_clear_scheduled_hook('incon_scss_cleanup');
}

// Create database tables separately
function incon_scss_create_tables() {
    try {
        global $wpdb;
        
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Stats table
        $table_name = $wpdb->prefix . 'incon_scss_stats';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            compile_time float NOT NULL,
            file_size bigint(20) NOT NULL,
            memory_used bigint(20) NOT NULL,
            errors text,
            compiled_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        dbDelta($sql);
        
        // Dependencies table
        $dependencies_table = $wpdb->prefix . 'incon_scss_dependencies';
        $sql = "CREATE TABLE IF NOT EXISTS {$dependencies_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            parent_file varchar(255) NOT NULL,
            dependency_file varchar(255) NOT NULL,
            dependency_type varchar(50) NOT NULL,
            last_modified datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        dbDelta($sql);
    } catch (Exception $e) {
        error_log('Incon SCSS: Table creation error: ' . $e->getMessage());
    }
}

// Register hooks
register_activation_hook(INCON_SCSS_PLUGIN_FILE, 'incon_scss_activate');
register_deactivation_hook(INCON_SCSS_PLUGIN_FILE, 'incon_scss_deactivate');

// Initialize plugin after WordPress is loaded
add_action('plugins_loaded', function() {
    // Load the main class file if it exists
    if (file_exists(INCON_SCSS_PLUGIN_DIR . 'includes/class-main.php')) {
        require_once INCON_SCSS_PLUGIN_DIR . 'includes/class-main.php';
    }
    
    // Initialize the plugin
    if (class_exists('InconSCSSCompiler')) {
        InconSCSSCompiler::get_instance();
    }
}, 10);