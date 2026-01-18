# WP Redis Manager

Un plugin WordPress completo con interfaccia grafica per gestire facilmente la cache Redis e le configurazioni del plugin WP Redis 1.4.7.

## 📋 Caratteristiche

### ✨ Gestione Gruppi Cache
- **Gruppi Non Persistenti**: Configura gruppi che non vengono salvati in Redis (essenziale per carrelli WooCommerce)
- **Redis Hash Groups**: Abilita Redis hashes per performance migliorate (riduce fino al 70% le chiamate Redis)
- **Global Groups**: Gestisci gruppi condivisi per installazioni multisite

### 🎯 Esclusione Pagine
- **Esclusione Pagine Specifiche**: Seleziona pagine dove disabilitare completamente Redis
- **Esclusione URL**: Configura pattern URL da escludere dalla cache
- Ideale per pagine dinamiche come carrello, checkout, area utente

### ⏱️ TTL Personalizzati
- Imposta scadenze diverse per ogni gruppo cache
- Valori predefiniti per casi d'uso comuni
- Ottimizza memoria Redis evitando dati obsoleti

### 🚀 Preset Configurazioni
Carica rapidamente configurazioni ottimizzate per:
- **WooCommerce**: Ottimizzato per ecommerce con carrello separato
- **Blog/Magazine**: Perfetto per siti content-heavy
- **Multisite**: Configurazione per network WordPress
- **Performance Massime**: Cache aggressiva per massima velocità

### 📊 Monitoring Real-time
- Statistiche cache in tempo reale (hits, misses, hit rate)
- Test connessione Redis
- Info server Redis (versione, memoria, uptime)
- Svuota cache con un click

## 📦 Installazione

### Requisiti
- WordPress 5.0 o superiore
- PHP 7.4 o superiore
- **WP Redis 1.4.7** (deve essere già installato e configurato)
- Redis server attivo

### Metodo 1: Upload Manuale

1. Scarica il plugin
2. Carica la cartella `wp-redis-manager` in `/wp-content/plugins/`
3. Attiva il plugin dal menu Plugin di WordPress
4. Vai su **Strumenti → Redis Manager**

### Metodo 2: Upload ZIP

1. Comprimi la cartella `wp-redis-manager` in un file .zip
2. In WordPress, vai su **Plugin → Aggiungi nuovo → Carica plugin**
3. Seleziona il file .zip e clicca "Installa ora"
4. Attiva il plugin
5. Vai su **Strumenti → Redis Manager**

## 🔧 Configurazione

### Setup Iniziale Rapido

1. **Vai su Strumenti → Redis Manager**

2. **Verifica Connessione Redis**
   - Clicca "Test Connessione"
   - Dovresti vedere stato "Connesso" con info Redis

3. **Carica un Preset** (consigliato per iniziare)
   - Vai alla tab "Preset"
   - Scegli "WooCommerce Ottimizzato" se usi WooCommerce
   - Oppure "Blog/Magazine" per un sito standard
   - Clicca "Carica Preset"

4. **Salva Configurazione**
   - Clicca "Salva Configurazione" in fondo
   - Verifica che vedi il messaggio di conferma

5. **Svuota Cache**
   - Clicca "Svuota Cache" per applicare le nuove configurazioni
   - Monitora le statistiche per verificare che funzioni

### Configurazione Manuale

#### Gruppi Non Persistenti (Tab: Gruppi Cache)

Inserisci un gruppo per riga. Raccomandati per WooCommerce:
```
wc_session_id
wc-session-id
woocommerce_session_id
cart
wc_cart
woocommerce_cart
```

#### Redis Hash Groups (Tab: Gruppi Cache)

Inserisci un gruppo per riga. Raccomandati:
```
post_meta
term_meta
user_meta
options
wc_var_prices
wc_attribute_taxonomies
```

#### Esclusione Pagine (Tab: Esclusione Pagine)

- **Pagine**: Seleziona da checklist
- **URL**: Inserisci URL parziali, uno per riga:
```
/cart
/checkout
/my-account
```

#### TTL Custom (Tab: TTL Custom)

Formato: `gruppo:secondi` (uno per riga)
```
posts:3600
wc_var_prices:1800
options:7200
terms:7200
```

Valori comuni:
- 300 = 5 minuti
- 1800 = 30 minuti
- 3600 = 1 ora
- 7200 = 2 ore

## 📊 Monitoraggio

### Dashboard
Il plugin mostra in tempo reale:

- **Cache Hits**: Quante volte il dato è stato trovato in cache
- **Cache Misses**: Quante volte il dato non era in cache
- **Hit Rate**: Percentuale di successo cache
  - 🟢 >85% = Eccellente
  - 🟡 70-85% = Buono
  - 🔴 <70% = Da ottimizzare
- **Redis Calls**: Numero totale chiamate Redis

### Azioni Rapide
- **Test Connessione**: Verifica che Redis sia raggiungibile
- **Svuota Cache**: Flush completo Redis
- **Aggiorna Stats**: Ricarica statistiche

## 🎯 Casi d'Uso Comuni

### Problema: Carrello WooCommerce Condiviso tra Utenti

