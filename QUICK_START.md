# 🚀 GUIDA RAPIDA - WP Redis Manager

## Installazione in 3 Step

### STEP 1: Carica il Plugin
```bash
# Via FTP/cPanel
Carica cartella wp-redis-manager in /wp-content/plugins/

# Via WP-CLI
wp plugin install wp-redis-manager.zip --activate
```

### STEP 2: Attiva Plugin
- Vai su **Plugin** in WordPress
- Trova "WP Redis Manager"
- Clicca **Attiva**

### STEP 3: Configura
- Vai su **Strumenti → Redis Manager**
- Clicca **Test Connessione** (deve dire "Connesso")
- Carica un **Preset** (consigliato: WooCommerce Ottimizzato)
- Clicca **Salva Configurazione**
- Clicca **Svuota Cache**

✅ **FATTO! Redis è configurato!**

---

## 🎯 Setup per WooCommerce (5 minuti)

**Problema da risolvere:** Carrello condiviso tra utenti

**Soluzione:**

1. **Vai su Strumenti → Redis Manager**

2. **Tab Preset:**
   - Clicca "Carica Preset" su "WooCommerce Ottimizzato"

3. **Salva:**
   - Clicca "Salva Configurazione"
   - Clicca "Svuota Cache"

4. **Test:**
   - Apri 2 browser (Chrome + Firefox)
   - Aggiungi prodotti diversi in ogni browser
   - Verifica che i carrelli siano separati

5. **Monitora:**
   - Controlla "Hit Rate" > 80%
   - Monitora "Redis Calls" < 1000

✅ **Carrello Risolto!**

---

## 📊 Interpretare le Statistiche

**Dashboard mostra 4 metriche:**

1. **Cache Hits** (verde è meglio)
   - Quante volte dati trovati in cache
   - Più alto = meglio

2. **Cache Misses** (basso è meglio)
   - Quante volte dati NON in cache
   - Più basso = meglio

3. **Hit Rate** (target: >80%)
   - 🟢 >85% = Eccellente
   - 🟡 70-85% = Buono
   - 🔴 <70% = Da migliorare

4. **Redis Calls**
   - Numero chiamate a Redis
   - <500 normale
   - <1000 accettabile
   - >1000 ottimizza

---

## ⚡ Fix Rapidi Problemi Comuni

### PROBLEMA 1: "Redis Non Disponibile"
```bash
# Verifica Redis attivo
redis-cli ping  # Deve dire: PONG

# Se non risponde, avvia
sudo systemctl start redis
```

### PROBLEMA 2: Hit Rate <70%
**Soluzione:**

1. Apri `wp-config.php`
2. Aggiungi (PRIMA di wp-settings.php):
```php
define( 'WP_REDIS_USE_CACHE_GROUPS', true );
```
3. Salva, poi nel plugin:
   - Svuota Cache
   - Aspetta 10 minuti
   - Ricontrolla hit rate

### PROBLEMA 3: Carrello Ancora Condiviso
**Soluzione:**

1. Vai su **Tab: Gruppi Cache**
2. Verifica che ci sia:
```
wc_session_id
cart
wc_cart
```
3. Se manca, aggiungi
4. Salva + Svuota Cache
5. Test 2 browser

### PROBLEMA 4: Redis Si Riempie
**Soluzione:**

1. Vai su **Tab: TTL Custom**
2. Aggiungi:
```
posts:3600
options:3600
transient:1800
```
3. Salva
4. Aspetta che vecchie chiavi scadano

---

## 🎨 Cosa Fare con Ogni Tab

### Tab 1: Gruppi Cache
**Quando usare:**
- Setup iniziale
- Problemi carrello
- Ottimizzazione performance

**Cosa fare:**
- Carica preset WooCommerce
- Oppure inserisci gruppi manualmente

### Tab 2: Esclusione Pagine
**Quando usare:**
- Pagine dinamiche che non devono cachare
- Area utente
- Checkout problematico

**Cosa fare:**
- Seleziona pagine da checklist
- Aggiungi URL tipo `/cart`, `/checkout`

### Tab 3: TTL Custom
**Quando usare:**
- Redis si riempie troppo
- Dati obsoleti
- Ottimizzazione memoria

**Cosa fare:**
- Imposta scadenze ai gruppi
- Valori comuni: 1800, 3600, 7200

### Tab 4: Preset
**Quando usare:**
- Setup iniziale veloce
- Non sai cosa configurare
- Reset a configurazione pulita

**Cosa fare:**
- Scegli preset adatto
- WooCommerce → ecommerce
- Blog → siti content
- Performance → massima velocità

---

## 🔧 Configurazione wp-config.php Ottimale

**Apri wp-config.php e aggiungi PRIMA di `wp-settings.php`:**

```php
// ===== REDIS CONFIGURATION =====

// Abilita hash groups (CRITICO per performance)
define( 'WP_REDIS_USE_CACHE_GROUPS', true );

// TTL default 1 ora
define( 'WP_REDIS_DEFAULT_EXPIRE_SECONDS', 3600 );

// Salt unico (importante se più siti)
define( 'WP_CACHE_KEY_SALT', DB_NAME . '_' );

// Server Redis
$redis_server = array(
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'auth'     => '',  // password se configurata
    'database' => 0,
);
```

**Poi nel plugin:**
1. Carica preset WooCommerce
2. Salva + Svuota Cache

✅ **Configurazione Completa!**

---

## 📋 Checklist Post-Installazione

Verifica che tutto funzioni:

- [ ] Plugin attivato
- [ ] Redis Manager appare in menu Strumenti
- [ ] Test Connessione = "Connesso"
- [ ] Preset caricato
- [ ] Configurazione salvata
- [ ] Cache svuotata
- [ ] Hit Rate > 70%
- [ ] Test carrello (2 browser) = separati
- [ ] Nessun errore PHP log
- [ ] Sito funziona normalmente

Se tutto ✅ = **SUCCESS!** 🎉

---

## 🆘 Quando Chiedere Aiuto

**Prima di chiedere supporto, fornisci:**

1. Screenshot dashboard Redis Manager
2. Output di:
```bash
redis-cli ping
redis-cli INFO stats
wp plugin list
```
3. Hit Rate attuale
4. Descrizione problema esatto
5. Cosa hai già provato

---

## 🎯 Obiettivi Target

Dopo configurazione corretta:

| Metrica | Target | Se Sotto |
|---------|--------|----------|
| Hit Rate | >85% | Abilita hash groups |
| Redis Calls | <500 | Già ottimo |
| Caricamento Pagina | <2s | Ottimizza TTL |
| Separazione Carrelli | 100% | Verifica gruppi non persistenti |

---

## 💡 Tips & Tricks

**Tip 1:** Usa preset come base, poi personalizza
**Tip 2:** Monitora hit rate giornalmente primi giorni
**Tip 3:** Non disabilitare cache su troppe pagine
**Tip 4:** TTL più bassi = dati più freschi ma più carico DB
**Tip 5:** Svuota cache dopo ogni modifica configurazione

---

## 🚀 Next Steps

Dopo setup base:

1. **Settimana 1:** Monitora hit rate
2. **Settimana 2:** Ottimizza TTL se necessario
3. **Settimana 3:** Aggiungi esclusioni se servono
4. **Mensile:** Controlla memoria Redis

---

**Tutto qui! Configurazione Redis completata in 5 minuti! 🎉**

**Problemi? Rileggi sezione "Fix Rapidi" sopra!**
