<?php

class InconSCSS_PostCSS {
    
    private $settings;
    private $plugins = array();
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init_plugins();
    }
    
    private function init_plugins() {
        $enabled_plugins = $this->settings['postcss_plugins'] ?? array();
        
        if (in_array('autoprefixer', $enabled_plugins) || $this->settings['autoprefixer']) {
            $this->plugins['autoprefixer'] = array(
                'enabled' => true,
                'options' => array(
                    'browsers' => $this->settings['browsers_list'] ?? 'last 2 versions',
                    'cascade' => true,
                    'add' => true,
                    'remove' => true,
                    'supports' => true,
                    'flexbox' => true,
                    'grid' => 'autoplace'
                )
            );
        }
        
        if (in_array('cssnano', $enabled_plugins) || $this->settings['minify']) {
            $this->plugins['cssnano'] = array(
                'enabled' => true,
                'options' => array(
                    'preset' => 'default'
                )
            );
        }
        
        if (in_array('purgecss', $enabled_plugins) || $this->settings['remove_unused_css']) {
            $this->plugins['purgecss'] = array(
                'enabled' => true,
                'options' => array(
                    'content' => $this->get_content_files(),
                    'safelist' => $this->get_safelist()
                )
            );
        }
        
        $this->plugins = apply_filters('incon_scss_postcss_plugins', $this->plugins);
    }
    
    public function process($css, $from = null) {
        if (empty($this->plugins)) {
            return $css;
        }
        
        $node_path = $this->find_node();
        if (!$node_path) {
            return $this->process_php_fallback($css);
        }
        
        $temp_input = tempnam(sys_get_temp_dir(), 'scss_in_');
        $temp_output = tempnam(sys_get_temp_dir(), 'scss_out_');
        
        file_put_contents($temp_input, $css);
        
        $config = $this->generate_postcss_config();
        $config_file = tempnam(sys_get_temp_dir(), 'postcss_config_');
        file_put_contents($config_file . '.json', json_encode($config));
        
        $command = sprintf(
            '%s %s --config %s -o %s %s 2>&1',
            escapeshellcmd($node_path),
            escapeshellarg($this->get_postcss_cli()),
            escapeshellarg($config_file . '.json'),
            escapeshellarg($temp_output),
            escapeshellarg($temp_input)
        );
        
        $output = shell_exec($command);
        $processed_css = file_get_contents($temp_output);
        
        unlink($temp_input);
        unlink($temp_output);
        unlink($config_file . '.json');
        
        return $processed_css ?: $css;
    }
    
    private function process_php_fallback($css) {
        foreach ($this->plugins as $plugin => $config) {
            if (!$config['enabled']) {
                continue;
            }
            
            switch ($plugin) {
                case 'autoprefixer':
                    $css = $this->apply_autoprefixer_php($css, $config['options']);
                    break;
                    
                case 'cssnano':
                    $css = $this->minify_css_php($css);
                    break;
                    
                case 'purgecss':
                    $css = $this->purge_css_php($css, $config['options']);
                    break;
            }
        }
        
        return $css;
    }
    
    private function apply_autoprefixer_php($css, $options) {
        $properties_to_prefix = array(
            'animation', 'animation-delay', 'animation-direction', 'animation-duration',
            'animation-fill-mode', 'animation-iteration-count', 'animation-name',
            'animation-play-state', 'animation-timing-function',
            'appearance', 'backface-visibility', 'background-clip', 'background-origin',
            'background-size', 'border-image', 'border-radius', 'box-shadow',
            'box-sizing', 'calc', 'column-count', 'column-gap', 'column-rule',
            'column-rule-color', 'column-rule-style', 'column-rule-width', 'column-span',
            'column-width', 'columns', 'filter', 'flex', 'flex-basis', 'flex-direction',
            'flex-flow', 'flex-grow', 'flex-shrink', 'flex-wrap', 'font-feature-settings',
            'font-kerning', 'font-variant-ligatures', 'gradient', 'grid', 'grid-area',
            'grid-auto-columns', 'grid-auto-flow', 'grid-auto-rows', 'grid-column',
            'grid-column-end', 'grid-column-gap', 'grid-column-start', 'grid-gap',
            'grid-row', 'grid-row-end', 'grid-row-gap', 'grid-row-start', 'grid-template',
            'grid-template-areas', 'grid-template-columns', 'grid-template-rows',
            'hyphens', 'justify-content', 'linear-gradient', 'mask', 'mask-clip',
            'mask-composite', 'mask-image', 'mask-mode', 'mask-origin', 'mask-position',
            'mask-repeat', 'mask-size', 'object-fit', 'object-position', 'order',
            'perspective', 'perspective-origin', 'placeholder', 'radial-gradient',
            'repeating-linear-gradient', 'repeating-radial-gradient', 'resize',
            'tab-size', 'text-decoration-color', 'text-decoration-line',
            'text-decoration-style', 'text-emphasis', 'text-emphasis-color',
            'text-emphasis-position', 'text-emphasis-style', 'text-overflow',
            'text-shadow', 'text-size-adjust', 'transform', 'transform-origin',
            'transform-style', 'transition', 'transition-delay', 'transition-duration',
            'transition-property', 'transition-timing-function', 'user-select',
            'writing-mode'
        );
        
        $prefixes = array('-webkit-', '-moz-', '-ms-', '-o-');
        
        foreach ($properties_to_prefix as $property) {
            $pattern = '/(?<!-)(' . preg_quote($property, '/') . ')\\s*:/i';
            
            if (preg_match($pattern, $css)) {
                $replacement = '';
                foreach ($prefixes as $prefix) {
                    $replacement .= $prefix . '$1: $2; ';
                }
                $replacement .= '$1:';
                
                $css = preg_replace($pattern, $replacement, $css);
            }
        }
        
        $css = preg_replace('/(::-webkit-input-placeholder)/', '::-webkit-input-placeholder', $css);
        $css = preg_replace('/(::-moz-placeholder)/', '::-moz-placeholder', $css);
        $css = preg_replace('/(:-ms-input-placeholder)/', ':-ms-input-placeholder', $css);
        $css = preg_replace('/(:placeholder-shown)/', ':placeholder-shown', $css);
        
        return $css;
    }
    
    private function minify_css_php($css) {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        // Remove whitespace
        $css = str_replace(array("\r\n", "\r", "\n", "\t"), '', $css);
        // Collapse multiple spaces
        $css = preg_replace('/\s+/', ' ', $css);
        // Remove spaces around punctuation
        $css = str_replace(array('; ', ': ', ' {', '{ ', ' }', '} ', ' ,', ', '), array(';', ':', '{', '{', '}', '}', ',', ','), $css);
        // Remove trailing semicolons before closing braces
        $css = preg_replace('/;}/', '}', $css);
        
        return trim($css);
    }
    
    private function purge_css_php($css, $options) {
        return $css;
    }
    
    private function find_node() {
        $possible_paths = array(
            '/usr/local/bin/node',
            '/usr/bin/node',
            '/opt/homebrew/bin/node',
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe'
        );
        
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        $output = shell_exec('which node 2>&1');
        if ($output && file_exists(trim($output))) {
            return trim($output);
        }
        
        return false;
    }
    
    private function get_postcss_cli() {
        $cli_path = INCON_SCSS_PLUGIN_DIR . 'node_modules/.bin/postcss';
        
        if (file_exists($cli_path)) {
            return $cli_path;
        }
        
        $global_cli = shell_exec('which postcss 2>&1');
        if ($global_cli && file_exists(trim($global_cli))) {
            return trim($global_cli);
        }
        
        return false;
    }
    
    private function generate_postcss_config() {
        $config = array(
            'map' => false,
            'plugins' => array()
        );
        
        foreach ($this->plugins as $plugin => $settings) {
            if ($settings['enabled']) {
                $config['plugins'][$plugin] = $settings['options'];
            }
        }
        
        return $config;
    }
    
    private function get_content_files() {
        $files = array();
        
        $theme_dir = get_stylesheet_directory();
        $extensions = array('php', 'html', 'js');
        
        foreach ($extensions as $ext) {
            $files = array_merge($files, glob($theme_dir . '/**/*.' . $ext));
        }
        
        return apply_filters('incon_scss_purgecss_content', $files);
    }
    
    private function get_safelist() {
        $safelist = array(
            'wp-*', 'admin-*', 'logged-in', 'no-js', 'js',
            'alignleft', 'alignright', 'aligncenter', 'alignfull', 'alignwide',
            'wp-block-*', 'has-*', 'is-*', 'menu-item-*', 'current-*'
        );
        
        return apply_filters('incon_scss_purgecss_safelist', $safelist);
    }
}