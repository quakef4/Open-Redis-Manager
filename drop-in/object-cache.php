<?php
/**
 * Object Cache Drop-in for Starter Redis Cache
 *
 * Provides a complete WP_Object_Cache implementation using Redis via phpredis.
 * No third-party dependencies required - connects directly to Redis server.
 *
 * Configuration via wp-config.php constants:
 *   SRC_REDIS_HOST          - Redis host (default: '127.0.0.1')
 *   SRC_REDIS_PORT          - Redis port (default: 6379)
 *   SRC_REDIS_PASSWORD      - Redis password (default: '')
 *   SRC_REDIS_DATABASE      - Redis database index 0-15 (default: 0)
 *   SRC_REDIS_PREFIX        - Key prefix for site isolation (default: $table_prefix)
 *   SRC_REDIS_MAXTTL        - Maximum TTL in seconds, 0 = no limit (default: 0)
 *   SRC_REDIS_TIMEOUT       - Connection timeout seconds (default: 1)
 *   SRC_REDIS_READ_TIMEOUT  - Read timeout seconds (default: 1)
 *   SRC_REDIS_DISABLED      - Disable Redis, use memory only (default: false)
 *   SRC_REDIS_SERIALIZER    - 'php' or 'igbinary' (default: auto-detect)
 *   SRC_REDIS_SOCKET        - Unix socket path (overrides host/port)
 *
 * Multi-domain setup (10 sites example):
 *   Each site's wp-config.php should define a unique SRC_REDIS_DATABASE (0-9)
 *   or a unique SRC_REDIS_PREFIX to isolate keys.
 *
 * @package StarterRedisCache
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if phpredis extension is available.
 * Without it, WordPress will fall back to its default object cache.
 */
if ( ! class_exists( 'Redis' ) ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'Starter Redis Cache: phpredis extension not found. Falling back to default object cache.' );
    }
    return;
}

/**
 * Check if Redis is explicitly disabled.
 */
if ( defined( 'SRC_REDIS_DISABLED' ) && SRC_REDIS_DISABLED ) {
    return;
}

/**
 * WordPress Object Cache implementation using Redis.
 *
 * Replaces the default WordPress in-memory object cache with a persistent
 * Redis-backed cache. Supports cache groups, TTL, non-persistent groups,
 * global groups (multisite), and Redis hash groups for performance.
 */
class WP_Object_Cache {

    /**
     * Redis connection instance.
     *
     * @var Redis|null
     */
    public $redis = null;

    /**
     * Whether Redis is connected and available.
     *
     * @var bool
     */
    public $redis_connected = false;

    /**
     * In-memory cache (L1 cache layer).
     *
     * @var array
     */
    private $cache = array();

    /**
     * List of non-persistent cache groups (memory-only, not stored in Redis).
     *
     * @var array
     */
    private $non_persistent_groups = array();

    /**
     * List of global cache groups (shared across multisite blogs).
     *
     * @var array
     */
    private $global_groups = array();

    /**
     * Cache groups that use Redis HASH structure for performance.
     *
     * @var array
     */
    private $redis_hash_groups = array();

    /**
     * Custom TTL per cache group (group => seconds).
     *
     * @var array
     */
    private $group_ttl = array();

    /**
     * Current blog ID for multisite key prefixing.
     *
     * @var int
     */
    private $blog_prefix = 0;

    /**
     * Redis key prefix for site isolation.
     *
     * @var string
     */
    private $key_prefix = '';

    /**
     * Cache hits counter.
     *
     * @var int
     */
    public $cache_hits = 0;

    /**
     * Cache misses counter.
     *
     * @var int
     */
    public $cache_misses = 0;

    /**
     * Total Redis calls counter.
     *
     * @var int
     */
    public $redis_calls = 0;

    /**
     * Maximum TTL in seconds (0 = no limit).
     *
     * @var int
     */
    private $max_ttl = 0;

    /**
     * Connection errors log.
     *
     * @var array
     */
    private $errors = array();

    /**
     * Whether to use igbinary serializer.
     *
     * @var bool
     */
    private $use_igbinary = false;

