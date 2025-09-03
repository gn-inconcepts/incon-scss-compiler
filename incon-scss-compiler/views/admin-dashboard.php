<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('incon_scss_settings', array());
$base_dir = $settings['base_dir'] ?? get_stylesheet_directory();
$scss_dir = $settings['scss_dir'] ?? '/scss/';
$css_dir = $settings['css_dir'] ?? '/css/';

$scss_path = $base_dir . $scss_dir;
$css_path = $base_dir . $css_dir;

// Get SCSS files
$scss_files = array();
if (is_dir($scss_path)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scss_path));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'scss') {
            $scss_files[] = $file->getPathname();
        }
    }
}

// Get CSS files for comparison
$css_files = array();
if (is_dir($css_path)) {
    $css_files = glob($css_path . '*.css');
}

// Calculate total size
$total_size = 0;
foreach ($scss_files as $file) {
    $total_size += filesize($file);
}
?>

<div class="wrap incon-scss-wrap">
    <h1><?php _e('SCSS Compiler Dashboard', 'incon-scss'); ?></h1>
    
    <div class="incon-scss-dashboard">
        <div class="incon-scss-stats-row">
            <div class="stats-card">
                <div class="stats-icon">
                    <span class="dashicons dashicons-media-code"></span>
                </div>
                <div class="stats-content">
                    <h3><?php echo count($scss_files); ?></h3>
                    <p><?php _e('SCSS Files', 'incon-scss'); ?></p>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon">
                    <span class="dashicons dashicons-archive"></span>
                </div>
                <div class="stats-content">
                    <h3><?php echo size_format($total_size); ?></h3>
                    <p><?php _e('Total Size', 'incon-scss'); ?></p>
                </div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="stats-content">
                    <h3><?php echo count($css_files); ?></h3>
                    <p><?php _e('Compiled Files', 'incon-scss'); ?></p>
                </div>
            </div>
        </div>
        
        <div id="compile-status"></div>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <button id="compile-all" class="button button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Compile All Files', 'incon-scss'); ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=incon-scss-compiler&tab=dependencies'); ?>" class="button">
                    <span class="dashicons dashicons-networking"></span>
                    <?php _e('Dependencies', 'incon-scss'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=incon-scss-settings'); ?>" class="button">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Settings', 'incon-scss'); ?>
                </a>
            </div>
        </div>
        
        <div class="incon-scss-main">
            <div class="incon-scss-files">
                <h2><?php _e('SCSS Files', 'incon-scss'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="check-column"><input type="checkbox" id="select-all" /></th>
                            <th><?php _e('File', 'incon-scss'); ?></th>
                            <th><?php _e('Size', 'incon-scss'); ?></th>
                            <th><?php _e('Modified', 'incon-scss'); ?></th>
                            <th><?php _e('CSS Output', 'incon-scss'); ?></th>
                            <th><?php _e('Actions', 'incon-scss'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scss_files as $file): 
                            $file_info = new SplFileInfo($file);
                            $relative_path = str_replace($scss_path, '', $file);
                            $css_file = $css_path . str_replace('.scss', '.css', basename($file));
                            $css_exists = file_exists($css_file);
                            
                            // Skip partials (files starting with _)
                            if (substr(basename($file), 0, 1) === '_') {
                                continue;
                            }
                        ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="files[]" value="<?php echo esc_attr($file); ?>" />
                            </th>
                            <td>
                                <strong><?php echo basename($file); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="#" class="edit-file" data-file="<?php echo esc_attr($file); ?>">
                                            <?php _e('Edit', 'incon-scss'); ?>
                                        </a> |
                                    </span>
                                    <span class="view-dependencies">
                                        <a href="#" class="view-deps" data-file="<?php echo esc_attr($file); ?>">
                                            <?php _e('Dependencies', 'incon-scss'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo size_format($file_info->getSize()); ?></td>
                            <td><?php echo human_time_diff($file_info->getMTime()); ?> ago</td>
                            <td>
                                <?php if ($css_exists): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php echo size_format(filesize($css_file)); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: #ffba00;"></span>
                                    <?php _e('Not compiled', 'incon-scss'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-small compile-single" data-file="<?php echo esc_attr($file); ?>">
                                    <?php _e('Compile', 'incon-scss'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <select id="bulk-action">
                            <option value=""><?php _e('Bulk Actions', 'incon-scss'); ?></option>
                            <option value="compile"><?php _e('Compile Selected', 'incon-scss'); ?></option>
                            <option value="delete-css"><?php _e('Delete CSS Output', 'incon-scss'); ?></option>
                        </select>
                        <button class="button" id="apply-bulk-action"><?php _e('Apply', 'incon-scss'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="incon-scss-sidebar">
            <div class="sidebar-widget">
                <h3><?php _e('Quick Settings', 'incon-scss'); ?></h3>
                <form id="quick-settings">
                    <label>
                        <input type="checkbox" name="auto_compile" <?php checked($settings['compile_on_save'] ?? false); ?> />
                        <?php _e('Auto-compile on page reload', 'incon-scss'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="minify" <?php checked($settings['minify'] ?? false); ?> />
                        <?php _e('Minify CSS output', 'incon-scss'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" name="source_maps" <?php checked($settings['source_maps'] ?? false); ?> />
                        <?php _e('Generate source maps', 'incon-scss'); ?>
                    </label>
                </form>
            </div>
            
            <div class="sidebar-widget">
                <h3><?php _e('Paths', 'incon-scss'); ?></h3>
                <p><strong><?php _e('SCSS:', 'incon-scss'); ?></strong><br><?php echo $scss_path; ?></p>
                <p><strong><?php _e('CSS:', 'incon-scss'); ?></strong><br><?php echo $css_path; ?></p>
            </div>
        </div>
    </div>
    
    <div id="file-editor-modal" class="incon-scss-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('Edit SCSS File', 'incon-scss'); ?></h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="scss-editor"></div>
            </div>
            <div class="modal-footer">
                <button class="button button-primary" id="save-file"><?php _e('Save', 'incon-scss'); ?></button>
                <button class="button modal-close"><?php _e('Cancel', 'incon-scss'); ?></button>
            </div>
        </div>
    </div>
    
    <div id="dependencies-modal" class="incon-scss-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php _e('File Dependencies', 'incon-scss'); ?></h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="dependency-graph"></div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Handle compile all button
    $('#compile-all').on('click', function() {
        var $btn = $(this);
        var $status = $('#compile-status');
        
        $btn.prop('disabled', true);
        $status.html('<div class="notice notice-info"><p>Compiling...</p></div>');
        
        // Use REST API for better authentication
        $.ajax({
            url: '<?php echo rest_url('incon-scss/v1/compile'); ?>',
            type: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                if (response.success) {
                    var msg = response.message || 'Compilation successful';
                    $status.html('<div class="notice notice-success"><p>✓ ' + msg + '</p></div>');
                    // Reload the page to show updated files
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    var errorMsg = (response.data && response.data.message) ? response.data.message : 
                                   response.message ? response.message : 'Unknown error';
                    $status.html('<div class="notice notice-error"><p>✗ ' + errorMsg + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                var errorMsg = error;
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {}
                $status.html('<div class="notice notice-error"><p>✗ Request failed: ' + errorMsg + '</p></div>');
            }
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });
    
    // Handle individual file compile buttons
    $('.compile-single').on('click', function() {
        var $btn = $(this);
        var file = $btn.data('file');
        var $row = $btn.closest('tr');
        
        $btn.prop('disabled', true).text('Compiling...');
        
        // Use REST API
        $.ajax({
            url: '<?php echo rest_url('incon-scss/v1/compile'); ?>',
            type: 'POST',
            data: JSON.stringify({ file: file }),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                if (response.success) {
                    $btn.text('✓ Compiled');
                    $row.css('background-color', '#d4f4dd');
                    setTimeout(function() {
                        $btn.text('Compile');
                        $row.css('background-color', '');
                        // Reload to update file info
                        location.reload();
                    }, 1500);
                } else {
                    var errorMsg = (response.data && response.data.message) ? response.data.message : 
                                   response.message ? response.message : 'Failed';
                    $btn.text('✗ ' + errorMsg);
                    $row.css('background-color', '#f4d4d4');
                    setTimeout(function() {
                        $btn.text('Compile');
                        $row.css('background-color', '');
                    }, 3000);
                }
            },
            error: function(xhr, status, error) {
                $btn.text('✗ Failed');
                setTimeout(function() {
                    $btn.text('Compile');
                }, 2000);
            }
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });
});
</script>