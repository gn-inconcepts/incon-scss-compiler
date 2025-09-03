<?php
/**
 * GitHub Plugin Updater
 * Updates plugin from a GitHub repository
 */

if (!defined('ABSPATH')) {
    exit;
}

class Incon_SCSS_GitHub_Updater {
    
    private $plugin_file;
    private $plugin_slug;
    private $version;
    private $github_username;
    private $github_repo;
    private $github_token;
    private $github_response;
    
    public function __construct($plugin_file, $config = array()) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($this->plugin_file);
        
        $defaults = array(
            'github_username' => 'gn-inconcepts',
            'github_repo' => 'incon-scss-compiler',
            'github_token' => '',
        );
        
        $config = wp_parse_args($config, $defaults);
        
        $this->github_username = $config['github_username'];
        $this->github_repo = $config['github_repo'];
        $this->github_token = $config['github_token'];
        
        add_action('admin_init', array($this, 'init'));
    }
    
    public function init() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $plugin_data = get_plugin_data($this->plugin_file);
        $this->version = $plugin_data['Version'];
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'rename_github_folder'), 10, 4);
        add_action('upgrader_process_complete', array($this, 'purge_cache'), 10, 2);
    }
    
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $github_data = $this->get_github_release();
        
        if (!$github_data) {
            return $transient;
        }
        
        $latest_version = ltrim($github_data->tag_name, 'v');
        
        if (version_compare($this->version, $latest_version, '<')) {
            $update = array(
                'slug' => current(explode('/', $this->plugin_slug)),
                'plugin' => $this->plugin_slug,
                'new_version' => $latest_version,
                'url' => "https://github.com/{$this->github_username}/{$this->github_repo}",
                'package' => $github_data->zipball_url,
            );
            
            if ($this->github_token) {
                $update['package'] = add_query_arg(
                    array('access_token' => $this->github_token),
                    $update['package']
                );
            }
            
            $transient->response[$this->plugin_slug] = (object) $update;
        }
        
        return $transient;
    }
    
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if ($args->slug !== current(explode('/', $this->plugin_slug))) {
            return $result;
        }
        
        $github_data = $this->get_github_release();
        
        if (!$github_data) {
            return $result;
        }
        
        $plugin_data = get_plugin_data($this->plugin_file);
        
        $plugin_info = array(
            'name' => $plugin_data['Name'],
            'slug' => current(explode('/', $this->plugin_slug)),
            'version' => ltrim($github_data->tag_name, 'v'),
            'author' => $plugin_data['Author'],
            'homepage' => $plugin_data['PluginURI'],
            'short_description' => $plugin_data['Description'],
            'sections' => array(
                'description' => $plugin_data['Description'],
                'changelog' => $this->parse_changelog($github_data->body),
            ),
            'download_link' => $github_data->zipball_url,
            'trunk' => $github_data->zipball_url,
            'last_updated' => $github_data->published_at,
        );
        
        return (object) $plugin_info;
    }
    
    private function get_github_release() {
        $cache_key = 'incon_scss_github_' . md5($this->github_username . $this->github_repo);
        $github_data = get_transient($cache_key);
        
        if ($github_data !== false) {
            return $github_data;
        }
        
        $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
        
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
        );
        
        if ($this->github_token) {
            $args['headers']['Authorization'] = "token {$this->github_token}";
        }
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (empty($data->tag_name)) {
            return false;
        }
        
        set_transient($cache_key, $data, 12 * HOUR_IN_SECONDS);
        
        return $data;
    }
    
    public function rename_github_folder($source, $remote_source, $upgrader, $args) {
        if (!isset($args['plugin']) || $args['plugin'] !== $this->plugin_slug) {
            return $source;
        }
        
        $corrected_source = trailingslashit($remote_source) . dirname($this->plugin_slug);
        
        if (rename($source, $corrected_source)) {
            return $corrected_source;
        }
        
        return $source;
    }
    
    public function purge_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            $cache_key = 'incon_scss_github_' . md5($this->github_username . $this->github_repo);
            delete_transient($cache_key);
        }
    }
    
    private function parse_changelog($markdown) {
        $html = '<h4>Changelog</h4>';
        $html .= '<pre>' . esc_html($markdown) . '</pre>';
        return $html;
    }
}