    /**
     * Constructor. Connects to Redis and initializes cache.
     */
    public function __construct() {
        global $table_prefix, $blog_id;

        $this->blog_prefix = (int) ( $blog_id ?? 1 );
        $this->max_ttl     = defined( 'SRC_REDIS_MAXTTL' ) ? (int) SRC_REDIS_MAXTTL : 0;

        // Determine key prefix for site isolation
        if ( defined( 'SRC_REDIS_PREFIX' ) && SRC_REDIS_PREFIX !== '' ) {
            $this->key_prefix = SRC_REDIS_PREFIX;
        } elseif ( isset( $table_prefix ) ) {
            $this->key_prefix = $table_prefix;
        } else {
            $this->key_prefix = 'wp_';
        }

        // Check serializer preference
        $serializer = defined( 'SRC_REDIS_SERIALIZER' ) ? SRC_REDIS_SERIALIZER : 'auto';
        if ( $serializer === 'igbinary' || ( $serializer === 'auto' && function_exists( 'igbinary_serialize' ) ) ) {
            $this->use_igbinary = true;
        }

        $this->connect();
    }

    /**
     * Connect to Redis server.
     *
     * @return bool Whether connection was successful.
     */
    private function connect() {
        try {
            $this->redis = new Redis();

            $host         = defined( 'SRC_REDIS_HOST' ) ? SRC_REDIS_HOST : '127.0.0.1';
            $port         = defined( 'SRC_REDIS_PORT' ) ? (int) SRC_REDIS_PORT : 6379;
            $timeout      = defined( 'SRC_REDIS_TIMEOUT' ) ? (float) SRC_REDIS_TIMEOUT : 1.0;
            $read_timeout = defined( 'SRC_REDIS_READ_TIMEOUT' ) ? (float) SRC_REDIS_READ_TIMEOUT : 1.0;

            // Connect via Unix socket or TCP
            if ( defined( 'SRC_REDIS_SOCKET' ) && SRC_REDIS_SOCKET ) {
                if ( ! file_exists( SRC_REDIS_SOCKET ) ) {
                    throw new Exception( 'Redis socket non trovato: ' . SRC_REDIS_SOCKET );
                }
                $connected = $this->redis->connect( SRC_REDIS_SOCKET, 0, $timeout, null, 0, $read_timeout );
            } else {
                $connected = $this->redis->connect( $host, $port, $timeout, null, 0, $read_timeout );
            }

            if ( ! $connected ) {
                $via = ( defined( 'SRC_REDIS_SOCKET' ) && SRC_REDIS_SOCKET ) ? 'socket ' . SRC_REDIS_SOCKET : "{$host}:{$port}";
                throw new Exception( "Redis connection failed via {$via}" );
            }

            // Authenticate if password is set
            if ( defined( 'SRC_REDIS_PASSWORD' ) && SRC_REDIS_PASSWORD !== '' ) {
                if ( ! $this->redis->auth( SRC_REDIS_PASSWORD ) ) {
                    throw new Exception( 'Redis authentication failed' );
                }
            }

            // Select database for site isolation
            $database = defined( 'SRC_REDIS_DATABASE' ) ? (int) SRC_REDIS_DATABASE : 0;
            if ( $database > 0 ) {
                if ( ! $this->redis->select( $database ) ) {
                    throw new Exception( "Redis database select failed (db: {$database})" );
                }
            }

            // Set serializer
            if ( $this->use_igbinary && defined( 'Redis::SERIALIZER_IGBINARY' ) ) {
                $this->redis->setOption( Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY );
            } else {
                $this->redis->setOption( Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP );
            }

            $this->redis_connected = true;
            return true;

        } catch ( Exception $e ) {
            $this->handle_connection_error( $e );
            return false;
        }
    }

