# Open Redis Manager

[![Version](https://img.shields.io/badge/version-1.0.2-blue.svg)](https://github.com/quakef4/Wp-redis-manager)
[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Plugin WordPress standalone per la gestione completa della cache Redis. Include il proprio drop-in `object-cache.php`, monitor attività in tempo reale, esplora chiavi, gestione gruppi cache, configurazione wp-config.php e preset ottimizzati per WooCommerce e YITH.

**Nessuna dipendenza da terzi** — si connette direttamente al server Redis tramite l'estensione phpredis.

## Caratteristiche

### Drop-in Object Cache Integrato
- Drop-in `object-cache.php` proprietario con implementazione completa di `WP_Object_Cache`
- Installazione, aggiornamento e rimozione direttamente dall'interfaccia admin
- Rilevamento automatico di drop-in esterni già installati
- Supporto serializzazione PHP e igbinary

### Configurazione Redis da Interfaccia
- Gestione completa delle costanti `SRC_REDIS_*` in `wp-config.php`
- Connessione via TCP o Unix socket
- Selezione database (0-15) per isolamento siti
- Prefisso chiavi personalizzabile
- Test connessione in tempo reale
- Backup automatico prima di ogni modifica a wp-config.php

### Gestione Gruppi Cache
- **Gruppi Non Persistenti**: Configura gruppi che restano solo in RAM (essenziale per carrelli WooCommerce)
- **Redis Hash Groups**: Abilita Redis hashes per performance migliorate (riduce fino al 70% le chiamate Redis)
- **Global Groups**: Gestisci gruppi condivisi per installazioni multisite

### TTL Personalizzati
- Imposta scadenze diverse per ogni gruppo cache
- Valori predefiniti per casi d'uso comuni
- Ottimizza memoria Redis evitando dati obsoleti

### Preset Configurazioni
Carica rapidamente configurazioni ottimizzate per:
- **WooCommerce Standard**: Ottimizzato per ecommerce con carrello separato
- **YITH Request a Quote**: Configurazione sicura per YITH plugin
- **Multi-Dominio (10 siti)**: TTL aggressivi per server con molti siti WooCommerce
- **Blog/Magazine**: Perfetto per siti content-heavy
- **WordPress Multisite**: Configurazione per network WordPress
- **Prestazioni Massime**: Cache aggressiva per massima velocità

### Monitor Attività Redis
Monitora le attività Redis in tempo reale con interfaccia dettagliata:

- **Informazioni Server**: Versione Redis, modalità, sistema operativo, uptime, porta
- **Utilizzo Memoria**: Memoria usata, picco, massimo, frammentazione, memoria Lua
- **Client Connessi**: Numero client connessi, bloccati, tracking
- **Keyspace**: Database utilizzati con conteggio chiavi e TTL medio

#### Statistiche Comandi
Visualizza i comandi Redis più utilizzati con:
- Filtro per nome comando (es: GET, SET, HGET)
- Ordinamento per chiamate, tempo totale o tempo medio
- Limitazione top 10/25/50 o tutti
- Codifica colori: lettura (verde), scrittura (giallo), info (blu)

#### Slowlog (Query Lente)
Monitora le query Redis che superano la soglia configurata:
- Filtro per comando
- Filtro per durata minima (> 1ms, > 10ms, > 100ms)
- Timestamp, durata, comando completo e client

### Esplora Chiavi Redis
Browser completo per le chiavi memorizzate in Redis:

- **Ricerca con Pattern**: Usa `*` come wildcard (es: `wp_*:posts:*`, `*session*`)
- **Filtro per Tipo**: String, List, Set, Sorted Set, Hash, Stream
- **Limite Risultati**: 50, 100, 200, 500 chiavi
- **Paginazione**: Carica più risultati incrementalmente

#### Dettagli Chiave
Ispeziona qualsiasi chiave con modal dettagliata:
- Tipo, TTL, encoding interno, dimensione memoria
- Visualizzazione valore formattata:
  - **String**: testo con JSON/PHP deserializzato automaticamente
  - **List**: lista ordinata elementi
  - **Set**: insieme elementi
  - **Sorted Set**: tabella membro/score
  - **Hash**: tabella campo/valore
  - **Stream**: entries JSON formattate
- Eliminazione chiave singola con conferma

### Dashboard Monitoring
- Statistiche cache in tempo reale (hits, misses, hit rate)
- Test connessione Redis
- Info server Redis (versione, memoria, uptime)
- Svuota cache con un click

## Installazione

### Requisiti
- WordPress 5.0 o superiore
- PHP 7.4 o superiore
- Estensione **phpredis** installata
- Redis server attivo (versione 4.0+ consigliata per funzionalità complete)

> **Nota:** Questo plugin NON richiede WP Redis o altri plugin Redis — include il proprio drop-in object-cache.php.

### Metodo 1: Upload Manuale

1. Scarica il plugin
2. Carica la cartella nella directory `/wp-content/plugins/`
3. Attiva il plugin dal menu Plugin di WordPress
4. Vai su **Strumenti → Redis Manager**

### Metodo 2: Upload ZIP

1. Comprimi la cartella del plugin in un file .zip
2. In WordPress, vai su **Plugin → Aggiungi nuovo → Carica plugin**
3. Seleziona il file .zip e clicca "Installa ora"
4. Attiva il plugin
5. Vai su **Strumenti → Redis Manager**

## Configurazione

### Setup Iniziale Rapido

1. **Vai su Strumenti → Redis Manager**

2. **Configura la Connessione Redis** (tab "Configurazione")
   - Imposta host, porta e password se necessario
   - Clicca "Testa Connessione" per verificare
   - Clicca "Salva in wp-config.php" per scrivere le costanti

3. **Installa il Drop-in**
   - Nella sezione drop-in, clicca "Installa Drop-in"
   - Verifica che lo stato passi a "Installato"

4. **Carica un Preset** (consigliato per iniziare)
   - Vai alla tab "Preset"
   - Scegli "WooCommerce Standard" se usi WooCommerce
   - Oppure "Blog/Magazine" per un sito standard
   - Clicca "Carica Preset"

5. **Salva e Verifica**
   - Clicca "Salva Configurazione"
   - Monitora le statistiche per verificare che la cache funzioni

### Costanti di Configurazione

Il plugin gestisce queste costanti in `wp-config.php`:

| Costante | Descrizione | Default |
|----------|-------------|---------|
| `SRC_REDIS_HOST` | Host del server Redis | `127.0.0.1` |
| `SRC_REDIS_PORT` | Porta del server Redis | `6379` |
| `SRC_REDIS_SOCKET` | Path Unix socket (sovrascrive host/porta) | — |
| `SRC_REDIS_PASSWORD` | Password di autenticazione | — |
| `SRC_REDIS_DATABASE` | Indice database (0-15) | `0` |
| `SRC_REDIS_PREFIX` | Prefisso chiavi per isolamento | `$table_prefix` |
| `SRC_REDIS_MAXTTL` | TTL massimo in secondi (0 = illimitato) | `0` |
| `SRC_REDIS_TIMEOUT` | Timeout connessione in secondi | `1` |
| `SRC_REDIS_READ_TIMEOUT` | Timeout lettura in secondi | `1` |
| `SRC_REDIS_SERIALIZER` | Metodo serializzazione: `php`, `igbinary` | `php` |
| `SRC_REDIS_DISABLED` | Disabilita Redis, usa solo RAM | `false` |

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

> **IMPORTANTE per YITH Request a Quote**: NON includere "options" se usi YITH Request a Quote! Le sessioni YITH sono salvate come WordPress options.

#### TTL Custom (Tab: TTL Custom)

Formato: `gruppo:secondi` (uno per riga)
```
posts:3600
wc_var_prices:1800
options:7200
terms:7200
transient:3600
```

Valori di riferimento:
- `300` = 5 minuti
- `1800` = 30 minuti
- `3600` = 1 ora
- `7200` = 2 ore
- `86400` = 1 giorno

## Guida alle Funzionalità

### Monitor Attività

1. Vai su **Strumenti → Redis Manager**
2. Clicca sulla tab **"Monitor Attività"**
3. I dati vengono caricati automaticamente
4. Abilita l'auto-refresh per aggiornamento ogni 10 secondi

#### Interpretare le Statistiche Comandi
- **Comandi in Verde (READ)**: GET, HGET, SCAN — operazioni di lettura
- **Comandi in Giallo (WRITE)**: SET, HSET, DEL — operazioni di scrittura
- **Comandi in Blu (INFO)**: INFO, CONFIG — comandi informativi

#### Interpretare lo Slowlog
- **Durata Verde (<10ms)**: Performance normale
- **Durata Gialla (10-100ms)**: Attenzione, query lenta
- **Durata Rossa (>100ms)**: Query molto lenta, investigare

### Esplora Chiavi Redis

#### Come Cercare
1. Vai alla tab **"Esplora Chiavi"**
2. Inserisci un pattern (default: `*` per tutte)
3. Seleziona filtri opzionali (tipo, limite)
4. Clicca **"Cerca"**

#### Pattern di Ricerca
```
*              — Tutte le chiavi
wp_*           — Chiavi che iniziano con wp_
*:posts:*      — Chiavi contenenti :posts:
*session*      — Chiavi contenenti session
```

#### Eliminare Chiavi

> **ATTENZIONE**: L'eliminazione è irreversibile!

1. Dalla lista: clicca **"Elimina"** sulla riga
2. Dal modal dettagli: clicca **"Elimina Chiave"**
3. Conferma l'eliminazione

### Dashboard — Hit Rate

- **> 85%** = Eccellente (verde)
- **70-85%** = Buono (giallo)
- **< 70%** = Da ottimizzare (rosso)

## Setup Multi-Dominio

Per server con più siti WordPress che condividono lo stesso server Redis, ogni sito deve avere un isolamento cache. Due approcci:

### Approccio 1: Database Separati (Consigliato)

Assegna un database Redis diverso (0-15) a ogni sito tramite la tab "Configurazione":

| Sito | `SRC_REDIS_DATABASE` |
|------|---------------------|
| sito1.com | `0` |
| sito2.com | `1` |
| sito3.com | `2` |
| ... | ... |
| sito15.com | `15` |

### Approccio 2: Prefisso Unico

Se hai più di 16 siti, usa un prefisso unico per ciascuno:

```php
define( 'SRC_REDIS_PREFIX', 'sito1_' );
```

### Connessione via Unix Socket

Per performance migliori su server locale:

```php
define( 'SRC_REDIS_SOCKET', '/var/run/redis/redis.sock' );
```

Quando si usa un socket, host e porta vengono ignorati.

## Casi d'Uso Comuni

### Carrello WooCommerce Condiviso tra Utenti

**Soluzione:**
1. Vai alla tab "Preset"
2. Carica "WooCommerce Standard"
3. Salva e svuota cache
4. Testa con 2 browser: i carrelli devono essere separati

### Redis Si Riempie Troppo

**Soluzione:**
1. Vai alla tab "TTL Custom"
2. Aggiungi scadenze ai gruppi principali:
   ```
   posts:3600
   options:3600
   transient:1800
   ```
3. Salva e monitora memoria nella tab "Monitor Attività"

### Hit Rate Basso (< 70%)

**Soluzione:**
1. Vai alla tab "Gruppi Cache"
2. Aggiungi gruppi a "Redis Hash Groups":
   ```
   post_meta
   term_meta
   user_meta
   options
   ```
3. Salva e svuota cache
4. Monitora hit rate dopo 10 minuti

### Identificare Query Lente

**Soluzione:**
1. Vai alla tab "Monitor Attività"
2. Scorri alla sezione "Slowlog"
3. Analizza comandi con durata alta
4. Pattern comuni problematici: KEYS, SCAN con pattern ampi

### Trovare Chiavi Specifiche

**Soluzione:**
1. Vai alla tab "Esplora Chiavi"
2. Usa pattern specifico: es. `*woocommerce*`
3. Filtra per tipo se necessario
4. Ispeziona valori per debug

## Troubleshooting

### Plugin Non Appare nel Menu

**Verifica:**
- Plugin attivato?
- Hai permessi amministratore?

```bash
wp plugin list
wp plugin activate starter-redis-cache
```

### "Redis Non Connesso"

```bash
# Redis server attivo?
redis-cli ping  # Deve rispondere: PONG

# phpredis installato?
php -m | grep redis
```

Se phpredis non è presente, installalo:
```bash
# Debian/Ubuntu
sudo apt install php-redis && sudo systemctl restart php*-fpm

# CentOS/RHEL
sudo yum install php-redis && sudo systemctl restart php-fpm
```

### Monitor Attività Non Carica

**Possibili cause:**
- Redis versione < 4.0 (MEMORY USAGE non disponibile)
- Permessi insufficienti su Redis (ACL)
- Timeout connessione

**Verifica:**
```bash
redis-cli INFO server | grep redis_version
redis-cli ACL WHOAMI
```

### Slowlog Vuoto

Non ci sono query che superano la soglia. Verifica la configurazione Redis:
```bash
redis-cli CONFIG GET slowlog-log-slower-than
# Default: 10000 (10ms)

# Per abbassare soglia a 1ms:
redis-cli CONFIG SET slowlog-log-slower-than 1000
```

### Drop-in Non Si Installa

- Verifica che `wp-content/` sia scrivibile dal webserver
- Controlla che non esista già un `object-cache.php` di un altro plugin
- Se esiste un drop-in esterno, rimuovilo manualmente:
  ```bash
  rm /percorso/wp-content/object-cache.php
  ```

### Configurazione Non Si Salva in wp-config.php

- Verifica che `wp-config.php` sia scrivibile dal webserver
- Il plugin crea un backup prima di ogni modifica
- Se il file ha un formato non standard, l'inserimento automatico potrebbe fallire

## Sicurezza

- Tutte le azioni richiedono capability `manage_options`
- AJAX protetto con nonce WordPress
- Input sanitizzati prima del salvataggio
- Output escaped nel rendering
- Eliminazione chiavi richiede conferma
- Backup automatico di wp-config.php prima delle modifiche

## Compatibilità

| Requisito | Versione |
|-----------|----------|
| WordPress | 5.0+ |
| PHP | 7.4, 8.0, 8.1, 8.2, 8.3 |
| phpredis | Richiesto |
| Redis Server | 4.0+ (consigliato) |
| WooCommerce | Tutte le versioni recenti |
| YITH Request a Quote | Con preset dedicato |
| WordPress Multisite | Supportato |

## Changelog

### 1.0.2
- Fix: Corretto cursore iniziale SCAN per phpredis nell'esplora chiavi
- Fix: Guida Unix socket corretta nel tab configurazione
- Fix: Serializzatore default cambiato da `auto` a `php` per evitare mismatch tra SAPI
- Fix: Validazione socket e messaggi errore migliorati nel test connessione
- Fix: DATABASE e PREFIX scritti sempre in wp-config.php
- Fix: Prevenuto autofill del browser nel campo host Redis

### 1.0.1
- Miglioramenti di stabilità e performance

### 1.0.0
- Release iniziale
- Drop-in object-cache.php integrato
- Gestione completa gruppi cache
- TTL personalizzati per gruppo
- 6 preset configurazioni
- Monitor attività Redis in tempo reale
- Esplora chiavi Redis con dettagli
- Configurazione Redis da interfaccia
- Gestione wp-config.php automatica
- Supporto WooCommerce e YITH
- Supporto multi-dominio e multisite

## Struttura del Plugin

```
starter-redis-cache.php          — File principale del plugin
drop-in/
  object-cache.php               — Drop-in WP_Object_Cache (Redis)
includes/
  class-src-admin.php            — Interfaccia admin e handler AJAX
  class-src-config.php           — Gestione costanti in wp-config.php
  class-src-dropin.php           — Installazione/rimozione drop-in
  class-src-woocommerce.php      — Integrazione WooCommerce
templates/
  admin-page.php                 — Template pagina admin
assets/
  js/admin.js                    — JavaScript interfaccia admin
  css/admin.css                  — Stili interfaccia admin
```

## Autore

**Antonino Lumia**

- GitHub: [github.com/quakef4](https://github.com/quakef4)
- Repository: [github.com/quakef4/Wp-redis-manager](https://github.com/quakef4/Wp-redis-manager)

## Contribuire

Le contribuzioni sono benvenute! Per contribuire:

1. Fai un fork del repository
2. Crea un branch per la tua feature (`git checkout -b feature/NuovaFeature`)
3. Committa le modifiche (`git commit -m 'Aggiunge NuovaFeature'`)
4. Pusha il branch (`git push origin feature/NuovaFeature`)
5. Apri una Pull Request

Per segnalare bug o richiedere feature:
- [Apri una Issue su GitHub](https://github.com/quakef4/Wp-redis-manager/issues)

## Licenza

Questo progetto è rilasciato sotto licenza [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
