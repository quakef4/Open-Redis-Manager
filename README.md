# WP Redis Manager

Un plugin WordPress completo con interfaccia grafica per gestire facilmente la cache Redis e le configurazioni del plugin WP Redis 1.4.7.

## Caratteristiche

### Gestione Gruppi Cache
- **Gruppi Non Persistenti**: Configura gruppi che non vengono salvati in Redis (essenziale per carrelli WooCommerce)
- **Redis Hash Groups**: Abilita Redis hashes per performance migliorate (riduce fino al 70% le chiamate Redis)
- **Global Groups**: Gestisci gruppi condivisi per installazioni multisite

### TTL Personalizzati
- Imposta scadenze diverse per ogni gruppo cache
- Valori predefiniti per casi d'uso comuni
- Ottimizza memoria Redis evitando dati obsoleti

### Preset Configurazioni
Carica rapidamente configurazioni ottimizzate per:
- **YITH Request a Quote**: Configurazione sicura per YITH plugin
- **WooCommerce**: Ottimizzato per ecommerce con carrello separato
- **Blog/Magazine**: Perfetto per siti content-heavy
- **Multisite**: Configurazione per network WordPress
- **Performance Massime**: Cache aggressiva per massima velocità

### Monitor Attività Redis (NUOVO in v1.1.0)
Monitora le attività Redis in tempo reale con interfaccia dettagliata:

- **Informazioni Server**: Versione Redis, modalità, sistema operativo, uptime, porta
- **Utilizzo Memoria**: Memoria usata, picco, massimo, frammentazione, memoria Lua
- **Client Connessi**: Numero client connessi, bloccati, tracking
- **Keyspace**: Database utilizzati con conteggio chiavi e TTL

#### Statistiche Comandi
Visualizza i comandi Redis più utilizzati con:
- Filtro per nome comando (es: GET, SET, HGET)
- Ordinamento per chiamate, tempo totale o tempo medio
- Limitazione top 10/25/50 o tutti
- Grafici a barre per visualizzazione immediata
- Codifica colori: lettura (verde), scrittura (giallo), info (blu)

#### Slowlog (Query Lente)
Monitora le query Redis che superano la soglia configurata:
- Filtro per comando
- Filtro per durata minima (microsecondi)
- Timestamp, durata, comando completo e client

### Esplora Chiavi Redis (NUOVO in v1.1.0)
Browser completo per le chiavi memorizzate in Redis:

- **Ricerca con Pattern**: Usa `*` come wildcard (es: `wp_*:posts:*`, `*session*`)
- **Filtro per Tipo**: String, List, Set, Sorted Set, Hash, Stream
- **Limite Risultati**: 50, 100, 200, 500 chiavi
- **Paginazione**: Carica più risultati incrementalmente

#### Informazioni Chiave
Per ogni chiave visualizza:
- Nome chiave
- Tipo Redis (con badge colorato)
- TTL (tempo di scadenza)
- Utilizzo memoria

#### Dettagli Chiave
Ispeziona qualsiasi chiave con modal dettagliata:
- Tipo, TTL, encoding interno, dimensione
- Visualizzazione valore formattata:
  - String: testo con JSON/PHP deserializzato
  - List: lista ordinata elementi
  - Set: insieme elementi
  - Sorted Set: tabella membro/score
  - Hash: tabella campo/valore
- Eliminazione chiave singola

### Monitoring Dashboard
- Statistiche cache in tempo reale (hits, misses, hit rate)
- Test connessione Redis
- Info server Redis (versione, memoria, uptime)
- Svuota cache con un click
- Auto-refresh ogni 30 secondi

## Installazione

### Requisiti
- WordPress 5.0 o superiore
- PHP 7.4 o superiore
- **WP Redis 1.4.7** (deve essere già installato e configurato)
- Redis server attivo (versione 4.0+ consigliata per funzionalità complete)

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

## Configurazione

### Setup Iniziale Rapido