    /**
     * Handle Redis connection errors gracefully.
     *
     * @param Exception $e The exception that occurred.
     */
    private function handle_connection_error( $e ) {
        $this->redis_connected = false;
        $this->redis           = null;
        $this->errors[]        = $e->getMessage();

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Starter Redis Cache: ' . $e->getMessage() );
        }
    }

    /**
     * Build the full Redis key from group and key name.
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return string Full Redis key.
     */
    private function build_key( $key, $group = 'default' ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        $prefix = $this->key_prefix;

        // Global groups don't use blog prefix
        if ( ! isset( $this->global_groups[ $group ] ) ) {
            $prefix .= $this->blog_prefix . ':';
        }

        return $prefix . $group . ':' . $key;
    }

    /**
     * Get the effective TTL for a cache entry.
     *
     * @param string $group  Cache group.
     * @param int    $expire Requested expiration.
     * @return int TTL in seconds, 0 for no expiry.
     */
    private function get_ttl( $group, $expire = 0 ) {
        $ttl = (int) $expire;

        // Apply group-specific TTL if no explicit TTL set
        if ( $ttl <= 0 && isset( $this->group_ttl[ $group ] ) ) {
            $ttl = (int) $this->group_ttl[ $group ];
        }

        // Enforce maximum TTL
        if ( $this->max_ttl > 0 && ( $ttl <= 0 || $ttl > $this->max_ttl ) ) {
            $ttl = $this->max_ttl;
        }

        return $ttl;
    }

    /**
     * Serialize value for storage in Redis.
     *
     * @param mixed $data The data to serialize.
     * @return mixed Serialized data.
     */
    private function maybe_serialize( $data ) {
        // Redis serializer handles this automatically via setOption
        return $data;
    }

    /**
     * Unserialize value from Redis.
     *
     * @param mixed $data The data to unserialize.
     * @return mixed Unserialized data.
     */
    private function maybe_unserialize( $data ) {
        // Redis serializer handles this automatically via setOption
        return $data;
    }

    /**
     * Add data to cache if it doesn't already exist.
     *
     * @param string $key    Cache key.
     * @param mixed  $data   Data to cache.
     * @param string $group  Cache group.
     * @param int    $expire Expiration time in seconds.
     * @return bool True if added, false if key exists or on failure.
     */
    public function add( $key, $data, $group = 'default', $expire = 0 ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        // Check if key already exists in memory
        if ( $this->_exists( $key, $group ) ) {
            return false;
        }

        return $this->set( $key, $data, $group, $expire );
    }

    /**
     * Replace data in cache only if it already exists.
     *
     * @param string $key    Cache key.
     * @param mixed  $data   Data to cache.
     * @param string $group  Cache group.
     * @param int    $expire Expiration time in seconds.
     * @return bool True if replaced, false if key doesn't exist or on failure.
     */
    public function replace( $key, $data, $group = 'default', $expire = 0 ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        if ( ! $this->_exists( $key, $group ) ) {
            return false;
        }

        return $this->set( $key, $data, $group, $expire );
    }

    /**
     * Set data in cache.
     *
     * @param string $key    Cache key.
     * @param mixed  $data   Data to cache.
     * @param string $group  Cache group.
     * @param int    $expire Expiration time in seconds.
     * @return bool True on success, false on failure.
     */
    public function set( $key, $data, $group = 'default', $expire = 0 ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        // Clone objects to prevent reference issues
        if ( is_object( $data ) ) {
            $data = clone $data;
        }

        // Always store in memory (L1 cache)
        $this->cache[ $group ][ $key ] = $data;

        // Non-persistent groups stay in memory only
        if ( isset( $this->non_persistent_groups[ $group ] ) ) {
            return true;
        }

        // Write to Redis
        if ( $this->redis_connected ) {
            try {
                $ttl      = $this->get_ttl( $group, $expire );
                $redis_key = $this->build_key( $key, $group );

                $this->redis_calls++;

                // Use Redis HASH for hash groups
                if ( isset( $this->redis_hash_groups[ $group ] ) ) {
                    $hash_key = $this->key_prefix;
                    if ( ! isset( $this->global_groups[ $group ] ) ) {
                        $hash_key .= $this->blog_prefix . ':';
                    }
                    $hash_key .= $group;

                    $this->redis->hSet( $hash_key, $key, $this->maybe_serialize( $data ) );

                    // HASH TTL applies to the entire hash, set it if needed
                    if ( $ttl > 0 ) {
                        $current_ttl = $this->redis->ttl( $hash_key );
                        if ( $current_ttl < 0 || $current_ttl < $ttl ) {
                            $this->redis->expire( $hash_key, $ttl );
                        }
                    }

                    return true;
                }

                // Standard key-value storage
                if ( $ttl > 0 ) {
                    $result = $this->redis->setex( $redis_key, $ttl, $this->maybe_serialize( $data ) );
                } else {
                    $result = $this->redis->set( $redis_key, $this->maybe_serialize( $data ) );
                }

                return (bool) $result;

            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
                return true; // Return true since data is in memory
            }
        }

        return true;
    }

    /**
     * Get cached data.
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @param bool   $force Force refresh from Redis (bypass memory cache).
     * @param bool   $found Whether the key was found (passed by reference).
     * @return mixed|false Cached data or false if not found.
     */
    public function get( $key, $group = 'default', $force = false, &$found = null ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        // Check memory cache first (L1)
        if ( ! $force && isset( $this->cache[ $group ] ) && array_key_exists( $key, $this->cache[ $group ] ) ) {
            $found = true;
            $this->cache_hits++;

            $data = $this->cache[ $group ][ $key ];
            return is_object( $data ) ? clone $data : $data;
        }

        // Non-persistent groups only live in memory
        if ( isset( $this->non_persistent_groups[ $group ] ) ) {
            $found = false;
            $this->cache_misses++;
            return false;
        }

        // Fetch from Redis
        if ( $this->redis_connected ) {
            try {
                $this->redis_calls++;

                // Use Redis HASH for hash groups
                if ( isset( $this->redis_hash_groups[ $group ] ) ) {
                    $hash_key = $this->key_prefix;
                    if ( ! isset( $this->global_groups[ $group ] ) ) {
                        $hash_key .= $this->blog_prefix . ':';
                    }
                    $hash_key .= $group;

                    $data = $this->redis->hGet( $hash_key, $key );
                } else {
                    $redis_key = $this->build_key( $key, $group );
                    $data      = $this->redis->get( $redis_key );
                }

                if ( $data === false ) {
                    $found = false;
                    $this->cache_misses++;
                    return false;
                }

                $data = $this->maybe_unserialize( $data );

                // Store in memory cache
                $this->cache[ $group ][ $key ] = $data;

                $found = true;
                $this->cache_hits++;

                return is_object( $data ) ? clone $data : $data;

            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
            }
        }

        $found = false;
        $this->cache_misses++;
        return false;
    }

    /**
     * Get multiple cached values at once.
     *
     * @param array  $keys  Array of cache keys.
     * @param string $group Cache group.
     * @param bool   $force Force refresh from Redis.
     * @return array Associative array of key => value pairs.
     */
    public function get_multiple( $keys, $group = 'default', $force = false ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        $result      = array();
        $missing_keys = array();

        // Check memory cache first
        foreach ( $keys as $key ) {
            if ( ! $force && isset( $this->cache[ $group ] ) && array_key_exists( $key, $this->cache[ $group ] ) ) {
                $data = $this->cache[ $group ][ $key ];
                $result[ $key ] = is_object( $data ) ? clone $data : $data;
                $this->cache_hits++;
            } else {
                $missing_keys[] = $key;
                $result[ $key ] = false;
            }
        }

        // Fetch missing keys from Redis
        if ( ! empty( $missing_keys ) && $this->redis_connected && ! isset( $this->non_persistent_groups[ $group ] ) ) {
            try {
                if ( isset( $this->redis_hash_groups[ $group ] ) ) {
                    // Use HMGET for hash groups
                    $hash_key = $this->key_prefix;
                    if ( ! isset( $this->global_groups[ $group ] ) ) {
                        $hash_key .= $this->blog_prefix . ':';
                    }
                    $hash_key .= $group;

                    $this->redis_calls++;
                    $redis_values = $this->redis->hMGet( $hash_key, $missing_keys );

                    foreach ( $missing_keys as $key ) {
                        if ( isset( $redis_values[ $key ] ) && $redis_values[ $key ] !== false ) {
                            $data = $this->maybe_unserialize( $redis_values[ $key ] );
                            $this->cache[ $group ][ $key ] = $data;
                            $result[ $key ] = is_object( $data ) ? clone $data : $data;
                            $this->cache_hits++;
                        } else {
                            $this->cache_misses++;
                        }
                    }
                } else {
                    // Use MGET for standard keys
                    $redis_keys = array();
                    foreach ( $missing_keys as $key ) {
                        $redis_keys[] = $this->build_key( $key, $group );
                    }

                    $this->redis_calls++;
                    $redis_values = $this->redis->mget( $redis_keys );

                    foreach ( $missing_keys as $i => $key ) {
                        if ( $redis_values[ $i ] !== false ) {
                            $data = $this->maybe_unserialize( $redis_values[ $i ] );
                            $this->cache[ $group ][ $key ] = $data;
                            $result[ $key ] = is_object( $data ) ? clone $data : $data;
                            $this->cache_hits++;
                        } else {
                            $this->cache_misses++;
                        }
                    }
                }
            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
                foreach ( $missing_keys as $key ) {
                    $this->cache_misses++;
                }
            }
        } else {
            foreach ( $missing_keys as $key ) {
                $this->cache_misses++;
            }
        }

        return $result;
    }

    /**
     * Delete cached data.
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return bool True on success, false on failure.
     */
    public function delete( $key, $group = 'default' ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        // Remove from memory
        unset( $this->cache[ $group ][ $key ] );

        // Non-persistent groups don't need Redis delete
        if ( isset( $this->non_persistent_groups[ $group ] ) ) {
            return true;
        }

        // Delete from Redis
        if ( $this->redis_connected ) {
            try {
                $this->redis_calls++;

                if ( isset( $this->redis_hash_groups[ $group ] ) ) {
                    $hash_key = $this->key_prefix;
                    if ( ! isset( $this->global_groups[ $group ] ) ) {
                        $hash_key .= $this->blog_prefix . ':';
                    }
                    $hash_key .= $group;

                    return (bool) $this->redis->hDel( $hash_key, $key );
                }

                $redis_key = $this->build_key( $key, $group );
                return (bool) $this->redis->del( $redis_key );

            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
            }
        }

        return true;
    }

    /**
     * Flush the entire cache.
     *
     * Uses key prefix to only flush current site's keys,
     * preserving other sites on the same Redis instance.
     *
     * @return bool True on success.
     */
    public function flush() {
        // Clear memory cache
        $this->cache = array();

        if ( $this->redis_connected ) {
            try {
                $this->redis_calls++;

                // If we have a dedicated database, flush it entirely
                if ( defined( 'SRC_REDIS_DATABASE' ) && (int) SRC_REDIS_DATABASE > 0 ) {
                    return $this->redis->flushDB();
                }

                // Otherwise, use SCAN to delete only prefixed keys
                $pattern = $this->key_prefix . '*';
                $cursor  = null;
                $deleted = 0;

                do {
                    $keys = $this->redis->scan( $cursor, $pattern, 500 );
                    if ( $keys !== false && ! empty( $keys ) ) {
                        $this->redis->del( $keys );
                        $deleted += count( $keys );
                    }
                } while ( $cursor > 0 );

                return true;

            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
            }
        }

        return true;
    }

    /**
     * Flush a specific cache group.
     *
     * @param string $group Cache group to flush.
     * @return bool True on success, false on failure.
     */
    public function flush_group( $group ) {
        if ( empty( $group ) ) {
            return false;
        }

        // Clear from memory
        unset( $this->cache[ $group ] );

        if ( isset( $this->non_persistent_groups[ $group ] ) ) {
            return true;
        }

        if ( $this->redis_connected ) {
            try {
                $this->redis_calls++;

                // For hash groups, delete the entire hash
                if ( isset( $this->redis_hash_groups[ $group ] ) ) {
                    $hash_key = $this->key_prefix;
                    if ( ! isset( $this->global_groups[ $group ] ) ) {
                        $hash_key .= $this->blog_prefix . ':';
                    }
                    $hash_key .= $group;

                    return (bool) $this->redis->del( $hash_key );
                }

                // For standard groups, scan and delete matching keys
                $prefix  = $this->key_prefix;
                if ( ! isset( $this->global_groups[ $group ] ) ) {
                    $prefix .= $this->blog_prefix . ':';
                }
                $pattern = $prefix . $group . ':*';
                $cursor  = null;

                do {
                    $keys = $this->redis->scan( $cursor, $pattern, 500 );
                    if ( $keys !== false && ! empty( $keys ) ) {
                        $this->redis->del( $keys );
                    }
                } while ( $cursor > 0 );

                return true;

            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
            }
        }

        return true;
    }

    /**
     * Increment a numeric cached value.
     *
     * @param string $key    Cache key.
     * @param int    $offset Amount to increment by.
     * @param string $group  Cache group.
     * @return int|false New value on success, false on failure.
     */
    public function incr( $key, $offset = 1, $group = 'default' ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        // Get current value
        $current = $this->get( $key, $group );
        if ( $current === false ) {
            return false;
        }

        $new_value = (int) $current + (int) $offset;
        if ( $new_value < 0 ) {
            $new_value = 0;
        }

        // Update in memory
        $this->cache[ $group ][ $key ] = $new_value;

        // Non-persistent groups only update memory
        if ( isset( $this->non_persistent_groups[ $group ] ) ) {
            return $new_value;
        }

        // Update in Redis
        if ( $this->redis_connected ) {
            try {
                $this->redis_calls++;

                if ( isset( $this->redis_hash_groups[ $group ] ) ) {
                    $hash_key = $this->key_prefix;
                    if ( ! isset( $this->global_groups[ $group ] ) ) {
                        $hash_key .= $this->blog_prefix . ':';
                    }
                    $hash_key .= $group;

                    $result = $this->redis->hIncrBy( $hash_key, $key, (int) $offset );
                    if ( $result < 0 ) {
                        $this->redis->hSet( $hash_key, $key, 0 );
                        $result = 0;
                    }
                    $this->cache[ $group ][ $key ] = $result;
                    return $result;
                }

                $redis_key = $this->build_key( $key, $group );
                $result = $this->redis->incrBy( $redis_key, (int) $offset );
                if ( $result < 0 ) {
                    $this->redis->set( $redis_key, 0 );
                    $result = 0;
                }
                $this->cache[ $group ][ $key ] = $result;
                return $result;

            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
            }
        }

        return $new_value;
    }

    /**
     * Decrement a numeric cached value.
     *
     * @param string $key    Cache key.
     * @param int    $offset Amount to decrement by.
     * @param string $group  Cache group.
     * @return int|false New value on success, false on failure.
     */
    public function decr( $key, $offset = 1, $group = 'default' ) {
        if ( empty( $group ) ) {
            $group = 'default';
        }

        // Get current value
        $current = $this->get( $key, $group );
        if ( $current === false ) {
            return false;
        }

        $new_value = (int) $current - (int) $offset;
        if ( $new_value < 0 ) {
            $new_value = 0;
        }

        // Update in memory
        $this->cache[ $group ][ $key ] = $new_value;

        // Non-persistent groups only update memory
        if ( isset( $this->non_persistent_groups[ $group ] ) ) {
            return $new_value;
        }

        // Update in Redis
        if ( $this->redis_connected ) {
            try {
                $this->redis_calls++;

                if ( isset( $this->redis_hash_groups[ $group ] ) ) {
                    $hash_key = $this->key_prefix;
                    if ( ! isset( $this->global_groups[ $group ] ) ) {
                        $hash_key .= $this->blog_prefix . ':';
                    }
                    $hash_key .= $group;

                    $result = $this->redis->hIncrBy( $hash_key, $key, -1 * (int) $offset );
                    if ( $result < 0 ) {
                        $this->redis->hSet( $hash_key, $key, 0 );
                        $result = 0;
                    }
                    $this->cache[ $group ][ $key ] = $result;
                    return $result;
                }

                $redis_key = $this->build_key( $key, $group );
                $result = $this->redis->decrBy( $redis_key, (int) $offset );
                if ( $result < 0 ) {
                    $this->redis->set( $redis_key, 0 );
                    $result = 0;
                }
                $this->cache[ $group ][ $key ] = $result;
                return $result;

            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
            }
        }

        return $new_value;
    }

    /**
     * Switch blog context for multisite.
     *
     * @param int $blog_id Blog ID to switch to.
     */
    public function switch_to_blog( $blog_id ) {
        $this->blog_prefix = (int) $blog_id;
    }

    /**
     * Register global cache groups (shared across multisite blogs).
     *
     * @param array $groups List of group names.
     */
    public function add_global_groups( $groups ) {
        $groups = (array) $groups;
        foreach ( $groups as $group ) {
            $this->global_groups[ $group ] = true;
        }
    }

    /**
     * Register non-persistent cache groups (memory only, not stored in Redis).
     *
     * @param array $groups List of group names.
     */
    public function add_non_persistent_groups( $groups ) {
        $groups = (array) $groups;
        foreach ( $groups as $group ) {
            $this->non_persistent_groups[ $group ] = true;
        }
    }

    /**
     * Register Redis hash groups (use HASH structure for better performance).
     *
     * @param array $groups List of group names.
     */
    public function add_redis_hash_groups( $groups ) {
        $groups = (array) $groups;
        foreach ( $groups as $group ) {
            $this->redis_hash_groups[ $group ] = true;
        }
    }

    /**
     * Set custom TTL for specific cache groups.
     *
     * @param array $ttls Associative array of group => seconds.
     */
    public function set_group_ttl( $ttls ) {
        foreach ( (array) $ttls as $group => $seconds ) {
            $this->group_ttl[ $group ] = (int) $seconds;
        }
    }

    /**
     * Check if a key exists in cache.
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return bool Whether the key exists.
     */
    protected function _exists( $key, $group ) {
        // Check memory first
        if ( isset( $this->cache[ $group ] ) && array_key_exists( $key, $this->cache[ $group ] ) ) {
            return true;
        }

        // Non-persistent groups only check memory
        if ( isset( $this->non_persistent_groups[ $group ] ) ) {
            return false;
        }

        // Check Redis
        if ( $this->redis_connected ) {
            try {
                $this->redis_calls++;

                if ( isset( $this->redis_hash_groups[ $group ] ) ) {
                    $hash_key = $this->key_prefix;
                    if ( ! isset( $this->global_groups[ $group ] ) ) {
                        $hash_key .= $this->blog_prefix . ':';
                    }
                    $hash_key .= $group;

                    return (bool) $this->redis->hExists( $hash_key, $key );
                }

                $redis_key = $this->build_key( $key, $group );
                return (bool) $this->redis->exists( $redis_key );

            } catch ( Exception $e ) {
                $this->handle_connection_error( $e );
            }
        }

        return false;
    }

    /**
     * Display cache statistics.
     */
    public function stats() {
        $total = $this->cache_hits + $this->cache_misses;
        $rate  = $total > 0 ? round( ( $this->cache_hits / $total ) * 100, 1 ) : 0;

        echo '<h3>Starter Redis Cache Statistics</h3>';
        echo '<p>';
        echo "Redis Connected: " . ( $this->redis_connected ? 'Yes' : 'No' ) . '<br>';
        echo "Cache Hits: {$this->cache_hits}<br>";
        echo "Cache Misses: {$this->cache_misses}<br>";
        echo "Hit Rate: {$rate}%<br>";
        echo "Redis Calls: {$this->redis_calls}<br>";
        echo '</p>';
    }

    /**
     * Close the Redis connection.
     *
     * @return bool True on success.
     */
    public function close() {
        if ( $this->redis_connected && $this->redis ) {
            try {
                $this->redis->close();
            } catch ( Exception $e ) {
                // Ignore close errors
            }
        }

        $this->redis_connected = false;
        return true;
    }

    /**
     * Get Redis connection info for the admin interface.
     *
     * @return array Connection and status information.
     */
    public function get_info() {
        $info = array(
            'connected'      => $this->redis_connected,
            'hits'           => $this->cache_hits,
            'misses'         => $this->cache_misses,
            'redis_calls'    => $this->redis_calls,
            'prefix'         => $this->key_prefix,
            'errors'         => $this->errors,
            'hash_groups'    => array_keys( $this->redis_hash_groups ),
            'non_persistent' => array_keys( $this->non_persistent_groups ),
            'global_groups'  => array_keys( $this->global_groups ),
            'group_ttl'      => $this->group_ttl,
        );

        if ( $this->redis_connected && $this->redis ) {
            try {
                $redis_info       = $this->redis->info();
                $info['version']  = $redis_info['redis_version'] ?? 'unknown';
                $info['memory']   = $redis_info['used_memory_human'] ?? 'unknown';
                $info['uptime']   = $redis_info['uptime_in_seconds'] ?? 0;
                $info['database'] = defined( 'SRC_REDIS_DATABASE' ) ? (int) SRC_REDIS_DATABASE : 0;
            } catch ( Exception $e ) {
                // Ignore info errors
            }
        }

        return $info;
    }

    /**
     * Get the raw Redis client for advanced operations.
     *
     * @return Redis|null The Redis client or null.
     */
    public function get_redis() {
        return $this->redis;
    }

    /**
     * Check if Redis is connected.
     *
     * @return bool
     */
    public function is_connected() {
        return $this->redis_connected;
    }

    /**
     * Declare supported features.
     *
     * @return array
     */
    public function supports( $feature ) {
        switch ( $feature ) {
            case 'add_multiple':
            case 'set_multiple':
            case 'get_multiple':
            case 'delete_multiple':
            case 'flush_runtime':
            case 'flush_group':
                return true;
            default:
                return false;
        }
    }

    /**
     * Set multiple values at once.
     *
     * @param array  $data   Key => value pairs.
     * @param string $group  Cache group.
     * @param int    $expire Expiration time in seconds.
     * @return array Array of keys => success.
     */
    public function set_multiple( $data, $group = 'default', $expire = 0 ) {
        $result = array();
        foreach ( $data as $key => $value ) {
            $result[ $key ] = $this->set( $key, $value, $group, $expire );
        }
        return $result;
    }

    /**
     * Add multiple values at once (only if they don't exist).
     *
     * @param array  $data   Key => value pairs.
     * @param string $group  Cache group.
     * @param int    $expire Expiration time in seconds.
     * @return array Array of keys => success.
     */
    public function add_multiple( $data, $group = 'default', $expire = 0 ) {
        $result = array();
        foreach ( $data as $key => $value ) {
            $result[ $key ] = $this->add( $key, $value, $group, $expire );
        }
        return $result;
    }

    /**
     * Delete multiple values at once.
     *
     * @param array  $keys  Cache keys.
     * @param string $group Cache group.
     * @return array Array of keys => success.
     */
    public function delete_multiple( $keys, $group = 'default' ) {
        $result = array();
        foreach ( $keys as $key ) {
            $result[ $key ] = $this->delete( $key, $group );
        }
        return $result;
    }

    /**
     * Flush the runtime (memory-only) cache without touching Redis.
     *
     * @return bool
     */
    public function flush_runtime() {
        $this->cache = array();
        return true;
    }
}


