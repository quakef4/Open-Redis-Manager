/**
 * WP Redis Manager - Admin JavaScript
 *
 * @package WP_Redis_Manager
 */

(function($) {
    'use strict';

    var RedisManager = {

        // Activity data cache
        activityData: null,
        activityInterval: null,
        keysCursor: '0',
        currentKeyDetails: null,

        /**
         * Initialize
         */
        init: function() {
            this.initTabs();
            this.initButtons();
            this.initPresets();
            this.initActivityMonitor();
            this.initKeysExplorer();
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

                // Load activity data when switching to activity tab
                if (target === '#tab-activity' && !RedisManager.activityData) {
                    RedisManager.getActivity();
                }
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
         * Initialize Activity Monitor
         */
        initActivityMonitor: function() {
            // Refresh activity button
            $('#refresh-activity').on('click', function(e) {
                e.preventDefault();
                RedisManager.getActivity();
            });

            // Auto-refresh toggle
            $('#activity-autorefresh').on('change', function() {
                if ($(this).is(':checked')) {
                    RedisManager.startActivityAutoRefresh();
                } else {
                    RedisManager.stopActivityAutoRefresh();
                }
            });

            // Command stats filters
            $('#cmd-filter, #cmd-sort, #cmd-limit').on('change keyup', function() {
                RedisManager.renderCommandStats();
            });

            // Slowlog filters
            $('#slowlog-filter, #slowlog-min-duration').on('change keyup', function() {
                RedisManager.renderSlowlog();
            });
        },

        /**
         * Initialize Keys Explorer
         */
        initKeysExplorer: function() {
            // Search keys button
            $('#search-keys').on('click', function(e) {
                e.preventDefault();
                RedisManager.keysCursor = '0';
                RedisManager.searchKeys(false);
            });

            // Load more button
            $('#load-more-keys').on('click', function(e) {
                e.preventDefault();
                RedisManager.searchKeys(true);
            });

            // Enter key on pattern input
            $('#keys-pattern').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    RedisManager.keysCursor = '0';
                    RedisManager.searchKeys(false);
                }
            });

            // Close modal
            $('#close-key-details').on('click', function() {
                $('#key-details-modal').fadeOut();
            });

            // Click outside modal to close
            $('#key-details-modal').on('click', function(e) {
                if ($(e.target).is('#key-details-modal')) {
                    $(this).fadeOut();
                }
            });

            // Delete key from modal
            $('#delete-key-modal').on('click', function() {
                if (RedisManager.currentKeyDetails) {
                    RedisManager.deleteKey(RedisManager.currentKeyDetails);
                }
            });
        },

        /**
         * Start Activity Auto Refresh
         */
        startActivityAutoRefresh: function() {
            if (this.activityInterval) {
                clearInterval(this.activityInterval);
            }
            this.activityInterval = setInterval(function() {
                if ($('#tab-activity').hasClass('active')) {
                    RedisManager.getActivity();
                }
            }, 10000);
        },

        /**
         * Stop Activity Auto Refresh
         */
        stopActivityAutoRefresh: function() {
            if (this.activityInterval) {
                clearInterval(this.activityInterval);
                this.activityInterval = null;
            }
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
         * Get Redis Activity
         */
        getActivity: function() {
            var $button = $('#refresh-activity');
            $button.addClass('loading');

            $.ajax({
                url: wpRedisManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_redis_manager_get_activity',
                    nonce: wpRedisManager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RedisManager.activityData = response.data;
                        RedisManager.renderActivityData();

                        // Start auto-refresh if enabled
                        if ($('#activity-autorefresh').is(':checked') && !RedisManager.activityInterval) {
                            RedisManager.startActivityAutoRefresh();
                        }
                    } else {
                        RedisManager.showNotice('error', response.data.message || 'Errore caricamento dati');
                    }
                },
                error: function() {
                    RedisManager.showNotice('error', 'Errore durante il caricamento dei dati attività');
                },
                complete: function() {
                    $button.removeClass('loading');
                }
            });
        },

        /**
         * Render Activity Data
         */
        renderActivityData: function() {
            var data = this.activityData;
            if (!data) return;

            // Server info
            if (data.server) {
                $('#act-redis-version').text(data.server.redis_version || '-');
                $('#act-redis-mode').text(data.server.redis_mode || '-');
                $('#act-redis-os').text(this.truncateText(data.server.os || '-', 30));
                $('#act-uptime').text(this.formatUptime(data.server.uptime_in_seconds));
                $('#act-port').text(data.server.tcp_port || '-');
            }

            // Memory info
            if (data.memory) {
                $('#act-mem-used').text(data.memory.used_memory_human || '-');
                $('#act-mem-peak').text(data.memory.used_memory_peak_human || '-');
                $('#act-mem-max').text(data.memory.maxmemory_human || 'Illimitata');
                $('#act-mem-frag').text(data.memory.mem_fragmentation_ratio || '-');
                $('#act-mem-lua').text(data.memory.used_memory_lua_human || '-');
            }

            // Clients info
            if (data.clients) {
                $('#act-clients-connected').text(data.clients.connected_clients || '0');
                $('#act-clients-blocked').text(data.clients.blocked_clients || '0');
                $('#act-clients-tracking').text(data.clients.tracking_clients || '0');
            }

            // Keyspace info
            this.renderKeyspace(data.keyspace);

            // Command stats
            this.renderCommandStats();

            // Slowlog
            this.renderSlowlog();
        },

        /**
         * Render Keyspace Info
         */
        renderKeyspace: function(keyspace) {
            var $container = $('#keyspace-info-list');
            $container.empty();

            if (!keyspace || Object.keys(keyspace).length === 0) {
                $container.html('<p class="empty-text">Nessun database con chiavi</p>');
                return;
            }

            var totalKeys = 0;
            var totalExpires = 0;

            for (var db in keyspace) {
                var info = keyspace[db];
                totalKeys += info.keys;
                totalExpires += info.expires;

                var avgTtl = info.avg_ttl > 0 ? this.formatDuration(info.avg_ttl) : 'N/A';

                $container.append(
                    '<div class="info-row">' +
                        '<span class="info-label db-label">' + db.toUpperCase() + ':</span>' +
                        '<span class="info-value">' +
                            info.keys.toLocaleString() + ' chiavi' +
                            (info.expires > 0 ? ' (' + info.expires + ' con TTL)' : '') +
                        '</span>' +
                    '</div>'
                );
            }

            // Add total
            $container.append(
                '<div class="info-row info-row-total">' +
                    '<span class="info-label">Totale:</span>' +
                    '<span class="info-value total-value">' + totalKeys.toLocaleString() + ' chiavi</span>' +
                '</div>'
            );
        },

        /**
         * Render Command Stats
         */
        renderCommandStats: function() {
            var data = this.activityData;
            if (!data || !data.commandstats) {
                return;
            }

            var $tbody = $('#command-stats-body');
            $tbody.empty();

            var filter = $('#cmd-filter').val().toUpperCase();
            var sortBy = $('#cmd-sort').val();
            var limit = parseInt($('#cmd-limit').val());

            // Convert to array and filter
            var commands = [];
            var maxCalls = 0;

            for (var cmd in data.commandstats) {
                if (filter && cmd.indexOf(filter) === -1) {
                    continue;
                }

                var stats = data.commandstats[cmd];
                commands.push({
                    name: cmd,
                    calls: stats.calls,
                    usec: stats.usec,
                    usec_per_call: stats.usec_per_call
                });

                if (stats.calls > maxCalls) {
                    maxCalls = stats.calls;
                }
            }

            // Sort
            commands.sort(function(a, b) {
                return b[sortBy] - a[sortBy];
            });

            // Limit
            if (limit > 0) {
                commands = commands.slice(0, limit);
            }

            if (commands.length === 0) {
                $tbody.html('<tr><td colspan="5" class="empty-text">Nessun comando trovato</td></tr>');
                return;
            }

            // Render
            commands.forEach(function(cmd) {
                var percentage = maxCalls > 0 ? (cmd.calls / maxCalls * 100) : 0;
                var cmdClass = RedisManager.getCommandClass(cmd.name);
                var timeMs = (cmd.usec / 1000).toFixed(2);

                $tbody.append(
                    '<tr>' +
                        '<td><span class="cmd-badge ' + cmdClass + '">' + cmd.name + '</span></td>' +
                        '<td class="num-cell">' + cmd.calls.toLocaleString() + '</td>' +
                        '<td class="num-cell">' + timeMs + '</td>' +
                        '<td class="num-cell">' + cmd.usec_per_call.toFixed(2) + '</td>' +
                        '<td class="bar-cell">' +
                            '<div class="bar-container">' +
                                '<div class="bar-fill ' + cmdClass + '" style="width: ' + percentage + '%"></div>' +
                            '</div>' +
                        '</td>' +
                    '</tr>'
                );
            });
        },

        /**
         * Get Command Class for styling
         */
        getCommandClass: function(cmd) {
            var readCmds = ['GET', 'MGET', 'HGET', 'HGETALL', 'HMGET', 'LRANGE', 'SMEMBERS', 'ZRANGE', 'SCAN', 'KEYS', 'EXISTS', 'TYPE', 'TTL'];
            var writeCmds = ['SET', 'MSET', 'HSET', 'HMSET', 'LPUSH', 'RPUSH', 'SADD', 'ZADD', 'DEL', 'UNLINK', 'EXPIRE', 'SETEX'];
            var infoCmds = ['INFO', 'DBSIZE', 'SLOWLOG', 'CONFIG', 'CLIENT', 'MEMORY'];

            if (readCmds.indexOf(cmd) !== -1) return 'cmd-read';
            if (writeCmds.indexOf(cmd) !== -1) return 'cmd-write';
            if (infoCmds.indexOf(cmd) !== -1) return 'cmd-info';
            return 'cmd-other';
        },

        /**
         * Render Slowlog
         */
        renderSlowlog: function() {
            var data = this.activityData;
            if (!data || !data.slowlog) {
                return;
            }

            var $tbody = $('#slowlog-body');
            var $empty = $('#slowlog-empty');
            var $table = $('#slowlog-table');

            $tbody.empty();

            var filter = $('#slowlog-filter').val().toUpperCase();
            var minDuration = parseInt($('#slowlog-min-duration').val()) || 0;

            // Filter entries
            var entries = data.slowlog.filter(function(entry) {
                if (filter && entry.command.toUpperCase().indexOf(filter) === -1) {
                    return false;
                }
                if (minDuration > 0 && entry.duration_us < minDuration) {
                    return false;
                }
                return true;
            });

            if (entries.length === 0) {
                $table.hide();
                $empty.show();
                return;
            }

            $table.show();
            $empty.hide();

            entries.forEach(function(entry) {
                var date = new Date(entry.timestamp * 1000);
                var dateStr = date.toLocaleDateString('it-IT') + ' ' + date.toLocaleTimeString('it-IT');
                var durationClass = entry.duration_us > 100000 ? 'duration-high' :
                                   (entry.duration_us > 10000 ? 'duration-medium' : 'duration-low');
                var cmdPreview = RedisManager.truncateText(entry.command, 60);

                $tbody.append(
                    '<tr>' +
                        '<td class="num-cell">' + entry.id + '</td>' +
                        '<td>' + dateStr + '</td>' +
                        '<td class="' + durationClass + '">' + RedisManager.formatMicroseconds(entry.duration_us) + '</td>' +
                        '<td class="cmd-cell" title="' + RedisManager.escapeHtml(entry.command) + '">' +
                            '<code>' + RedisManager.escapeHtml(cmdPreview) + '</code>' +
                        '</td>' +
                        '<td>' + (entry.client || '-') + '</td>' +
                    '</tr>'
                );
            });
        },

        /**
         * Search Keys
         */
        searchKeys: function(append) {
            var $button = append ? $('#load-more-keys') : $('#search-keys');
            $button.addClass('loading');

            var pattern = $('#keys-pattern').val() || '*';
            var typeFilter = $('#keys-type').val();
            var limit = parseInt($('#keys-limit').val()) || 100;

            $.ajax({
                url: wpRedisManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_redis_manager_get_keys',
                    nonce: wpRedisManager.nonce,
                    pattern: pattern,
                    type_filter: typeFilter,
                    limit: limit,
                    cursor: append ? RedisManager.keysCursor : '0'
                },
                success: function(response) {
                    if (response.success) {
                        RedisManager.keysCursor = response.data.cursor;
                        RedisManager.renderKeys(response.data.keys, append);

                        // Show/hide load more button
                        if (response.data.has_more) {
                            $('#load-more-keys').show();
                        } else {
                            $('#load-more-keys').hide();
                        }

                        // Update count
                        var currentCount = $('#keys-table-body tr').length;
                        if ($('#keys-table-body tr td.empty-text').length > 0) {
                            currentCount = 0;
                        }
                        $('#keys-count').text(currentCount + ' chiavi visualizzate');
                    } else {
                        RedisManager.showNotice('error', response.data.message || 'Errore ricerca chiavi');
                    }
                },
                error: function() {
                    RedisManager.showNotice('error', 'Errore durante la ricerca delle chiavi');
                },
                complete: function() {
                    $button.removeClass('loading');
                }
            });
        },

        /**
         * Render Keys
         */
        renderKeys: function(keys, append) {
            var $tbody = $('#keys-table-body');

            if (!append) {
                $tbody.empty();
            }

            if (keys.length === 0 && !append) {
                $tbody.html('<tr><td colspan="5" class="empty-text">Nessuna chiave trovata</td></tr>');
                return;
            }

            keys.forEach(function(key) {
                var ttlText = key.ttl === -1 ? 'Nessuno' :
                             (key.ttl === -2 ? 'Scaduta' : RedisManager.formatSeconds(key.ttl));
                var typeClass = 'type-' + key.type;

                $tbody.append(
                    '<tr data-key="' + RedisManager.escapeHtml(key.key) + '">' +
                        '<td class="key-cell">' +
                            '<code class="key-name" title="' + RedisManager.escapeHtml(key.key) + '">' +
                                RedisManager.truncateText(key.key, 50) +
                            '</code>' +
                        '</td>' +
                        '<td><span class="type-badge ' + typeClass + '">' + key.type + '</span></td>' +
                        '<td>' + ttlText + '</td>' +
                        '<td>' + key.memory + '</td>' +
                        '<td class="actions-cell">' +
                            '<button type="button" class="button button-small view-key-btn">Dettagli</button> ' +
                            '<button type="button" class="button button-small button-link-delete delete-key-btn">Elimina</button>' +
                        '</td>' +
                    '</tr>'
                );
            });

            // Bind events for new rows
            $tbody.find('.view-key-btn').off('click').on('click', function() {
                var key = $(this).closest('tr').data('key');
                RedisManager.viewKeyDetails(key);
            });

            $tbody.find('.delete-key-btn').off('click').on('click', function() {
                var key = $(this).closest('tr').data('key');
                if (confirm('Sei sicuro di voler eliminare la chiave "' + key + '"?')) {
                    RedisManager.deleteKey(key);
                }
            });
        },

        /**
         * View Key Details
         */
        viewKeyDetails: function(key) {
            RedisManager.currentKeyDetails = key;

            // Show modal with loading state
            $('#detail-key').text(key);
            $('#detail-type').text('Caricamento...');
            $('#detail-ttl').text('-');
            $('#detail-memory').text('-');
            $('#detail-encoding').text('-');
            $('#detail-length').text('-');
            $('#detail-value').text('Caricamento...');
            $('#key-details-modal').fadeIn();

            $.ajax({
                url: wpRedisManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_redis_manager_get_key_details',
                    nonce: wpRedisManager.nonce,
                    key: key
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var typeClass = 'type-' + data.type;

                        $('#detail-type').html('<span class="type-badge ' + typeClass + '">' + data.type + '</span>');
                        $('#detail-ttl').text(data.ttl === -1 ? 'Nessuno (persistente)' : RedisManager.formatSeconds(data.ttl));
                        $('#detail-memory').text(data.memory);
                        $('#detail-encoding').text(data.encoding);
                        $('#detail-length').text(data.length.toLocaleString() + ' elementi');

                        // Format value based on type
                        var valueHtml = RedisManager.formatKeyValue(data.value, data.type);
                        $('#detail-value').html(valueHtml);
                    } else {
                        $('#detail-value').text('Errore: ' + (response.data.message || 'Impossibile caricare'));
                    }
                },
                error: function() {
                    $('#detail-value').text('Errore durante il caricamento dei dettagli');
                }
            });
        },

        /**
         * Format Key Value for display
         */
        formatKeyValue: function(value, type) {
            if (value === null || value === undefined) {
                return '<span class="value-null">null</span>';
            }

            if (typeof value === 'string') {
                return '<code class="value-string">' + this.escapeHtml(value) + '</code>';
            }

            if (Array.isArray(value)) {
                if (value.length === 0) {
                    return '<span class="value-empty">(vuoto)</span>';
                }

                // For zset with scores
                if (type === 'zset' && value[0] && typeof value[0].score !== 'undefined') {
                    var html = '<table class="value-table"><thead><tr><th>Membro</th><th>Score</th></tr></thead><tbody>';
                    value.forEach(function(item) {
                        html += '<tr><td><code>' + RedisManager.escapeHtml(String(item.member)) + '</code></td>';
                        html += '<td>' + item.score + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    return html;
                }

                // For list/set
                var html = '<ol class="value-list">';
                value.forEach(function(item) {
                    html += '<li><code>' + RedisManager.escapeHtml(String(item)) + '</code></li>';
                });
                html += '</ol>';
                return html;
            }

            if (typeof value === 'object') {
                // For hash
                var html = '<table class="value-table"><thead><tr><th>Campo</th><th>Valore</th></tr></thead><tbody>';
                for (var field in value) {
                    html += '<tr><td><strong>' + RedisManager.escapeHtml(field) + '</strong></td>';
                    html += '<td><code>' + RedisManager.escapeHtml(String(value[field])) + '</code></td></tr>';
                }
                html += '</tbody></table>';
                return html;
            }

            return '<code>' + this.escapeHtml(String(value)) + '</code>';
        },

        /**
         * Delete Key
         */
        deleteKey: function(key) {
            $.ajax({
                url: wpRedisManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wp_redis_manager_delete_key',
                    nonce: wpRedisManager.nonce,
                    key: key
                },
                success: function(response) {
                    if (response.success) {
                        RedisManager.showNotice('success', response.data.message);

                        // Remove row from table
                        $('#keys-table-body tr[data-key="' + key + '"]').fadeOut(function() {
                            $(this).remove();
                        });

                        // Close modal if open
                        $('#key-details-modal').fadeOut();
                        RedisManager.currentKeyDetails = null;
                    } else {
                        RedisManager.showNotice('error', response.data.message || 'Errore eliminazione');
                    }
                },
                error: function() {
                    RedisManager.showNotice('error', 'Errore durante l\'eliminazione della chiave');
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
        },

        /**
         * Utility: Format Uptime
         */
        formatUptime: function(seconds) {
            if (!seconds) return '-';

            var days = Math.floor(seconds / 86400);
            var hours = Math.floor((seconds % 86400) / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);

            var parts = [];
            if (days > 0) parts.push(days + 'g');
            if (hours > 0) parts.push(hours + 'h');
            if (minutes > 0) parts.push(minutes + 'm');

            return parts.length > 0 ? parts.join(' ') : '< 1m';
        },

        /**
         * Utility: Format Duration (milliseconds from microseconds TTL)
         */
        formatDuration: function(ms) {
            if (ms < 1000) return ms + 'ms';
            if (ms < 60000) return (ms / 1000).toFixed(1) + 's';
            if (ms < 3600000) return Math.floor(ms / 60000) + 'm';
            return Math.floor(ms / 3600000) + 'h';
        },

        /**
         * Utility: Format Seconds to human readable
         */
        formatSeconds: function(seconds) {
            if (seconds < 60) return seconds + 's';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
            return Math.floor(seconds / 86400) + 'g ' + Math.floor((seconds % 86400) / 3600) + 'h';
        },

        /**
         * Utility: Format Microseconds
         */
        formatMicroseconds: function(us) {
            if (us < 1000) return us + ' μs';
            if (us < 1000000) return (us / 1000).toFixed(2) + ' ms';
            return (us / 1000000).toFixed(2) + ' s';
        },

        /**
         * Utility: Truncate Text
         */
        truncateText: function(text, maxLength) {
            if (!text) return '';
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength) + '...';
        },

        /**
         * Utility: Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RedisManager.init();
    });

})(jQuery);
