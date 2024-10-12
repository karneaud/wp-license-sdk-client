<?php

use Karneaud\License\Manager\WP_License_Server;

if (!defined('ABSPATH') || !defined('PACKAGE_VERSION') || !defined('LICENSE_SERVER_URL')) exit;

define('WP_THEME_DIR', WP_CONTENT_DIR . '/themes');

/**
 * Register all actions and filters for the plugin
 *
 * @link       https://kendallarneaud.me
 * @since      1.0.0
 *
 * @package    WP_Package_Loader
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    WP_Package_Loader
 * @author     Kendall Arneaud <kendall.arneaud@gmail.com>
 */
new class {
	/**
	 * The array of actions registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
	 */
	protected $actions;
	/**
	 * The array of filters registered with WordPress.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $filters    The filters registered with WordPress to fire when the plugin loads.
	 */
	protected $filters;
    protected $type = 'theme';
    protected $slug;
    protected $errors;
    protected $license_server;
    protected $license_key;
    protected $license_sig;
	/**
	 * Initialize the collections used to maintain the actions and filters.
	 *
	 * @since    1.0.0
	 */ 
	public function __construct() {
		$this->actions = array();
        $this->filters = array();
        $this->type = self::is_plugin(__DIR__) ? 'plugin' : 'theme';
        $package_type_dir_path = self::get_base_directory_path(__DIR__, "{$this->type}s");
        $path_parts = explode(DIRECTORY_SEPARATOR, trim($package_type_dir_path, DIRECTORY_SEPARATOR));
        $package_slug = array_shift($path_parts); 
        $this->slug = trim($package_slug, DIRECTORY_SEPARATOR);
        $this->license_server = new WP_License_Server(LICENSE_SERVER_URL, $this->slug, $this->type, PACKAGE_VERSION);
        $this->license_key = get_option("license_key_{$this->slug}");
        $this->license_sig = get_option("license_signature_{$this->slug}");
        // Register hooks for updates and license
        $this->register_license_hooks();
        call_user_func([$this, "register_{$this->type}_hooks"]);
        $this->run();
    }
	/**
	 * Add a new action to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook             The name of the WordPress action that is being registered.
	 * @param    object $component        A reference to the instance of the object on which the action is defined.
	 * @param    string $callback         The name of the function definition on the $component.
	 * @param    int    $priority         Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a new filter to the collection to be registered with WordPress.
	 *
	 * @since    1.0.0
	 * @param    string $hook             The name of the WordPress filter that is being registered.
	 * @param    object $component        A reference to the instance of the object on which the filter is defined.
	 * @param    string $callback         The name of the function definition on the $component.
	 * @param    int    $priority         Optional. The priority at which the function should be fired. Default is 10.
	 * @param    int    $accepted_args    Optional. The number of arguments that should be passed to the $callback. Default is 1
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * A utility function that is used to register the actions and hooks into a single
	 * collection.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @param    array  $hooks            The collection of hooks that is being registered (that is, actions or filters).
	 * @param    string $hook             The name of the WordPress filter that is being registered.
	 * @param    object $component        A reference to the instance of the object on which the filter is defined.
	 * @param    string $callback         The name of the function definition on the $component.
	 * @param    int    $priority         The priority at which the function should be fired.
	 * @param    int    $accepted_args    The number of arguments that should be passed to the $callback.
	 * @return   array                                  The collection of actions and filters registered with WordPress.
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		return $hooks;
	}

	/**
	 * Register the filters and actions with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}

		foreach ( $this->actions as $hook ) {
			add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
		}
	}
    /**
     * Register hooks for plugins
     */
    private function register_plugin_hooks() {
        $package = "{$this->slug}/{$this->slug}.php";
        register_activation_hook($package, $this, 'validate_plugin_license_on_activation');
        $this->add_action("after_plugin_row_{$package}", $this, 'print_license_under_plugin', 10, 3);
    }
    /**
     * Register hooks for themes
     */
    private function register_theme_hooks() {
        $this->add_action('admin_menu', $this, 'setup_theme_admin_menus', 10);
        $this->add_filter('custom_menu_order', $this, 'alter_admin_appearance_submenu_order',  10, 1);
        add_action('after_switch_theme', function() {
            if (! $this->license_server->validate_license($this->license_key, $this->license_sig) ) {
                $url = add_query_arg('page', 'theme-license', admin_url('themes.php'));
                if (!strstr($url, $_SERVER['REQUEST_URI']) && stristr($_SERVER['REQUEST_URI'], 'theme')) {
                    wp_redirect($url);
                    exit;
                }
            }
        });
        
        $this->add_action('current_screen', $this, 'validate_theme_license_on_activation');
    }

    /**
     * Register license hooks for AJAX and error handling.
     */
    private function register_license_hooks() {
        add_action("wp_ajax_{$this->slug}_activate_license", function (){ 
            $nonce = sanitize_text_field($_REQUEST['nonce']);
            $license_key = sanitize_text_field($_REQUEST['license_key']);
            $package_slug = sanitize_text_field($_REQUEST['package_slug']);
            if(!wp_verify_nonce( $nonce, 'license_nonce' ) || empty($license_key) || empty($package_slug)){
                $this->process_ajax_response('Request invalid', 500); 
            }

            $response = $this->license_server->activate_license($license_key, $package_slug, $this->type);
            if($response instanceof \Throwable)  {
                $response = [ $response->getMessage(), (int) $response->getCode() ];
            } else if(is_null($response)) {
                $response = ['License could not be activated!', 500 ];
            } else {
                update_option("license_signature_{$response->package_slug}", $response->license_signature);
                update_option("license_key_{$response->package_slug}", $response->license_key);
                $response = ["License Success: {$response->license_key} Activated!", 200];
            }
            
            $this->process_ajax_response(...$response);
        });
        add_action("wp_ajax_{$this->slug}_deactivate_license", function (){ 
            $nonce = sanitize_text_field($_REQUEST['nonce']);
            $license_key = sanitize_text_field($_REQUEST['license_key']);
            $package_slug = sanitize_text_field($_REQUEST['package_slug']);
            if(!wp_verify_nonce( $nonce, 'license_nonce' ) || empty($license_key) || empty($package_slug)){
                $this->process_ajax_response('Request invalid', 500); 
            }
            
            $response = $this->license_server->deactivate_license($license_key, $this->license_sig);
            if($response instanceof \Throwable)  {
                $response = [ $response->getMessage(), (int) $response->getCode() ];
            } else if(is_null($response)) {
                $response = ['License could not be deactivated!', 500 ];
            } else {
                delete_option("license_signature_{$response->package_slug}");
                delete_option("license_key_{$response->package_slug}");
                $response = ["License Success: {$response->license_key} Deactivated!", 200];
            }
            
            $this->process_ajax_response(...$response);
        });
        $this->add_action('admin_notices', $this, 'show_error_notice');
        $this->add_action( 'admin_enqueue_scripts',  $this, 'add_admin_scripts', 99, 1 );
        $this->add_action('get_template_part_license', $this, 'get_license_form', 10, 3);
    }

    private function process_ajax_response($message = '', $status = 200) {
        wp_send_json(['data' => compact('message')], $status); exit;
    }

    /**
     * Add admin scripts.
     */
    public function add_admin_scripts($hook) {
        $debug = defined('WP_DEBUG') && WP_DEBUG;
        $allowed_hooks = ['plugins.php', 'appearance_page_theme-license', 'appearance_page_parent-theme-license'];
        if (in_array($hook, $allowed_hooks, true) && stristr($hook, $this->type)) {
            $js_ext = $debug ? 'main.js' : 'main.min.js';
            $separator = DIRECTORY_SEPARATOR;
            $js_dir = plugin_dir_path(__FILE__) . "js{$separator}{$js_ext}";
            $js_url = ($this->type == 'theme') ? (get_stylesheet_directory_uri() . '/' . self::get_base_directory_path($js_dir, $this->slug) ):( plugin_dir_url(__FILE__) . "js/{$js_ext}");
            if (file_exists($js_dir)) {
                $ver_js = filemtime($js_dir);
                wp_enqueue_script("wp-license-manager-script-{$this->slug}", $js_url, ['jquery'], $ver_js, true);
                wp_localize_script("wp-license-manager-script-{$this->slug}", 'WP_LicenseManager', [
                    'action_prefix' => $this->slug,
                    'ajax_url'      => admin_url('admin-ajax.php'),
                ]);
            }
        }
    }


    /**
     * Print license form under the plugin page.
     */
    public function print_license_under_plugin($plugin_file = null, $plugin_data = null, $status = null) {
        get_template_part('license', 'page-plugin-row', compact('plugin_file', 'plugin_data', 'status'));
    }

    /**
     * Print license form on the theme page.
     */
    public function print_license_form_theme_page($a = null, $b = null, $c = null) {
        $theme = wp_get_theme();
        $title = __('Theme License - ', 'wp-license-manager') . $theme->get('Name');
        get_template_part('license', 'page-theme', compact('theme', 'title'));
    }

    /**
     * Setup theme license admin menus.
     */
    public function setup_theme_admin_menus() {
        add_submenu_page('themes.php', 'Theme License', 'Theme License', 'manage_options', 'theme-license', [$this, 'theme_license_settings']);
    }

    /**
     * Theme license settings page.
     */
    public function theme_license_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you are not allowed to access this page.'));
        }

        $this->print_license_form_theme_page();
    }

    /**
     * Alter admin submenu order for theme pages.
     */
    public function alter_admin_appearance_submenu_order($menu_ord) {
        global $submenu;

        $theme_menu = $submenu['themes.php'];
        $reordered_menu = [];
        $first_key = 0;
        $license_menu = null;

        foreach ($theme_menu as $key => $menu) {
            if ('themes.php' === $menu[2]) {
                $reordered_menu[$key] = $menu;
                $first_key = $key;
            } elseif ('theme-license' === $menu[2]) {
                $license_menu = $menu;
            } else {
                $reordered_menu[$key + 1] = $menu;
            }
        }

        $reordered_menu[$first_key + 1] = $license_menu;
        ksort($reordered_menu);
        $submenu['themes.php'] = $reordered_menu;

        return $menu_ord;
    }

    /**
     * Validate theme license on activation.
     */
    public function validate_theme_license_on_activation($screen) {
        if ( !$this->license_server->validate_license($this->license_key, $this->license_sig)) {
            if ($screen->base === 'themes' && $this->slug === get_stylesheet()) {
                $themes = array_filter(array_keys(wp_get_themes()), fn($theme) => $theme !== $this->slug) + [WP_DEFAULT_THEME];
                $default = array_shift($themes);
                switch_theme($default);
                $this->errorHandler(400, __('Theme license is not valid. Theme deactivated', 'wp-license-manager'));
            }
        }
    }

    public function validate_plugin_license_on_activation() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you are not allowed to access this page.'));
        }

        if (!$this->license_server->validate_license($this->license_key, $this->license_sig)) 
        {
            
            deactivate_plugins("{$this->slug}/{$this->slug}.php");
            $this->errorHandler(400, 'License is not valid.');
        }
    }

    public function get_license_form($slug, $name, $args)
    {
        // @todo remove in 2.0
        $license      = $this->license_key;
        $package_id   = $this->type == 'theme'? $this->slug : "{$this->slug}/{$this->slug}.php";
        $package_slug = $this->slug;
        $title = "License Authorization for " . ucfirst($this->type);
        include __DIR__ . "/templates/license-{$name}.php";
    }
    /**
     * Show error notices in the admin area.
     */
    public function show_error_notice() {
        if ($this->errors && $this->errors->has_errors()) {
            foreach ($this->errors->get_error_messages() as $error) {
                printf('<div class="notice notice-error is-dismissible"><p>%s: %s</p></div>', esc_html($this->slug), esc_html($error));
            }
        }
    }

    /**
     * Handle errors and add them to the WP_Error object.
     */
    private function errorHandler($code = '', $message = '') {
        if (!isset($this->errors)) {
            $this->errors = new WP_Error();
        }
        $this->errors->add($code, $message);
    }

    /**
     * Check if a file belongs to a plugin.
     */
    public static function is_plugin($absolute_path) {
        return is_file($absolute_path) && strpos($absolute_path, wp_normalize_path(WP_PLUGIN_DIR)) === 0;
    }

    /**
     * Check if a file belongs to a theme.
     */
    public static function is_theme($absolute_path) {
        return file_exists($absolute_path . '/style.css') ? basename($absolute_path) : null;
    }

    /**
     * Get the base directory path of the plugin/theme.
     */
    public static function get_base_directory_path($dir, $slug) {
        $plugin_dir = [];
        $count = count(explode(DIRECTORY_SEPARATOR, $dir));
        while (!in_array($slug, $plugin_dir) && ($count >= 0)) {
            $plugin_dir[] = basename($dir);
            $dir = dirname($dir);
            $count--;
        }

        $plugin_dir = array_reverse($plugin_dir);
        array_shift($plugin_dir);

        return join(DIRECTORY_SEPARATOR, $plugin_dir);
    }

};