<?php

class InconSCSS_Settings {
    
    private $options;
    private $option_name = 'incon_scss_settings';
    
    public function __construct() {
        $this->options = get_option($this->option_name, array());
    }
    
    public function get($key, $default = null) {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }
    
    public function set($key, $value) {
        $this->options[$key] = $value;
        return update_option($this->option_name, $this->options);
    }
    
    public function get_all() {
        return $this->options;
    }
    
    public function update($settings) {
        $this->options = wp_parse_args($settings, $this->options);
        return update_option($this->option_name, $this->options);
    }
    
    public function reset() {
        return delete_option($this->option_name);
    }
    
    public function get_base_locations() {
        $locations = array(
            'theme' => array(
                'label' => __('Current Theme', 'incon-scss'),
                'path' => get_stylesheet_directory(),
                'url' => get_stylesheet_directory_uri()
            ),
            'parent-theme' => array(
                'label' => __('Parent Theme', 'incon-scss'),
                'path' => get_template_directory(),
                'url' => get_template_directory_uri()
            ),
            'uploads' => array(
                'label' => __('Uploads Directory', 'incon-scss'),
                'path' => wp_upload_dir()['basedir'],
                'url' => wp_upload_dir()['baseurl']
            ),
            'plugin' => array(
                'label' => __('Plugin Directory', 'incon-scss'),
                'path' => INCON_SCSS_PLUGIN_DIR,
                'url' => INCON_SCSS_PLUGIN_URL
            ),
            'content' => array(
                'label' => __('Content Directory', 'incon-scss'),
                'path' => WP_CONTENT_DIR,
                'url' => content_url()
            )
        );
        
        if (get_stylesheet_directory() === get_template_directory()) {
            unset($locations['parent-theme']);
        }
        
        return apply_filters('incon_scss_base_locations', $locations);
    }
    
    public function validate_directories() {
        $errors = array();
        
        $base_dir = $this->get('base_dir');
        $scss_dir = $base_dir . $this->get('scss_dir');
        $css_dir = $base_dir . $this->get('css_dir');
        
        if (!is_dir($scss_dir)) {
            $errors[] = sprintf(__('SCSS directory does not exist: %s', 'incon-scss'), $scss_dir);
        }
        
        if (!is_dir($css_dir)) {
            if (!wp_mkdir_p($css_dir)) {
                $errors[] = sprintf(__('Could not create CSS directory: %s', 'incon-scss'), $css_dir);
            }
        } elseif (!is_writable($css_dir)) {
            $errors[] = sprintf(__('CSS directory is not writable: %s', 'incon-scss'), $css_dir);
        }
        
        $cache_dir = $this->get('cache_dir');
        if (!is_dir($cache_dir)) {
            if (!wp_mkdir_p($cache_dir)) {
                $errors[] = sprintf(__('Could not create cache directory: %s', 'incon-scss'), $cache_dir);
            }
        } elseif (!is_writable($cache_dir)) {
            $errors[] = sprintf(__('Cache directory is not writable: %s', 'incon-scss'), $cache_dir);
        }
        
        return $errors;
    }
    
    public function export() {
        return json_encode($this->options, JSON_PRETTY_PRINT);
    }
    
    public function import($json) {
        $settings = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->update($settings);
        }
        return false;
    }
}