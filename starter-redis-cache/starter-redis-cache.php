<?php
/**
 * Plugin Name: Starter Redis Cache
 * Plugin URI: https://github.com/developer/starter-redis-cache
 * Description: Gestione completa di Redis per WordPress e WooCommerce senza dipendenze da terzi. Ottimizzato per server multi-dominio con 10+ siti.
 * Version: 1.0.0
 * Author: Developer
 * Author URI: https://developer.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: starter-redis-cache
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'SRC_VERSION', '1.0.0' );
define( 'SRC_PLUGIN_FILE', __FILE__ );
define( 'SRC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SRC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SRC_OPTION_NAME', 'starter_redis_cache_settings' );

/**
 * Main plugin class - Singleton pattern.
 */
final class Starter_Redis_Cache {

    /**
     * Single instance.
     *
     * @var Starter_Redis_Cache|null
     */
    private static $instance = null;

    /**
     * Admin handler.
     *
     * @var SRC_Admin|null
     */
    private $admin = null;

    /**
     * Drop-in manager.
     *
     * @var SRC_Dropin|null
     */
    private $dropin = null;

    /**
     * WooCommerce handler.
     *
     * @var SRC_WooCommerce|null
     */
    private $woocommerce = null;

    /**
     * Get singleton instance.
     *
     * @return Starter_Redis_Cache
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->load_includes();
        $this->init_hooks();
    }

    /**
     * Load required files.
     */
    private function load_includes() {
        require_once SRC_PLUGIN_DIR . 'includes/class-src-dropin.php';
        require_once SRC_PLUGIN_DIR . 'includes/class-src-admin.php';
        require_once SRC_PLUGIN_DIR . 'includes/class-src-woocommerce.php';
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        // Plugin lifecycle
        register_activation_hook( SRC_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( SRC_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Initialize components
        add_action( 'plugins_loaded', array( $this, 'init_components' ), 5 );

        // Apply cache configuration early
        add_action( 'plugins_loaded', array( $this, 'apply_cache_configuration' ), 1 );

        // Plugin action links
        add_filter( 'plugin_action_links_' . SRC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
    }

    /**
     * Initialize plugin components.
     */
    public function init_components() {
        $this->dropin      = new SRC_Dropin();
        $this->woocommerce = new SRC_WooCommerce();

        if ( is_admin() ) {
            $this->admin = new SRC_Admin();
        }
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        // Set default settings
        $defaults = array(
            'enabled'              => true,
            'non_persistent_groups' => "wc_session_id\nwc-session-id\nwoocommerce_session_id\ncart\nwc_cart\nwoocommerce_cart",
            'redis_hash_groups'    => "post_meta\nterm_meta\nuser_meta",
            'global_groups'        => '',
            'custom_ttl'           => '',
        );

        $existing = get_option( SRC_OPTION_NAME );
        if ( false === $existing ) {
            add_option( SRC_OPTION_NAME, $defaults );
        }

        // Install drop-in if phpredis is available
        if ( class_exists( 'Redis' ) ) {
            $dropin = new SRC_Dropin();
            $dropin->install();
        }
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Remove drop-in
        $dropin = new SRC_Dropin();
        $dropin->uninstall();

        // Flush cache to clean up
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
    }

    /**
     * Apply cache group configuration from saved settings.
     */
    public function apply_cache_configuration() {
        global $wp_object_cache;

        $settings = get_option( SRC_OPTION_NAME );
        if ( ! $settings || empty( $settings['enabled'] ) ) {
            return;
        }

        // Apply non-persistent groups
        if ( ! empty( $settings['non_persistent_groups'] ) ) {
            $groups = $this->parse_groups( $settings['non_persistent_groups'] );
            if ( ! empty( $groups ) && function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
                wp_cache_add_non_persistent_groups( $groups );
            }
        }

        // Apply Redis hash groups
        if ( ! empty( $settings['redis_hash_groups'] ) ) {
            $groups = $this->parse_groups( $settings['redis_hash_groups'] );
            if ( ! empty( $groups ) && function_exists( 'wp_cache_add_redis_hash_groups' ) ) {
                wp_cache_add_redis_hash_groups( $groups );
            }
        }

        // Apply global groups
        if ( ! empty( $settings['global_groups'] ) ) {
            $groups = $this->parse_groups( $settings['global_groups'] );
            if ( ! empty( $groups ) && function_exists( 'wp_cache_add_global_groups' ) ) {
                wp_cache_add_global_groups( $groups );
            }
        }

        // Apply custom TTL
        if ( ! empty( $settings['custom_ttl'] ) ) {
            $ttls = $this->parse_ttl( $settings['custom_ttl'] );
            if ( ! empty( $ttls ) && function_exists( 'wp_cache_set_group_ttl' ) ) {
                wp_cache_set_group_ttl( $ttls );
            }
        }
    }

    /**
     * Parse groups from textarea input (one per line).
     *
     * @param string $input Newline-separated group names.
     * @return array Parsed group names.
     */
    private function parse_groups( $input ) {
        $groups = array_map( 'trim', explode( "\n", $input ) );
        return array_filter( $groups, function ( $g ) {
            return $g !== '';
        } );
    }

    /**
     * Parse TTL configuration from textarea (group:seconds per line).
     *
     * @param string $input Newline-separated group:seconds pairs.
     * @return array Associative array of group => seconds.
     */
    private function parse_ttl( $input ) {
        $ttls  = array();
        $lines = array_map( 'trim', explode( "\n", $input ) );
        foreach ( $lines as $line ) {
            if ( strpos( $line, ':' ) !== false ) {
                list( $group, $seconds ) = explode( ':', $line, 2 );
                $group   = trim( $group );
                $seconds = (int) trim( $seconds );
                if ( $group !== '' && $seconds > 0 ) {
                    $ttls[ $group ] = $seconds;
                }
            }
        }
        return $ttls;
    }

    /**
     * Add settings link to plugin list page.
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'tools.php?page=starter-redis-cache' ),
            __( 'Impostazioni', 'starter-redis-cache' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Get plugin settings.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = array(
            'enabled'              => true,
            'non_persistent_groups' => '',
            'redis_hash_groups'    => '',
            'global_groups'        => '',
            'custom_ttl'           => '',
        );

        $settings = get_option( SRC_OPTION_NAME, $defaults );
        return wp_parse_args( $settings, $defaults );
    }
}

// Initialize plugin
function starter_redis_cache() {
    return Starter_Redis_Cache::get_instance();
}

add_action( 'plugins_loaded', 'starter_redis_cache', 0 );
