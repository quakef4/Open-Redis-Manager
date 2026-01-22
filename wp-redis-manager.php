<?php
/**
 * Plugin Name: WP Redis Manager
 * Plugin URI: https://github.com/quakef4/Wp-redis-manager
 * Description: Interfaccia admin completa per gestire Redis cache in WordPress. Monitor attività, esplora chiavi, gestione gruppi cache e preset ottimizzati per WooCommerce e YITH.
 * Version: 1.1.0
 * Author: Antonino Lumia
 * Author URI: https://github.com/quakef4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-redis-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package WP_Redis_Manager
 * @author Antonino Lumia
 * @copyright 2024-2026 Antonino Lumia
 * @license GPL-2.0+
 */

// Previeni accesso diretto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Costanti plugin
define( 'WP_REDIS_MANAGER_VERSION', '1.1.0' );
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
        add_action( 'wp_ajax_wp_redis_manager_get_activity', array( $this, 'ajax_get_activity' ) );
        add_action( 'wp_ajax_wp_redis_manager_get_keys', array( $this, 'ajax_get_keys' ) );
        add_action( 'wp_ajax_wp_redis_manager_get_key_details', array( $this, 'ajax_get_key_details' ) );
        add_action( 'wp_ajax_wp_redis_manager_delete_key', array( $this, 'ajax_delete_key' ) );
        
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
     * AJAX: Get Redis activity (slowlog, info, commands stats)
     */
    public function ajax_get_activity() {
        check_ajax_referer( 'wp_redis_manager_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }

        global $wp_object_cache;

        $activity = array(
            'slowlog' => array(),
            'commandstats' => array(),
            'clients' => array(),
            'memory' => array(),
            'keyspace' => array(),
            'server' => array(),
        );

        if ( isset( $wp_object_cache ) && ! empty( $wp_object_cache->is_redis_connected ) && isset( $wp_object_cache->redis ) ) {
            $redis = $wp_object_cache->redis;

            try {
                // Get slowlog entries
                $slowlog = $redis->slowlog( 'get', 50 );
                if ( is_array( $slowlog ) ) {
                    foreach ( $slowlog as $entry ) {
                        $activity['slowlog'][] = array(
                            'id' => $entry[0] ?? 0,
                            'timestamp' => $entry[1] ?? 0,
                            'duration_us' => $entry[2] ?? 0,
                            'command' => is_array( $entry[3] ?? null ) ? implode( ' ', $entry[3] ) : ( $entry[3] ?? '' ),
                            'client' => $entry[4] ?? '',
                            'client_name' => $entry[5] ?? '',
                        );
                    }
                }

                // Get command stats
                $info_all = $redis->info( 'all' );

                // Parse commandstats from info
                if ( is_array( $info_all ) ) {
                    foreach ( $info_all as $key => $value ) {
                        if ( strpos( $key, 'cmdstat_' ) === 0 ) {
                            $cmd_name = strtoupper( str_replace( 'cmdstat_', '', $key ) );
                            // Parse value like "calls=123,usec=456,usec_per_call=3.70"
                            $stats = array();
                            $parts = explode( ',', $value );
                            foreach ( $parts as $part ) {
                                $kv = explode( '=', $part );
                                if ( count( $kv ) === 2 ) {
                                    $stats[ $kv[0] ] = $kv[1];
                                }
                            }
                            $activity['commandstats'][ $cmd_name ] = array(
                                'calls' => intval( $stats['calls'] ?? 0 ),
                                'usec' => intval( $stats['usec'] ?? 0 ),
                                'usec_per_call' => floatval( $stats['usec_per_call'] ?? 0 ),
                            );
                        }
                    }
                }

                // Sort by calls descending
                uasort( $activity['commandstats'], function( $a, $b ) {
                    return $b['calls'] - $a['calls'];
                });

                // Get client info
                $clients_info = $redis->info( 'clients' );
                $activity['clients'] = array(
                    'connected_clients' => $clients_info['connected_clients'] ?? 0,
                    'blocked_clients' => $clients_info['blocked_clients'] ?? 0,
                    'tracking_clients' => $clients_info['tracking_clients'] ?? 0,
                );

                // Get memory info
                $memory_info = $redis->info( 'memory' );
                $activity['memory'] = array(
                    'used_memory_human' => $memory_info['used_memory_human'] ?? 'N/A',
                    'used_memory_peak_human' => $memory_info['used_memory_peak_human'] ?? 'N/A',
                    'used_memory_lua_human' => $memory_info['used_memory_lua_human'] ?? 'N/A',
                    'maxmemory_human' => $memory_info['maxmemory_human'] ?? 'N/A',
                    'mem_fragmentation_ratio' => $memory_info['mem_fragmentation_ratio'] ?? 'N/A',
                );

                // Get keyspace info
                $keyspace_info = $redis->info( 'keyspace' );
                foreach ( $keyspace_info as $db => $value ) {
                    if ( strpos( $db, 'db' ) === 0 ) {
                        // Parse "keys=123,expires=456,avg_ttl=789"
                        $stats = array();
                        $parts = explode( ',', $value );
                        foreach ( $parts as $part ) {
                            $kv = explode( '=', $part );
                            if ( count( $kv ) === 2 ) {
                                $stats[ $kv[0] ] = $kv[1];
                            }
                        }
                        $activity['keyspace'][ $db ] = array(
                            'keys' => intval( $stats['keys'] ?? 0 ),
                            'expires' => intval( $stats['expires'] ?? 0 ),
                            'avg_ttl' => intval( $stats['avg_ttl'] ?? 0 ),
                        );
                    }
                }

                // Get server info
                $server_info = $redis->info( 'server' );
                $activity['server'] = array(
                    'redis_version' => $server_info['redis_version'] ?? 'N/A',
                    'redis_mode' => $server_info['redis_mode'] ?? 'N/A',
                    'os' => $server_info['os'] ?? 'N/A',
                    'uptime_in_seconds' => $server_info['uptime_in_seconds'] ?? 0,
                    'uptime_in_days' => $server_info['uptime_in_days'] ?? 0,
                    'hz' => $server_info['hz'] ?? 10,
                    'tcp_port' => $server_info['tcp_port'] ?? 6379,
                );

            } catch ( Exception $e ) {
                wp_send_json_error( array( 'message' => 'Errore Redis: ' . $e->getMessage() ) );
            }
        } else {
            wp_send_json_error( array( 'message' => 'Redis non disponibile' ) );
        }

        wp_send_json_success( $activity );
    }

    /**
     * AJAX: Get Redis keys with pattern and filtering
     */
    public function ajax_get_keys() {
        check_ajax_referer( 'wp_redis_manager_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }

        global $wp_object_cache;

        $pattern = isset( $_POST['pattern'] ) ? sanitize_text_field( $_POST['pattern'] ) : '*';
        $limit = isset( $_POST['limit'] ) ? min( absint( $_POST['limit'] ), 500 ) : 100;
        $cursor = isset( $_POST['cursor'] ) ? sanitize_text_field( $_POST['cursor'] ) : '0';
        $type_filter = isset( $_POST['type_filter'] ) ? sanitize_text_field( $_POST['type_filter'] ) : '';

        $keys_data = array(
            'keys' => array(),
            'cursor' => '0',
            'total_scanned' => 0,
            'has_more' => false,
        );

        if ( isset( $wp_object_cache ) && ! empty( $wp_object_cache->is_redis_connected ) && isset( $wp_object_cache->redis ) ) {
            $redis = $wp_object_cache->redis;

            try {
                // Use SCAN to get keys incrementally
                $result = $redis->scan( $cursor, array(
                    'match' => $pattern,
                    'count' => $limit * 2, // Scan more to filter
                ) );

                if ( $result !== false ) {
                    $keys_data['cursor'] = (string) $result[0];
                    $keys_data['has_more'] = $result[0] !== 0;
                    $scanned_keys = $result[1] ?? array();

                    $count = 0;
                    foreach ( $scanned_keys as $key ) {
                        if ( $count >= $limit ) {
                            break;
                        }

                        $type = $redis->type( $key );
                        $type_name = $this->get_redis_type_name( $type );

                        // Apply type filter
                        if ( ! empty( $type_filter ) && $type_name !== $type_filter ) {
                            continue;
                        }

                        $ttl = $redis->ttl( $key );
                        $memory = 0;

                        // Try to get memory usage (Redis 4.0+)
                        try {
                            $memory = $redis->rawCommand( 'MEMORY', 'USAGE', $key );
                        } catch ( Exception $e ) {
                            $memory = 0;
                        }

                        $keys_data['keys'][] = array(
                            'key' => $key,
                            'type' => $type_name,
                            'ttl' => $ttl,
                            'memory' => $memory ? $this->format_bytes( $memory ) : 'N/A',
                            'memory_raw' => $memory,
                        );

                        $count++;
                    }

                    $keys_data['total_scanned'] = count( $scanned_keys );
                }

            } catch ( Exception $e ) {
                wp_send_json_error( array( 'message' => 'Errore: ' . $e->getMessage() ) );
            }
        } else {
            wp_send_json_error( array( 'message' => 'Redis non disponibile' ) );
        }

        wp_send_json_success( $keys_data );
    }

    /**
     * AJAX: Get details for a specific key
     */
    public function ajax_get_key_details() {
        check_ajax_referer( 'wp_redis_manager_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }

        global $wp_object_cache;

        $key = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';

        if ( empty( $key ) ) {
            wp_send_json_error( array( 'message' => 'Chiave non specificata' ) );
        }

        $details = array(
            'key' => $key,
            'type' => 'unknown',
            'ttl' => -1,
            'encoding' => 'N/A',
            'value' => null,
            'length' => 0,
            'memory' => 'N/A',
        );

        if ( isset( $wp_object_cache ) && ! empty( $wp_object_cache->is_redis_connected ) && isset( $wp_object_cache->redis ) ) {
            $redis = $wp_object_cache->redis;

            try {
                $type = $redis->type( $key );
                $type_name = $this->get_redis_type_name( $type );
                $details['type'] = $type_name;
                $details['ttl'] = $redis->ttl( $key );

                // Get encoding
                try {
                    $details['encoding'] = $redis->object( 'encoding', $key );
                } catch ( Exception $e ) {
                    $details['encoding'] = 'N/A';
                }

                // Get memory
                try {
                    $memory = $redis->rawCommand( 'MEMORY', 'USAGE', $key );
                    $details['memory'] = $memory ? $this->format_bytes( $memory ) : 'N/A';
                } catch ( Exception $e ) {
                    $details['memory'] = 'N/A';
                }

                // Get value based on type
                switch ( $type_name ) {
                    case 'string':
                        $value = $redis->get( $key );
                        $details['value'] = $this->format_value_preview( $value );
                        $details['length'] = strlen( $value );
                        break;

                    case 'list':
                        $details['length'] = $redis->lLen( $key );
                        $value = $redis->lRange( $key, 0, 19 ); // First 20 items
                        $details['value'] = array_map( array( $this, 'format_value_preview' ), $value );
                        break;

                    case 'set':
                        $details['length'] = $redis->sCard( $key );
                        $value = $redis->sMembers( $key );
                        $value = array_slice( $value, 0, 20 ); // First 20 items
                        $details['value'] = array_map( array( $this, 'format_value_preview' ), $value );
                        break;

                    case 'zset':
                        $details['length'] = $redis->zCard( $key );
                        $value = $redis->zRange( $key, 0, 19, true ); // First 20 with scores
                        $formatted = array();
                        foreach ( $value as $member => $score ) {
                            $formatted[] = array(
                                'member' => $this->format_value_preview( $member ),
                                'score' => $score,
                            );
                        }
                        $details['value'] = $formatted;
                        break;

                    case 'hash':
                        $details['length'] = $redis->hLen( $key );
                        $value = $redis->hGetAll( $key );
                        $value = array_slice( $value, 0, 20, true ); // First 20 fields
                        $formatted = array();
                        foreach ( $value as $field => $val ) {
                            $formatted[ $field ] = $this->format_value_preview( $val );
                        }
                        $details['value'] = $formatted;
                        break;

                    case 'stream':
                        try {
                            $details['length'] = $redis->xLen( $key );
                            $value = $redis->xRange( $key, '-', '+', 10 ); // First 10 entries
                            $details['value'] = $value;
                        } catch ( Exception $e ) {
                            $details['value'] = 'Stream non supportato';
                        }
                        break;

                    default:
                        $details['value'] = 'Tipo non supportato';
                }

            } catch ( Exception $e ) {
                wp_send_json_error( array( 'message' => 'Errore: ' . $e->getMessage() ) );
            }
        } else {
            wp_send_json_error( array( 'message' => 'Redis non disponibile' ) );
        }

        wp_send_json_success( $details );
    }

    /**
     * AJAX: Delete a specific key
     */
    public function ajax_delete_key() {
        check_ajax_referer( 'wp_redis_manager_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permessi insufficienti' ) );
        }

        global $wp_object_cache;

        $key = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';

        if ( empty( $key ) ) {
            wp_send_json_error( array( 'message' => 'Chiave non specificata' ) );
        }

        if ( isset( $wp_object_cache ) && ! empty( $wp_object_cache->is_redis_connected ) && isset( $wp_object_cache->redis ) ) {
            $redis = $wp_object_cache->redis;

            try {
                $result = $redis->del( $key );
                if ( $result > 0 ) {
                    wp_send_json_success( array( 'message' => 'Chiave eliminata con successo' ) );
                } else {
                    wp_send_json_error( array( 'message' => 'Chiave non trovata' ) );
                }
            } catch ( Exception $e ) {
                wp_send_json_error( array( 'message' => 'Errore: ' . $e->getMessage() ) );
            }
        } else {
            wp_send_json_error( array( 'message' => 'Redis non disponibile' ) );
        }
    }

    /**
     * Get Redis type name from type constant
     */
    private function get_redis_type_name( $type ) {
        $types = array(
            0 => 'none',
            1 => 'string',
            2 => 'set',
            3 => 'list',
            4 => 'zset',
            5 => 'hash',
            6 => 'stream',
        );

        // Handle both numeric and string types
        if ( is_numeric( $type ) ) {
            return $types[ (int) $type ] ?? 'unknown';
        }

        return strtolower( $type );
    }

    /**
     * Format bytes to human readable
     */
    private function format_bytes( $bytes, $precision = 2 ) {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );

        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );

        $bytes /= pow( 1024, $pow );

        return round( $bytes, $precision ) . ' ' . $units[ $pow ];
    }

    /**
     * Format value for preview (truncate and decode if needed)
     */
    private function format_value_preview( $value, $max_length = 500 ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        // Try to unserialize PHP data
        $unserialized = @unserialize( $value );
        if ( $unserialized !== false || $value === 'b:0;' ) {
            $value = print_r( $unserialized, true );
        }

        // Try to decode JSON
        $decoded = json_decode( $value, true );
        if ( json_last_error() === JSON_ERROR_NONE && $decoded !== null ) {
            $value = json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        }

        // Truncate if too long
        if ( strlen( $value ) > $max_length ) {
            $value = substr( $value, 0, $max_length ) . '... [truncated]';
        }

        return $value;
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
