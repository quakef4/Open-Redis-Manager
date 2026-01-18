<?php
/**
 * Plugin Name: WP Redis Manager
 * Plugin URI: https://github.com/yourusername/wp-redis-manager
 * Description: Interfaccia admin per gestire gruppi cache Redis e pagine specifiche. Compatibile con WP Redis 1.4.7
 * Version: 1.0.0
 * Author: Redis Manager Team
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-redis-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * 
 * @package WP_Redis_Manager
 */

// Previeni accesso diretto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Costanti plugin
define( 'WP_REDIS_MANAGER_VERSION', '1.0.0' );
define( 'WP_REDIS_MANAGER_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_REDIS_MANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_REDIS_MANAGER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Classe principale WP Redis Manager
 */
class WP_Redis_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Option name per salvare configurazioni
     */
    const OPTION_NAME = 'wp_redis_manager_settings';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook attivazione/disattivazione
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        
        // Hook admin
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        
        // Hook AJAX
        add_action( 'wp_ajax_wp_redis_manager_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_wp_redis_manager_flush_cache', array( $this, 'ajax_flush_cache' ) );
        add_action( 'wp_ajax_wp_redis_manager_get_stats', array( $this, 'ajax_get_stats' ) );
        
        // Applica configurazioni cache
        add_action( 'muplugins_loaded', array( $this, 'apply_cache_configuration' ), 1 );
        add_action( 'plugins_loaded', array( $this, 'apply_cache_configuration' ), 1 );
        
        // Hook per disabilitare cache su pagine specifiche - REMOVED
        // Causava flush Redis inefficiente ad ogni visita pagina
        // add_action( 'template_redirect', array( $this, 'maybe_disable_cache_for_page' ), 1 );
        
        // Admin notices
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }
    
    /**
     * Attivazione plugin
     */
    public function activate() {
        // Imposta configurazioni default
        $default_settings = array(
            'non_persistent_groups' => array(
                'wc_session_id',
                'wc-session-id',
                'woocommerce_session_id',
                'cart',
                'wc_cart',
                'woocommerce_cart',
            ),
            'redis_hash_groups' => array(
                'post_meta',
                'term_meta',
                'user_meta',
                // NOTE: NON includere 'options' se usi YITH Request a Quote
                // Le sessioni YITH sono salvate come options
            ),
            'global_groups' => array(),
            'custom_ttl' => array(),
            'enabled' => true,
        );
        
        if ( ! get_option( self::OPTION_NAME ) ) {
            add_option( self::OPTION_NAME, $default_settings );
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Disattivazione plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Aggiungi menu admin
     */
    public function add_admin_menu() {
        add_management_page(
            __( 'Redis Manager', 'wp-redis-manager' ),
            __( 'Redis Manager', 'wp-redis-manager' ),
            'manage_options',
            'wp-redis-manager',
            array( $this, 'render_admin_page' )
        );
    }
    
    /**
     * Registra settings
     */
    public function register_settings() {
        register_setting(
            'wp_redis_manager_settings_group',
            self::OPTION_NAME,
            array( $this, 'sanitize_settings' )
        );
    }
    
    /**
     * Sanitize settings prima del salvataggio
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        // Enabled
        $sanitized['enabled'] = isset( $input['enabled'] ) && $input['enabled'] === 'on';
        
        // Non-persistent groups
        $sanitized['non_persistent_groups'] = isset( $input['non_persistent_groups'] )
            ? array_map( 'sanitize_text_field', $input['non_persistent_groups'] )
            : array();
        
        // Redis hash groups
        $sanitized['redis_hash_groups'] = isset( $input['redis_hash_groups'] )
            ? array_map( 'sanitize_text_field', $input['redis_hash_groups'] )
            : array();
        
        // Global groups
        $sanitized['global_groups'] = isset( $input['global_groups'] )
            ? array_map( 'sanitize_text_field', $input['global_groups'] )
            : array();
        
        // Excluded pages and URLs - REMOVED (causes inefficient Redis flush)
        
        // Custom TTL
        $sanitized['custom_ttl'] = isset( $input['custom_ttl'] ) && is_array( $input['custom_ttl'] )
            ? array_map( 'absint', $input['custom_ttl'] )
            : array();
        
        return $sanitized;
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'tools_page_wp-redis-manager' !== $hook ) {
            return;
        }
        
        wp_enqueue_style(
            'wp-redis-manager-admin',
            WP_REDIS_MANAGER_URL . 'assets/css/admin.css',
            array(),
            WP_REDIS_MANAGER_VERSION
        );
        
        wp_enqueue_script(
            'wp-redis-manager-admin',
            WP_REDIS_MANAGER_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            WP_REDIS_MANAGER_VERSION,
            true
        );
        
        wp_localize_script(
            'wp-redis-manager-admin',
            'wpRedisManager',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'wp_redis_manager_nonce' ),
                'strings' => array(
                    'testingConnection' => __( 'Testando connessione...', 'wp-redis-manager' ),
                    'connectionSuccess' => __( 'Connessione Redis riuscita!', 'wp-redis-manager' ),
                    'connectionFailed' => __( 'Connessione Redis fallita!', 'wp-redis-manager' ),
                    'flushingCache' => __( 'Svuotando cache...', 'wp-redis-manager' ),
                    'cacheFlushSuccess' => __( 'Cache svuotata con successo!', 'wp-redis-manager' ),
                    'cacheFlushed' => __( 'Cache Redis svuotata!', 'wp-redis-manager' ),
                ),
            )
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Salva settings se form submitted
        if ( isset( $_POST['wp_redis_manager_save'] ) ) {
            check_admin_referer( 'wp_redis_manager_settings' );
            
            $settings = array(
                'enabled' => isset( $_POST['enabled'] ) ? 'on' : 'off',
                'non_persistent_groups' => isset( $_POST['non_persistent_groups'] )
                    ? array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $_POST['non_persistent_groups'] ) ) ) )
                    : array(),
                'redis_hash_groups' => isset( $_POST['redis_hash_groups'] )
                    ? array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $_POST['redis_hash_groups'] ) ) ) )
                    : array(),
                'global_groups' => isset( $_POST['global_groups'] )
                    ? array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $_POST['global_groups'] ) ) ) )
                    : array(),
                'excluded_pages' => isset( $_POST['excluded_pages'] ) ? array_map( 'absint', $_POST['excluded_pages'] ) : array(),
                'excluded_urls' => isset( $_POST['excluded_urls'] )
                    ? array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $_POST['excluded_urls'] ) ) ) )
                    : array(),
                'custom_ttl' => isset( $_POST['custom_ttl'] ) ? $this->parse_custom_ttl( $_POST['custom_ttl'] ) : array(),
            );
            
            update_option( self::OPTION_NAME, $settings );
            
            echo '<div class="notice notice-success is-dismissible"><p>' .
                __( 'Impostazioni salvate con successo!', 'wp-redis-manager' ) .
                '</p></div>';
        }
        
        $settings = $this->get_settings();
        
        include WP_REDIS_MANAGER_PATH . 'templates/admin-page.php';
    }
    
    /**
     * Parse custom TTL from textarea
     */
    private function parse_custom_ttl( $input ) {
        if ( empty( $input ) ) {
            return array();
        }
        
        $lines = explode( "\n", $input );
        $ttl_array = array();
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) || strpos( $line, ':' ) === false ) {
                continue;
            }
            
            list( $group, $ttl ) = array_map( 'trim', explode( ':', $line, 2 ) );
            if ( ! empty( $group ) && is_numeric( $ttl ) ) {
                $ttl_array[ sanitize_text_field( $group ) ] = absint( $ttl );
            }
        }
        
        return $ttl_array;
    }
    
    /**
     * Get settings
     */
    public function get_settings() {
        $defaults = array(
            'enabled' => true,
            'non_persistent_groups' => array(),
            'redis_hash_groups' => array(),
            'global_groups' => array(),
            'custom_ttl' => array(),
        );
        
        $settings = get_option( self::OPTION_NAME, $defaults );
        
        return wp_parse_args( $settings, $defaults );
    }
    
    /**
     * Applica configurazioni cache
     */
    public function apply_cache_configuration() {
        $settings = $this->get_settings();
        
        if ( ! $settings['enabled'] ) {
            return;
        }
        
        if ( ! function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
            return;
        }
        
        // Non-persistent groups
        if ( ! empty( $settings['non_persistent_groups'] ) ) {
            wp_cache_add_non_persistent_groups( $settings['non_persistent_groups'] );
        }
        
        // Redis hash groups
        if ( ! empty( $settings['redis_hash_groups'] ) ) {
            wp_cache_add_redis_hash_groups( $settings['redis_hash_groups'] );
        }
        
        // Global groups
        if ( ! empty( $settings['global_groups'] ) ) {
            wp_cache_add_global_groups( $settings['global_groups'] );
        }
    }
    
    /**
     * REMOVED: Page exclusion functionality
     * Causava flush Redis ad ogni visita, rendendo il caching inefficace
     */
    
    /**
     * AJAX: Test Redis connection
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'wp_redis_manager_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }
        
        global $wp_object_cache;
        
        $response = array(
            'connected' => false,
            'message' => __( 'Redis non disponibile', 'wp-redis-manager' ),
        );
        
        if ( isset( $wp_object_cache ) && ! empty( $wp_object_cache->is_redis_connected ) ) {
            $response['connected'] = true;
            $response['message'] = __( 'Redis connesso con successo!', 'wp-redis-manager' );
            
            // Aggiungi info Redis
            if ( method_exists( $wp_object_cache->redis, 'info' ) ) {
                $info = $wp_object_cache->redis->info();
                $response['info'] = array(
                    'version' => $info['redis_version'] ?? 'N/A',
                    'memory' => $info['used_memory_human'] ?? 'N/A',
                    'uptime' => isset( $info['uptime_in_days'] ) ? $info['uptime_in_days'] . ' giorni' : 'N/A',
                );
            }
        }
        
        wp_send_json_success( $response );
    }
    
    /**
     * AJAX: Flush cache
     */
    public function ajax_flush_cache() {
        check_ajax_referer( 'wp_redis_manager_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }
        
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
            wp_send_json_success( array(
                'message' => __( 'Cache Redis svuotata con successo!', 'wp-redis-manager' ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Funzione wp_cache_flush non disponibile', 'wp-redis-manager' ),
            ) );
        }
    }
    
    /**
     * AJAX: Get cache stats
     */
    public function ajax_get_stats() {
        check_ajax_referer( 'wp_redis_manager_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }
        
        global $wp_object_cache;
        
        $stats = array(
            'hits' => 0,
            'misses' => 0,
            'hit_rate' => '0%',
            'redis_calls' => 0,
        );
        
        if ( isset( $wp_object_cache ) ) {
            $total = $wp_object_cache->cache_hits + $wp_object_cache->cache_misses;
            $hit_rate = $total > 0 ? round( ( $wp_object_cache->cache_hits / $total ) * 100, 2 ) : 0;
            
            $stats = array(
                'hits' => $wp_object_cache->cache_hits,
                'misses' => $wp_object_cache->cache_misses,
                'hit_rate' => $hit_rate . '%',
                'redis_calls' => isset( $wp_object_cache->redis_calls )
                    ? array_sum( $wp_object_cache->redis_calls )
                    : 0,
            );
        }
        
        wp_send_json_success( $stats );
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        // Verifica se WP Redis è installato
        if ( ! defined( 'WP_REDIS_OBJECT_CACHE' ) && current_user_can( 'manage_options' ) ) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e( 'WP Redis Manager:', 'wp-redis-manager' ); ?></strong>
                    <?php _e( 'Il plugin WP Redis non sembra essere attivo. Installa e configura WP Redis per usare questo manager.', 'wp-redis-manager' ); ?>
                </p>
            </div>
            <?php
        }
    }
}

// Inizializza plugin
WP_Redis_Manager::get_instance();
