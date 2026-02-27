/**
 * Open Redis Manager - Admin JavaScript
 *
 * Handles all admin interface interactions: tabs, AJAX calls,
 * activity monitoring, key exploration, and preset management.
 *
 * @package OpenRedisManager
 */
(function ($) {
    'use strict';

    var SRC = {
        autoRefreshInterval: null,
        statsInterval: null,
        currentCursor: '0',
        currentKeyForModal: '',
        activityLoaded: false,

        /**
         * Initialize the admin interface.
         */
        init: function () {
            this.initTabs();
            this.initButtons();
            this.initPresets();
            this.initKeysExplorer();
            this.initModal();
            this.testConnection();
            this.getStats();

            // Auto-refresh stats every 30s
            this.statsInterval = setInterval(function () {
                SRC.getStats();
            }, 30000);
        },

        // =====================================================================
        // Tabs
        // =====================================================================

        initTabs: function () {
            $('.src-tabs .nav-tab').on('click', function (e) {
                e.preventDefault();
                var tab = $(this).data('tab');

                $('.src-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                $('.src-tab-content').removeClass('active');
                $('#' + tab).addClass('active');

                // Lazy load activity on first visit
                if (tab === 'tab-activity' && !SRC.activityLoaded) {
                    SRC.getActivity();
                    SRC.activityLoaded = true;
                }
            });
        },

        // =====================================================================
        // Buttons
        // =====================================================================

        initButtons: function () {
            $('#src-test-connection').on('click', function () {
                SRC.testConnection();
            });

            $('#src-flush-cache').on('click', function () {
                if (confirm(srcRedis.i18n.confirmFlush)) {
                    SRC.flushCache();
                }
            });

            $('#src-refresh-stats').on('click', function () {
                SRC.getStats();
            });

            $('#src-refresh-activity').on('click', function () {
                SRC.getActivity();
            });

            $('#src-auto-refresh-toggle').on('change', function () {
                if ($(this).is(':checked')) {
                    SRC.autoRefreshInterval = setInterval(function () {
                        SRC.getActivity();
                    }, 10000);
                } else {
                    clearInterval(SRC.autoRefreshInterval);
                    SRC.autoRefreshInterval = null;
                }
            });

            // Drop-in management
            $(document).on('click', '#src-install-dropin', function () {
                if (confirm(srcRedis.i18n.confirmInstall)) {
                    SRC.installDropin();
                }
            });

            $(document).on('click', '#src-uninstall-dropin', function () {
                if (confirm(srcRedis.i18n.confirmUninstall)) {
                    SRC.uninstallDropin();
                }
            });

            // Settings save (via AJAX)
            $('#src-save-settings').on('click', function () {
                SRC.saveSettings();
            });

            // wp-config.php management
            $('#src-save-config').on('click', function () {
                SRC.saveConfig();
            });

            $('#src-test-config').on('click', function () {
                SRC.testConfig();
            });

            $('#src-remove-config').on('click', function () {
                if (confirm('Rimuovere la configurazione Redis da wp-config.php?')) {
                    SRC.removeConfig();
                }
            });

            // Command stats filters
            $('#src-cmd-filter, #src-cmd-sort, #src-cmd-limit').on('input change', function () {
                SRC.filterCommands();
            });

            // Slowlog filters
            $('#src-slowlog-filter, #src-slowlog-min-duration').on('input change', function () {
                SRC.filterSlowlog();
            });
        },

        // =====================================================================
        // Presets
        // =====================================================================

        initPresets: function () {
            $('.src-preset-card').on('click', function () {
                var preset = $(this).data('preset');
                if (!srcPresets || !srcPresets[preset]) return;

                var config = srcPresets[preset];

                if (config.non_persistent_groups !== undefined) {
                    SRC.mergeTextareaLines('#src-non-persistent', config.non_persistent_groups);
                }
                if (config.redis_hash_groups !== undefined) {
                    SRC.mergeTextareaLines('#src-hash-groups', config.redis_hash_groups);
                }
                if (config.global_groups !== undefined) {
                    SRC.mergeTextareaLines('#src-global-groups', config.global_groups);
                }
                if (config.custom_ttl !== undefined) {
                    SRC.mergeTextareaTtl('#src-custom-ttl', config.custom_ttl);
                }

                // Highlight selected
                $('.src-preset-card').removeClass('src-preset-active');
                $(this).addClass('src-preset-active');

                // Switch to groups tab
                $('.src-tabs .nav-tab').removeClass('nav-tab-active');
                $('.src-tabs .nav-tab[data-tab="tab-groups"]').addClass('nav-tab-active');
                $('.src-tab-content').removeClass('active');
                $('#tab-groups').addClass('active');

                SRC.showNotice('Preset applicato (unito ai gruppi esistenti). Salva le impostazioni per attivare.', 'success');
            });
        },

        // =====================================================================
        // Keys Explorer
        // =====================================================================

        initKeysExplorer: function () {
            $('#src-search-keys').on('click', function () {
                SRC.currentCursor = '0';
                SRC.searchKeys(false);
            });

            $('#src-key-pattern').on('keypress', function (e) {
                if (e.which === 13) {
                    e.preventDefault();
                    SRC.currentCursor = '0';
                    SRC.searchKeys(false);
                }
            });

            $('#src-keys-load-more').on('click', function () {
                SRC.searchKeys(true);
            });
        },

        searchKeys: function (append) {
            var $btn = $('#src-search-keys');
            $btn.prop('disabled', true).text('Ricerca...');

            $.post(srcRedis.ajaxUrl, {
                action: 'src_get_keys',
                nonce: srcRedis.nonce,
                pattern: $('#src-key-pattern').val() || '*',
                type: $('#src-key-type').val(),
                limit: $('#src-key-limit').val(),
                cursor: SRC.currentCursor
            }, function (response) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Cerca');

                if (!response.success) {
                    SRC.showNotice(response.data || srcRedis.i18n.error, 'error');
                    return;
                }

                var data = response.data;
                var $tbody = $('#src-keys-tbody');

                if (!append) {
                    $tbody.empty();
                }

                if (data.keys.length === 0 && !append) {
                    $tbody.html('<tr><td colspan="5" class="src-empty">' + srcRedis.i18n.noKeys + '</td></tr>');
                    $('#src-keys-pagination').hide();
                    return;
                }

                data.keys.forEach(function (key) {
                    var ttlText = key.ttl === -1 ? '<span class="src-ttl-permanent">Permanente</span>' :
                        key.ttl === -2 ? '<span class="src-ttl-expired">Scaduto</span>' :
                            '<span class="src-ttl-value">' + SRC.formatSeconds(key.ttl) + '</span>';

                    var memoryText = key.memory > 0 ? SRC.formatBytes(key.memory) : '-';

                    $tbody.append(
                        '<tr>' +
                        '<td class="src-key-name" title="' + SRC.escapeHtml(key.key) + '">' +
                        SRC.truncateText(SRC.escapeHtml(key.key), 60) + '</td>' +
                        '<td><span class="src-type-badge src-type-' + key.type + '">' + key.type + '</span></td>' +
                        '<td>' + ttlText + '</td>' +
                        '<td>' + memoryText + '</td>' +
                        '<td>' +
                        '<button type="button" class="button button-small src-btn-view-key" data-key="' +
                        SRC.escapeHtml(key.key) + '">Dettagli</button> ' +
                        '<button type="button" class="button button-small button-link-delete src-btn-delete-key" data-key="' +
                        SRC.escapeHtml(key.key) + '">Elimina</button>' +
                        '</td>' +
                        '</tr>'
                    );
                });

                // Pagination
                SRC.currentCursor = data.cursor;
                if (data.cursor !== '0' && data.keys.length > 0) {
                    $('#src-keys-pagination').show();
                    $('#src-keys-count').text(data.total + ' chiavi caricate');
                } else {
                    $('#src-keys-pagination').hide();
                }

                // Bind key actions
                $tbody.find('.src-btn-view-key').off('click').on('click', function () {
                    SRC.viewKeyDetails($(this).data('key'));
                });

                $tbody.find('.src-btn-delete-key').off('click').on('click', function () {
                    if (confirm(srcRedis.i18n.confirmDelete)) {
                        SRC.deleteKey($(this).data('key'), $(this).closest('tr'));
                    }
                });
            }).fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Cerca');
                SRC.showNotice(srcRedis.i18n.error, 'error');
            });
        },

        viewKeyDetails: function (key) {
            SRC.currentKeyForModal = key;
            $('#src-modal-title').text(key);
            $('#src-modal-body').html('<div class="src-loading">Caricamento...</div>');
            $('#src-key-modal').show();

            $.post(srcRedis.ajaxUrl, {
                action: 'src_get_key_details',
                nonce: srcRedis.nonce,
                key: key
            }, function (response) {
                if (!response.success) {
                    $('#src-modal-body').html('<div class="src-error">' + (response.data || srcRedis.i18n.error) + '</div>');
                    return;
                }

                var d = response.data;
                var html = '<div class="src-key-meta">';
                html += '<div class="src-meta-item"><strong>Tipo:</strong> <span class="src-type-badge src-type-' + d.type + '">' + d.type + '</span></div>';
                html += '<div class="src-meta-item"><strong>TTL:</strong> ' + (d.ttl === -1 ? 'Permanente' : d.ttl === -2 ? 'Scaduto' : SRC.formatSeconds(d.ttl)) + '</div>';
                html += '<div class="src-meta-item"><strong>Memoria:</strong> ' + (d.memory > 0 ? SRC.formatBytes(d.memory) : 'N/A') + '</div>';
                html += '<div class="src-meta-item"><strong>Encoding:</strong> ' + (d.encoding || 'N/A') + '</div>';
                html += '<div class="src-meta-item"><strong>Lunghezza:</strong> ' + d.length + '</div>';
                html += '</div>';

                html += '<div class="src-key-value">';
                html += '<h4>Valore</h4>';
                html += SRC.renderValue(d.type, d.value);
                html += '</div>';

                $('#src-modal-body').html(html);
            });
        },

        renderValue: function (type, value) {
            if (value === null || value === undefined) {
                return '<pre class="src-value-display">null</pre>';
            }

            switch (type) {
                case 'string':
                    if (typeof value === 'object' && value.type) {
                        var content = value.type === 'json'
                            ? JSON.stringify(value.data, null, 2)
                            : (typeof value.data === 'object' ? JSON.stringify(value.data, null, 2) : String(value.data));

                        return '<div class="src-value-type">' + value.type + '</div>' +
                            '<pre class="src-value-display">' + SRC.escapeHtml(content) + '</pre>';
                    }
                    return '<pre class="src-value-display">' + SRC.escapeHtml(String(value)) + '</pre>';

                case 'list':
                    if (!Array.isArray(value) || value.length === 0) {
                        return '<p class="src-empty-value">Lista vuota</p>';
                    }
                    var html = '<ol class="src-list-display">';
                    value.forEach(function (item) {
                        html += '<li>' + SRC.escapeHtml(String(item)) + '</li>';
                    });
                    html += '</ol>';
                    return html;

                case 'set':
                    if (!Array.isArray(value) || value.length === 0) {
                        return '<p class="src-empty-value">Set vuoto</p>';
                    }
                    var html = '<ul class="src-set-display">';
                    value.forEach(function (item) {
                        html += '<li>' + SRC.escapeHtml(String(item)) + '</li>';
                    });
                    html += '</ul>';
                    return html;

                case 'zset':
                    if (typeof value !== 'object' || Object.keys(value).length === 0) {
                        return '<p class="src-empty-value">Sorted set vuoto</p>';
                    }
                    var html = '<table class="src-zset-table"><thead><tr><th>Membro</th><th>Score</th></tr></thead><tbody>';
                    for (var member in value) {
                        html += '<tr><td>' + SRC.escapeHtml(member) + '</td><td>' + value[member] + '</td></tr>';
                    }
                    html += '</tbody></table>';
                    return html;

                case 'hash':
                    if (typeof value !== 'object' || Object.keys(value).length === 0) {
                        return '<p class="src-empty-value">Hash vuoto</p>';
                    }
                    var html = '<table class="src-hash-table"><thead><tr><th>Campo</th><th>Valore</th></tr></thead><tbody>';
                    for (var field in value) {
                        html += '<tr><td>' + SRC.escapeHtml(field) + '</td><td class="src-hash-value">' +
                            SRC.escapeHtml(SRC.truncateText(String(value[field]), 200)) + '</td></tr>';
                    }
                    html += '</tbody></table>';
                    return html;

                case 'stream':
                    if (typeof value !== 'object' || Object.keys(value).length === 0) {
                        return '<p class="src-empty-value">Stream vuoto</p>';
                    }
                    return '<pre class="src-value-display">' + SRC.escapeHtml(JSON.stringify(value, null, 2)) + '</pre>';

                default:
                    return '<pre class="src-value-display">' + SRC.escapeHtml(JSON.stringify(value, null, 2)) + '</pre>';
            }
        },

        deleteKey: function (key, $row) {
            $.post(srcRedis.ajaxUrl, {
                action: 'src_delete_key',
                nonce: srcRedis.nonce,
                key: key
            }, function (response) {
                if (response.success) {
                    if ($row) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                    }
                    SRC.showNotice(response.data.message, 'success');
                } else {
                    SRC.showNotice(response.data || srcRedis.i18n.error, 'error');
                }
            });
        },

        // =====================================================================
        // Modal
        // =====================================================================

        initModal: function () {
            $('#src-modal-close, #src-modal-close-btn, .src-modal-overlay').on('click', function () {
                $('#src-key-modal').hide();
            });

            $('#src-modal-delete').on('click', function () {
                if (SRC.currentKeyForModal && confirm(srcRedis.i18n.confirmDelete)) {
                    SRC.deleteKey(SRC.currentKeyForModal);
                    $('#src-key-modal').hide();
                    // Remove from table
                    $('.src-btn-view-key[data-key="' + SRC.currentKeyForModal + '"]').closest('tr').fadeOut(300, function () {
                        $(this).remove();
                    });
                }
            });

            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    $('#src-key-modal').hide();
                }
            });
        },

        // =====================================================================
        // AJAX: Connection & Stats
        // =====================================================================

        testConnection: function () {
            var $btn = $('#src-test-connection');
            $btn.prop('disabled', true);

            $.post(srcRedis.ajaxUrl, {
                action: 'src_test_connection',
                nonce: srcRedis.nonce
            }, function (response) {
                $btn.prop('disabled', false);

                var $dot = $('#src-status-dot');
                var $text = $('#src-status-text');
                var $info = $('#src-status-info');

                if (response.success) {
                    var d = response.data;
                    $dot.removeClass('src-dot-disconnected').addClass('src-dot-connected');
                    $text.text(srcRedis.i18n.connected);

                    var infoHtml = '<span>Redis ' + SRC.escapeHtml(d.version) + '</span>';
                    infoHtml += '<span>Memoria: ' + SRC.escapeHtml(d.memory) + '</span>';
                    infoHtml += '<span>Uptime: ' + SRC.formatUptime(d.uptime) + '</span>';
                    if (d.database !== undefined) {
                        infoHtml += '<span>DB: ' + d.database + '</span>';
                    }
                    if (d.prefix) {
                        infoHtml += '<span>Prefisso: ' + SRC.escapeHtml(d.prefix) + '</span>';
                    }
                    $info.html(infoHtml);

                    if (!d.dropin) {
                        SRC.showNotice(d.message, 'warning');
                    }
                } else {
                    $dot.removeClass('src-dot-connected').addClass('src-dot-disconnected');
                    $text.text(srcRedis.i18n.disconnected);
                    $info.html('<span class="src-error-text">' +
                        SRC.escapeHtml(response.data.message || srcRedis.i18n.error) + '</span>');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $('#src-status-dot').removeClass('src-dot-connected').addClass('src-dot-disconnected');
                $('#src-status-text').text(srcRedis.i18n.disconnected);
            });
        },

        getStats: function () {
            $.post(srcRedis.ajaxUrl, {
                action: 'src_get_stats',
                nonce: srcRedis.nonce
            }, function (response) {
                if (response.success) {
                    var d = response.data;
                    $('#src-stat-hits').text(SRC.formatNumber(d.hits));
                    $('#src-stat-misses').text(SRC.formatNumber(d.misses));
                    $('#src-stat-rate').text(d.hit_rate + '%')
                        .removeClass('src-rate-good src-rate-medium src-rate-bad')
                        .addClass(d.hit_rate >= 80 ? 'src-rate-good' : d.hit_rate >= 50 ? 'src-rate-medium' : 'src-rate-bad');
                    $('#src-stat-calls').text(SRC.formatNumber(d.redis_calls));
                }
            });
        },

        flushCache: function () {
            var $btn = $('#src-flush-cache');
            $btn.prop('disabled', true);

            $.post(srcRedis.ajaxUrl, {
                action: 'src_flush_cache',
                nonce: srcRedis.nonce
            }, function (response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    SRC.showNotice(response.data.message, 'success');
                    SRC.getStats();
                } else {
                    SRC.showNotice(response.data || srcRedis.i18n.error, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
            });
        },

        // =====================================================================
        // AJAX: Activity Monitor
        // =====================================================================

        _allCommandData: [],
        _allSlowlogData: [],

        getActivity: function () {
            $.post(srcRedis.ajaxUrl, {
                action: 'src_get_activity',
                nonce: srcRedis.nonce
            }, function (response) {
                if (!response.success) {
                    SRC.showNotice(response.data || srcRedis.i18n.error, 'error');
                    return;
                }

                var d = response.data;

                // Server info
                var serverHtml = '';
                if (d.server) {
                    serverHtml += '<div class="src-info-row"><span>Versione:</span><strong>' + SRC.escapeHtml(d.server.redis_version) + '</strong></div>';
                    serverHtml += '<div class="src-info-row"><span>Modalit&agrave;:</span><strong>' + SRC.escapeHtml(d.server.redis_mode) + '</strong></div>';
                    serverHtml += '<div class="src-info-row"><span>OS:</span><strong>' + SRC.escapeHtml(d.server.os) + '</strong></div>';
                    serverHtml += '<div class="src-info-row"><span>Uptime:</span><strong>' + SRC.formatUptime(d.server.uptime) + '</strong></div>';
                    serverHtml += '<div class="src-info-row"><span>Porta:</span><strong>' + d.server.tcp_port + '</strong></div>';
                    serverHtml += '<div class="src-info-row"><span>PID:</span><strong>' + d.server.process_id + '</strong></div>';
                }
                $('#src-server-info').html(serverHtml || '<span class="src-no-data">Non disponibile</span>');

                // Memory info
                var memHtml = '';
                if (d.memory) {
                    memHtml += '<div class="src-info-row"><span>In uso:</span><strong class="src-value-highlight">' + SRC.escapeHtml(d.memory.used_memory_human) + '</strong></div>';
                    memHtml += '<div class="src-info-row"><span>Picco:</span><strong>' + SRC.escapeHtml(d.memory.used_memory_peak_human) + '</strong></div>';
                    memHtml += '<div class="src-info-row"><span>Massimo:</span><strong>' + SRC.escapeHtml(d.memory.maxmemory_human) + '</strong></div>';
                    memHtml += '<div class="src-info-row"><span>Frammentazione:</span><strong>' + d.memory.mem_fragmentation_ratio + '</strong></div>';
                    memHtml += '<div class="src-info-row"><span>Lua:</span><strong>' + SRC.escapeHtml(d.memory.used_memory_lua_human) + '</strong></div>';
                }
                $('#src-memory-info').html(memHtml || '<span class="src-no-data">Non disponibile</span>');

                // Clients info
                var clientHtml = '';
                if (d.clients) {
                    clientHtml += '<div class="src-info-row"><span>Connessi:</span><strong class="src-value-highlight">' + d.clients.connected_clients + '</strong></div>';
                    clientHtml += '<div class="src-info-row"><span>Bloccati:</span><strong>' + d.clients.blocked_clients + '</strong></div>';
                }
                $('#src-clients-info').html(clientHtml || '<span class="src-no-data">Non disponibile</span>');

                // Keyspace
                var ksHtml = '';
                if (d.keyspace && Object.keys(d.keyspace).length > 0) {
                    for (var db in d.keyspace) {
                        ksHtml += '<div class="src-info-row"><span>' + SRC.escapeHtml(db) + ':</span><strong>' +
                            SRC.escapeHtml(d.keyspace[db]) + '</strong></div>';
                    }
                } else {
                    ksHtml = '<span class="src-no-data">Nessun database attivo</span>';
                }
                $('#src-keyspace-info').html(ksHtml);

                // Command stats
                SRC._allCommandData = d.commands || [];
                SRC.filterCommands();

                // Slowlog
                SRC._allSlowlogData = d.slowlog || [];
                SRC.filterSlowlog();
            });
        },

        filterCommands: function () {
            var filter = ($('#src-cmd-filter').val() || '').toLowerCase();
            var sortBy = $('#src-cmd-sort').val() || 'calls';
            var limit = parseInt($('#src-cmd-limit').val()) || 0;

            var commands = SRC._allCommandData.slice();

            // Filter
            if (filter) {
                commands = commands.filter(function (c) {
                    return c.command.toLowerCase().indexOf(filter) !== -1;
                });
            }

            // Sort
            commands.sort(function (a, b) {
                return b[sortBy] - a[sortBy];
            });

            // Limit
            if (limit > 0) {
                commands = commands.slice(0, limit);
            }

            // Find max for bar chart
            var maxCalls = commands.length > 0 ? commands[0][sortBy] : 1;

            var html = '';
            if (commands.length === 0) {
                html = '<tr><td colspan="5" class="src-empty">Nessun dato disponibile</td></tr>';
            } else {
                commands.forEach(function (cmd) {
                    var barWidth = maxCalls > 0 ? Math.round((cmd[sortBy] / maxCalls) * 100) : 0;
                    var cmdClass = SRC.getCommandClass(cmd.command);

                    html += '<tr>';
                    html += '<td><span class="src-cmd-badge ' + cmdClass + '">' + SRC.escapeHtml(cmd.command) + '</span></td>';
                    html += '<td>' + SRC.formatNumber(cmd.calls) + '</td>';
                    html += '<td>' + SRC.formatMicroseconds(cmd.usec) + '</td>';
                    html += '<td>' + SRC.formatMicroseconds(cmd.usec_per_call) + '</td>';
                    html += '<td><div class="src-bar" style="width:' + barWidth + '%"></div></td>';
                    html += '</tr>';
                });
            }

            $('#src-command-tbody').html(html);
        },

        filterSlowlog: function () {
            var filter = ($('#src-slowlog-filter').val() || '').toLowerCase();
            var minDuration = parseInt($('#src-slowlog-min-duration').val()) || 0;

            var entries = SRC._allSlowlogData.slice();

            // Filter by command
            if (filter) {
                entries = entries.filter(function (e) {
                    return e.command.toLowerCase().indexOf(filter) !== -1;
                });
            }

            // Filter by duration
            if (minDuration > 0) {
                entries = entries.filter(function (e) {
                    return e.duration >= minDuration;
                });
            }

            var html = '';
            if (entries.length === 0) {
                html = '<tr><td colspan="5" class="src-empty src-empty-good">Nessuna query lenta registrata</td></tr>';
            } else {
                entries.forEach(function (entry) {
                    var durationMs = entry.duration / 1000;
                    var durationClass = durationMs > 100 ? 'src-duration-slow' :
                        durationMs > 10 ? 'src-duration-medium' : 'src-duration-fast';

                    var date = entry.timestamp > 0 ? new Date(entry.timestamp * 1000).toLocaleString() : '-';

                    html += '<tr>';
                    html += '<td>' + entry.id + '</td>';
                    html += '<td>' + date + '</td>';
                    html += '<td class="' + durationClass + '">' + SRC.formatMicroseconds(entry.duration) + '</td>';
                    html += '<td class="src-slowlog-cmd" title="' + SRC.escapeHtml(entry.command) + '">' +
                        SRC.escapeHtml(SRC.truncateText(entry.command, 80)) + '</td>';
                    html += '<td>' + SRC.escapeHtml(entry.client || '-') + '</td>';
                    html += '</tr>';
                });
            }

            $('#src-slowlog-tbody').html(html);
        },

        // =====================================================================
        // AJAX: Drop-in Management
        // =====================================================================

        installDropin: function () {
            $.post(srcRedis.ajaxUrl, {
                action: 'src_install_dropin',
                nonce: srcRedis.nonce
            }, function (response) {
                if (response.success) {
                    SRC.showNotice(response.data.message, 'success');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    SRC.showNotice(response.data.message || srcRedis.i18n.error, 'error');
                }
            });
        },

        uninstallDropin: function () {
            $.post(srcRedis.ajaxUrl, {
                action: 'src_uninstall_dropin',
                nonce: srcRedis.nonce
            }, function (response) {
                if (response.success) {
                    SRC.showNotice(response.data.message, 'success');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    SRC.showNotice(response.data.message || srcRedis.i18n.error, 'error');
                }
            });
        },

        // =====================================================================
        // AJAX: Settings Save
        // =====================================================================

        /**
         * Save plugin settings (groups, TTL) via AJAX, then reload page.
         */
        saveSettings: function () {
            var $btn = $('#src-save-settings');
            var $status = $('#src-settings-status');
            $btn.prop('disabled', true).text('Salvataggio...');
            $status.text('').removeClass('src-status-ok src-status-err');

            var settings = {
                enabled: $('#src-enabled').is(':checked') ? '1' : '',
                non_persistent_groups: $('#src-non-persistent').val() || '',
                redis_hash_groups: $('#src-hash-groups').val() || '',
                global_groups: $('#src-global-groups').val() || '',
                custom_ttl: $('#src-custom-ttl').val() || ''
            };

            $.ajax({
                url: srcRedis.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'src_save_settings',
                    nonce: srcRedis.nonce,
                    settings: settings
                },
                timeout: 15000
            }).done(function (response) {
                if (response && response.success) {
                    // Reload page so values are read fresh from DB.
                    window.location.href = window.location.pathname +
                        window.location.search.replace(/&settings-saved=\d+/, '') +
                        '&settings-saved=1';
                } else {
                    $btn.prop('disabled', false).text('Salva Impostazioni');
                    var msg = (response && response.data && response.data.message)
                        ? response.data.message : 'Errore durante il salvataggio.';
                    $status.text(msg).addClass('src-status-err');
                    SRC.showNotice(msg, 'error');
                }
            }).fail(function (xhr, status, error) {
                $btn.prop('disabled', false).text('Salva Impostazioni');
                var msg = 'Errore di rete: ' + (error || status);
                $status.text(msg).addClass('src-status-err');
                SRC.showNotice(msg, 'error');
            });
        },

        // =====================================================================
        // AJAX: wp-config.php Management
        // =====================================================================

        /**
         * Collect config form values into an object.
         */
        getConfigValues: function () {
            var config = {};
            $('#src-config-form').find('input, select').each(function () {
                var $el = $(this);
                var name = $el.attr('name');
                if (!name) return;

                if ($el.attr('type') === 'checkbox') {
                    config[name] = $el.is(':checked') ? '1' : '';
                } else {
                    config[name] = $el.val();
                }
            });
            return config;
        },

        /**
         * Validate config form values before saving.
         */
        validateConfig: function () {
            var host = $('#cfg-host').val().trim();
            if (!host) {
                SRC.showNotice('Host Redis obbligatorio.', 'error');
                $('#cfg-host').focus();
                return false;
            }
            if (host.indexOf('@') !== -1) {
                SRC.showNotice('Il campo Host contiene un indirizzo email. Inserisci un IP o hostname (es. 127.0.0.1).', 'error');
                $('#cfg-host').val('127.0.0.1').focus();
                return false;
            }
            return true;
        },

        /**
         * Save configuration to wp-config.php.
         */
        saveConfig: function () {
            if (!SRC.validateConfig()) return;

            var $btn = $('#src-save-config');
            var $status = $('#src-config-status');
            $btn.prop('disabled', true);
            $status.text('Salvataggio...').removeClass('src-status-ok src-status-err');

            $.post(srcRedis.ajaxUrl, {
                action: 'src_save_config',
                nonce: srcRedis.nonce,
                config: SRC.getConfigValues()
            }, function (response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $status.text(response.data.message).addClass('src-status-ok');
                    SRC.showNotice(response.data.message, 'success');
                } else {
                    $status.text(response.data.message || srcRedis.i18n.error).addClass('src-status-err');
                    SRC.showNotice(response.data.message || srcRedis.i18n.error, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $status.text(srcRedis.i18n.error).addClass('src-status-err');
            });
        },

        /**
         * Test connection with current form values (before saving).
         */
        testConfig: function () {
            if (!SRC.validateConfig()) return;

            var $btn = $('#src-test-config');
            var $status = $('#src-config-status');
            $btn.prop('disabled', true);
            $status.text('Test connessione...').removeClass('src-status-ok src-status-err');

            $.post(srcRedis.ajaxUrl, {
                action: 'src_test_config',
                nonce: srcRedis.nonce,
                config: SRC.getConfigValues()
            }, function (response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $status.text(response.data.message).addClass('src-status-ok');
                    SRC.showNotice(response.data.message, 'success');
                } else {
                    $status.text(response.data.message || srcRedis.i18n.error).addClass('src-status-err');
                    SRC.showNotice(response.data.message || srcRedis.i18n.error, 'error');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $status.text(srcRedis.i18n.error).addClass('src-status-err');
            });
        },

        /**
         * Remove SRC constants from wp-config.php.
         */
        removeConfig: function () {
            var $status = $('#src-config-status');
            $status.text('Rimozione...').removeClass('src-status-ok src-status-err');

            $.post(srcRedis.ajaxUrl, {
                action: 'src_remove_config',
                nonce: srcRedis.nonce
            }, function (response) {
                if (response.success) {
                    $status.text(response.data.message).addClass('src-status-ok');
                    SRC.showNotice(response.data.message, 'success');
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    $status.text(response.data.message || srcRedis.i18n.error).addClass('src-status-err');
                }
            });
        },

        // =====================================================================
        // Utility Functions
        // =====================================================================

        escapeHtml: function (text) {
            if (typeof text !== 'string') return String(text);
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        },

        formatNumber: function (num) {
            if (num === undefined || num === null) return '0';
            return parseInt(num).toLocaleString('it-IT');
        },

        formatBytes: function (bytes) {
            if (bytes === 0) return '0 B';
            var k = 1024;
            var sizes = ['B', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        },

        formatUptime: function (seconds) {
            if (!seconds) return '0s';
            seconds = parseInt(seconds);
            var d = Math.floor(seconds / 86400);
            var h = Math.floor((seconds % 86400) / 3600);
            var m = Math.floor((seconds % 3600) / 60);

            var parts = [];
            if (d > 0) parts.push(d + 'g');
            if (h > 0) parts.push(h + 'h');
            if (m > 0) parts.push(m + 'm');
            return parts.length > 0 ? parts.join(' ') : '< 1m';
        },

        formatSeconds: function (seconds) {
            if (seconds <= 0) return '-';
            if (seconds < 60) return seconds + 's';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ' + (seconds % 60) + 's';
            var h = Math.floor(seconds / 3600);
            var m = Math.floor((seconds % 3600) / 60);
            return h + 'h ' + m + 'm';
        },

        formatMicroseconds: function (us) {
            if (us === 0) return '0';
            if (us < 1000) return us.toFixed(1) + 'us';
            if (us < 1000000) return (us / 1000).toFixed(1) + 'ms';
            return (us / 1000000).toFixed(2) + 's';
        },

        truncateText: function (text, maxLen) {
            if (!text || text.length <= maxLen) return text;
            return text.substring(0, maxLen) + '...';
        },

        getCommandClass: function (cmd) {
            cmd = cmd.toLowerCase();
            var readCmds = ['get', 'mget', 'hget', 'hgetall', 'hmget', 'lrange', 'scard', 'smembers',
                'zrange', 'zcard', 'exists', 'type', 'ttl', 'scan', 'hscan', 'sscan', 'zscan',
                'strlen', 'llen', 'hlen', 'dbsize', 'keys', 'randomkey', 'xrange', 'xlen'];
            var writeCmds = ['set', 'setex', 'mset', 'hset', 'hmset', 'del', 'lpush', 'rpush',
                'sadd', 'zadd', 'incr', 'incrby', 'decr', 'decrby', 'hdel', 'expire',
                'flushdb', 'flushall', 'xadd'];
            var infoCmds = ['info', 'ping', 'select', 'auth', 'config', 'slowlog', 'command',
                'client', 'memory', 'object', 'debug', 'monitor'];

            if (readCmds.indexOf(cmd) !== -1) return 'src-cmd-read';
            if (writeCmds.indexOf(cmd) !== -1) return 'src-cmd-write';
            if (infoCmds.indexOf(cmd) !== -1) return 'src-cmd-info';
            return 'src-cmd-other';
        },

        /**
         * Merge preset lines into a textarea without overwriting existing entries.
         * Adds only lines from presetValue that are not already present.
         */
        mergeTextareaLines: function (selector, presetValue) {
            var $el = $(selector);
            var existing = $el.val().split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
            var incoming = (presetValue || '').split('\n').map(function (l) { return l.trim(); }).filter(Boolean);

            var existingSet = {};
            existing.forEach(function (line) { existingSet[line.toLowerCase()] = true; });

            incoming.forEach(function (line) {
                if (!existingSet[line.toLowerCase()]) {
                    existing.push(line);
                    existingSet[line.toLowerCase()] = true;
                }
            });

            $el.val(existing.join('\n'));
        },

        /**
         * Merge preset TTL entries (group:seconds) into a textarea.
         * Existing TTL values for the same group are preserved; only new groups are added.
         */
        mergeTextareaTtl: function (selector, presetValue) {
            var $el = $(selector);
            var existing = $el.val().split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
            var incoming = (presetValue || '').split('\n').map(function (l) { return l.trim(); }).filter(Boolean);

            var existingGroups = {};
            existing.forEach(function (line) {
                var group = line.split(':')[0].trim().toLowerCase();
                if (group) existingGroups[group] = true;
            });

            incoming.forEach(function (line) {
                var group = line.split(':')[0].trim().toLowerCase();
                if (group && !existingGroups[group]) {
                    existing.push(line);
                    existingGroups[group] = true;
                }
            });

            $el.val(existing.join('\n'));
        },

        showNotice: function (message, type) {
            type = type || 'info';
            var $notice = $('<div class="src-notice src-notice-' + type + '">' +
                '<span>' + SRC.escapeHtml(message) + '</span>' +
                '<button type="button" class="src-notice-dismiss">&times;</button>' +
                '</div>');

            $('.src-wrap').prepend($notice);

            $notice.find('.src-notice-dismiss').on('click', function () {
                $notice.fadeOut(200, function () { $(this).remove(); });
            });

            setTimeout(function () {
                $notice.fadeOut(300, function () { $(this).remove(); });
            }, 5000);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function () {
        if ($('.src-wrap').length) {
            SRC.init();
        }
    });

})(jQuery);
