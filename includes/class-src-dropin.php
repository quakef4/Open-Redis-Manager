<?php
/**
 * Drop-in installer/manager for object-cache.php.
 *
 * Handles installing, updating, and removing the object-cache.php
 * drop-in from wp-content/.
 *
 * @package OpenRedisManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SRC_Dropin {

    /**
     * Path to the source drop-in template.
     *
     * @var string
     */
    private $source;

    /**
     * Path to the installed drop-in.
     *
     * @var string
     */
    private $target;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->source = SRC_PLUGIN_DIR . 'drop-in/object-cache.php';
        $this->target = WP_CONTENT_DIR . '/object-cache.php';
    }

    /**
     * Install the object-cache.php drop-in.
     *
     * @return bool True on success, false on failure.
     */
    public function install() {
        if ( ! file_exists( $this->source ) ) {
            return false;
        }

        // Check if another drop-in exists that's not ours
        if ( $this->is_foreign_dropin() ) {
            return false;
        }

        // Copy drop-in to wp-content
        $result = @copy( $this->source, $this->target );

        if ( $result && function_exists( 'wp_opcache_invalidate' ) ) {
            wp_opcache_invalidate( $this->target, true );
        }

        return $result;
    }

    /**
     * Remove the object-cache.php drop-in.
     *
     * @return bool True on success, false on failure.
     */
    public function uninstall() {
        if ( ! $this->is_our_dropin() ) {
            return false;
        }

        $result = @unlink( $this->target );

        if ( $result && function_exists( 'wp_opcache_invalidate' ) ) {
            wp_opcache_invalidate( $this->target, true );
        }

        return $result;
    }

    /**
     * Update the drop-in if the source is newer.
     *
     * @return bool True if updated, false otherwise.
     */
    public function update() {
        if ( ! $this->is_our_dropin() ) {
            return false;
        }

        if ( ! $this->needs_update() ) {
            return false;
        }

        return $this->install();
    }

    /**
     * Check if the installed drop-in is ours.
     *
     * @return bool
     */
    public function is_our_dropin() {
        if ( ! file_exists( $this->target ) ) {
            return false;
        }

        $content = @file_get_contents( $this->target );
        if ( $content === false ) {
            return false;
        }

        return strpos( $content, 'Open Redis Manager' ) !== false
            || strpos( $content, 'Starter Redis Cache' ) !== false;
    }

    /**
     * Check if a foreign (non-ours) drop-in is installed.
     *
     * @return bool
     */
    public function is_foreign_dropin() {
        if ( ! file_exists( $this->target ) ) {
            return false;
        }

        return ! $this->is_our_dropin();
    }

    /**
     * Check if the drop-in needs to be updated.
     *
     * @return bool
     */
    public function needs_update() {
        if ( ! file_exists( $this->target ) || ! file_exists( $this->source ) ) {
            return false;
        }

        return md5_file( $this->source ) !== md5_file( $this->target );
    }

    /**
     * Check if the drop-in is installed (either ours or foreign).
     *
     * @return bool
     */
    public function is_installed() {
        return file_exists( $this->target );
    }

    /**
     * Get the status of the drop-in.
     *
     * @return array Status information.
     */
    public function get_status() {
        $status = array(
            'installed'    => $this->is_installed(),
            'is_ours'      => $this->is_our_dropin(),
            'is_foreign'   => $this->is_foreign_dropin(),
            'needs_update' => $this->needs_update(),
            'writable'     => $this->is_writable(),
            'phpredis'     => class_exists( 'Redis' ),
        );

        if ( $status['is_foreign'] ) {
            $status['foreign_info'] = $this->get_foreign_info();
        }

        return $status;
    }

    /**
     * Check if wp-content directory is writable.
     *
     * @return bool
     */
    public function is_writable() {
        if ( file_exists( $this->target ) ) {
            return is_writable( $this->target );
        }
        return is_writable( WP_CONTENT_DIR );
    }

    /**
     * Get info about a foreign drop-in.
     *
     * @return string Description of the foreign drop-in.
     */
    private function get_foreign_info() {
        $content = @file_get_contents( $this->target );
        if ( $content === false ) {
            return 'Unknown drop-in';
        }

        // Try to extract plugin name from header
        if ( preg_match( '/Plugin Name:\s*(.+)/i', $content, $matches ) ) {
            return trim( $matches[1] );
        }

        // Check for known drop-ins
        if ( strpos( $content, 'WP Redis' ) !== false ) {
            return 'WP Redis (Till Krüss)';
        }
        if ( strpos( $content, 'Redis Object Cache' ) !== false ) {
            return 'Redis Object Cache';
        }

        return 'Unknown object cache drop-in';
    }
}