1. **Vai su Strumenti → Redis Manager**

2. **Verifica Connessione Redis**
   - Clicca "Test Connessione"
   - Dovresti vedere stato "Connesso" con info Redis

3. **Carica un Preset** (consigliato per iniziare)
   - Vai alla tab "Preset"
   - Scegli "WooCommerce Standard" se usi WooCommerce
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

**IMPORTANTE per YITH Request a Quote**: NON includere "options" se usi YITH Request a Quote! Le sessioni YITH sono salvate come WordPress options.

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
- 86400 = 1 giorno

## Utilizzo Monitor Attività

### Come Accedere

1. Vai su **Strumenti → Redis Manager**
2. Clicca sulla tab **"Monitor Attività"**
3. I dati vengono caricati automaticamente

### Interpretare i Dati

#### Statistiche Comandi
- **Comandi in Verde (READ)**: GET, HGET, SCAN - operazioni di lettura
- **Comandi in Giallo (WRITE)**: SET, HSET, DEL - operazioni di scrittura
- **Comandi in Blu (INFO)**: INFO, CONFIG - comandi informativi

#### Slowlog
- **Durata Verde (<10ms)**: Performance normale
- **Durata Gialla (10-100ms)**: Attenzione, query lenta
- **Durata Rossa (>100ms)**: Query molto lenta, investigare

### Filtri Disponibili

#### Filtro Comandi
```
GET       - Solo comandi GET
HGET      - Solo comandi HGET
SET,DEL   - Multipli comandi
```

#### Pattern Ricerca Chiavi
```
*              - Tutte le chiavi
wp_*           - Chiavi che iniziano con wp_
*:posts:*      - Chiavi contenenti :posts:
*session*      - Chiavi contenenti session
```

## Esplora Chiavi Redis

### Come Cercare

1. Vai alla tab **"Esplora Chiavi"**
2. Inserisci un pattern (default: `*` per tutte)
3. Seleziona filtri opzionali (tipo, limite)
4. Clicca **"Cerca Chiavi"**

### Visualizzare Dettagli

1. Clicca **"Dettagli"** su qualsiasi chiave
2. Modal mostra:
   - Metadati (tipo, TTL, memoria, encoding)
   - Valore formattato (JSON/PHP deserializzato automaticamente)

### Eliminare Chiavi

**ATTENZIONE**: L'eliminazione è irreversibile!

1. Dalla lista: clicca **"Elimina"** sulla riga
2. Dal modal dettagli: clicca **"Elimina Chiave"**
3. Conferma l'eliminazione

## Dashboard Monitoring

### Statistiche
Il plugin mostra in tempo reale:

- **Cache Hits**: Quante volte il dato è stato trovato in cache
- **Cache Misses**: Quante volte il dato non era in cache
- **Hit Rate**: Percentuale di successo cache
  - >85% = Eccellente (verde)
  - 70-85% = Buono (giallo)
  - <70% = Da ottimizzare (rosso)
- **Redis Calls**: Numero totale chiamate Redis

### Azioni Rapide
- **Test Connessione**: Verifica che Redis sia raggiungibile
- **Svuota Cache**: Flush completo Redis
- **Aggiorna Stats**: Ricarica statistiche

## Casi d'Uso Comuni

### Problema: Carrello WooCommerce Condiviso tra Utenti

**Soluzione:**
1. Vai alla tab "Preset"
2. Carica "WooCommerce Standard"
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

### Problema: Identificare Query Lente

**Soluzione:**
1. Vai alla tab "Monitor Attività"
2. Scorri alla sezione "Slowlog"
3. Analizza comandi con durata alta
4. Pattern comuni problematici: KEYS, SCAN con pattern ampi

### Problema: Trovare Chiavi Specifiche

**Soluzione:**
1. Vai alla tab "Esplora Chiavi"
2. Usa pattern specifico: es. `*woocommerce*`
3. Filtra per tipo se necessario
4. Ispeziona valori per debug

