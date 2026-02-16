<?php
/**
 * Manages reading and writing SRC_REDIS_* constants in wp-config.php.
 *
 * Safely injects and updates Redis configuration constants without
 * corrupting the wp-config.php file.
 *
 * @package StarterRedisCache
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SRC_Config {

    /**
     * Path to wp-config.php.
     *
     * @var string
     */
    private $config_path;

    /**
     * All SRC constants with defaults and descriptions.
     *
     * @var array
     */
    private static $constants = array(
        'SRC_REDIS_HOST'         => array( 'default' => '127.0.0.1', 'type' => 'string',  'label' => 'Host Redis' ),
        'SRC_REDIS_PORT'         => array( 'default' => 6379,        'type' => 'int',     'label' => 'Porta' ),
        'SRC_REDIS_SOCKET'       => array( 'default' => '',          'type' => 'string',  'label' => 'Unix Socket' ),
        'SRC_REDIS_PASSWORD'     => array( 'default' => '',          'type' => 'string',  'label' => 'Password' ),
        'SRC_REDIS_DATABASE'     => array( 'default' => 0,           'type' => 'int',     'label' => 'Database (0-15)' ),
        'SRC_REDIS_PREFIX'       => array( 'default' => '',          'type' => 'string',  'label' => 'Prefisso chiavi' ),
        'SRC_REDIS_MAXTTL'       => array( 'default' => 0,           'type' => 'int',     'label' => 'TTL Massimo (secondi)' ),
        'SRC_REDIS_TIMEOUT'      => array( 'default' => 1,           'type' => 'float',   'label' => 'Timeout connessione (s)' ),
        'SRC_REDIS_READ_TIMEOUT' => array( 'default' => 1,           'type' => 'float',   'label' => 'Timeout lettura (s)' ),
        'SRC_REDIS_DISABLED'     => array( 'default' => false,       'type' => 'bool',    'label' => 'Disabilita Redis' ),
        'SRC_REDIS_SERIALIZER'   => array( 'default' => 'php',       'type' => 'string',  'label' => 'Serializer' ),
    );

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config_path = $this->find_wp_config();
    }

    /**
     * Find the wp-config.php file path.
     *
     * @return string Path to wp-config.php.
     */
    private function find_wp_config() {
        // Standard location
        if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
            return ABSPATH . 'wp-config.php';
        }

        // One level up (common in some setups)
        if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
            return dirname( ABSPATH ) . '/wp-config.php';
        }

        return '';
    }

    /**
     * Check if wp-config.php is writable.
     *
     * @return bool
     */
    public function is_writable() {
        return $this->config_path !== '' && is_writable( $this->config_path );
    }

    /**
     * Check if wp-config.php exists and is readable.
     *
     * @return bool
     */
    public function is_readable() {
        return $this->config_path !== '' && is_readable( $this->config_path );
    }

    /**
     * Get the path to wp-config.php.
     *
     * @return string
     */
    public function get_path() {
        return $this->config_path;
    }

    /**
     * Get current values of all SRC constants.
     *
     * @return array Constant name => current value.
     */
    public function get_current_values() {
        $values = array();

        foreach ( self::$constants as $name => $info ) {
            if ( defined( $name ) ) {
                $values[ $name ] = constant( $name );
            } else {
                $values[ $name ] = $info['default'];
            }
        }

        return $values;
    }

    /**
     * Get constant definitions metadata.
     *
     * @return array
     */
    public static function get_constants_info() {
        return self::$constants;
    }

    /**
     * Update SRC constants in wp-config.php.
     *
     * Inserts or updates constants in a clearly marked block.
     *
     * @param array $values Constant name => value pairs.
     * @return bool|string True on success, error message on failure.
     */
    public function update_constants( $values ) {
        if ( ! $this->is_writable() ) {
            return 'wp-config.php non è scrivibile. Controlla i permessi del file.';
        }

        $content = file_get_contents( $this->config_path );
        if ( $content === false ) {
            return 'Impossibile leggere wp-config.php.';
        }

        // Build the constants block
        $block = $this->build_constants_block( $values );

        // Check if our block already exists
        $start_marker = '/* BEGIN Starter Redis Cache */';
        $end_marker   = '/* END Starter Redis Cache */';

        $start_pos = strpos( $content, $start_marker );
        $end_pos   = strpos( $content, $end_marker );

        if ( $start_pos !== false && $end_pos !== false ) {
            // Replace existing block
            $before  = substr( $content, 0, $start_pos );
            $after   = substr( $content, $end_pos + strlen( $end_marker ) );
            $content = $before . $block . $after;
        } else {
            // Insert before "That's all, stop editing!" or before the require_once wp-settings.php
            $insert_before = $this->find_insertion_point( $content );
            if ( $insert_before === false ) {
                return 'Impossibile trovare il punto di inserimento in wp-config.php. Il file potrebbe avere un formato non standard.';
            }

            $before  = substr( $content, 0, $insert_before );
            $after   = substr( $content, $insert_before );
            $content = $before . $block . "\n" . $after;
        }

        // Create backup before writing
        $backup = $this->config_path . '.src-backup-' . date( 'Ymd-His' );
        @copy( $this->config_path, $backup );

        // Write the updated content
        $result = file_put_contents( $this->config_path, $content );
        if ( $result === false ) {
            return 'Errore durante la scrittura di wp-config.php.';
        }

        // Invalidate opcache
        if ( function_exists( 'opcache_invalidate' ) ) {
            opcache_invalidate( $this->config_path, true );
        }

        return true;
    }

    /**
     * Remove SRC constants block from wp-config.php.
     *
     * @return bool|string True on success, error message on failure.
     */
    public function remove_constants() {
        if ( ! $this->is_writable() ) {
            return 'wp-config.php non è scrivibile.';
        }

        $content = file_get_contents( $this->config_path );
        if ( $content === false ) {
            return 'Impossibile leggere wp-config.php.';
        }

        $start_marker = '/* BEGIN Starter Redis Cache */';
        $end_marker   = '/* END Starter Redis Cache */';

        $start_pos = strpos( $content, $start_marker );
        $end_pos   = strpos( $content, $end_marker );

        if ( $start_pos === false || $end_pos === false ) {
            return true; // Nothing to remove
        }

        // Remove the block plus any trailing newlines
        $before  = rtrim( substr( $content, 0, $start_pos ) ) . "\n\n";
        $after   = ltrim( substr( $content, $end_pos + strlen( $end_marker ) ) );
        $content = $before . $after;

        $result = file_put_contents( $this->config_path, $content );
        if ( $result === false ) {
            return 'Errore durante la scrittura di wp-config.php.';
        }

        if ( function_exists( 'opcache_invalidate' ) ) {
            opcache_invalidate( $this->config_path, true );
        }

        return true;
    }

    /**
     * Build the constants block to insert into wp-config.php.
     *
     * @param array $values Constant values.
     * @return string PHP code block.
     */
    private function build_constants_block( $values ) {
        $lines = array();
        $lines[] = '/* BEGIN Starter Redis Cache */';

        foreach ( self::$constants as $name => $info ) {
            if ( ! isset( $values[ $name ] ) ) {
                continue;
            }

            $value = $values[ $name ];

            // Skip empty/default values to keep wp-config.php clean
            if ( $this->is_default_value( $name, $value ) ) {
                continue;
            }

            $php_value = $this->format_php_value( $value, $info['type'] );
            $lines[]   = "define( '{$name}', {$php_value} );";
        }

        $lines[] = '/* END Starter Redis Cache */';

        return implode( "\n", $lines );
    }

    /**
     * Check if a value matches the default.
     *
     * @param string $name  Constant name.
     * @param mixed  $value Value to check.
     * @return bool
     */
    private function is_default_value( $name, $value ) {
        if ( ! isset( self::$constants[ $name ] ) ) {
            return true;
        }

        $default = self::$constants[ $name ]['default'];

        // Critical constants should always be written
        $always_write = array(
            'SRC_REDIS_HOST',
            'SRC_REDIS_PORT',
            'SRC_REDIS_DATABASE',
            'SRC_REDIS_PREFIX',
        );
        if ( in_array( $name, $always_write, true ) ) {
            return false;
        }

        // Empty strings and zero values are defaults (skip them)
        if ( $value === '' && $default === '' ) {
            return true;
        }
        if ( $value === '0' || $value === 0 ) {
            if ( $default === 0 || $default === false ) {
                return true;
            }
        }

        return $value == $default;
    }

    /**
     * Format a value as PHP code.
     *
     * @param mixed  $value The value.
     * @param string $type  The type (string, int, float, bool).
     * @return string PHP representation.
     */
    private function format_php_value( $value, $type ) {
        switch ( $type ) {
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return $value ? 'true' : 'false';
            case 'string':
            default:
                return "'" . addcslashes( (string) $value, "'" ) . "'";
        }
    }

    /**
     * Find the correct insertion point in wp-config.php.
     *
     * @param string $content File content.
     * @return int|false Position to insert before, or false.
     */
    private function find_insertion_point( $content ) {
        // Try "That's all, stop editing!" comment
        $markers = array(
            "/* That's all, stop editing!",
            "/* That's all, stop editing!",
            '/** Sets up WordPress vars and included files.',
            "require_once ABSPATH . 'wp-settings.php'",
            "require_once(ABSPATH . 'wp-settings.php')",
        );

        foreach ( $markers as $marker ) {
            $pos = strpos( $content, $marker );
            if ( $pos !== false ) {
                // Go back to the start of the line
                $line_start = strrpos( substr( $content, 0, $pos ), "\n" );
                return $line_start !== false ? $line_start : $pos;
            }
        }

        return false;
    }

    /**
     * Check if any SRC constants are defined in wp-config.php.
     *
     * @return bool
     */
    public function has_constants() {
        if ( ! $this->is_readable() ) {
            return false;
        }

        $content = file_get_contents( $this->config_path );
        return strpos( $content, 'SRC_REDIS_' ) !== false;
    }

    /**
     * Check if our managed block exists in wp-config.php.
     *
     * @return bool
     */
    public function has_managed_block() {
        if ( ! $this->is_readable() ) {
            return false;
        }

        $content = file_get_contents( $this->config_path );
        return strpos( $content, '/* BEGIN Starter Redis Cache */' ) !== false;
    }

    /**
     * Sanitize incoming values from the form.
     *
     * @param array $raw Raw POST values.
     * @return array Sanitized values.
     */
    public static function sanitize_values( $raw ) {
        $sanitized = array();

        foreach ( self::$constants as $name => $info ) {
            if ( ! isset( $raw[ $name ] ) ) {
                $sanitized[ $name ] = $info['default'];
                continue;
            }

            $value = $raw[ $name ];

            switch ( $info['type'] ) {
                case 'int':
                    $sanitized[ $name ] = (int) $value;
                    break;
                case 'float':
                    $sanitized[ $name ] = (float) $value;
                    break;
                case 'bool':
                    $sanitized[ $name ] = ! empty( $value ) && $value !== 'false';
                    break;
                case 'string':
                default:
                    $sanitized[ $name ] = sanitize_text_field( $value );
                    break;
            }
        }

        // Validate host: must be IP or hostname, not an email
        if ( isset( $sanitized['SRC_REDIS_HOST'] ) ) {
            $host = trim( $sanitized['SRC_REDIS_HOST'] );
            // Reject email addresses (browser autofill protection)
            if ( strpos( $host, '@' ) !== false ) {
                $sanitized['SRC_REDIS_HOST'] = '127.0.0.1';
            }
            // Reject empty or whitespace-only
            if ( $host === '' ) {
                $sanitized['SRC_REDIS_HOST'] = '127.0.0.1';
            }
        }

        // Auto-populate prefix with $table_prefix if left empty
        if ( isset( $sanitized['SRC_REDIS_PREFIX'] ) && $sanitized['SRC_REDIS_PREFIX'] === '' ) {
            global $table_prefix;
            $sanitized['SRC_REDIS_PREFIX'] = isset( $table_prefix ) ? $table_prefix : 'wp_';
        }

        // Validate database range
        if ( isset( $sanitized['SRC_REDIS_DATABASE'] ) ) {
            $sanitized['SRC_REDIS_DATABASE'] = max( 0, min( 15, $sanitized['SRC_REDIS_DATABASE'] ) );
        }

        // Validate port range
        if ( isset( $sanitized['SRC_REDIS_PORT'] ) ) {
            $sanitized['SRC_REDIS_PORT'] = max( 1, min( 65535, $sanitized['SRC_REDIS_PORT'] ) );
        }

        return $sanitized;
    }

    /**
     * Test connection with given parameters (before writing to wp-config.php).
     *
     * @param array $values Connection parameters.
     * @return array Test result with 'success' and 'message' keys.
     */
    public static function test_connection( $values ) {
        if ( ! class_exists( 'Redis' ) ) {
            return array(
                'success' => false,
                'message' => 'Estensione phpredis non disponibile.',
            );
        }

        $use_socket = ! empty( $values['SRC_REDIS_SOCKET'] );

        // Validate socket file before attempting connection
        if ( $use_socket ) {
            $socket_path = $values['SRC_REDIS_SOCKET'];
            if ( ! file_exists( $socket_path ) ) {
                return array(
                    'success' => false,
                    'message' => "Socket non trovato: {$socket_path}",
                );
            }
            if ( ! is_readable( $socket_path ) || ! is_writable( $socket_path ) ) {
                return array(
                    'success' => false,
                    'message' => "Permessi insufficienti sul socket: {$socket_path} (utente PHP: " . get_current_user() . ')',
                );
            }
        }

        try {
            $redis   = new Redis();
            $host    = $values['SRC_REDIS_HOST'] ?: '127.0.0.1';
            $port    = (int) ( $values['SRC_REDIS_PORT'] ?: 6379 );
            $timeout = (float) ( $values['SRC_REDIS_TIMEOUT'] ?: 1.0 );

            if ( $use_socket ) {
                $redis->connect( $socket_path, 0, $timeout );
            } else {
                $redis->connect( $host, $port, $timeout );
            }

            if ( ! empty( $values['SRC_REDIS_PASSWORD'] ) ) {
                $redis->auth( $values['SRC_REDIS_PASSWORD'] );
            }

            $db = (int) ( $values['SRC_REDIS_DATABASE'] ?? 0 );
            if ( $db > 0 ) {
                $redis->select( $db );
            }

            $info    = $redis->info();
            $version = $info['redis_version'] ?? 'unknown';
            $memory  = $info['used_memory_human'] ?? 'unknown';

            $redis->close();

            $via = $use_socket ? "socket {$socket_path}" : "{$host}:{$port}";

            return array(
                'success' => true,
                'message' => "Connessione riuscita via {$via}. Redis {$version}, Memoria: {$memory}",
                'version' => $version,
                'memory'  => $memory,
            );

        } catch ( Exception $e ) {
            $via = $use_socket ? "socket {$socket_path}" : "{$host}:{$port}";
            return array(
                'success' => false,
                'message' => "Connessione fallita via {$via}: " . $e->getMessage(),
            );
        }
    }
}
