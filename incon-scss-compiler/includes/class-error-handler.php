<?php

class InconSCSS_ErrorHandler {
    
    private $errors = array();
    private $log_file;
    private $settings;
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->log_file = $settings['cache_dir'] . 'error.log';
    }
    
    public function add_error($file, $message, $line = null, $severity = 'error') {
        $error = array(
            'file' => $file,
            'message' => $message,
            'line' => $line,
            'severity' => $severity,
            'time' => current_time('mysql'),
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''
        );
        
        $this->errors[] = $error;
        $this->log_error($error);
        
        return $this;
    }
    
    private function log_error($error) {
        $log_entry = sprintf(
            "[%s] %s: %s in %s",
            $error['time'],
            strtoupper($error['severity']),
            $error['message'],
            $error['file']
        );
        
        if ($error['line']) {
            $log_entry .= " on line {$error['line']}";
        }
        
        if ($error['url']) {
            $log_entry .= " (URL: {$error['url']})";
        }
        
        $log_entry .= PHP_EOL;
        
        error_log($log_entry, 3, $this->log_file);
        
        $this->rotate_log();
    }
    
    private function rotate_log() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        $max_size = 5 * 1024 * 1024;
        
        if (filesize($this->log_file) > $max_size) {
            $backup = $this->log_file . '.' . date('Y-m-d-His');
            rename($this->log_file, $backup);
            
            $old_logs = glob($this->settings['cache_dir'] . 'error.log.*');
            if (count($old_logs) > 5) {
                usort($old_logs, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                
                $to_delete = array_slice($old_logs, 0, count($old_logs) - 5);
                foreach ($to_delete as $file) {
                    unlink($file);
                }
            }
        }
    }
    
    public function get_errors() {
        return $this->errors;
    }
    
    public function has_errors() {
        return !empty($this->errors);
    }
    
    public function clear_errors() {
        $this->errors = array();
        return $this;
    }
    
    public function display_errors() {
        if (!$this->has_errors()) {
            return;
        }
        
        $display_mode = $this->settings['error_display'];
        
        switch ($display_mode) {
            case 'admin':
                if (is_admin()) {
                    add_action('admin_notices', array($this, 'show_admin_notices'));
                }
                break;
                
            case 'frontend':
                if (!is_admin()) {
                    add_action('wp_footer', array($this, 'show_frontend_errors'));
                }
                break;
                
            case 'console':
                add_action('wp_footer', array($this, 'show_console_errors'));
                add_action('admin_footer', array($this, 'show_console_errors'));
                break;
                
            case 'none':
            default:
                break;
        }
    }
    
    public function show_admin_notices() {
        foreach ($this->errors as $error) {
            $class = 'notice notice-' . ($error['severity'] === 'warning' ? 'warning' : 'error');
            printf(
                '<div class="%s"><p><strong>SCSS Compiler:</strong> %s in %s</p></div>',
                esc_attr($class),
                esc_html($error['message']),
                esc_html(basename($error['file']))
            );
        }
    }
    
    public function show_frontend_errors() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div class="incon-scss-errors" style="position: fixed; bottom: 20px; right: 20px; max-width: 400px; z-index: 99999;">';
        foreach ($this->errors as $error) {
            $bg_color = $error['severity'] === 'warning' ? '#ffba00' : '#dc3232';
            printf(
                '<div style="background: %s; color: white; padding: 10px; margin-bottom: 5px; border-radius: 4px;">' .
                '<strong>SCSS:</strong> %s in %s</div>',
                $bg_color,
                esc_html($error['message']),
                esc_html(basename($error['file']))
            );
        }
        echo '</div>';
    }
    
    public function show_console_errors() {
        if (empty($this->errors)) {
            return;
        }
        
        echo '<script>';
        echo 'console.group("SCSS Compiler Errors");';
        foreach ($this->errors as $error) {
            $method = $error['severity'] === 'warning' ? 'warn' : 'error';
            printf(
                'console.%s("%s in %s", %s);',
                $method,
                addslashes($error['message']),
                addslashes($error['file']),
                json_encode($error)
            );
        }
        echo 'console.groupEnd();';
        echo '</script>';
    }
    
    public function get_log_contents($lines = 100) {
        if (!file_exists($this->log_file)) {
            return array();
        }
        
        $file = new SplFileObject($this->log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start = max(0, $total_lines - $lines);
        $result = array();
        
        $file->seek($start);
        while (!$file->eof()) {
            $line = $file->fgets();
            if (!empty(trim($line))) {
                $result[] = $line;
            }
        }
        
        return $result;
    }
    
    public function clear_log() {
        if (file_exists($this->log_file)) {
            return unlink($this->log_file);
        }
        return true;
    }
}