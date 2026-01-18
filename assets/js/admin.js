/**
 * WP Redis Manager - Admin JavaScript
 * 
 * @package WP_Redis_Manager
 */

(function($) {
    'use strict';

    var RedisManager = {
        
        /**
         * Initialize
         */
        init: function() {
            this.initTabs();
            this.initButtons();
            this.initPresets();
            this.testConnection();
            this.getStats();
            
            // Auto-refresh stats every 30 seconds
            setInterval(function() {
                RedisManager.getStats();
            }, 30000);
        },
        
        /**
         * Initialize Tabs
         */
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Update active content
                $('.tab-content').removeClass('active');
                $(target).addClass('active');
            });
        },
        
        /**
         * Initialize Buttons
         */
        initButtons: function() {
            // Test Connection
            $('#test-connection').on('click', function(e) {
                e.preventDefault();
                RedisManager.testConnection();
            });
            
            // Flush Cache
            $('#flush-cache').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Sei sicuro di voler svuotare completamente la cache Redis?')) {
                    return;
                }
                
                RedisManager.flushCache();
            });
            
            // Refresh Stats
            $('#refresh-stats').on('click', function(e) {
                e.preventDefault();
                RedisManager.getStats();
            });
        },
        
        /**
         * Initialize Presets
         */
        initPresets: function() {
            $('.load-preset').on('click', function(e) {
                e.preventDefault();
                
                var preset = $(this).data('preset');
                
                if (!confirm('Caricare il preset "' + preset + '"? Questo sovrascriverà le configurazioni correnti.')) {
                    return;
                }
                
                RedisManager.loadPreset(preset);
            });
        },
        
        /**
         * Test Redis Connection
         */
        testConnection: function() {
            var $button = $('#test-connection');
            var $statusDot = $('#redis-status-dot');
            var $statusText = $('#redis-status-text');
            var $redisInfo = $('#redis-info');
            
            $button.addClass('loading');
            $statusText.text(wpRedisManager.strings.testingConnection);
            $statusDot.removeClass('connected disconnected');
            
            $.ajax({
                url: wpRedisManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_redis_manager_test_connection',
                    nonce: wpRedisManager.nonce
                },
                success: function(response) {
                    if (response.success && response.data.connected) {
                        $statusDot.addClass('connected');
                        $statusText.text(response.data.message);
                        
                        // Show info if available
                        if (response.data.info) {
                            $('#redis-version').text(response.data.info.version);
                            $('#redis-memory').text(response.data.info.memory);
                            $('#redis-uptime').text(response.data.info.uptime);
                            $redisInfo.slideDown();
                        }
                        
                        RedisManager.showNotice('success', response.data.message);
                    } else {
                        $statusDot.addClass('disconnected');
                        $statusText.text(response.data.message);
                        $redisInfo.slideUp();
                        RedisManager.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    $statusDot.addClass('disconnected');
                    $statusText.text('Errore di connessione');
                    $redisInfo.slideUp();
                    RedisManager.showNotice('error', 'Errore durante il test di connessione');
                },
                complete: function() {
                    $button.removeClass('loading');
                }
            });
        },
        
        /**
         * Flush Cache
         */
        flushCache: function() {
            var $button = $('#flush-cache');
            
            $button.addClass('loading');
            
            $.ajax({
                url: wpRedisManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_redis_manager_flush_cache',
                    nonce: wpRedisManager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RedisManager.showNotice('success', response.data.message);
                        RedisManager.getStats();
                    } else {
                        RedisManager.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    RedisManager.showNotice('error', 'Errore durante lo svuotamento della cache');
                },
                complete: function() {
                    $button.removeClass('loading');
                }
            });
        },
        
        /**
         * Get Cache Stats
         */
        getStats: function() {
            $.ajax({
                url: wpRedisManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_redis_manager_get_stats',
                    nonce: wpRedisManager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#cache-hits').text(response.data.hits.toLocaleString());
                        $('#cache-misses').text(response.data.misses.toLocaleString());
                        $('#hit-rate').text(response.data.hit_rate);
                        $('#redis-calls').text(response.data.redis_calls.toLocaleString());
                        
                        // Update hit rate color
                        var hitRate = parseFloat(response.data.hit_rate);
                        var $hitRateBox = $('#hit-rate').parent();
                        
                        if (hitRate >= 85) {
                            $hitRateBox.css('border-color', '#00a32a');
                        } else if (hitRate >= 70) {
                            $hitRateBox.css('border-color', '#dba617');
                        } else {
                            $hitRateBox.css('border-color', '#d63638');
                        }
                    }
                }
            });
        },
        
        /**
         * Load Preset
         */
        loadPreset: function(presetName) {
            if (typeof wpRedisPresets === 'undefined' || !wpRedisPresets[presetName]) {
                RedisManager.showNotice('error', 'Preset non trovato');
                return;
            }
            
            var preset = wpRedisPresets[presetName];
            
            // Load non-persistent groups
            if (preset.non_persistent_groups) {
                $('#non_persistent_groups').val(preset.non_persistent_groups);
            }
            
            // Load redis hash groups
            if (preset.redis_hash_groups) {
                $('#redis_hash_groups').val(preset.redis_hash_groups);
            }
            
            // Load global groups
            if (preset.global_groups) {
                $('#global_groups').val(preset.global_groups);
            }
            
            // Load custom TTL
            if (preset.custom_ttl) {
                $('#custom_ttl').val(preset.custom_ttl);
            }
            
            // Switch to first tab
            $('.nav-tab').first().trigger('click');
            
            RedisManager.showNotice('success', 'Preset "' + presetName + '" caricato! Clicca "Salva Configurazione" per applicare.');
            
            // Scroll to save button
            $('html, body').animate({
                scrollTop: $('.submit').offset().top - 100
            }, 500);
        },
        
        /**
         * Show Notice
         */
        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            
            $('.wp-redis-manager h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        RedisManager.init();
    });
    
})(jQuery);
