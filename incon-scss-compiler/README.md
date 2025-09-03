# Incon SCSS Compiler for WordPress

A powerful WordPress plugin for compiling SCSS to CSS with advanced features and real-time compilation.

## Features

- **Real-time SCSS Compilation** - Compile SCSS files on-the-fly
- **Auto-compile on Save** - Automatically recompile when SCSS files change
- **Source Maps** - Generate source maps for easier debugging
- **Autoprefixer** - Automatically add vendor prefixes
- **CSS Minification** - Optimize CSS output for production
- **Dependency Tracking** - Track and visualize file dependencies
- **Compilation Statistics** - Monitor performance and optimization
- **Hot Reload** - Live CSS updates without page refresh (admin only)
- **WordPress Integration** - Custom SCSS functions for WordPress data
- **Modern Admin UI** - Clean, responsive dashboard interface

## Installation

1. Upload the `incon-scss-compiler` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Tools > SCSS Compiler** to configure settings

### Installing ScssPhp Library

The plugin requires the ScssPhp library. Choose one of these methods:

#### Method 1: Composer (Recommended)
```bash
cd wp-content/plugins/incon-scss-compiler
composer require scssphp/scssphp
```

#### Method 2: Manual Installation
1. Download ScssPhp from [https://github.com/scssphp/scssphp](https://github.com/scssphp/scssphp)
2. Extract to `wp-content/plugins/incon-scss-compiler/scssphp/`

## Configuration

### Basic Settings

1. Go to **Tools > SCSS Compiler > Settings**
2. Configure your directories:
   - **Base Directory**: Choose where your SCSS files are located
   - **SCSS Directory**: Path to SCSS files (e.g., `/scss/`)
   - **CSS Directory**: Output path for compiled CSS (e.g., `/css/`)

### Development Mode

Enable these options for development:
- **Auto-compile on page reload** - Check for changes on each page load
- **File Watching** - Monitor files and compile via AJAX
- **Hot Reload** - Live CSS updates in admin area

### Production Settings

For production sites:
- Set **Output Style** to "Compressed"
- Enable **Minify CSS Output**
- Disable development features
- Enable **CSS Caching**

## Usage

### Manual Compilation

1. Navigate to **Tools > SCSS Compiler**
2. Click **Compile All Files** to compile all SCSS files
3. Or click **Compile** next to individual files

### Automatic Compilation

With **Auto-compile on save** enabled, files compile automatically when:
- SCSS files are modified
- Dependencies change
- Page is reloaded (in development mode)

### Custom WordPress Functions

Use these custom SCSS functions in your stylesheets:

```scss
// Get WordPress option
.header {
    background: wp-option('header_color', '#333');
}

// Get theme customizer value
.logo {
    width: theme-mod('logo_width', '200px');
}

// Generate asset URL
.hero {
    background-image: asset-url('images/hero.jpg');
}

// Automatic contrast color
.button {
    background: $primary-color;
    color: contrast-color($primary-color);
}
```

## File Structure

```
your-theme/
├── scss/
│   ├── _variables.scss    # Variables and settings
│   ├── _mixins.scss       # Reusable mixins
│   ├── _components.scss   # Component styles
│   └── style.scss         # Main stylesheet
└── css/
    └── style.css          # Compiled output
```

**Note**: Files starting with underscore (_) are partials and won't be compiled directly.

## Testing

Run the included test files to verify installation:

1. **Basic Test**: `/wp-content/plugins/incon-scss-compiler/test-simple-compile.php`
2. **Complete Test Suite**: `/wp-content/plugins/incon-scss-compiler/test-complete.php`
3. **Standalone Test**: `/wp-content/plugins/incon-scss-compiler/test-standalone.php`

## Troubleshooting

### Compilation Fails

1. Check directory permissions (must be writable)
2. Verify ScssPhp library is installed
3. Check PHP error logs for details
4. Run test files to diagnose issues

### AJAX Not Working

1. Ensure you're logged in as admin
2. Check browser console for errors
3. Verify nonce is being generated
4. Test with `/test-ajax.php`

### Files Not Auto-compiling

1. Enable **Auto-compile on save** in settings
2. Check file modification times
3. Clear any caching plugins
4. Verify dependency tracking is enabled

## API Hooks

### Filters

```php
// Add custom import paths
add_filter('incon_scss_import_paths', function($paths) {
    $paths[] = get_template_directory() . '/vendor/scss/';
    return $paths;
});

// Add SCSS variables
add_filter('incon_scss_variables', function($vars) {
    $vars['primary-color'] = get_theme_mod('primary_color', '#007cba');
    return $vars;
});
```

### Actions

```php
// After compilation
add_action('incon_scss_compiled', function($file, $output) {
    // Custom post-compilation logic
}, 10, 2);

// Register custom functions
add_action('incon_scss_register_functions', function($compiler) {
    $compiler->registerFunction('my-function', function($args) {
        // Custom SCSS function logic
    });
});
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- ScssPhp library
- Write permissions for CSS output directory

## Support

For issues or feature requests, please contact the plugin developer.

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Core SCSS compilation
- Admin dashboard
- Auto-compilation features
- WordPress integration