<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('incon_scss_settings', array());
?>

<div class="wrap">
    <h1><?php _e('SCSS Compiler Settings', 'incon-scss'); ?></h1>
    
    <form method="post" action="options.php">
        <?php settings_fields('incon_scss_settings_group'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="base_dir"><?php _e('Base Directory', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <select name="incon_scss_settings[base_dir]" id="base_dir">
                            <option value="<?php echo get_stylesheet_directory(); ?>" <?php selected($settings['base_dir'], get_stylesheet_directory()); ?>>
                                <?php _e('Current Theme', 'incon-scss'); ?>
                            </option>
                            <?php if (get_stylesheet_directory() !== get_template_directory()): ?>
                            <option value="<?php echo get_template_directory(); ?>" <?php selected($settings['base_dir'], get_template_directory()); ?>>
                                <?php _e('Parent Theme', 'incon-scss'); ?>
                            </option>
                            <?php endif; ?>
                            <option value="<?php echo wp_upload_dir()['basedir']; ?>" <?php selected($settings['base_dir'], wp_upload_dir()['basedir']); ?>>
                                <?php _e('Uploads Directory', 'incon-scss'); ?>
                            </option>
                        </select>
                        <p class="description"><?php _e('Choose the base location for your SCSS files', 'incon-scss'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="scss_dir"><?php _e('SCSS Directory', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="incon_scss_settings[scss_dir]" id="scss_dir" 
                               value="<?php echo esc_attr($settings['scss_dir'] ?? '/scss/'); ?>" class="regular-text" />
                        <p class="description"><?php _e('Path to SCSS files relative to base directory (e.g., /scss/)', 'incon-scss'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="css_dir"><?php _e('CSS Directory', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="incon_scss_settings[css_dir]" id="css_dir" 
                               value="<?php echo esc_attr($settings['css_dir'] ?? '/css/'); ?>" class="regular-text" />
                        <p class="description"><?php _e('Output directory for compiled CSS files (e.g., /css/)', 'incon-scss'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Compilation Settings', 'incon-scss'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="incon_scss_settings[output_style]" value="compressed" 
                                       <?php checked($settings['output_style'] ?? 'compressed', 'compressed'); ?> />
                                <?php _e('Compressed (Production)', 'incon-scss'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="incon_scss_settings[output_style]" value="expanded" 
                                       <?php checked($settings['output_style'] ?? '', 'expanded'); ?> />
                                <?php _e('Expanded (Development)', 'incon-scss'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Features', 'incon-scss'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="incon_scss_settings[source_maps]" value="1" 
                                       <?php checked($settings['source_maps'] ?? false, true); ?> />
                                <?php _e('Generate Source Maps', 'incon-scss'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="incon_scss_settings[autoprefixer]" value="1" 
                                       <?php checked($settings['autoprefixer'] ?? false, true); ?> />
                                <?php _e('Enable Autoprefixer', 'incon-scss'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="incon_scss_settings[minify]" value="1" 
                                       <?php checked($settings['minify'] ?? false, true); ?> />
                                <?php _e('Minify CSS Output', 'incon-scss'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="incon_scss_settings[enqueue_compiled]" value="1" 
                                       <?php checked($settings['enqueue_compiled'] ?? false, true); ?> />
                                <?php _e('Auto-enqueue Compiled CSS', 'incon-scss'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr style="background: #f0f0f1;">
                    <th scope="row"><?php _e('Development Mode', 'incon-scss'); ?></th>
                    <td>
                        <fieldset>
                            <label style="font-weight: bold;">
                                <input type="checkbox" name="incon_scss_settings[compile_on_save]" value="1" 
                                       <?php checked($settings['compile_on_save'] ?? false, true); ?> />
                                <?php _e('Auto-compile on page reload (Development)', 'incon-scss'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically recompile SCSS files when they change (checks on each page load)', 'incon-scss'); ?></p>
                            <br>
                            
                            <label>
                                <input type="checkbox" name="incon_scss_settings[watch_enabled]" value="1" 
                                       <?php checked($settings['watch_enabled'] ?? false, true); ?> />
                                <?php _e('Enable File Watching', 'incon-scss'); ?>
                            </label>
                            <p class="description"><?php _e('Watch for file changes and compile automatically (uses AJAX)', 'incon-scss'); ?></p>
                            <br>
                            
                            <label>
                                <input type="checkbox" name="incon_scss_settings[hot_reload]" value="1" 
                                       <?php checked($settings['hot_reload'] ?? false, true); ?> />
                                <?php _e('Hot Reload CSS', 'incon-scss'); ?>
                            </label>
                            <p class="description"><?php _e('Live reload CSS without page refresh (admin only)', 'incon-scss'); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="error_display"><?php _e('Error Display', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <select name="incon_scss_settings[error_display]" id="error_display">
                            <option value="admin" <?php selected($settings['error_display'] ?? 'admin', 'admin'); ?>>
                                <?php _e('Show in Admin Only', 'incon-scss'); ?>
                            </option>
                            <option value="frontend" <?php selected($settings['error_display'] ?? '', 'frontend'); ?>>
                                <?php _e('Show on Frontend (logged in users)', 'incon-scss'); ?>
                            </option>
                            <option value="console" <?php selected($settings['error_display'] ?? '', 'console'); ?>>
                                <?php _e('Console Only', 'incon-scss'); ?>
                            </option>
                            <option value="none" <?php selected($settings['error_display'] ?? '', 'none'); ?>>
                                <?php _e('Hide Errors', 'incon-scss'); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Advanced', 'incon-scss'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="incon_scss_settings[dependency_tracking]" value="1" 
                                       <?php checked($settings['dependency_tracking'] ?? false, true); ?> />
                                <?php _e('Enable Dependency Tracking', 'incon-scss'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="incon_scss_settings[custom_functions]" value="1" 
                                       <?php checked($settings['custom_functions'] ?? false, true); ?> />
                                <?php _e('Enable WordPress Custom Functions', 'incon-scss'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <hr>
    
    <h2><?php _e('Quick Test', 'incon-scss'); ?></h2>
    <p><?php _e('Test if SCSS compilation is working:', 'incon-scss'); ?></p>
    <button id="test-compile" class="button"><?php _e('Test Compilation', 'incon-scss'); ?></button>
    <div id="test-result" style="margin-top: 10px;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#test-compile').on('click', function() {
        var $btn = $(this);
        var $result = $('#test-result');
        
        $btn.prop('disabled', true);
        $result.html('<p>Testing...</p>');
        
        console.log('Testing compilation...');
        
        // Use REST API endpoint
        $.ajax({
            url: '<?php echo rest_url('incon-scss/v1/compile'); ?>',
            type: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                console.log('Response:', response);
                if (response.success) {
                    var msg = response.message || 'Compilation successful';
                    if (response.files && response.files.length > 0) {
                        msg += ' - ' + response.files.length + ' files compiled';
                    }
                    $result.html('<div class="notice notice-success"><p>✓ ' + msg + '</p></div>');
                } else {
                    var errorMsg = 'Unknown error';
                    if (response.data && response.data.message) {
                        errorMsg = response.data.message;
                    } else if (response.message) {
                        errorMsg = response.message;
                    } else if (typeof response === 'string') {
                        errorMsg = 'Invalid response: ' + response.substring(0, 100);
                    }
                    $result.html('<div class="notice notice-error"><p>✗ Error: ' + errorMsg + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX failed:', status, error);
                console.error('Response:', xhr.responseText);
                
                var errorMsg = error;
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.message) {
                        errorMsg = response.message;
                    }
                } catch(e) {}
                
                $result.html('<div class="notice notice-error"><p>✗ Request failed: ' + errorMsg + '</p></div>');
                
                // Show raw response for debugging
                if (xhr.responseText) {
                    $result.append('<details><summary>Debug Info</summary><pre>' + xhr.responseText.substring(0, 500) + '</pre></details>');
                }
            }
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });
});
</script>