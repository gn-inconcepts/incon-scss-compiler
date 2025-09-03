<?php
/**
 * Admin Updates Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$update_source = get_option('incon_scss_update_source', 'github');
$github_username = get_option('incon_scss_github_username', 'incon');
$github_repo = get_option('incon_scss_github_repo', 'incon-scss-compiler');
$github_token = get_option('incon_scss_github_token', '');
$update_server_url = get_option('incon_scss_update_server_url', '');
$license_key = get_option('incon_scss_license_key', '');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="incon-scss-update-settings">
        <h2><?php _e('Update Settings', 'incon-scss'); ?></h2>
        
        <form method="post" action="options.php">
            <?php settings_fields('incon_scss_update_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="incon_scss_update_source"><?php _e('Update Source', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <select name="incon_scss_update_source" id="incon_scss_update_source">
                            <option value="none" <?php selected($update_source, 'none'); ?>>
                                <?php _e('No automatic updates', 'incon-scss'); ?>
                            </option>
                            <option value="wordpress" <?php selected($update_source, 'wordpress'); ?>>
                                <?php _e('WordPress.org Repository', 'incon-scss'); ?>
                            </option>
                            <option value="github" <?php selected($update_source, 'github'); ?>>
                                <?php _e('GitHub Repository', 'incon-scss'); ?>
                            </option>
                            <option value="custom" <?php selected($update_source, 'custom'); ?>>
                                <?php _e('Custom Update Server', 'incon-scss'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Choose where to check for plugin updates', 'incon-scss'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr class="github-settings" style="<?php echo $update_source !== 'github' ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="incon_scss_github_username"><?php _e('GitHub Username', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="incon_scss_github_username" id="incon_scss_github_username" 
                               value="<?php echo esc_attr($github_username); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('GitHub username or organization', 'incon-scss'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr class="github-settings" style="<?php echo $update_source !== 'github' ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="incon_scss_github_repo"><?php _e('GitHub Repository', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="incon_scss_github_repo" id="incon_scss_github_repo" 
                               value="<?php echo esc_attr($github_repo); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('Repository name on GitHub', 'incon-scss'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr class="github-settings" style="<?php echo $update_source !== 'github' ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="incon_scss_github_token"><?php _e('GitHub Access Token', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="incon_scss_github_token" id="incon_scss_github_token" 
                               value="<?php echo esc_attr($github_token); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('Optional: For private repositories. Generate at GitHub Settings > Developer settings > Personal access tokens', 'incon-scss'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr class="custom-settings" style="<?php echo $update_source !== 'custom' ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="incon_scss_update_server_url"><?php _e('Update Server URL', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="incon_scss_update_server_url" id="incon_scss_update_server_url" 
                               value="<?php echo esc_attr($update_server_url); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('Your custom update server endpoint', 'incon-scss'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr class="custom-settings" style="<?php echo $update_source !== 'custom' ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="incon_scss_license_key"><?php _e('License Key', 'incon-scss'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="incon_scss_license_key" id="incon_scss_license_key" 
                               value="<?php echo esc_attr($license_key); ?>" class="regular-text" />
                        <p class="description">
                            <?php _e('License key for premium updates', 'incon-scss'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Update Settings', 'incon-scss')); ?>
        </form>
        
        <div class="incon-scss-update-info">
            <h3><?php _e('Current Version', 'incon-scss'); ?></h3>
            <p><?php echo INCON_SCSS_VERSION; ?></p>
            
            <h3><?php _e('Update Check', 'incon-scss'); ?></h3>
            <button type="button" class="button" id="check-for-updates">
                <?php _e('Check for Updates Now', 'incon-scss'); ?>
            </button>
            <div id="update-check-result"></div>
        </div>
    </div>
</div>

<style>
.incon-scss-update-settings {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}

.incon-scss-update-info {
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #ddd;
}

#update-check-result {
    margin-top: 10px;
    padding: 10px;
    background: #f5f5f5;
    border-left: 4px solid #72aee6;
    display: none;
}

#update-check-result.success {
    border-color: #46b450;
}

#update-check-result.error {
    border-color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#incon_scss_update_source').on('change', function() {
        var source = $(this).val();
        
        $('.github-settings').toggle(source === 'github');
        $('.custom-settings').toggle(source === 'custom');
    });
    
    $('#check-for-updates').on('click', function() {
        var $button = $(this);
        var $result = $('#update-check-result');
        
        $button.prop('disabled', true).text('<?php _e('Checking...', 'incon-scss'); ?>');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'incon_scss_check_updates',
                nonce: '<?php echo wp_create_nonce('incon_scss_check_updates'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.removeClass('error').addClass('success')
                           .html(response.data.message).show();
                } else {
                    $result.removeClass('success').addClass('error')
                           .html(response.data.message || '<?php _e('Update check failed', 'incon-scss'); ?>').show();
                }
            },
            error: function() {
                $result.removeClass('success').addClass('error')
                       .html('<?php _e('Network error during update check', 'incon-scss'); ?>').show();
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('Check for Updates Now', 'incon-scss'); ?>');
            }
        });
    });
});
</script>