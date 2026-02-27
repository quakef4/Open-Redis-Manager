<?php
/**
 * Admin interface and AJAX handlers for Open Redis Manager.
 *
 * @package OpenRedisManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SRC_Admin {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

        // AJAX handlers
        $ajax_actions = array(
            'src_test_connection',
            'src_flush_cache',
            'src_get_stats',
            'src_get_activity',
            'src_get_keys',
            'src_get_key_details',
            'src_delete_key',
            'src_install_dropin',
            'src_uninstall_dropin',
            'src_flush_group',
            'src_save_config',
            'src_test_config',
            'src_remove_config',
            'src_save_settings',
        );

        foreach ( $ajax_actions as $action ) {
            add_action( 'wp_ajax_' . $action, array( $this, $action ) );
        }
    }

    /**
     * Register admin menu page.
     */
    public function add_admin_menu() {
        add_management_page(
            __( 'Open Redis Manager', 'starter-redis-cache' ),
            __( 'Redis Manager', 'starter-redis-cache' ),
            'manage_options',
            'starter-redis-cache',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting(
            'starter_redis_cache',
            SRC_OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
            )
        );
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Raw settings.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();

        $sanitized['enabled'] = ! empty( $input['enabled'] );

        $sanitized['non_persistent_groups'] = isset( $input['non_persistent_groups'] )
            ? sanitize_textarea_field( $input['non_persistent_groups'] )
            : '';

        $sanitized['redis_hash_groups'] = isset( $input['redis_hash_groups'] )
            ? sanitize_textarea_field( $input['redis_hash_groups'] )
            : '';

        $sanitized['global_groups'] = isset( $input['global_groups'] )
            ? sanitize_textarea_field( $input['global_groups'] )
            : '';

        $sanitized['custom_ttl'] = isset( $input['custom_ttl'] )
            ? sanitize_textarea_field( $input['custom_ttl'] )
            : '';

        return $sanitized;
    }

    /**
     * Enqueue admin CSS and JS.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'tools_page_starter-redis-cache' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'src-admin-css',
            SRC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SRC_VERSION
        );

        wp_enqueue_script(
            'src-admin-js',
            SRC_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            SRC_VERSION,
            true
        );

        wp_localize_script( 'src-admin-js', 'srcRedis', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'src_nonce' ),
            'i18n'    => array(
                'confirmFlush'      => __( 'Svuotare tutta la cache Redis? Questa azione non può essere annullata.', 'starter-redis-cache' ),
                'confirmDelete'     => __( 'Eliminare questa chiave? Questa azione non può essere annullata.', 'starter-redis-cache' ),
                'confirmInstall'    => __( 'Installare il drop-in object-cache.php?', 'starter-redis-cache' ),
                'confirmUninstall'  => __( 'Rimuovere il drop-in object-cache.php? La cache Redis verrà disabilitata.', 'starter-redis-cache' ),
                'confirmFlushGroup' => __( 'Svuotare questo gruppo cache?', 'starter-redis-cache' ),
                'connected'         => __( 'Connesso', 'starter-redis-cache' ),
                'disconnected'      => __( 'Disconnesso', 'starter-redis-cache' ),
                'loading'           => __( 'Caricamento...', 'starter-redis-cache' ),
                'noKeys'            => __( 'Nessuna chiave trovata', 'starter-redis-cache' ),
                'error'             => __( 'Errore', 'starter-redis-cache' ),
                'success'           => __( 'Completato', 'starter-redis-cache' ),
            ),
        ) );
    }

    /**
     * Render the admin page.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Force fresh read from DB (bypass object cache) after a save.
        if ( ! empty( $_GET['settings-saved'] ) ) {
            wp_cache_flush();
        }

        $settings     = Open_Redis_Manager::get_settings();
        $dropin       = new SRC_Dropin();
        $status       = $dropin->get_status();
        $config       = new SRC_Config();
        $config_state = array(
            'writable'      => $config->is_writable(),
            'readable'      => $config->is_readable(),
            'has_block'     => $config->has_managed_block(),
            'has_constants' => $config->has_constants(),
            'path'          => $config->get_path(),
            'values'        => $config->get_current_values(),
            'constants'     => SRC_Config::get_constants_info(),
        );

        include SRC_PLUGIN_DIR . 'templates/admin-page.php';
    }

    /**
     * Show admin notices.
     */
    public function admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $dropin = new SRC_Dropin();

        // Check phpredis
        if ( ! class_exists( 'Redis' ) ) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Open Redis Manager:</strong> ';
            echo esc_html__( 'L\'estensione PHP phpredis non è installata. Il plugin richiede phpredis per funzionare.', 'starter-redis-cache' );
            echo '</p></div>';
            return;
        }

        // Check if drop-in is not installed
        if ( ! $dropin->is_installed() ) {
            $screen = get_current_screen();
            if ( $screen && $screen->id !== 'tools_page_starter-redis-cache' ) {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>Open Redis Manager:</strong> ';
                printf(
                    esc_html__( 'Il drop-in object-cache.php non è installato. %sVai alle impostazioni%s per installarlo.', 'starter-redis-cache' ),
                    '<a href="' . esc_url( admin_url( 'tools.php?page=starter-redis-cache' ) ) . '">',
                    '</a>'
                );
                echo '</p></div>';
            }
        }

        // Check if foreign drop-in exists
        if ( $dropin->is_foreign_dropin() ) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>Open Redis Manager:</strong> ';
            echo esc_html__( 'Un altro drop-in object-cache.php è già installato. Rimuovilo prima di attivare Open Redis Manager.', 'starter-redis-cache' );
            echo '</p></div>';
        }
    }

    /**
     * Get the Redis client from the object cache.
     *
     * @return Redis|null
     */
    private function get_redis() {
        global $wp_object_cache;

        if ( ! $wp_object_cache ) {
            return null;
        }

        if ( method_exists( $wp_object_cache, 'get_redis' ) ) {
            return $wp_object_cache->get_redis();
        }

        if ( isset( $wp_object_cache->redis ) && $wp_object_cache->redis instanceof Redis ) {
            return $wp_object_cache->redis;
        }

        return null;
    }

    /**
     * Check if Redis is connected via the object cache.
     *
     * @return bool
     */
    private function is_redis_connected() {
        global $wp_object_cache;

        if ( ! $wp_object_cache ) {
            return false;
        }

        if ( method_exists( $wp_object_cache, 'is_connected' ) ) {
            return $wp_object_cache->is_connected();
        }

        if ( isset( $wp_object_cache->redis_connected ) ) {
            return (bool) $wp_object_cache->redis_connected;
        }

        return false;
    }

    // =========================================================================
    // AJAX Handlers
    // =========================================================================

    /**
     * Test Redis connection.
     */
    public function src_test_connection() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $redis = $this->get_redis();

        if ( ! $redis ) {
            // Try a direct connection test
            if ( ! class_exists( 'Redis' ) ) {
                wp_send_json_error( array(
                    'message' => 'Estensione phpredis non disponibile',
                ) );
            }

            $use_socket  = defined( 'SRC_REDIS_SOCKET' ) && SRC_REDIS_SOCKET;
            $socket_path = $use_socket ? SRC_REDIS_SOCKET : '';

            try {
                $test_redis = new Redis();
                $host       = defined( 'SRC_REDIS_HOST' ) ? SRC_REDIS_HOST : '127.0.0.1';
                $port       = defined( 'SRC_REDIS_PORT' ) ? (int) SRC_REDIS_PORT : 6379;
                $timeout    = defined( 'SRC_REDIS_TIMEOUT' ) ? (float) SRC_REDIS_TIMEOUT : 1.0;

                if ( $use_socket ) {
                    $test_redis->connect( $socket_path, 0, $timeout );
                } else {
                    $test_redis->connect( $host, $port, $timeout );
                }

                if ( defined( 'SRC_REDIS_PASSWORD' ) && SRC_REDIS_PASSWORD !== '' ) {
                    $test_redis->auth( SRC_REDIS_PASSWORD );
                }

                $database = defined( 'SRC_REDIS_DATABASE' ) ? (int) SRC_REDIS_DATABASE : 0;
                if ( $database > 0 ) {
                    $test_redis->select( $database );
                }

                $info = $test_redis->info();
                $test_redis->close();

                $via = $use_socket ? "socket {$socket_path}" : "{$host}:{$port}";

                wp_send_json_success( array(
                    'connected' => true,
                    'version'   => $info['redis_version'] ?? 'unknown',
                    'memory'    => $info['used_memory_human'] ?? 'unknown',
                    'uptime'    => $info['uptime_in_seconds'] ?? 0,
                    'dropin'    => false,
                    'message'   => "Connessione Redis riuscita via {$via} (drop-in non attivo)",
                ) );

            } catch ( Exception $e ) {
                $via = $use_socket ? 'socket ' . SRC_REDIS_SOCKET : "{$host}:{$port}";
                wp_send_json_error( array(
                    'message' => "Connessione fallita via {$via}: " . $e->getMessage(),
                ) );
            }
        }

        try {
            $info = $redis->info();

            wp_send_json_success( array(
                'connected' => true,
                'version'   => $info['redis_version'] ?? 'unknown',
                'memory'    => $info['used_memory_human'] ?? 'unknown',
                'uptime'    => $info['uptime_in_seconds'] ?? 0,
                'dropin'    => true,
                'database'  => defined( 'SRC_REDIS_DATABASE' ) ? (int) SRC_REDIS_DATABASE : 0,
                'prefix'    => defined( 'SRC_REDIS_PREFIX' ) ? SRC_REDIS_PREFIX : ( $GLOBALS['table_prefix'] ?? 'wp_' ),
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'Errore connessione: ' . $e->getMessage(),
            ) );
        }
    }

    /**
     * Flush the entire cache.
     */
    public function src_flush_cache() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        if ( function_exists( 'wp_cache_flush' ) ) {
            $result = wp_cache_flush();
            wp_send_json_success( array(
                'message' => $result ? 'Cache svuotata con successo' : 'Errore durante lo svuotamento',
            ) );
        } else {
            wp_send_json_error( 'wp_cache_flush non disponibile' );
        }
    }

    /**
     * Flush a specific cache group.
     */
    public function src_flush_group() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $group = isset( $_POST['group'] ) ? sanitize_text_field( $_POST['group'] ) : '';
        if ( empty( $group ) ) {
            wp_send_json_error( 'Gruppo non specificato' );
        }

        if ( function_exists( 'wp_cache_flush_group' ) ) {
            $result = wp_cache_flush_group( $group );
            wp_send_json_success( array(
                'message' => $result
                    ? sprintf( 'Gruppo "%s" svuotato con successo', $group )
                    : 'Errore durante lo svuotamento del gruppo',
            ) );
        } else {
            wp_send_json_error( 'wp_cache_flush_group non disponibile' );
        }
    }

    /**
     * Get cache statistics.
     */
    public function src_get_stats() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        global $wp_object_cache;

        $stats = array(
            'hits'        => 0,
            'misses'      => 0,
            'hit_rate'    => 0,
            'redis_calls' => 0,
            'connected'   => false,
        );

        if ( $wp_object_cache ) {
            if ( method_exists( $wp_object_cache, 'get_info' ) ) {
                $info = $wp_object_cache->get_info();
                $stats['hits']        = $info['hits'] ?? 0;
                $stats['misses']      = $info['misses'] ?? 0;
                $stats['redis_calls'] = $info['redis_calls'] ?? 0;
                $stats['connected']   = $info['connected'] ?? false;
            } else {
                $stats['hits']   = $wp_object_cache->cache_hits ?? 0;
                $stats['misses'] = $wp_object_cache->cache_misses ?? 0;
                $stats['connected'] = $this->is_redis_connected();
            }

            $total = $stats['hits'] + $stats['misses'];
            $stats['hit_rate'] = $total > 0 ? round( ( $stats['hits'] / $total ) * 100, 1 ) : 0;
        }

        wp_send_json_success( $stats );
    }

    /**
     * Get Redis activity monitoring data.
     */
    public function src_get_activity() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $redis = $this->get_redis();
        if ( ! $redis ) {
            wp_send_json_error( 'Redis non connesso' );
        }

        try {
            $activity = array();

            // Server info
            $server_info = $redis->info( 'server' );
            $activity['server'] = array(
                'redis_version' => $server_info['redis_version'] ?? 'unknown',
                'redis_mode'    => $server_info['redis_mode'] ?? 'unknown',
                'os'            => $server_info['os'] ?? 'unknown',
                'uptime'        => $server_info['uptime_in_seconds'] ?? 0,
                'tcp_port'      => $server_info['tcp_port'] ?? 0,
                'process_id'    => $server_info['process_id'] ?? 0,
            );

            // Memory info
            $memory_info = $redis->info( 'memory' );
            $activity['memory'] = array(
                'used_memory_human'      => $memory_info['used_memory_human'] ?? '0B',
                'used_memory_peak_human' => $memory_info['used_memory_peak_human'] ?? '0B',
                'maxmemory_human'        => $memory_info['maxmemory_human'] ?? '0B',
                'mem_fragmentation_ratio' => $memory_info['mem_fragmentation_ratio'] ?? 0,
                'used_memory_lua_human'  => $memory_info['used_memory_lua_human'] ?? '0B',
            );

            // Client info
            $client_info = $redis->info( 'clients' );
            $activity['clients'] = array(
                'connected_clients' => $client_info['connected_clients'] ?? 0,
                'blocked_clients'   => $client_info['blocked_clients'] ?? 0,
            );

            // Keyspace info
            $keyspace_info = $redis->info( 'keyspace' );
            $activity['keyspace'] = array();
            foreach ( $keyspace_info as $db => $data ) {
                if ( strpos( $db, 'db' ) === 0 ) {
                    $activity['keyspace'][ $db ] = $data;
                }
            }

            // Command stats
            $commandstats = $redis->info( 'commandstats' );
            $activity['commands'] = array();
            if ( is_array( $commandstats ) ) {
                foreach ( $commandstats as $cmd => $data ) {
                    $cmd_name = str_replace( 'cmdstat_', '', $cmd );
                    if ( is_string( $data ) ) {
                        // Parse "calls=123,usec=456,usec_per_call=3.70"
                        $parsed = array();
                        foreach ( explode( ',', $data ) as $pair ) {
                            $parts = explode( '=', $pair );
                            if ( count( $parts ) === 2 ) {
                                $parsed[ $parts[0] ] = $parts[1];
                            }
                        }
                        $activity['commands'][] = array(
                            'command'       => $cmd_name,
                            'calls'         => (int) ( $parsed['calls'] ?? 0 ),
                            'usec'          => (int) ( $parsed['usec'] ?? 0 ),
                            'usec_per_call' => (float) ( $parsed['usec_per_call'] ?? 0 ),
                        );
                    }
                }

                // Sort by calls descending
                usort( $activity['commands'], function ( $a, $b ) {
                    return $b['calls'] - $a['calls'];
                } );
            }

            // Slowlog
            $activity['slowlog'] = array();
            try {
                $slowlog_raw = $redis->slowlog( 'get', 50 );
                if ( is_array( $slowlog_raw ) ) {
                    foreach ( $slowlog_raw as $entry ) {
                        $activity['slowlog'][] = array(
                            'id'        => $entry[0] ?? 0,
                            'timestamp' => $entry[1] ?? 0,
                            'duration'  => $entry[2] ?? 0,
                            'command'   => is_array( $entry[3] ?? null ) ? implode( ' ', $entry[3] ) : ( $entry[3] ?? '' ),
                            'client'    => $entry[4] ?? '',
                        );
                    }
                }
            } catch ( Exception $e ) {
                // Slowlog may not be available
            }

            wp_send_json_success( $activity );

        } catch ( Exception $e ) {
            wp_send_json_error( 'Errore: ' . $e->getMessage() );
        }
    }

    /**
     * Search and list Redis keys.
     */
    public function src_get_keys() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $redis = $this->get_redis();
        if ( ! $redis ) {
            wp_send_json_error( 'Redis non connesso' );
        }

        $pattern = isset( $_POST['pattern'] ) ? sanitize_text_field( $_POST['pattern'] ) : '*';
        $type    = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';
        $limit   = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 100;
        $cursor  = isset( $_POST['cursor'] ) ? sanitize_text_field( $_POST['cursor'] ) : '0';

        $limit = max( 10, min( 500, $limit ) );

        try {
            $keys       = array();
            // phpredis requires NULL for the initial SCAN call.
            // Passing 0 makes phpredis think the scan is already complete.
            $scan_cursor    = ( '0' === $cursor || '' === $cursor ) ? null : (int) $cursor;
            $iterations     = 0;
            $max_iterations = 100;

            do {
                $result = $redis->scan( $scan_cursor, $pattern, 100 );
                $iterations++;

                if ( $result !== false && is_array( $result ) ) {
                    foreach ( $result as $key ) {
                        // Filter by type if specified
                        if ( $type !== '' ) {
                            $key_type = $redis->type( $key );
                            $type_map = array(
                                'string' => Redis::REDIS_STRING,
                                'list'   => Redis::REDIS_LIST,
                                'set'    => Redis::REDIS_SET,
                                'zset'   => Redis::REDIS_ZSET,
                                'hash'   => Redis::REDIS_HASH,
                                'stream' => defined( 'Redis::REDIS_STREAM' ) ? Redis::REDIS_STREAM : 6,
                            );

                            if ( isset( $type_map[ $type ] ) && $key_type !== $type_map[ $type ] ) {
                                continue;
                            }
                        }

                        $ttl = $redis->ttl( $key );
                        $key_type_raw = $redis->type( $key );
                        $type_names = array(
                            Redis::REDIS_STRING => 'string',
                            Redis::REDIS_LIST   => 'list',
                            Redis::REDIS_SET    => 'set',
                            Redis::REDIS_ZSET   => 'zset',
                            Redis::REDIS_HASH   => 'hash',
                        );
                        if ( defined( 'Redis::REDIS_STREAM' ) ) {
                            $type_names[ Redis::REDIS_STREAM ] = 'stream';
                        }

                        $memory = 0;
                        try {
                            $memory = $redis->rawCommand( 'MEMORY', 'USAGE', $key );
                        } catch ( Exception $e ) {
                            // MEMORY USAGE not available in older Redis versions
                        }

                        $keys[] = array(
                            'key'    => $key,
                            'type'   => $type_names[ $key_type_raw ] ?? 'unknown',
                            'ttl'    => $ttl,
                            'memory' => (int) $memory,
                        );

                        if ( count( $keys ) >= $limit ) {
                            break 2;
                        }
                    }
                }
            } while ( $scan_cursor > 0 && $iterations < $max_iterations && count( $keys ) < $limit );

            wp_send_json_success( array(
                'keys'   => $keys,
                'cursor' => (string) $scan_cursor,
                'total'  => count( $keys ),
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( 'Errore: ' . $e->getMessage() );
        }
    }

    /**
     * Get detailed info about a specific key.
     */
    public function src_get_key_details() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $redis = $this->get_redis();
        if ( ! $redis ) {
            wp_send_json_error( 'Redis non connesso' );
        }

        $key = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
        if ( empty( $key ) ) {
            wp_send_json_error( 'Chiave non specificata' );
        }

        try {
            $type_raw = $redis->type( $key );
            $type_map = array(
                Redis::REDIS_STRING => 'string',
                Redis::REDIS_LIST   => 'list',
                Redis::REDIS_SET    => 'set',
                Redis::REDIS_ZSET   => 'zset',
                Redis::REDIS_HASH   => 'hash',
            );
            if ( defined( 'Redis::REDIS_STREAM' ) ) {
                $type_map[ Redis::REDIS_STREAM ] = 'stream';
            }

            $type = $type_map[ $type_raw ] ?? 'unknown';
            $ttl  = $redis->ttl( $key );

            $memory = 0;
            try {
                $memory = $redis->rawCommand( 'MEMORY', 'USAGE', $key );
            } catch ( Exception $e ) {
                // Ignore
            }

            $encoding = '';
            try {
                $encoding = $redis->rawCommand( 'OBJECT', 'ENCODING', $key );
            } catch ( Exception $e ) {
                // Ignore
            }

            // Get value based on type
            $value  = null;
            $length = 0;

            switch ( $type ) {
                case 'string':
                    $raw_value = $redis->get( $key );
                    $length    = $redis->strlen( $key );

                    // Try to decode the value
                    $value = $raw_value;
                    if ( is_string( $raw_value ) ) {
                        $json = @json_decode( $raw_value, true );
                        if ( json_last_error() === JSON_ERROR_NONE ) {
                            $value = array( 'type' => 'json', 'data' => $json );
                        } else {
                            $unserialized = @unserialize( $raw_value );
                            if ( $unserialized !== false || $raw_value === 'b:0;' ) {
                                $value = array( 'type' => 'serialized', 'data' => $unserialized );
                            } else {
                                $value = array( 'type' => 'string', 'data' => $raw_value );
                            }
                        }
                    }
                    break;

                case 'list':
                    $length = $redis->lLen( $key );
                    $value  = $redis->lRange( $key, 0, min( 19, $length - 1 ) );
                    break;

                case 'set':
                    $length  = $redis->sCard( $key );
                    $members = $redis->sScan( $key, $cursor, '*', 20 );
                    $value   = $members ?: array();
                    break;

                case 'zset':
                    $length = $redis->zCard( $key );
                    $value  = $redis->zRange( $key, 0, 19, true );
                    break;

                case 'hash':
                    $length = $redis->hLen( $key );
                    $cursor = null;
                    $fields = array();
                    $count  = 0;
                    do {
                        $result = $redis->hScan( $key, $cursor, '*', 20 );
                        if ( is_array( $result ) ) {
                            foreach ( $result as $field => $val ) {
                                $fields[ $field ] = $val;
                                $count++;
                                if ( $count >= 20 ) {
                                    break 2;
                                }
                            }
                        }
                    } while ( $cursor > 0 && $count < 20 );
                    $value = $fields;
                    break;

                case 'stream':
                    try {
                        $length = $redis->xLen( $key );
                        $value  = $redis->xRange( $key, '-', '+', 10 );
                    } catch ( Exception $e ) {
                        $value  = array();
                        $length = 0;
                    }
                    break;
            }

            wp_send_json_success( array(
                'key'      => $key,
                'type'     => $type,
                'ttl'      => $ttl,
                'memory'   => (int) $memory,
                'encoding' => $encoding,
                'length'   => $length,
                'value'    => $value,
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( 'Errore: ' . $e->getMessage() );
        }
    }

    /**
     * Delete a Redis key.
     */
    public function src_delete_key() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $redis = $this->get_redis();
        if ( ! $redis ) {
            wp_send_json_error( 'Redis non connesso' );
        }

        $key = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
        if ( empty( $key ) ) {
            wp_send_json_error( 'Chiave non specificata' );
        }

        try {
            $result = $redis->del( $key );
            wp_send_json_success( array(
                'message' => $result ? 'Chiave eliminata con successo' : 'Chiave non trovata',
                'deleted' => (bool) $result,
            ) );
        } catch ( Exception $e ) {
            wp_send_json_error( 'Errore: ' . $e->getMessage() );
        }
    }

    /**
     * Install the object-cache.php drop-in.
     */
    public function src_install_dropin() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $dropin = new SRC_Dropin();

        if ( $dropin->is_foreign_dropin() ) {
            wp_send_json_error( array(
                'message' => 'Un altro drop-in è già installato. Rimuovilo prima.',
            ) );
        }

        if ( ! $dropin->is_writable() ) {
            wp_send_json_error( array(
                'message' => 'La directory wp-content non è scrivibile.',
            ) );
        }

        $result = $dropin->install();

        if ( $result ) {
            wp_send_json_success( array(
                'message' => 'Drop-in installato con successo. La cache Redis è ora attiva.',
            ) );
        } else {
            wp_send_json_error( array(
                'message' => 'Errore durante l\'installazione del drop-in.',
            ) );
        }
    }

    /**
     * Remove the object-cache.php drop-in.
     */
    public function src_uninstall_dropin() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $dropin = new SRC_Dropin();

        if ( ! $dropin->is_our_dropin() ) {
            wp_send_json_error( array(
                'message' => 'Il drop-in installato non appartiene a Open Redis Manager.',
            ) );
        }

        $result = $dropin->uninstall();

        if ( $result ) {
            wp_send_json_success( array(
                'message' => 'Drop-in rimosso. La cache Redis è stata disabilitata.',
            ) );
        } else {
            wp_send_json_error( array(
                'message' => 'Errore durante la rimozione del drop-in.',
            ) );
        }
    }

    // =========================================================================
    // AJAX: Settings Management
    // =========================================================================

    /**
     * Save plugin settings via AJAX.
     */
    public function src_save_settings() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permesso negato.' ) );
        }

        $raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
        if ( ! is_array( $raw ) ) {
            wp_send_json_error( array( 'message' => 'Dati non validi.' ) );
        }

        $sanitized = $this->sanitize_settings( $raw );

        // Write directly to the database to avoid object cache interference.
        global $wpdb;
        $option_name  = SRC_OPTION_NAME;
        $option_value = maybe_serialize( $sanitized );

        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $option_name
            )
        );

        if ( null !== $existing ) {
            $result = $wpdb->update(
                $wpdb->options,
                array( 'option_value' => $option_value ),
                array( 'option_name' => $option_name )
            );
        } else {
            $result = $wpdb->insert(
                $wpdb->options,
                array(
                    'option_name'  => $option_name,
                    'option_value' => $option_value,
                    'autoload'     => 'yes',
                )
            );
        }

        if ( false === $result ) {
            wp_send_json_error( array(
                'message' => 'Errore database: ' . $wpdb->last_error,
            ) );
        }

        // Flush ALL object cache to guarantee the next page load reads from DB.
        wp_cache_flush();

        wp_send_json_success( array(
            'message' => 'Impostazioni salvate con successo.',
        ) );
    }

    // =========================================================================
    // AJAX: wp-config.php Management
    // =========================================================================

    /**
     * Save Redis configuration to wp-config.php.
     */
    public function src_save_config() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $raw_values = isset( $_POST['config'] ) ? $_POST['config'] : array();
        if ( ! is_array( $raw_values ) ) {
            wp_send_json_error( 'Dati non validi' );
        }

        $values = SRC_Config::sanitize_values( $raw_values );

        $config = new SRC_Config();

        if ( ! $config->is_writable() ) {
            wp_send_json_error( array(
                'message' => 'wp-config.php non è scrivibile. Controlla i permessi (' . $config->get_path() . ').',
            ) );
        }

        $result = $config->update_constants( $values );

        if ( $result === true ) {
            wp_send_json_success( array(
                'message' => 'Configurazione salvata in wp-config.php. Ricarica la pagina per applicare.',
                'values'  => $values,
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result,
            ) );
        }
    }

    /**
     * Test Redis connection with provided parameters (before saving).
     */
    public function src_test_config() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $raw_values = isset( $_POST['config'] ) ? $_POST['config'] : array();
        if ( ! is_array( $raw_values ) ) {
            wp_send_json_error( 'Dati non validi' );
        }

        $values = SRC_Config::sanitize_values( $raw_values );
        $result = SRC_Config::test_connection( $values );

        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    /**
     * Remove SRC constants from wp-config.php.
     */
    public function src_remove_config() {
        check_ajax_referer( 'src_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permesso negato' );
        }

        $config = new SRC_Config();
        $result = $config->remove_constants();

        if ( $result === true ) {
            wp_send_json_success( array(
                'message' => 'Configurazione rimossa da wp-config.php.',
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $result,
            ) );
        }
    }
}
