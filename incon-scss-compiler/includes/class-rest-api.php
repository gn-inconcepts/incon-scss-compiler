<?php
/**
 * REST API endpoint for SCSS compilation
 */

class InconSCSS_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        register_rest_route('incon-scss/v1', '/compile', array(
            'methods' => 'POST',
            'callback' => array($this, 'compile'),
            'permission_callback' => array($this, 'check_permission'),
            'args' => array(
                'file' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    public function check_permission() {
        return current_user_can('manage_options');
    }
    
    public function compile($request) {
        $file = $request->get_param('file');
        
        // Load compiler
        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
            $scss_lib = INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php';
            $vendor_autoload = INCON_SCSS_PLUGIN_DIR . 'vendor/autoload.php';
            
            if (file_exists($vendor_autoload)) {
                require_once $vendor_autoload;
            } elseif (file_exists($scss_lib)) {
                require_once $scss_lib;
            } else {
                return new WP_Error('missing_library', 'ScssPhp library not found', array('status' => 500));
            }
        }
        
        $compiler_file = INCON_SCSS_PLUGIN_DIR . 'includes/class-compiler.php';
        if (!file_exists($compiler_file)) {
            return new WP_Error('missing_compiler', 'Compiler class not found', array('status' => 500));
        }
        
        if (!class_exists('InconSCSS_Compiler')) {
            require_once $compiler_file;
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
        
        try {
            $compiler = new InconSCSS_Compiler($settings);
            
            if ($file) {
                $result = $compiler->compile_file($file);
            } else {
                $result = $compiler->compile_all();
            }
            
            return rest_ensure_response($result);
            
        } catch (Exception $e) {
            return new WP_Error(
                'compilation_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }
}