// ============================================================================
// WordPress Cache API Functions
// ============================================================================

/**
 * Initialize the object cache.
 */
function wp_cache_init() {
    global $wp_object_cache;

    if ( ! ( $wp_object_cache instanceof WP_Object_Cache ) ) {
        $wp_object_cache = new WP_Object_Cache();
    }
}

/**
 * Add data to the cache.
 */
function wp_cache_add( $key, $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->add( $key, $data, $group, $expire );
}

/**
 * Add multiple values to the cache.
 */
function wp_cache_add_multiple( array $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->add_multiple( $data, $group, $expire );
}

/**
 * Replace data in the cache.
 */
function wp_cache_replace( $key, $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->replace( $key, $data, $group, $expire );
}

/**
 * Set data in the cache.
 */
function wp_cache_set( $key, $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->set( $key, $data, $group, $expire );
}

/**
 * Set multiple values in the cache.
 */
function wp_cache_set_multiple( array $data, $group = '', $expire = 0 ) {
    global $wp_object_cache;
    return $wp_object_cache->set_multiple( $data, $group, $expire );
}

/**
 * Get data from the cache.
 */
function wp_cache_get( $key, $group = '', $force = false, &$found = null ) {
    global $wp_object_cache;
    return $wp_object_cache->get( $key, $group, $force, $found );
}

