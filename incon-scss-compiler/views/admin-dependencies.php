<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('incon_scss_settings', array());
$scss_dir = $settings['base_dir'] . $settings['scss_dir'];

// Get dependencies if tracking is enabled
$dependencies = array();
if ($settings['dependency_tracking'] && class_exists('InconSCSS_DependencyTracker')) {
    $tracker = new InconSCSS_DependencyTracker();
    $dependencies = $tracker->get_dependency_graph();
}
?>

<div class="wrap">
    <h1><?php _e('SCSS File Dependencies', 'incon-scss'); ?></h1>
    
    <?php if (!$settings['dependency_tracking']): ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('Dependency tracking is disabled.', 'incon-scss'); ?>
                <a href="<?php echo admin_url('admin.php?page=incon-scss-settings'); ?>">
                    <?php _e('Enable it in settings', 'incon-scss'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="dependency-info" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4;">
        <h2><?php _e('How Dependencies Work', 'incon-scss'); ?></h2>
        <p><?php _e('Dependencies are tracked when SCSS files import other files using @import or @use directives.', 'incon-scss'); ?></p>
        <ul style="list-style: disc; margin-left: 20px;">
            <li><?php _e('Main files: SCSS files that compile to CSS (no underscore prefix)', 'incon-scss'); ?></li>
            <li><?php _e('Partials: SCSS files that are imported (underscore prefix, e.g., _variables.scss)', 'incon-scss'); ?></li>
            <li><?php _e('When a partial changes, all files that import it need recompilation', 'incon-scss'); ?></li>
        </ul>
    </div>
    
    <?php
    // Scan for SCSS files
    $main_files = array();
    $partial_files = array();
    
    if (is_dir($scss_dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scss_dir),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'scss') {
                $filename = $file->getBasename();
                $filepath = $file->getPathname();
                
                if (substr($filename, 0, 1) === '_') {
                    $partial_files[] = array(
                        'name' => $filename,
                        'path' => $filepath,
                        'relative' => str_replace($scss_dir, '', $filepath)
                    );
                } else {
                    $main_files[] = array(
                        'name' => $filename,
                        'path' => $filepath,
                        'relative' => str_replace($scss_dir, '', $filepath)
                    );
                }
            }
        }
    }
    ?>
    
    <h2><?php _e('Main SCSS Files', 'incon-scss'); ?></h2>
    <?php if (empty($main_files)): ?>
        <p><?php _e('No main SCSS files found.', 'incon-scss'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('File', 'incon-scss'); ?></th>
                    <th><?php _e('Path', 'incon-scss'); ?></th>
                    <th><?php _e('Imports', 'incon-scss'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($main_files as $file): ?>
                <tr>
                    <td><strong><?php echo esc_html($file['name']); ?></strong></td>
                    <td><code><?php echo esc_html($file['relative']); ?></code></td>
                    <td>
                        <?php
                        // Check for imports
                        $content = file_get_contents($file['path']);
                        preg_match_all('/@import\s+["\']([^"\']+)["\']/i', $content, $matches);
                        preg_match_all('/@use\s+["\']([^"\']+)["\']/i', $content, $use_matches);
                        
                        $imports = array_merge($matches[1] ?? array(), $use_matches[1] ?? array());
                        
                        if (!empty($imports)) {
                            echo '<ul style="margin: 0;">';
                            foreach ($imports as $import) {
                                echo '<li>' . esc_html($import) . '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<em>' . __('No imports', 'incon-scss') . '</em>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <h2><?php _e('Partial Files', 'incon-scss'); ?></h2>
    <?php if (empty($partial_files)): ?>
        <p><?php _e('No partial files found.', 'incon-scss'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Partial File', 'incon-scss'); ?></th>
                    <th><?php _e('Path', 'incon-scss'); ?></th>
                    <th><?php _e('Used By', 'incon-scss'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($partial_files as $partial): ?>
                <tr>
                    <td><strong><?php echo esc_html($partial['name']); ?></strong></td>
                    <td><code><?php echo esc_html($partial['relative']); ?></code></td>
                    <td>
                        <?php
                        // Find which files import this partial
                        $partial_name = str_replace('.scss', '', $partial['name']);
                        $partial_name = ltrim($partial_name, '_');
                        $used_by = array();
                        
                        foreach ($main_files as $main) {
                            $content = file_get_contents($main['path']);
                            if (strpos($content, $partial_name) !== false) {
                                $used_by[] = $main['name'];
                            }
                        }
                        
                        if (!empty($used_by)) {
                            echo implode(', ', array_map('esc_html', $used_by));
                        } else {
                            echo '<em>' . __('Not used', 'incon-scss') . '</em>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <?php if (!empty($dependencies['nodes'])): ?>
    <h2><?php _e('Dependency Graph', 'incon-scss'); ?></h2>
    <div id="dependency-graph" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; min-height: 400px;">
        <canvas id="graph-canvas"></canvas>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Simple dependency visualization
        var data = <?php echo json_encode($dependencies); ?>;
        
        if (data.nodes && data.nodes.length > 0) {
            // You could use a library like vis.js or d3.js here for better visualization
            $('#graph-canvas').html('<p>Dependencies tracked: ' + data.nodes.length + ' files</p>');
        }
    });
    </script>
    <?php endif; ?>
</div>