<?php

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use ScssPhp\ScssPhp\ValueConverter;

class InconSCSS_Compiler {
    
    private $settings;
    private $scss_compiler;
    private $errors = array();
    private $compiled_files = array();
    private $dependencies = array();
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init_compiler();
    }
    
    private function init_compiler() {
        if (!class_exists('ScssPhp\ScssPhp\Compiler')) {
            // Try vendor autoload first
            if (file_exists(INCON_SCSS_PLUGIN_DIR . 'vendor/autoload.php')) {
                require_once INCON_SCSS_PLUGIN_DIR . 'vendor/autoload.php';
            }
            // Fallback to bundled ScssPhp
            else if (file_exists(INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php')) {
                require_once INCON_SCSS_PLUGIN_DIR . 'scssphp/scss.inc.php';
            }
        }
        
        $this->scss_compiler = new Compiler();
        
        $output_style = $this->get_output_style();
        $this->scss_compiler->setOutputStyle($output_style);
        
        $import_paths = apply_filters('incon_scss_import_paths', array());
        foreach ($import_paths as $path) {
            if (is_dir($path)) {
                $this->scss_compiler->addImportPath($path);
            }
        }
        
        $variables = apply_filters('incon_scss_variables', array());
        if (!empty($variables)) {
            $parsed_vars = array();
            foreach ($variables as $key => $value) {
                try {
                    $parsed_vars[$key] = ValueConverter::parseValue($value);
                } catch (Exception $e) {
                    $this->add_error($key, $e->getMessage());
                }
            }
            $this->scss_compiler->addVariables($parsed_vars);
        }
        
        if ($this->settings['source_maps']) {
            $this->configure_source_maps();
        }
        
        if (isset($this->settings['custom_functions']) && $this->settings['custom_functions']) {
            $this->register_custom_functions();
        }
    }
    
    private function get_output_style() {
        switch ($this->settings['output_style']) {
            case 'expanded':
                return OutputStyle::EXPANDED;
            case 'compressed':
            default:
                return OutputStyle::COMPRESSED;
        }
    }
    
    private function configure_source_maps() {
        $map_type = constant('ScssPhp\ScssPhp\Compiler::SOURCE_MAP_' . strtoupper($this->settings['source_map_type']));
        $this->scss_compiler->setSourceMap($map_type);
        
        if ($this->settings['source_map_type'] === 'file') {
            $css_dir = $this->settings['base_dir'] . $this->settings['css_dir'];
            $this->scss_compiler->setSourceMapOptions(array(
                'sourceMapWriteTo' => $css_dir,
                'sourceMapURL' => $this->settings['css_dir'],
                'sourceMapBasepath' => $this->settings['base_dir'],
                'sourceRoot' => home_url('/')
            ));
        }
    }
    
    private function register_custom_functions() {
        if (!$this->settings['custom_functions']) {
            return;
        }
        
        $this->scss_compiler->registerFunction('wp-option', function($args) {
            $option_name = $this->scss_compiler->assertString($args[0])->getValue();
            $default = isset($args[1]) ? $this->scss_compiler->assertString($args[1])->getValue() : '';
            $value = get_option($option_name, $default);
            return ValueConverter::parseValue($value);
        });
        
        $this->scss_compiler->registerFunction('theme-mod', function($args) {
            $mod_name = $this->scss_compiler->assertString($args[0])->getValue();
            $default = isset($args[1]) ? $this->scss_compiler->assertString($args[1])->getValue() : '';
            $value = get_theme_mod($mod_name, $default);
            return ValueConverter::parseValue($value);
        });
        
        $this->scss_compiler->registerFunction('asset-url', function($args) {
            $path = $this->scss_compiler->assertString($args[0])->getValue();
            $url = $this->get_asset_url($path);
            return ValueConverter::parseValue("url('$url')");
        });
        
        $this->scss_compiler->registerFunction('contrast-color', function($args) {
            $color = $this->scss_compiler->assertColor($args[0]);
            $dark = isset($args[1]) ? $this->scss_compiler->assertColor($args[1]) : ValueConverter::parseValue('#000');
            $light = isset($args[2]) ? $this->scss_compiler->assertColor($args[2]) : ValueConverter::parseValue('#fff');
            
            $rgb = $color->toRGB();
            $brightness = ($rgb[0] * 299 + $rgb[1] * 587 + $rgb[2] * 114) / 1000;
            
            return $brightness > 128 ? $dark : $light;
        });
        
        do_action('incon_scss_register_functions', $this->scss_compiler);
    }
    
    private function get_asset_url($path) {
        $base_url = '';
        $base_dir = $this->settings['base_dir'];
        
        if ($base_dir === get_stylesheet_directory()) {
            $base_url = get_stylesheet_directory_uri();
        } elseif ($base_dir === get_template_directory()) {
            $base_url = get_template_directory_uri();
        } elseif (strpos($base_dir, WP_CONTENT_DIR) === 0) {
            $base_url = content_url(str_replace(WP_CONTENT_DIR, '', $base_dir));
        }
        
        return $base_url . '/' . ltrim($path, '/');
    }
    
    public function compile_all() {
        $scss_dir = $this->settings['base_dir'] . $this->settings['scss_dir'];
        
        if (!is_dir($scss_dir)) {
            return array(
                'success' => false,
                'data' => array(
                    'message' => 'SCSS directory not found: ' . $scss_dir
                )
            );
        }
        
        $files = $this->get_scss_files($scss_dir);
        
        if (empty($files)) {
            return array(
                'success' => false,
                'data' => array(
                    'message' => 'No SCSS files found in: ' . $scss_dir
                )
            );
        }
        
        $results = array();
        $compiled_count = 0;
        
        foreach ($files as $file) {
            if (substr(basename($file), 0, 1) !== '_') {
                $result = $this->compile_file($file);
                $results[] = $result;
                if ($result['success']) {
                    $compiled_count++;
                }
            }
        }
        
        return array(
            'success' => $compiled_count > 0,
            'message' => $compiled_count > 0 ? "Compiled $compiled_count files" : 'No files compiled',
            'files' => $results
        );
    }
    
    public function compile_file($scss_file) {
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        if (!file_exists($scss_file)) {
            return array(
                'success' => false,
                'file' => basename($scss_file),
                'message' => 'File not found: ' . $scss_file
            );
        }
        
        $scss_content = file_get_contents($scss_file);
        if ($scss_content === false) {
            return array(
                'success' => false,
                'file' => basename($scss_file),
                'message' => 'Could not read file: ' . $scss_file
            );
        }
        
        $css_filename = str_replace('.scss', '.css', basename($scss_file));
        $css_file = $this->settings['base_dir'] . $this->settings['css_dir'] . $css_filename;
        
        try {
            $this->track_dependencies($scss_file);
            
            $result = $this->scss_compiler->compileString($scss_content, $scss_file);
            $css = $result->getCss();
            
            if ($this->settings['autoprefixer']) {
                $css = $this->apply_autoprefixer($css);
            }
            
            if ($this->settings['minify']) {
                $css = $this->minify_css($css);
            }
            
            if ($this->settings['remove_unused_css']) {
                $css = $this->remove_unused_css($css);
            }
            
            $css_dir = dirname($css_file);
            if (!is_dir($css_dir)) {
                if (!wp_mkdir_p($css_dir)) {
                    throw new Exception('Could not create CSS directory: ' . $css_dir);
                }
            }
            
            if (!is_writable($css_dir)) {
                throw new Exception('CSS directory is not writable: ' . $css_dir);
            }
            
            $bytes_written = file_put_contents($css_file, $css);
            if ($bytes_written === false) {
                throw new Exception('Could not write CSS file: ' . $css_file);
            }
            
            if ($this->settings['source_maps'] && $this->settings['source_map_type'] === 'file') {
                $map = $result->getSourceMap();
                if ($map) {
                    file_put_contents($css_file . '.map', $map);
                }
            }
            
            $compile_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage() - $start_memory;
            
            return array(
                'success' => true,
                'file' => basename($scss_file),
                'output' => basename($css_file),
                'size' => $this->format_bytes(strlen($css)),
                'time' => round($compile_time * 1000, 2) . 'ms',
                'memory' => $this->format_bytes($memory_used)
            );
            
        } catch (Exception $e) {
            $this->add_error($scss_file, $e->getMessage());
            
            return array(
                'success' => false,
                'file' => basename($scss_file),
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            );
        }
    }
    
    private function apply_autoprefixer($css) {
        if (!class_exists('Sabberworm\CSS\Parser')) {
            return $css;
        }
        
        try {
            $parser = new Sabberworm\CSS\Parser($css);
            $doc = $parser->parse();
            
            $prefixes = array('-webkit-', '-moz-', '-ms-', '-o-');
            $properties_to_prefix = array(
                'transform', 'transition', 'animation', 'box-shadow',
                'border-radius', 'flex', 'flex-direction', 'justify-content',
                'align-items', 'user-select', 'appearance', 'backdrop-filter'
            );
            
            foreach ($doc->getAllRuleSets() as $rule_set) {
                foreach ($rule_set->getRules() as $rule) {
                    $property = $rule->getRule();
                    if (in_array($property, $properties_to_prefix)) {
                        $value = $rule->getValue();
                        foreach ($prefixes as $prefix) {
                            $prefixed_property = $prefix . $property;
                            if (!$rule_set->getRules($prefixed_property)) {
                                $new_rule = clone $rule;
                                $new_rule->setRule($prefixed_property);
                                $rule_set->addRule($new_rule);
                            }
                        }
                    }
                }
            }
            
            return $doc->render();
            
        } catch (Exception $e) {
            $this->add_error('autoprefixer', $e->getMessage());
            return $css;
        }
    }
    
    private function minify_css($css) {
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        $css = str_replace(array("\r\n", "\r", "\n", "\t"), '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace(array('; ', ': ', ' {', '{ ', ' }', '} ', ' ,', ', '), array(';', ':', '{', '{', '}', '}', ',', ','), $css);
        
        return trim($css);
    }
    
    private function remove_unused_css($css) {
        return $css;
    }
    
    private function track_dependencies($file) {
        if (!$this->settings['dependency_tracking']) {
            return;
        }
        
        $content = file_get_contents($file);
        
        // Match @import, @use, and @forward statements
        $patterns = array(
            '/@import\s+["\']([^"\']+)["\']/i',
            '/@use\s+["\']([^"\']+)["\']/i',
            '/@forward\s+["\']([^"\']+)["\']/i'
        );
        
        $all_imports = array();
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            if (!empty($matches[1])) {
                $all_imports = array_merge($all_imports, $matches[1]);
            }
        }
        
        if (!empty($all_imports)) {
            if (!isset($this->dependencies[$file])) {
                $this->dependencies[$file] = array();
            }
            
            foreach ($all_imports as $import) {
                // Remove any "as" alias or "with" configuration
                $import = preg_replace('/\s+(as|with)\s+.*/i', '', $import);
                $import = trim($import);
                
                $import_file = $this->resolve_import($import, dirname($file));
                if ($import_file && file_exists($import_file)) {
                    // Avoid duplicates
                    if (!in_array($import_file, $this->dependencies[$file])) {
                        $this->dependencies[$file][] = $import_file;
                        // Recursively track dependencies
                        $this->track_dependencies($import_file);
                    }
                }
            }
        }
    }
    
    private function resolve_import($import, $base_dir) {
        // Add .scss extension if not present
        if (!pathinfo($import, PATHINFO_EXTENSION)) {
            $import .= '.scss';
        }
        
        // Try to find partial (with underscore prefix)
        if (substr(basename($import), 0, 1) !== '_') {
            $partial = dirname($import) . '/_' . basename($import);
            if (file_exists($base_dir . '/' . $partial)) {
                return $base_dir . '/' . $partial;
            }
        }
        
        // Try the direct path
        if (file_exists($base_dir . '/' . $import)) {
            return $base_dir . '/' . $import;
        }
        
        // Try in the SCSS directory if not found
        $scss_dir = $this->settings['base_dir'] . $this->settings['scss_dir'];
        if ($scss_dir !== $base_dir) {
            // Try partial in SCSS directory
            if (substr(basename($import), 0, 1) !== '_') {
                $partial = dirname($import) . '/_' . basename($import);
                if (file_exists($scss_dir . '/' . $partial)) {
                    return $scss_dir . '/' . $partial;
                }
            }
            
            // Try direct path in SCSS directory
            if (file_exists($scss_dir . '/' . $import)) {
                return $scss_dir . '/' . $import;
            }
        }
        
        // Check if scss_compiler is initialized before trying to get import paths
        if ($this->scss_compiler && method_exists($this->scss_compiler, 'getImportPaths')) {
            $import_paths = $this->scss_compiler->getImportPaths();
            foreach ($import_paths as $path) {
                if (file_exists($path . '/' . $import)) {
                    return $path . '/' . $import;
                }
            }
        }
        
        return false;
    }
    
    public function needs_compilation() {
        $scss_dir = $this->settings['base_dir'] . $this->settings['scss_dir'];
        $css_dir = $this->settings['base_dir'] . $this->settings['css_dir'];
        
        if (!is_dir($scss_dir) || !is_dir($css_dir)) {
            return true;
        }
        
        $scss_files = $this->get_scss_files($scss_dir);
        
        foreach ($scss_files as $scss_file) {
            if (substr(basename($scss_file), 0, 1) === '_') {
                continue;
            }
            
            $css_file = $css_dir . str_replace('.scss', '.css', basename($scss_file));
            
            if (!file_exists($css_file)) {
                return true;
            }
            
            if (filemtime($scss_file) > filemtime($css_file)) {
                return true;
            }
            
            if ($this->settings['dependency_tracking']) {
                $this->track_dependencies($scss_file);
                if (isset($this->dependencies[$scss_file])) {
                    foreach ($this->dependencies[$scss_file] as $dep) {
                        if (filemtime($dep) > filemtime($css_file)) {
                            return true;
                        }
                    }
                }
            }
        }
        
        return false;
    }
    
    private function get_scss_files($dir) {
        $files = array();
        
        if (!is_dir($dir)) {
            return $files;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'scss') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    
    private function add_error($file, $message) {
        $this->errors[] = array(
            'file' => $file,
            'message' => $message,
            'time' => current_time('mysql')
        );
    }
    
    public function get_errors() {
        return $this->errors;
    }
    
    public function get_dependencies() {
        return $this->dependencies;
    }
    
    
    private function format_bytes($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}