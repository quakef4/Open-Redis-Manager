<?php
/**
 * Admin Page Template
 * 
 * @package WP_Redis_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap wp-redis-manager">
    <h1>
        <?php echo esc_html( get_admin_page_title() ); ?>
        <span class="wp-redis-version">v<?php echo WP_REDIS_MANAGER_VERSION; ?></span>
    </h1>

    <!-- Status Card -->
    <div class="redis-status-card">
        <h2><?php _e( 'Stato Redis', 'wp-redis-manager' ); ?></h2>
        <div class="redis-status-content">
            <div class="status-indicator">
                <span class="status-dot" id="redis-status-dot"></span>
                <span class="status-text" id="redis-status-text"><?php _e( 'Verificando...', 'wp-redis-manager' ); ?></span>
            </div>
            <div class="redis-info" id="redis-info" style="display:none;">
                <div class="info-item">
                    <strong><?php _e( 'Versione:', 'wp-redis-manager' ); ?></strong>
                    <span id="redis-version">-</span>
                </div>
                <div class="info-item">
                    <strong><?php _e( 'Memoria:', 'wp-redis-manager' ); ?></strong>
                    <span id="redis-memory">-</span>
                </div>
                <div class="info-item">
                    <strong><?php _e( 'Uptime:', 'wp-redis-manager' ); ?></strong>
                    <span id="redis-uptime">-</span>
                </div>
            </div>
            <div class="status-actions">
                <button type="button" class="button" id="test-connection">
                    <?php _e( 'Test Connessione', 'wp-redis-manager' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="flush-cache">
                    <?php _e( 'Svuota Cache', 'wp-redis-manager' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="refresh-stats">
                    <?php _e( 'Aggiorna Stats', 'wp-redis-manager' ); ?>
                </button>
            </div>
        </div>

        <!-- Cache Stats -->
        <div class="cache-stats">
            <div class="stat-box">
                <div class="stat-value" id="cache-hits">0</div>
                <div class="stat-label"><?php _e( 'Cache Hits', 'wp-redis-manager' ); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-value" id="cache-misses">0</div>
                <div class="stat-label"><?php _e( 'Cache Misses', 'wp-redis-manager' ); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-value" id="hit-rate">0%</div>
                <div class="stat-label"><?php _e( 'Hit Rate', 'wp-redis-manager' ); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-value" id="redis-calls">0</div>
                <div class="stat-label"><?php _e( 'Redis Calls', 'wp-redis-manager' ); ?></div>
            </div>
        </div>
    </div>

    <!-- Main Form -->
    <form method="post" action="" class="redis-manager-form">
        <?php wp_nonce_field( 'wp_redis_manager_settings' ); ?>
        
        <!-- Enable/Disable -->
        <div class="settings-section">
            <h2><?php _e( 'Configurazione Generale', 'wp-redis-manager' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enabled"><?php _e( 'Abilita Manager', 'wp-redis-manager' ); ?></label>
                    </th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" id="enabled" name="enabled" <?php checked( $settings['enabled'], true ); ?>>
                            <span class="slider"></span>
                        </label>
                        <p class="description">
                            <?php _e( 'Abilita o disabilita la gestione della cache Redis tramite questo plugin.', 'wp-redis-manager' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tabs -->
        <div class="nav-tab-wrapper">
            <a href="#tab-groups" class="nav-tab nav-tab-active"><?php _e( 'Gruppi Cache', 'wp-redis-manager' ); ?></a>
            <a href="#tab-ttl" class="nav-tab"><?php _e( 'TTL Custom', 'wp-redis-manager' ); ?></a>
            <a href="#tab-activity" class="nav-tab"><?php _e( 'Monitor Attività', 'wp-redis-manager' ); ?></a>
            <a href="#tab-keys" class="nav-tab"><?php _e( 'Esplora Chiavi', 'wp-redis-manager' ); ?></a>
            <a href="#tab-presets" class="nav-tab"><?php _e( 'Preset', 'wp-redis-manager' ); ?></a>
        </div>

        <!-- Tab: Cache Groups -->
        <div id="tab-groups" class="tab-content active">
            <div class="settings-section">
                <h2><?php _e( 'Gruppi Non Persistenti', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Gruppi che NON vengono salvati in Redis (solo in memoria). Critico per carrelli e sessioni.', 'wp-redis-manager' ); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="non_persistent_groups"><?php _e( 'Gruppi', 'wp-redis-manager' ); ?></label>
                        </th>
                        <td>
                            <textarea 
                                id="non_persistent_groups" 
                                name="non_persistent_groups" 
                                rows="8" 
                                class="large-text code"
                                placeholder="wc_session_id&#10;cart&#10;wc_cart"
                            ><?php echo esc_textarea( implode( "\n", $settings['non_persistent_groups'] ) ); ?></textarea>
                            <p class="description">
                                <?php _e( 'Un gruppo per riga. Raccomandati: wc_session_id, cart, wc_cart, woocommerce_session_id', 'wp-redis-manager' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php _e( 'Redis Hash Groups', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Gruppi che usano Redis Hashes per performance migliorate. Riduce fino al 70% le chiamate Redis.', 'wp-redis-manager' ); ?>
                </p>
                
                <div class="notice notice-warning inline">
                    <p>
                        <strong><?php _e( '⚠️ IMPORTANTE per YITH Request a Quote:', 'wp-redis-manager' ); ?></strong><br>
                        <?php _e( 'NON includere "options" in questo elenco se usi YITH Request a Quote! Le sessioni YITH sono salvate come WordPress options e includere questo gruppo causa conflitti tra utenti. Usa il preset "YITH Request a Quote" per configurazione sicura.', 'wp-redis-manager' ); ?>
                    </p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="redis_hash_groups"><?php _e( 'Gruppi', 'wp-redis-manager' ); ?></label>
                        </th>
                        <td>
                            <textarea 
                                id="redis_hash_groups" 
                                name="redis_hash_groups" 
                                rows="8" 
                                class="large-text code"
                                placeholder="post_meta&#10;term_meta&#10;user_meta&#10;options"
                            ><?php echo esc_textarea( implode( "\n", $settings['redis_hash_groups'] ) ); ?></textarea>
                            <p class="description">
                                <?php _e( 'Un gruppo per riga. Raccomandati per WooCommerce: post_meta, term_meta, user_meta, options, wc_var_prices', 'wp-redis-manager' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php _e( 'Global Groups (Multisite)', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Gruppi condivisi tra tutti i blog in una installazione multisite.', 'wp-redis-manager' ); ?>
                </p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="global_groups"><?php _e( 'Gruppi', 'wp-redis-manager' ); ?></label>
                        </th>
                        <td>
                            <textarea 
                                id="global_groups" 
                                name="global_groups" 
                                rows="6" 
                                class="large-text code"
                                placeholder="users&#10;userlogins&#10;usermeta&#10;site-options"
                            ><?php echo esc_textarea( implode( "\n", $settings['global_groups'] ) ); ?></textarea>
                            <p class="description">
                                <?php _e( 'Un gruppo per riga. Lascia vuoto se non usi multisite.', 'wp-redis-manager' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>


        <!-- Tab: Custom TTL -->
        <div id="tab-ttl" class="tab-content">
            <div class="settings-section">
                <h2><?php _e( 'TTL Custom per Gruppo', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Imposta scadenza personalizzata (in secondi) per gruppi specifici.', 'wp-redis-manager' ); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="custom_ttl"><?php _e( 'TTL', 'wp-redis-manager' ); ?></label>
                        </th>
                        <td>
                            <?php
                            $ttl_text = '';
                            foreach ( $settings['custom_ttl'] as $group => $ttl ) {
                                $ttl_text .= $group . ':' . $ttl . "\n";
                            }
                            ?>
                            <textarea 
                                id="custom_ttl" 
                                name="custom_ttl" 
                                rows="10" 
                                class="large-text code"
                                placeholder="posts:3600&#10;wc_var_prices:1800&#10;options:7200"
                            ><?php echo esc_textarea( $ttl_text ); ?></textarea>
                            <p class="description">
                                <?php _e( 'Formato: gruppo:secondi (uno per riga). Es: posts:3600 = posts scade dopo 1 ora', 'wp-redis-manager' ); ?>
                            </p>
                            
                            <div class="ttl-presets">
                                <strong><?php _e( 'Valori comuni:', 'wp-redis-manager' ); ?></strong>
                                <ul>
                                    <li>300 = 5 minuti</li>
                                    <li>600 = 10 minuti</li>
                                    <li>1800 = 30 minuti</li>
                                    <li>3600 = 1 ora</li>
                                    <li>7200 = 2 ore</li>
                                    <li>86400 = 1 giorno</li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="notice notice-info inline">
                    <p>
                        <strong><?php _e( 'Nota:', 'wp-redis-manager' ); ?></strong>
                        <?php _e( 'TTL custom richiede filtro personalizzato. Se non vedi effetto, controlla che il filtro redis_object_cache_set_expiration sia implementato.', 'wp-redis-manager' ); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Tab: Activity Monitor -->
        <div id="tab-activity" class="tab-content">
            <div class="settings-section">
                <h2><?php _e( 'Monitor Attività Redis', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Monitora le attività di Redis in tempo reale: comandi eseguiti, performance e utilizzo risorse.', 'wp-redis-manager' ); ?>
                </p>

                <!-- Activity Controls -->
                <div class="activity-controls">
                    <button type="button" class="button button-primary" id="refresh-activity">
                        <?php _e( 'Aggiorna Dati', 'wp-redis-manager' ); ?>
                    </button>
                    <label class="activity-autorefresh">
                        <input type="checkbox" id="activity-autorefresh" checked>
                        <?php _e( 'Auto-refresh ogni 10s', 'wp-redis-manager' ); ?>
                    </label>
                </div>

                <!-- Server & Memory Info Grid -->
                <div class="activity-grid">
                    <!-- Server Info -->
                    <div class="activity-card">
                        <h3><?php _e( 'Informazioni Server', 'wp-redis-manager' ); ?></h3>
                        <div class="activity-info-list" id="server-info-list">
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Versione Redis:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-redis-version">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Modalità:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-redis-mode">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Sistema:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-redis-os">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Uptime:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-uptime">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Porta:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-port">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Memory Info -->
                    <div class="activity-card">
                        <h3><?php _e( 'Utilizzo Memoria', 'wp-redis-manager' ); ?></h3>
                        <div class="activity-info-list" id="memory-info-list">
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Memoria Usata:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value memory-value" id="act-mem-used">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Picco Memoria:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-mem-peak">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Memoria Max:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-mem-max">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Frammentazione:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-mem-frag">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Memoria Lua:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-mem-lua">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Clients Info -->
                    <div class="activity-card">
                        <h3><?php _e( 'Client Connessi', 'wp-redis-manager' ); ?></h3>
                        <div class="activity-info-list" id="clients-info-list">
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Client Connessi:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value client-value" id="act-clients-connected">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Client Bloccati:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-clients-blocked">-</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><?php _e( 'Tracking Clients:', 'wp-redis-manager' ); ?></span>
                                <span class="info-value" id="act-clients-tracking">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Keyspace Info -->
                    <div class="activity-card">
                        <h3><?php _e( 'Keyspace', 'wp-redis-manager' ); ?></h3>
                        <div class="activity-info-list" id="keyspace-info-list">
                            <p class="loading-text"><?php _e( 'Caricamento...', 'wp-redis-manager' ); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Command Stats Section -->
                <h2><?php _e( 'Statistiche Comandi', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Visualizza i comandi Redis più utilizzati ordinati per numero di chiamate.', 'wp-redis-manager' ); ?>
                </p>

                <!-- Command Stats Filters -->
                <div class="command-stats-filters">
                    <div class="filter-group">
                        <label for="cmd-filter"><?php _e( 'Filtra comando:', 'wp-redis-manager' ); ?></label>
                        <input type="text" id="cmd-filter" placeholder="<?php _e( 'Es: GET, SET, HGET...', 'wp-redis-manager' ); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="cmd-sort"><?php _e( 'Ordina per:', 'wp-redis-manager' ); ?></label>
                        <select id="cmd-sort">
                            <option value="calls"><?php _e( 'Chiamate', 'wp-redis-manager' ); ?></option>
                            <option value="usec"><?php _e( 'Tempo totale', 'wp-redis-manager' ); ?></option>
                            <option value="usec_per_call"><?php _e( 'Tempo medio', 'wp-redis-manager' ); ?></option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="cmd-limit"><?php _e( 'Mostra:', 'wp-redis-manager' ); ?></label>
                        <select id="cmd-limit">
                            <option value="10">Top 10</option>
                            <option value="25" selected>Top 25</option>
                            <option value="50">Top 50</option>
                            <option value="0"><?php _e( 'Tutti', 'wp-redis-manager' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Command Stats Table -->
                <div class="command-stats-wrapper">
                    <table class="command-stats-table" id="command-stats-table">
                        <thead>
                            <tr>
                                <th><?php _e( 'Comando', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Chiamate', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Tempo Tot. (ms)', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Tempo Medio (μs)', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Grafico', 'wp-redis-manager' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="command-stats-body">
                            <tr>
                                <td colspan="5" class="loading-text"><?php _e( 'Caricamento...', 'wp-redis-manager' ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Slowlog Section -->
                <h2><?php _e( 'Slowlog (Query Lente)', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Mostra le query Redis che hanno superato la soglia di tempo configurata in Redis (slowlog-log-slower-than).', 'wp-redis-manager' ); ?>
                </p>

                <!-- Slowlog Filters -->
                <div class="slowlog-filters">
                    <div class="filter-group">
                        <label for="slowlog-filter"><?php _e( 'Filtra comando:', 'wp-redis-manager' ); ?></label>
                        <input type="text" id="slowlog-filter" placeholder="<?php _e( 'Es: KEYS, SCAN...', 'wp-redis-manager' ); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="slowlog-min-duration"><?php _e( 'Durata min (μs):', 'wp-redis-manager' ); ?></label>
                        <input type="number" id="slowlog-min-duration" value="0" min="0" step="1000">
                    </div>
                </div>

                <!-- Slowlog Table -->
                <div class="slowlog-wrapper">
                    <table class="slowlog-table" id="slowlog-table">
                        <thead>
                            <tr>
                                <th><?php _e( 'ID', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Data/Ora', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Durata', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Comando', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Client', 'wp-redis-manager' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="slowlog-body">
                            <tr>
                                <td colspan="5" class="loading-text"><?php _e( 'Caricamento...', 'wp-redis-manager' ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="slowlog-empty" id="slowlog-empty" style="display:none;">
                        <p><?php _e( 'Nessuna query lenta registrata. Ottimo!', 'wp-redis-manager' ); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Keys Explorer -->
        <div id="tab-keys" class="tab-content">
            <div class="settings-section">
                <h2><?php _e( 'Esplora Chiavi Redis', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Cerca, filtra e ispeziona le chiavi memorizzate in Redis. Puoi eliminare chiavi singole.', 'wp-redis-manager' ); ?>
                </p>

                <!-- Keys Search Controls -->
                <div class="keys-controls">
                    <div class="keys-search-row">
                        <div class="filter-group filter-large">
                            <label for="keys-pattern"><?php _e( 'Pattern di ricerca:', 'wp-redis-manager' ); ?></label>
                            <input type="text" id="keys-pattern" value="*" placeholder="*">
                            <p class="filter-help"><?php _e( 'Usa * come wildcard. Es: wp_*:posts:*, *session*', 'wp-redis-manager' ); ?></p>
                        </div>
                        <div class="filter-group">
                            <label for="keys-type"><?php _e( 'Tipo:', 'wp-redis-manager' ); ?></label>
                            <select id="keys-type">
                                <option value=""><?php _e( 'Tutti i tipi', 'wp-redis-manager' ); ?></option>
                                <option value="string">String</option>
                                <option value="list">List</option>
                                <option value="set">Set</option>
                                <option value="zset">Sorted Set</option>
                                <option value="hash">Hash</option>
                                <option value="stream">Stream</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="keys-limit"><?php _e( 'Limite:', 'wp-redis-manager' ); ?></label>
                            <select id="keys-limit">
                                <option value="50">50</option>
                                <option value="100" selected>100</option>
                                <option value="200">200</option>
                                <option value="500">500</option>
                            </select>
                        </div>
                    </div>
                    <div class="keys-actions">
                        <button type="button" class="button button-primary" id="search-keys">
                            <?php _e( 'Cerca Chiavi', 'wp-redis-manager' ); ?>
                        </button>
                        <button type="button" class="button" id="load-more-keys" style="display:none;">
                            <?php _e( 'Carica Altri', 'wp-redis-manager' ); ?>
                        </button>
                        <span class="keys-count" id="keys-count"></span>
                    </div>
                </div>

                <!-- Keys Table -->
                <div class="keys-table-wrapper">
                    <table class="keys-table" id="keys-table">
                        <thead>
                            <tr>
                                <th><?php _e( 'Chiave', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Tipo', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'TTL', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Memoria', 'wp-redis-manager' ); ?></th>
                                <th><?php _e( 'Azioni', 'wp-redis-manager' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="keys-table-body">
                            <tr>
                                <td colspan="5" class="empty-text"><?php _e( 'Clicca "Cerca Chiavi" per esplorare Redis', 'wp-redis-manager' ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Key Details Modal -->
                <div class="key-details-modal" id="key-details-modal" style="display:none;">
                    <div class="key-details-content">
                        <div class="key-details-header">
                            <h3><?php _e( 'Dettagli Chiave', 'wp-redis-manager' ); ?></h3>
                            <button type="button" class="key-details-close" id="close-key-details">&times;</button>
                        </div>
                        <div class="key-details-body" id="key-details-body">
                            <div class="key-meta">
                                <div class="meta-item">
                                    <strong><?php _e( 'Chiave:', 'wp-redis-manager' ); ?></strong>
                                    <code id="detail-key">-</code>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e( 'Tipo:', 'wp-redis-manager' ); ?></strong>
                                    <span id="detail-type" class="type-badge">-</span>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e( 'TTL:', 'wp-redis-manager' ); ?></strong>
                                    <span id="detail-ttl">-</span>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e( 'Memoria:', 'wp-redis-manager' ); ?></strong>
                                    <span id="detail-memory">-</span>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e( 'Encoding:', 'wp-redis-manager' ); ?></strong>
                                    <span id="detail-encoding">-</span>
                                </div>
                                <div class="meta-item">
                                    <strong><?php _e( 'Lunghezza:', 'wp-redis-manager' ); ?></strong>
                                    <span id="detail-length">-</span>
                                </div>
                            </div>
                            <div class="key-value-section">
                                <h4><?php _e( 'Valore:', 'wp-redis-manager' ); ?></h4>
                                <pre id="detail-value" class="key-value-display">-</pre>
                            </div>
                        </div>
                        <div class="key-details-footer">
                            <button type="button" class="button button-link-delete" id="delete-key-modal">
                                <?php _e( 'Elimina Chiave', 'wp-redis-manager' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: Presets -->
        <div id="tab-presets" class="tab-content">
            <div class="settings-section">
                <h2><?php _e( 'Configurazioni Predefinite', 'wp-redis-manager' ); ?></h2>
                <p class="description">
                    <?php _e( 'Carica rapidamente configurazioni ottimizzate per casi d\'uso comuni.', 'wp-redis-manager' ); ?>
                </p>
                
                <div class="presets-grid">
                    <!-- Preset: YITH Request a Quote -->
                    <div class="preset-card">
                        <h3><?php _e( 'YITH Request a Quote', 'wp-redis-manager' ); ?></h3>
                        <p><?php _e( 'Configurazione specifica per YITH Request a Quote. Esclude "options" da hash groups per evitare conflitti sessioni.', 'wp-redis-manager' ); ?></p>
                        <button type="button" class="button button-primary load-preset" data-preset="yith">
                            <?php _e( 'Carica Preset', 'wp-redis-manager' ); ?>
                        </button>
                    </div>

                    <!-- Preset: WooCommerce -->
                    <div class="preset-card">
                        <h3><?php _e( 'WooCommerce Standard', 'wp-redis-manager' ); ?></h3>
                        <p><?php _e( 'Configurazione per WooCommerce standard con carrello. Non usare se hai YITH Request a Quote.', 'wp-redis-manager' ); ?></p>
                        <button type="button" class="button button-primary load-preset" data-preset="woocommerce">
                            <?php _e( 'Carica Preset', 'wp-redis-manager' ); ?>
                        </button>
                    </div>

                    <!-- Preset: Blog -->
                    <div class="preset-card">
                        <h3><?php _e( 'Blog/Magazine', 'wp-redis-manager' ); ?></h3>
                        <p><?php _e( 'Ottimizzato per blog e magazine con molti post e categorie.', 'wp-redis-manager' ); ?></p>
                        <button type="button" class="button button-primary load-preset" data-preset="blog">
                            <?php _e( 'Carica Preset', 'wp-redis-manager' ); ?>
                        </button>
                    </div>

                    <!-- Preset: Multisite -->
                    <div class="preset-card">
                        <h3><?php _e( 'Multisite', 'wp-redis-manager' ); ?></h3>
                        <p><?php _e( 'Configurazione per installazioni WordPress Multisite.', 'wp-redis-manager' ); ?></p>
                        <button type="button" class="button button-primary load-preset" data-preset="multisite">
                            <?php _e( 'Carica Preset', 'wp-redis-manager' ); ?>
                        </button>
                    </div>

                    <!-- Preset: Performance -->
                    <div class="preset-card">
                        <h3><?php _e( 'Performance Massime', 'wp-redis-manager' ); ?></h3>
                        <p><?php _e( 'Massime performance con cache aggressiva. Solo per siti senza sistemi di preventivi/quote.', 'wp-redis-manager' ); ?></p>
                        <button type="button" class="button button-primary load-preset" data-preset="performance">
                            <?php _e( 'Carica Preset', 'wp-redis-manager' ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <p class="submit">
            <button type="submit" name="wp_redis_manager_save" class="button button-primary button-large">
                <?php _e( 'Salva Configurazione', 'wp-redis-manager' ); ?>
            </button>
        </p>
    </form>
</div>

<script type="text/javascript">
// Presets data
var wpRedisPresets = {
    'yith': {
        'non_persistent_groups': 'wc_session_id\nwc-session-id\nwoocommerce_session_id\ncart\nwc_cart\nwoocommerce_cart\nwc_notices\nwoocommerce_notices\nyith_ywraq\nywraq\nyith_ywraq_session\nyith_session\nyith_wcwl\nyith_wl\nrequest_quote\nuser_meta\noptions',
        'redis_hash_groups': 'post_meta\nterm_meta\nwc_var_prices\nwc_attribute_taxonomies',
        'global_groups': '',
        'custom_ttl': 'posts:3600\nterms:7200'
    },
    'woocommerce': {
        'non_persistent_groups': 'wc_session_id\nwc-session-id\nwoocommerce_session_id\ncart\nwc_cart\nwoocommerce_cart\nwc_notices\nwoocommerce_notices',
        'redis_hash_groups': 'post_meta\nterm_meta\nuser_meta\noptions\nwc_var_prices\nwc_webhooks\nwc_attribute_taxonomies',
        'global_groups': '',
        'custom_ttl': 'posts:3600\nwc_var_prices:1800\noptions:3600\nterms:7200'
    },
    'blog': {
        'non_persistent_groups': '',
        'redis_hash_groups': 'post_meta\nterm_meta\nuser_meta\noptions\ntransient',
        'global_groups': '',
        'custom_ttl': 'posts:7200\nterms:7200\noptions:3600'
    },
    'multisite': {
        'non_persistent_groups': '',
        'redis_hash_groups': 'post_meta\nterm_meta\nuser_meta\noptions',
        'global_groups': 'users\nuserlogins\nusermeta\nuser_meta\nsite-transient\nsite-options\nblog-lookup\nblog-details\nnetworks\nsites',
        'custom_ttl': ''
    },
    'performance': {
        'non_persistent_groups': '',
        'redis_hash_groups': 'post_meta\nterm_meta\nuser_meta\ncomment_meta\noptions\nterms\ntransient\nposts',
        'global_groups': '',
        'custom_ttl': 'posts:7200\noptions:7200\nterms:14400'
    }
};
</script>
