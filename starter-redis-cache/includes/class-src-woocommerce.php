<?php
/**
 * WooCommerce integration for Starter Redis Cache.
 *
 * Automatically configures cache groups for WooCommerce compatibility
 * and handles session isolation between users on multi-domain setups.
 *
 * @package StarterRedisCache
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SRC_WooCommerce {

    /**
     * WooCommerce session groups that must be non-persistent.
     *
     * @var array
     */
    private static $session_groups = array(
        'wc_session_id',
        'wc-session-id',
        'woocommerce_session_id',
        'cart',
        'wc_cart',
        'woocommerce_cart',
        'woocommerce_sessions',
    );

    /**
     * Constructor.
     */
    public function __construct() {
        // Hook early to ensure WooCommerce session groups are always non-persistent
        add_action( 'plugins_loaded', array( $this, 'enforce_session_isolation' ), 2 );

        // WooCommerce-specific hooks
        add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
    }

    /**
     * Enforce that WooCommerce session groups are always non-persistent.
     * This prevents the critical "shared cart" bug between users.
     */
    public function enforce_session_isolation() {
        if ( ! function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
            return;
        }

        // Always mark WooCommerce session groups as non-persistent
        // regardless of user configuration
        wp_cache_add_non_persistent_groups( self::$session_groups );
    }

    /**
     * Additional WooCommerce initialization.
     */
    public function woocommerce_init() {
        // Add WooCommerce-specific non-persistent groups
        if ( function_exists( 'wp_cache_add_non_persistent_groups' ) ) {
            $extra_groups = array(
                'wc_store_api',
                'woocommerce_notices',
            );
            wp_cache_add_non_persistent_groups( $extra_groups );
        }
    }

    /**
     * Get the recommended preset configuration for WooCommerce.
     *
     * @return array Preset configuration.
     */
    public static function get_woocommerce_preset() {
        return array(
            'non_persistent_groups' => implode( "\n", array_merge(
                self::$session_groups,
                array( 'woocommerce_notices', 'wc_store_api' )
            ) ),
            'redis_hash_groups' => implode( "\n", array(
                'post_meta',
                'term_meta',
                'user_meta',
                'options',
                'wc_var_prices',
                'wc_webhooks',
                'wc_attribute_taxonomies',
            ) ),
            'custom_ttl' => implode( "\n", array(
                'posts:3600',
                'wc_var_prices:1800',
                'options:3600',
                'terms:7200',
            ) ),
        );
    }

    /**
     * Get the recommended preset for YITH Request a Quote compatibility.
     *
     * @return array Preset configuration.
     */
    public static function get_yith_preset() {
        return array(
            'non_persistent_groups' => implode( "\n", array_merge(
                self::$session_groups,
                array(
                    'yith_ywraq',
                    'ywraq',
                    'request_quote',
                    'yith_session',
                    'yith_wcwl',
                    'yith_wl',
                    'user_meta',
                    'options',
                )
            ) ),
            'redis_hash_groups' => implode( "\n", array(
                'post_meta',
                'term_meta',
                'wc_var_prices',
                'wc_attribute_taxonomies',
            ) ),
            'custom_ttl' => implode( "\n", array(
                'posts:3600',
                'terms:7200',
            ) ),
        );
    }

    /**
     * Get the recommended preset for a blog/magazine site.
     *
     * @return array Preset configuration.
     */
    public static function get_blog_preset() {
        return array(
            'non_persistent_groups' => '',
            'redis_hash_groups' => implode( "\n", array(
                'post_meta',
                'term_meta',
                'user_meta',
                'options',
                'transient',
            ) ),
            'custom_ttl' => implode( "\n", array(
                'posts:7200',
                'terms:7200',
                'options:3600',
            ) ),
        );
    }

    /**
     * Get the recommended preset for multisite installations.
     *
     * @return array Preset configuration.
     */
    public static function get_multisite_preset() {
        return array(
            'non_persistent_groups' => '',
            'redis_hash_groups' => implode( "\n", array(
                'post_meta',
                'term_meta',
                'user_meta',
                'options',
            ) ),
            'global_groups' => implode( "\n", array(
                'users',
                'userlogins',
                'usermeta',
                'user_meta',
                'site-transient',
                'site-options',
                'blog-lookup',
                'blog-details',
                'networks',
                'sites',
            ) ),
            'custom_ttl' => '',
        );
    }

    /**
     * Get the maximum performance preset.
     *
     * @return array Preset configuration.
     */
    public static function get_performance_preset() {
        return array(
            'non_persistent_groups' => implode( "\n", self::$session_groups ),
            'redis_hash_groups' => implode( "\n", array(
                'post_meta',
                'term_meta',
                'user_meta',
                'comment_meta',
                'options',
                'terms',
                'transient',
                'posts',
            ) ),
            'custom_ttl' => implode( "\n", array(
                'posts:7200',
                'options:7200',
                'terms:14400',
            ) ),
        );
    }

    /**
     * Get the multi-domain preset optimized for 10 WooCommerce sites.
     *
     * @return array Preset configuration.
     */
    public static function get_multi_domain_preset() {
        return array(
            'non_persistent_groups' => implode( "\n", array_merge(
                self::$session_groups,
                array( 'woocommerce_notices', 'wc_store_api' )
            ) ),
            'redis_hash_groups' => implode( "\n", array(
                'post_meta',
                'term_meta',
                'user_meta',
                'options',
                'wc_var_prices',
            ) ),
            'custom_ttl' => implode( "\n", array(
                'posts:3600',
                'options:1800',
                'wc_var_prices:1800',
                'terms:3600',
                'transient:1800',
            ) ),
        );
    }
}
