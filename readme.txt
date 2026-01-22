=== WP Redis Manager ===
Contributors: antoninolumia
Donate link: https://github.com/quakef4
Tags: redis, cache, woocommerce, performance, object-cache, yith
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Interfaccia admin completa per gestire Redis cache in WordPress. Monitor attività, esplora chiavi, gestione gruppi cache e preset ottimizzati.

== Description ==

WP Redis Manager fornisce un'interfaccia grafica completa per gestire la cache Redis in WordPress. Progettato per funzionare con WP Redis di Pantheon, offre funzionalità avanzate di monitoraggio e configurazione.

= Caratteristiche Principali =

**Gestione Gruppi Cache**

* Gruppi Non Persistenti: Configura gruppi che non vengono salvati in Redis (essenziale per carrelli WooCommerce)
* Redis Hash Groups: Abilita Redis hashes per performance migliorate (riduce fino al 70% le chiamate Redis)
* Global Groups: Gestisci gruppi condivisi per installazioni multisite

**Monitor Attività Redis (v1.1.0)**

* Informazioni Server: Versione Redis, modalità, sistema operativo, uptime
* Utilizzo Memoria: Memoria usata, picco, massimo, frammentazione
* Statistiche Comandi: Visualizza i comandi più utilizzati con grafici
* Slowlog: Monitora le query Redis lente con filtri

**Esplora Chiavi Redis (v1.1.0)**

* Ricerca con pattern wildcard
* Filtro per tipo (string, list, set, hash, etc.)
* Visualizzazione dettagli chiave
* Eliminazione chiavi singole

**Preset Configurazioni**

* YITH Request a Quote: Configurazione sicura per plugin YITH
* WooCommerce Standard: Ottimizzato per ecommerce
* Blog/Magazine: Perfetto per siti content-heavy
* Multisite: Configurazione per network WordPress
* Performance Massime: Cache aggressiva

**Dashboard Monitoring**

* Statistiche cache in tempo reale (hits, misses, hit rate)
* Test connessione Redis
* Info server Redis
* Svuota cache con un click

= Requisiti =

* WordPress 5.0 o superiore
* PHP 7.4 o superiore
* WP Redis 1.4.7+ (deve essere già installato)
* Redis server attivo

= Link Utili =

* [Repository GitHub](https://github.com/quakef4/Wp-redis-manager)
* [Segnala Bug](https://github.com/quakef4/Wp-redis-manager/issues)
* [Documentazione](https://github.com/quakef4/Wp-redis-manager#readme)

== Installation ==

= Installazione Automatica =

1. Vai su Plugin > Aggiungi nuovo nel pannello WordPress
2. Cerca "WP Redis Manager"
3. Clicca "Installa ora" e poi "Attiva"

= Installazione Manuale =

1. Scarica il plugin da GitHub
2. Carica la cartella `wp-redis-manager` in `/wp-content/plugins/`
3. Attiva il plugin dal menu Plugin
4. Vai su Strumenti > Redis Manager

= Configurazione =

1. Vai su Strumenti > Redis Manager
2. Verifica la connessione Redis cliccando "Test Connessione"
3. Carica un preset dalla tab "Preset" (consigliato "YITH" se usi YITH plugins)
4. Salva la configurazione
5. Svuota la cache per applicare le modifiche

== Frequently Asked Questions ==

= È necessario WP Redis? =

Sì, WP Redis Manager è un'interfaccia per gestire WP Redis di Pantheon. Devi avere WP Redis installato e configurato.

= Funziona con WooCommerce? =

Sì, include preset ottimizzati per WooCommerce che escludono carrello e sessioni dalla cache.

= Funziona con YITH Request a Quote? =

Sì, include un preset specifico per YITH che esclude le options dalla cache Redis per evitare problemi con le sessioni quote.

= Perché il carrello viene condiviso tra utenti? =

Devi configurare i gruppi non persistenti. Carica il preset "WooCommerce" o "YITH" dalla tab Preset.

= Perché ho problemi dopo molte ore di login? =

Se usi YITH plugins, carica il preset "YITH" che esclude options e user_meta dalla cache Redis.

= Come monitoro le performance Redis? =

Vai alla tab "Monitor Attività" per vedere statistiche comandi, slowlog e utilizzo memoria in tempo reale.

= Come cerco una chiave specifica in Redis? =

Vai alla tab "Esplora Chiavi", inserisci un pattern (es. `*session*`) e clicca "Cerca Chiavi".

== Screenshots ==

1. Dashboard principale con stato Redis e statistiche cache
2. Gestione gruppi cache (non persistenti, hash groups)
3. Monitor Attività con statistiche comandi Redis
4. Slowlog per identificare query lente
5. Esplora Chiavi con ricerca pattern
6. Dettagli chiave con visualizzazione valore
7. Preset configurazioni per casi d'uso comuni

== Changelog ==

= 1.1.0 =
* NUOVO: Monitor Attività Redis completo
  * Informazioni server, memoria, client
  * Statistiche comandi con filtri e grafici
  * Slowlog con filtri per durata
  * Auto-refresh ogni 10 secondi
* NUOVO: Esplora Chiavi Redis
  * Ricerca con pattern wildcard
  * Filtro per tipo
  * Visualizzazione dettagli chiave
  * Eliminazione chiavi singole
* NUOVO: Preset YITH Request a Quote ottimizzato
* Migliorato: Interfaccia responsive
* Migliorato: Formattazione valori (JSON/PHP deserializzato)
* Corretto: Problema sessioni YITH dopo molte ore di login

= 1.0.0 =
* Release iniziale
* Gestione gruppi cache
* TTL personalizzati
* 4 preset configurazioni
* Monitoring real-time
* Test connessione Redis
* Flush cache

== Upgrade Notice ==

= 1.1.0 =
Aggiornamento importante con Monitor Attività e Esplora Chiavi. Include fix critico per utenti YITH Request a Quote.

= 1.0.0 =
Prima versione stabile.

== Additional Info ==

= Compatibilità =

* WordPress 5.0+
* WP Redis 1.4.7+
* WooCommerce (tutte le versioni recenti)
* YITH Request a Quote
* YITH WooCommerce Wishlist
* WordPress Multisite
* PHP 7.4, 8.0, 8.1, 8.2
* Redis 4.0+ (consigliato)

= Sicurezza =

* Tutte le azioni richiedono capability `manage_options`
* AJAX protetto con nonce
* Input sanitizzati
* Output escaped

= Supporto =

Per supporto tecnico, apri una issue su GitHub:
https://github.com/quakef4/Wp-redis-manager/issues
