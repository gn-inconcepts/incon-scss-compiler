<?php

class InconSCSS_Cache {
    
    private $cache_dir;
    private $cache_time = 3600;
    
    public function __construct($cache_dir) {
        $this->cache_dir = trailingslashit($cache_dir);
        $this->ensure_cache_dir();
    }
    
    private function ensure_cache_dir() {
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }
    
    public function get($key) {
        $file = $this->get_cache_file($key);
        
        if (!file_exists($file)) {
            return false;
        }
        
        $data = file_get_contents($file);
        $cache = unserialize($data);
        
        if ($cache['expires'] < time()) {
            unlink($file);
            return false;
        }
        
        return $cache['data'];
    }
    
    public function set($key, $data, $expires = null) {
        if ($expires === null) {
            $expires = $this->cache_time;
        }
        
        $cache = array(
            'data' => $data,
            'expires' => time() + $expires
        );
        
        $file = $this->get_cache_file($key);
        return file_put_contents($file, serialize($cache));
    }
    
    public function delete($key) {
        $file = $this->get_cache_file($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return false;
    }
    
    public function clear() {
        $files = glob($this->cache_dir . '*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    public function cleanup() {
        $files = glob($this->cache_dir . '*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            $data = file_get_contents($file);
            $cache = unserialize($data);
            
            if ($cache['expires'] < time()) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    private function get_cache_file($key) {
        $key = md5($key);
        return $this->cache_dir . $key . '.cache';
    }
    
    public function get_size() {
        $size = 0;
        $files = glob($this->cache_dir . '*.cache');
        
        foreach ($files as $file) {
            $size += filesize($file);
        }
        
        return $size;
    }
    
    public function get_count() {
        $files = glob($this->cache_dir . '*.cache');
        return count($files);
    }
}