## Integrazione con wp-config.php

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

## Troubleshooting

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

### Monitor Attività Non Carica

**Possibili cause:**
- Redis versione < 4.0 (alcune funzionalità non disponibili)
- Permessi insufficienti su Redis
- Timeout connessione

**Soluzione:**
1. Verifica versione Redis: `redis-cli INFO server | grep redis_version`
2. Verifica permessi: `redis-cli ACL WHOAMI`
3. Aumenta timeout in wp-config.php se necessario

### Slowlog Vuoto

**Motivo:** Non ci sono query che superano la soglia

**Verifica configurazione Redis:**
```bash
redis-cli CONFIG GET slowlog-log-slower-than
# Default: 10000 (10ms)

# Per abbassare soglia (es. 1ms):
redis-cli CONFIG SET slowlog-log-slower-than 1000
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

## Sicurezza

- Tutte le azioni richiedono capability `manage_options`
- AJAX protetto con nonce
- Input sanitizzati prima del salvataggio
- Output escaped nel rendering
- Eliminazione chiavi richiede conferma

## Compatibilità

- WordPress 5.0+
- WP Redis 1.4.7+
- WooCommerce (tutte le versioni recenti)
- YITH Request a Quote (con preset dedicato)
- WordPress Multisite
- PHP 7.4, 8.0, 8.1, 8.2
- Redis 4.0+ (consigliato per funzionalità complete)

## Changelog

### 1.1.0 - 2026-01-18
- **NUOVO**: Monitor Attività Redis completo
  - Informazioni server, memoria, client
  - Statistiche comandi con filtri e grafici
  - Slowlog con filtri per durata
  - Auto-refresh ogni 10 secondi
- **NUOVO**: Esplora Chiavi Redis
  - Ricerca con pattern wildcard
  - Filtro per tipo (string, list, set, hash, etc.)
  - Visualizzazione dettagli chiave
  - Eliminazione chiavi singole
  - Paginazione risultati
- **NUOVO**: Preset YITH Request a Quote
- Migliorato: Interfaccia responsive
- Migliorato: Formattazione valori (JSON/PHP deserializzato)
- Rimosso: Funzionalità esclusione pagine (causava flush inefficiente)

### 1.0.0 - 2026-01-05
- Release iniziale
- Gestione gruppi cache
- TTL personalizzati
- 4 preset configurazioni
- Monitoring real-time
- Test connessione Redis
- Flush cache con un click

## Contribuire

Segnalazioni bug e richieste feature:
- Apri una issue su GitHub
- Descrivi il problema in dettaglio
- Includi screenshot se possibile

## Licenza

GPL v2 or later

## Supporto

Per supporto:
1. Verifica la sezione Troubleshooting
2. Controlla i log PHP
3. Testa connessione Redis
4. Apri issue con dettagli completi

## Roadmap

- [ ] Export/Import configurazioni
- [ ] Grafici performance storici
- [ ] Notifiche email errori
- [ ] WP-CLI commands
- [ ] REST API endpoints
- [ ] Configurazione multi-sito avanzata
- [x] Monitor attività Redis
- [x] Browser chiavi Redis

## Screenshots

1. **Dashboard**: Overview stato Redis e statistiche cache
2. **Gruppi Cache**: Gestione gruppi non persistenti e hash groups
3. **TTL Custom**: Configurazione scadenze personalizzate
4. **Monitor Attività**: Statistiche comandi e slowlog
5. **Esplora Chiavi**: Browser e visualizzatore chiavi Redis
6. **Preset**: Caricamento rapido configurazioni ottimizzate

## Crediti

Sviluppato per la community WordPress

Compatibile con:
- [WP Redis](https://github.com/pantheon-systems/wp-redis) by Pantheon
- [WooCommerce](https://woocommerce.com)
- [YITH Request a Quote](https://yithemes.com)

---

**Buon caching!**
