<?php

class InconSCSS_FileWatcher {
    
    private $settings;
    private $watched_files = array();
    private $last_check = 0;
    
    public function __construct($settings) {
        $this->settings = $settings;
    }
    
    public function init() {
        if (!$this->settings['watch_enabled']) {
            return;
        }
        
        add_action('wp_ajax_incon_scss_check_changes', array($this, 'ajax_check_changes'));
        add_action('wp_ajax_nopriv_incon_scss_check_changes', array($this, 'ajax_check_changes'));
        
        $this->scan_files();
    }
    
    private function scan_files() {
        $scss_dir = $this->settings['base_dir'] . $this->settings['scss_dir'];
        
        if (!is_dir($scss_dir)) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scss_dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'scss') {
                $this->watched_files[$file->getPathname()] = $file->getMTime();
            }
        }
        
        $this->save_watch_data();
    }
    
    public function check_for_changes() {
        $current_files = array();
        $scss_dir = $this->settings['base_dir'] . $this->settings['scss_dir'];
        
        if (!is_dir($scss_dir)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scss_dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'scss') {
                $current_files[$file->getPathname()] = $file->getMTime();
            }
        }
        
        $saved_files = $this->get_watch_data();
        
        foreach ($current_files as $path => $mtime) {
            if (!isset($saved_files[$path]) || $saved_files[$path] != $mtime) {
                return true;
            }
        }
        
        foreach ($saved_files as $path => $mtime) {
            if (!isset($current_files[$path])) {
                return true;
            }
        }
        
        return false;
    }
    
    public function ajax_check_changes() {
        check_ajax_referer('incon_scss_nonce', 'nonce');
        
        $changed = $this->check_for_changes();
        
        if ($changed) {
            $this->scan_files();
        }
        
        wp_send_json(array('changed' => $changed));
    }
    
    private function save_watch_data() {
        set_transient('incon_scss_watch_data', $this->watched_files, HOUR_IN_SECONDS);
        $this->last_check = time();
    }
    
    private function get_watch_data() {
        $data = get_transient('incon_scss_watch_data');
        return $data ? $data : array();
    }
    
    public function get_changed_files() {
        $changed = array();
        $saved_files = $this->get_watch_data();
        
        foreach ($this->watched_files as $path => $mtime) {
            if (!isset($saved_files[$path]) || $saved_files[$path] != $mtime) {
                $changed[] = $path;
            }
        }
        
        return $changed;
    }
    
    public function reset() {
        delete_transient('incon_scss_watch_data');
        $this->watched_files = array();
        $this->scan_files();
    }
}