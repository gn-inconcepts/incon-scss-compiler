=== Incon SCSS Compiler ===
Contributors: inconcepts
Tags: scss, sass, css, compiler, preprocessor, autoprefixer, postcss, development
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Advanced SCSS compiler for WordPress with real-time compilation, autoprefixer, and modern CSS features.

== Description ==

Incon SCSS Compiler is a powerful WordPress plugin that brings professional SCSS compilation to your WordPress development workflow. It provides real-time compilation, advanced features, and seamless integration with WordPress themes and plugins.

= Key Features =

* **Real-time SCSS Compilation** - Automatically compile SCSS files to CSS on save
* **Autoprefixer Support** - Automatically add vendor prefixes for better browser compatibility
* **Source Maps** - Generate source maps for easier debugging
* **File Watching** - Monitor SCSS files for changes and recompile automatically
* **Dependency Tracking** - Intelligent tracking of @import dependencies
* **Multiple Output Styles** - Choose from compressed, expanded, or other formats
* **Cache Management** - Built-in caching system for optimized performance
* **REST API Support** - Compile SCSS remotely via REST API
* **Error Handling** - Comprehensive error reporting and debugging tools
* **PostCSS Integration** - Support for PostCSS plugins
* **GitHub Updates** - Automatic updates from GitHub releases

= Perfect For =

* Theme developers who want to use SCSS in their themes
* Plugin developers needing CSS preprocessing
* Agencies managing multiple WordPress sites
* Developers wanting modern CSS workflows in WordPress

= Technical Requirements =

* WordPress 5.0 or higher
* PHP 7.2 or higher
* Write permissions for cache directory

== Installation ==

1. Upload the `incon-scss-compiler` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Settings > SCSS Compiler to configure your settings
4. Place your SCSS files in your theme's `/scss/` directory
5. The plugin will automatically compile them to the `/css/` directory

= Manual Installation =

1. Download the plugin zip file
2. Login to your WordPress admin panel
3. Navigate to Plugins > Add New > Upload Plugin
4. Choose the downloaded zip file
5. Click "Install Now" and then "Activate"

== Frequently Asked Questions ==

= Where should I place my SCSS files? =

By default, place your SCSS files in your theme's `/scss/` directory. The compiled CSS files will be generated in the `/css/` directory. You can customize these paths in the plugin settings.

= Can I use this with my existing theme? =

Yes! The plugin works with any WordPress theme. Simply add your SCSS files to the configured directory and they will be compiled automatically.

= Does it support @import statements? =

Yes, the plugin fully supports @import statements and tracks dependencies intelligently. When you update an imported file, all dependent files are automatically recompiled.

= Can I use modern CSS features? =

Absolutely! The plugin supports all modern SCSS features including:
* Variables
* Nesting
* Mixins
* Functions
* Extends
* Operators
* And more!

= Is autoprefixer included? =

Yes, autoprefixer is built-in and can be configured to target specific browser versions through the settings panel.

= How do I enable source maps? =

Source maps are enabled by default. You can configure them in Settings > SCSS Compiler > Source Maps.

= Can I compile SCSS programmatically? =

Yes, the plugin provides hooks and a REST API for programmatic compilation:

`// Compile via PHP
do_action('incon_scss_compile', $scss_file);

// Or use the REST API
POST /wp-json/incon-scss/v1/compile`

= Does it work with WordPress Multisite? =

Yes, the plugin is fully compatible with WordPress Multisite installations.

== Screenshots ==

1. Main dashboard showing compilation statistics and recent activity
2. Settings page with all configuration options
3. Dependency tracking visualization
4. Error reporting interface
5. Cache management panel

== Changelog ==

= 1.0.0 =
* Initial release
* Full SCSS compilation support
* Autoprefixer integration
* Source maps generation
* File watching capabilities
* Dependency tracking
* REST API endpoints
* Cache management
* Error handling
* PostCSS support
* GitHub update integration

== Upgrade Notice ==

= 1.0.0 =
Initial release of Incon SCSS Compiler. Install to start using SCSS in your WordPress development.

== Developer Documentation ==

= Hooks and Filters =

**Actions:**
* `incon_scss_before_compile` - Fired before compilation starts
* `incon_scss_after_compile` - Fired after successful compilation
* `incon_scss_compile_error` - Fired when compilation fails

**Filters:**
* `incon_scss_variables` - Modify SCSS variables before compilation
* `incon_scss_import_paths` - Add custom import paths
* `incon_scss_output_style` - Change output style dynamically
* `incon_scss_compiler_options` - Modify compiler options

= REST API Endpoints =

* `POST /wp-json/incon-scss/v1/compile` - Compile SCSS file
* `GET /wp-json/incon-scss/v1/status` - Get compilation status
* `POST /wp-json/incon-scss/v1/clear-cache` - Clear compilation cache

= Example Usage =

`// Add custom SCSS variables
add_filter('incon_scss_variables', function($variables) {
    $variables['primary-color'] = '#007cba';
    $variables['font-size'] = '16px';
    return $variables;
});

// Add custom import paths
add_filter('incon_scss_import_paths', function($paths) {
    $paths[] = get_template_directory() . '/assets/scss/';
    return $paths;
});`

== Support ==

For support, please visit [GitHub Issues](https://github.com/gn-inconcepts/incon-scss-compiler/issues)

== Privacy Policy ==

This plugin does not collect or transmit any personal data. All compilation happens locally on your server.

== Credits ==

This plugin uses the following open-source libraries:
* [ScssPhp](https://scssphp.github.io/scssphp/) - SCSS compiler in PHP
* [Autoprefixer](https://autoprefixer.github.io/) - PostCSS plugin for vendor prefixes