/**
 * Get multiple values from the cache.
 */
function wp_cache_get_multiple( $keys, $group = '', $force = false ) {
    global $wp_object_cache;
    return $wp_object_cache->get_multiple( $keys, $group, $force );
}

/**
 * Delete data from the cache.
 */
function wp_cache_delete( $key, $group = '' ) {
    global $wp_object_cache;
    return $wp_object_cache->delete( $key, $group );
}

/**
 * Delete multiple values from the cache.
 */
function wp_cache_delete_multiple( array $keys, $group = '' ) {
    global $wp_object_cache;
    return $wp_object_cache->delete_multiple( $keys, $group );
}

/**
 * Flush the entire cache.
 */
function wp_cache_flush() {
    global $wp_object_cache;
    return $wp_object_cache->flush();
}

/**
 * Flush a specific cache group.
 */
function wp_cache_flush_group( $group ) {
    global $wp_object_cache;
    return $wp_object_cache->flush_group( $group );
}

/**
 * Flush only the runtime (memory) cache.
 */
function wp_cache_flush_runtime() {
    global $wp_object_cache;
    return $wp_object_cache->flush_runtime();
}

/**
 * Close the cache connection.
 */
function wp_cache_close() {
    global $wp_object_cache;
    return $wp_object_cache->close();
}

