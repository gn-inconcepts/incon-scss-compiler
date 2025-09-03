<?php

class InconSCSS_DependencyTracker {
    
    private $dependencies = array();
    private $reverse_dependencies = array();
    
    public function __construct() {
        $this->load_dependencies();
    }
    
    private function load_dependencies() {
        global $wpdb;
        $table = $wpdb->prefix . 'incon_scss_dependencies';
        
        $results = $wpdb->get_results("SELECT * FROM $table");
        
        foreach ($results as $row) {
            if (!isset($this->dependencies[$row->parent_file])) {
                $this->dependencies[$row->parent_file] = array();
            }
            $this->dependencies[$row->parent_file][] = $row->dependency_file;
            
            if (!isset($this->reverse_dependencies[$row->dependency_file])) {
                $this->reverse_dependencies[$row->dependency_file] = array();
            }
            $this->reverse_dependencies[$row->dependency_file][] = $row->parent_file;
        }
    }
    
    public function add_dependency($parent, $dependency, $type = 'import') {
        if (!isset($this->dependencies[$parent])) {
            $this->dependencies[$parent] = array();
        }
        
        if (!in_array($dependency, $this->dependencies[$parent])) {
            $this->dependencies[$parent][] = $dependency;
            
            if (!isset($this->reverse_dependencies[$dependency])) {
                $this->reverse_dependencies[$dependency] = array();
            }
            $this->reverse_dependencies[$dependency][] = $parent;
            
            $this->save_dependency($parent, $dependency, $type);
        }
    }
    
    private function save_dependency($parent, $dependency, $type) {
        global $wpdb;
        $table = $wpdb->prefix . 'incon_scss_dependencies';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE parent_file = %s AND dependency_file = %s",
            $parent, $dependency
        ));
        
        if (!$existing) {
            $wpdb->insert($table, array(
                'parent_file' => $parent,
                'dependency_file' => $dependency,
                'dependency_type' => $type,
                'last_modified' => current_time('mysql')
            ));
        } else {
            $wpdb->update($table, 
                array('last_modified' => current_time('mysql')),
                array('id' => $existing)
            );
        }
    }
    
    public function get_dependencies($file) {
        return isset($this->dependencies[$file]) ? $this->dependencies[$file] : array();
    }
    
    public function get_dependents($file) {
        return isset($this->reverse_dependencies[$file]) ? $this->reverse_dependencies[$file] : array();
    }
    
    public function get_all_dependencies($file, &$visited = array()) {
        if (in_array($file, $visited)) {
            return array();
        }
        
        $visited[] = $file;
        $all_deps = $this->get_dependencies($file);
        
        foreach ($all_deps as $dep) {
            $nested = $this->get_all_dependencies($dep, $visited);
            $all_deps = array_merge($all_deps, $nested);
        }
        
        return array_unique($all_deps);
    }
    
    public function get_all_dependents($file, &$visited = array()) {
        if (in_array($file, $visited)) {
            return array();
        }
        
        $visited[] = $file;
        $all_deps = $this->get_dependents($file);
        
        foreach ($all_deps as $dep) {
            $nested = $this->get_all_dependents($dep, $visited);
            $all_deps = array_merge($all_deps, $nested);
        }
        
        return array_unique($all_deps);
    }
    
    public function clear_dependencies($file = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'incon_scss_dependencies';
        
        if ($file) {
            $wpdb->delete($table, array('parent_file' => $file));
            unset($this->dependencies[$file]);
        } else {
            $wpdb->query("TRUNCATE TABLE $table");
            $this->dependencies = array();
            $this->reverse_dependencies = array();
        }
    }
    
    public function get_dependency_graph() {
        return array(
            'nodes' => $this->get_nodes(),
            'edges' => $this->get_edges()
        );
    }
    
    private function get_nodes() {
        $nodes = array();
        $all_files = array_merge(
            array_keys($this->dependencies),
            array_keys($this->reverse_dependencies)
        );
        
        foreach (array_unique($all_files) as $file) {
            $nodes[] = array(
                'id' => md5($file),
                'label' => basename($file),
                'file' => $file,
                'type' => substr(basename($file), 0, 1) === '_' ? 'partial' : 'main'
            );
        }
        
        return $nodes;
    }
    
    private function get_edges() {
        $edges = array();
        
        foreach ($this->dependencies as $parent => $deps) {
            foreach ($deps as $dep) {
                $edges[] = array(
                    'from' => md5($parent),
                    'to' => md5($dep)
                );
            }
        }
        
        return $edges;
    }
}