**Soluzione:**
1. Vai alla tab "Preset"
2. Carica "WooCommerce Ottimizzato"
3. Salva e Svuota Cache
4. Test con 2 browser: carrelli devono essere separati

### Problema: Redis Si Riempie Troppo

**Soluzione:**
1. Vai alla tab "TTL Custom"
2. Aggiungi scadenze ai gruppi principali:
```
posts:3600
options:3600
transient:1800
```
3. Salva e monitora memoria Redis

### Problema: Performance Basse su Pagine Specifiche

**Soluzione:**
1. Vai alla tab "Esclusione Pagine"
2. Seleziona le pagine problematiche
3. Oppure aggiungi URL specifici
4. Salva configurazione

### Problema: Hit Rate Basso (<70%)

**Soluzione:**
1. Verifica che `WP_REDIS_USE_CACHE_GROUPS` sia `true` in wp-config.php
2. Vai alla tab "Gruppi Cache"
3. Aggiungi gruppi a "Redis Hash Groups":
```
post_meta
term_meta
user_meta
options
```
4. Salva e Svuota Cache
5. Monitora hit rate dopo 10 minuti

## ⚙️ Integrazione con wp-config.php

Per massime performance, aggiungi in `wp-config.php`:

```php
// PRIMA di: require_once ABSPATH . 'wp-settings.php';

// Abilita Redis Hashes (raccomandato)
define( 'WP_REDIS_USE_CACHE_GROUPS', true );

// TTL default
define( 'WP_REDIS_DEFAULT_EXPIRE_SECONDS', 3600 );

// Cache key salt (importante se più siti)
define( 'WP_CACHE_KEY_SALT', DB_NAME . '_' );

// Configurazione server Redis
$redis_server = array(
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'auth'     => '', // password se configurata
    'database' => 0,
);
```

## 🔍 Troubleshooting

### Plugin Non Appare nel Menu

**Verifica:**
- Plugin attivato?
- Hai permessi amministratore?

**Soluzione:**
```bash
wp plugin list
wp plugin activate wp-redis-manager
```

### "Redis Non Disponibile"

**Verifica:**
```bash
# Redis server attivo?
redis-cli ping  # Deve rispondere: PONG

# WP Redis installato?
ls -la /percorso/wp-content/object-cache.php  # Deve esistere
```

**Soluzione:**
```bash
# Avvia Redis
sudo systemctl start redis

# Verifica WP Redis
wp plugin list | grep redis
```

### Configurazioni Non Si Applicano

**Verifica:**
1. Clicchi "Salva Configurazione"?
2. Vedi messaggio di conferma?
3. Hai svuotato cache dopo?

**Soluzione:**
1. Salva configurazione
2. Clicca "Svuota Cache"
3. Ricarica pagina e verifica stats

### Hit Rate Sempre 0%

**Problema:** Cache non funziona

**Verifica:**
```bash
# Test manuale
wp eval 'wp_cache_set("test", "value"); echo wp_cache_get("test");'
# Deve stampare: value
```

**Soluzione:**
- Verifica che object-cache.php esista
- Verifica connessione Redis
- Controlla log PHP per errori

## 🛡️ Sicurezza

- Tutte le azioni richiedono capability `manage_options`
- AJAX protetto con nonce
- Input sanitizzati prima del salvataggio
- Output escaped nel rendering

## 🔄 Compatibilità

- ✅ WordPress 5.0+
- ✅ WP Redis 1.4.7
- ✅ WooCommerce (tutte le versioni recenti)
- ✅ WordPress Multisite
- ✅ PHP 7.4, 8.0, 8.1, 8.2

## 📝 Changelog

### 1.0.0 - 2026-01-05
- 🎉 Release iniziale
- ✨ Gestione gruppi cache
- ✨ Esclusione pagine/URL
- ✨ TTL personalizzati
- ✨ 4 preset configurazioni
- ✨ Monitoring real-time
- ✨ Test connessione Redis
- ✨ Flush cache con un click

## 🤝 Contribuire

Segnalazioni bug e richieste feature:
- Apri una issue su GitHub
- Descrivi il problema in dettaglio
- Includi screenshot se possibile

## 📄 Licenza

GPL v2 or later

## ✨ Supporto

Per supporto:
1. Verifica la sezione Troubleshooting
2. Controlla i log PHP
3. Testa connessione Redis
4. Apri issue con dettagli completi

## 🎯 Roadmap

- [ ] Export/Import configurazioni
- [ ] Log attività cache
- [ ] Grafici performance
- [ ] Notifiche email errori
- [ ] WP-CLI commands
- [ ] REST API endpoints
- [ ] Configurazione multi-sito avanzata

## 📸 Screenshots

1. **Dashboard**: Overview stato Redis e statistiche
2. **Gruppi Cache**: Gestione gruppi non persistenti e hash groups
3. **Esclusione Pagine**: Selezione pagine e URL da escludere
4. **TTL Custom**: Configurazione scadenze personalizzate
5. **Preset**: Caricamento rapido configurazioni ottimizzate

## 🙏 Crediti

Sviluppato con ❤️ per la community WordPress

Compatibile con:
- [WP Redis](https://github.com/pantheon-systems/wp-redis) by Pantheon
- [WooCommerce](https://woocommerce.com)

---

**Buon caching! 🚀**
