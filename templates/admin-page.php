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
        'non_persistent_groups': 'wc_session_id\nwc-session-id\nwoocommerce_session_id\ncart\nwc_cart\nwoocommerce_cart',
        'redis_hash_groups': 'post_meta\nterm_meta\nuser_meta\nwc_var_prices\nwc_attribute_taxonomies',
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