/**
 * Increment a cached value.
 */
function wp_cache_incr( $key, $offset = 1, $group = '' ) {
    global $wp_object_cache;
    return $wp_object_cache->incr( $key, $offset, $group );
}

/**
 * Decrement a cached value.
 */
function wp_cache_decr( $key, $offset = 1, $group = '' ) {
    global $wp_object_cache;
    return $wp_object_cache->decr( $key, $offset, $group );
}

/**
 * Add global cache groups.
 */
function wp_cache_add_global_groups( $groups ) {
    global $wp_object_cache;
    $wp_object_cache->add_global_groups( $groups );
}

/**
 * Add non-persistent cache groups.
 */
function wp_cache_add_non_persistent_groups( $groups ) {
    global $wp_object_cache;
    $wp_object_cache->add_non_persistent_groups( $groups );
}

/**
 * Add Redis hash groups (custom function for performance optimization).
 */
function wp_cache_add_redis_hash_groups( $groups ) {
    global $wp_object_cache;
    if ( method_exists( $wp_object_cache, 'add_redis_hash_groups' ) ) {
        $wp_object_cache->add_redis_hash_groups( $groups );
    }
}

/**
 * Set custom TTL for cache groups (custom function).
 */
function wp_cache_set_group_ttl( $ttls ) {
    global $wp_object_cache;
    if ( method_exists( $wp_object_cache, 'set_group_ttl' ) ) {
        $wp_object_cache->set_group_ttl( $ttls );
    }
}

/**
 * Switch to a different blog context.
 */
function wp_cache_switch_to_blog( $blog_id ) {
    global $wp_object_cache;
    $wp_object_cache->switch_to_blog( $blog_id );
}

/**
 * Check if the cache supports a given feature.
 */
function wp_cache_supports( $feature ) {
    global $wp_object_cache;
    return $wp_object_cache->supports( $feature );
}
