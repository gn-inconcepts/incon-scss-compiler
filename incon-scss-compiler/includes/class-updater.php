<?php
/**
 * Plugin Updater Class
 * Handles automatic updates for the plugin from a custom server
 */

if (!defined('ABSPATH')) {
    exit;
}

class Incon_SCSS_Updater {
    
    private $plugin_slug;
    private $version;
    private $update_server_url;
    private $plugin_file;
    private $plugin_data;
    private $cache_key;
    private $cache_expiration;
    
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($this->plugin_file);
        $this->cache_key = 'incon_scss_update_' . md5($this->plugin_slug);
        $this->cache_expiration = 12 * HOUR_IN_SECONDS;
        
        $this->update_server_url = 'https://updates.yourdomain.com/check';
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_action('upgrader_process_complete', array($this, 'purge_cache'), 10, 2);
        add_action('admin_init', array($this, 'init_plugin_data'));
    }
    
    public function init_plugin_data() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $this->plugin_data = get_plugin_data($this->plugin_file);
        $this->version = $this->plugin_data['Version'];
    }
    
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_data = $this->get_remote_data();
        
        if ($remote_data && version_compare($this->version, $remote_data->version, '<')) {
            $update_data = array(
                'id' => $this->plugin_slug,
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote_data->version,
                'url' => $remote_data->homepage ?? '',
                'package' => $remote_data->download_url ?? '',
                'icons' => $remote_data->icons ?? array(),
                'banners' => $remote_data->banners ?? array(),
                'banners_rtl' => array(),
                'tested' => $remote_data->tested ?? '',
                'requires_php' => $remote_data->requires_php ?? '',
                'compatibility' => new stdClass(),
            );
            
            $transient->response[$this->plugin_slug] = (object) $update_data;
        }
        
        return $transient;
    }
    
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if ($args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }
        
        $remote_data = $this->get_remote_data();
        
        if (!$remote_data) {
            return $result;
        }
        
        $plugin_info = array(
            'name' => $this->plugin_data['Name'],
            'slug' => dirname($this->plugin_slug),
            'version' => $remote_data->version,
            'author' => $this->plugin_data['Author'],
            'homepage' => $remote_data->homepage ?? '',
            'short_description' => $remote_data->short_description ?? '',
            'sections' => array(
                'description' => $remote_data->description ?? '',
                'changelog' => $remote_data->changelog ?? '',
                'installation' => $remote_data->installation ?? '',
            ),
            'download_link' => $remote_data->download_url ?? '',
            'trunk' => $remote_data->download_url ?? '',
            'last_updated' => $remote_data->last_updated ?? '',
            'banners' => $remote_data->banners ?? array(),
            'icons' => $remote_data->icons ?? array(),
        );
        
        return (object) $plugin_info;
    }
    
    private function get_remote_data() {
        $cache = get_transient($this->cache_key);
        
        if ($cache !== false) {
            return $cache;
        }
        
        $request_args = array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json',
            ),
            'body' => array(
                'plugin_slug' => $this->plugin_slug,
                'version' => $this->version,
                'url' => home_url(),
                'license_key' => get_option('incon_scss_license_key', ''),
            ),
        );
        
        $response = wp_remote_post($this->update_server_url, $request_args);
        
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (!$data || !isset($data->version)) {
            return false;
        }
        
        set_transient($this->cache_key, $data, $this->cache_expiration);
        
        return $data;
    }
    
    public function purge_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient($this->cache_key);
        }
    }
}