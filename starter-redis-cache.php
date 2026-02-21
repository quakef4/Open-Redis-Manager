<?php
/**
 * Plugin Name: Open Redis Manager
 * Plugin URI: https://github.com/quakef4/Wp-redis-manager
 * Description: Gestione completa di Redis per WordPress e WooCommerce senza dipendenze da terzi. Ottimizzato per server multi-dominio con 10+ siti.
 * Version: 1.0.2
 * Author: quakef4
 * Author URI: https://github.com/quakef4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: starter-redis-cache
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// PHP version check - bail early if incompatible
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Open Redis Manager:</strong> Richiede PHP 7.4 o superiore. Versione attuale: ' . esc_html( PHP_VERSION );
        echo '</p></div>';
    } );
    return;
}

// Plugin constants
define( 'SRC_VERSION', '1.0.2' );
define( 'SRC_PLUGIN_FILE', __FILE__ );
define( 'SRC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SRC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SRC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SRC_OPTION_NAME', 'starter_redis_cache_settings' );

// ============================================================================
// Load include files at file level (not lazily) so classes are always available.
// Use file_exists to prevent fatal errors if a file is missing.
// ============================================================================
$src_includes = array(
    'includes/class-src-dropin.php',
    'includes/class-src-config.php',
    'includes/class-src-woocommerce.php',
);

// Admin class only in admin context
if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
    $src_includes[] = 'includes/class-src-admin.php';
}

foreach ( $src_includes as $src_file ) {
    $src_path = SRC_PLUGIN_DIR . $src_file;
    if ( file_exists( $src_path ) ) {
        require_once $src_path;
    } else {
        // Log missing file but don't crash
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Open Redis Manager: file mancante - ' . $src_path );
        }
    }
}
unset( $src_includes, $src_file, $src_path );

// ============================================================================
// Register activation/deactivation hooks at file level.
// CRITICAL: these MUST be registered here, not inside plugins_loaded,
// otherwise they never fire during plugin activation/deactivation.
// ============================================================================
register_activation_hook( __FILE__, 'src_activate' );
register_deactivation_hook( __FILE__, 'src_deactivate' );

/**
 * Plugin activation callback.
 */
function src_activate() {
    // Set default settings if not already present
    $defaults = array(
        'enabled'               => true,
        'non_persistent_groups' => "wc_session_id\nwc-session-id\nwoocommerce_session_id\ncart\nwc_cart\nwoocommerce_cart",
        'redis_hash_groups'     => "post_meta\nterm_meta\nuser_meta",
        'global_groups'         => '',
        'custom_ttl'            => '',
    );

    $existing = get_option( SRC_OPTION_NAME );
    if ( false === $existing ) {
        add_option( SRC_OPTION_NAME, $defaults );
    }

    // Do NOT auto-install drop-in on activation.
    // The user should do it from the admin panel after verifying
    // the Redis connection and configuration.
}

/**
 * Plugin deactivation callback.
 */
function src_deactivate() {
    // Remove drop-in only if it's ours
    if ( class_exists( 'SRC_Dropin' ) ) {
        $dropin = new SRC_Dropin();
        if ( $dropin->is_our_dropin() ) {
            $dropin->uninstall();
        }
    }
}

/**
 * Main plugin class - Singleton pattern.
 */
final class Open_Redis_Manager {

    /**
     * Single instance.
     *
     * @var Open_Redis_Manager|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return Open_Redis_Manager
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
        // Apply cache configuration as early as possible
        $this->apply_cache_configuration();

        // WooCommerce session isolation (must happen immediately, not on a hook)
        if ( class_exists( 'SRC_WooCommerce' ) ) {
            SRC_WooCommerce::enforce_session_isolation();
        }

        // Admin initialization
        if ( is_admin() && class_exists( 'SRC_Admin' ) ) {
            new SRC_Admin();
        }

        // Plugin action links
        add_filter( 'plugin_action_links_' . SRC_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
    }

    /**
     * Apply cache group configuration from saved settings.
     */
    public function apply_cache_configuration() {
        $settings = get_option( SRC_OPTION_NAME );
        if ( ! is_array( $settings ) || empty( $settings['enabled'] ) ) {
            return;
        }

        // Apply non-persistent groups
        if ( ! empty( $settings['non_persistent_groups'] ) ) {
            $groups = self::parse_groups( $settings['non_persistent_groups'] );
            if ( ! empty( $groups ) && function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
                wp_cache_add_non_persistent_groups( $groups );
            }
        }

        // Apply Redis hash groups
        if ( ! empty( $settings['redis_hash_groups'] ) ) {
            $groups = self::parse_groups( $settings['redis_hash_groups'] );
            if ( ! empty( $groups ) && function_exists( 'wp_cache_add_redis_hash_groups' ) ) {
                wp_cache_add_redis_hash_groups( $groups );
            }
        }

        // Apply global groups
        if ( ! empty( $settings['global_groups'] ) ) {
            $groups = self::parse_groups( $settings['global_groups'] );
            if ( ! empty( $groups ) && function_exists( 'wp_cache_add_global_groups' ) ) {
                wp_cache_add_global_groups( $groups );
            }
        }

        // Apply custom TTL
        if ( ! empty( $settings['custom_ttl'] ) ) {
            $ttls = self::parse_ttl( $settings['custom_ttl'] );
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
    public static function parse_groups( $input ) {
        if ( ! is_string( $input ) ) {
            return array();
        }
        $groups = array_map( 'trim', explode( "\n", $input ) );
        return array_values( array_filter( $groups, function ( $g ) {
            return $g !== '';
        } ) );
    }

    /**
     * Parse TTL configuration from textarea (group:seconds per line).
     *
     * @param string $input Newline-separated group:seconds pairs.
     * @return array Associative array of group => seconds.
     */
    public static function parse_ttl( $input ) {
        if ( ! is_string( $input ) ) {
            return array();
        }
        $ttls  = array();
        $lines = array_map( 'trim', explode( "\n", $input ) );
        foreach ( $lines as $line ) {
            if ( strpos( $line, ':' ) !== false ) {
                $parts   = explode( ':', $line, 2 );
                $group   = trim( $parts[0] );
                $seconds = (int) trim( $parts[1] );
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
            esc_url( admin_url( 'tools.php?page=starter-redis-cache' ) ),
            esc_html__( 'Impostazioni', 'starter-redis-cache' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Get plugin settings with safe defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = array(
            'enabled'               => true,
            'non_persistent_groups' => '',
            'redis_hash_groups'     => '',
            'global_groups'         => '',
            'custom_ttl'            => '',
        );

        $settings = get_option( SRC_OPTION_NAME, $defaults );
        if ( ! is_array( $settings ) ) {
            return $defaults;
        }
        return wp_parse_args( $settings, $defaults );
    }
}

// ============================================================================
// Initialize plugin on plugins_loaded.
// ============================================================================
add_action( 'plugins_loaded', function () {
    Open_Redis_Manager::get_instance();
}, 1 );
