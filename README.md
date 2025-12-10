# Sivustamo Ohjeet

Keskitetty ohjeiden hallintajärjestelmä WordPress-sivustoille.

## Yleiskatsaus

Järjestelmä koostuu kahdesta WordPress-lisäosasta:

1. **Sivustamo Master** - Hallintalisäosa master-sivustolle (sivustamo.dev)
2. **Sivustamo** - Client-lisäosa asiakassivustoille

## Ominaisuudet

### Master-lisäosa (v1.1.0)
- Ohjeiden ja kategorioiden hallinta Gutenberg-editorilla
- **Käyttöoikeusryhmät** - Ryhmäpohjainen ohjeiden jakelu
- Sivustojen/lisenssien hallinta (API-avaimet + domain-validointi)
- REST API ohjeiden jakeluun
- Statistiikka ja palautteiden seuranta
- Dashboard widget
- **WP-CLI komennot** massojen hallintaan

### Client-lisäosa (v1.0.7)
- Automaattinen synkronointi masterilta (kerran päivässä)
- Manuaalinen synkronointi
- Paikalliset ohjeet
- Käyttöoikeushallinta (admin, editor, shop_manager)
- Responsiivinen frontend (`/sivustamo-ohjeet/`)
- Palautelomake (peukut, tähdet, kommentti)
- Dashboard widget
- Shortcodet
- **Ympäristömuuttujatuki** automatisointiin

---

## Asennus

### Master-sivusto

1. Kopioi `sivustamo-master/` kansioon `wp-content/plugins/`
2. Aktivoi lisäosa WordPressin hallintapaneelista
3. Luo ryhmä: **Sivustamo → Ryhmät → Lisää ryhmä**
   - Nimeä "Oletus" ja valitse "Tämä on oletusryhmä"
4. Luo kategorioita: **Sivustamo → Kategoriat**
5. Luo ohjeita: **Sivustamo → Ohjeet**
6. Luo sivustoja: **Sivustamo → Sivustot** (saat API-avaimen)

### Client-sivusto

#### Vaihtoehto 1: Manuaalinen konfigurointi

1. Kopioi `sivustamo/` kansioon `wp-content/plugins/`
2. Aktivoi lisäosa
3. Mene: **Sivustamo Ohjeet → Asetukset**
4. Syötä:
   - Master URL (esim. `https://sivustamo.dev`)
   - API-avain
   - Secret
5. Klikkaa "Testaa yhteys" ja "Synkronoi ohjeet"

#### Vaihtoehto 2: Ympäristömuuttujat (suositeltu tuotantoon)

Lisää `wp-config.php` tiedostoon:

```php
// Sivustamo API -asetukset
define('SIVUSTAMO_MASTER_URL', 'https://sivustamo.dev');
define('SIVUSTAMO_API_KEY', 'SVM_XXXX-XXXX-XXXX-XXXX');
define('SIVUSTAMO_SECRET', '64-merkkinen-salaisuus-tähän');
```

Kun asetukset on määritelty wp-config.php:ssä:
- Kentät näkyvät lukittuina asetussivulla
- Asetuksia ei voi muuttaa hallintapaneelista
- Mahdollistaa automatisoinnin (Ansible, Puppet, skriptit)

---

## Käyttöoikeusryhmät

Ryhmäjärjestelmä mahdollistaa ohjeiden kohdentamisen tietyille sivustoille.

### Esimerkki

```
Ryhmät:
├── Oletus (oletusryhmä) - Kaikki sivustot
├── WooCommerce - Verkkokauppasivustot
├── Premium - Premium-asiakkaat
└── Starter - Peruspaketti
```

### Miten ryhmät toimivat

1. **Sivusto kuuluu ryhmiin** - Valitaan sivuston asetuksissa
2. **Ohje näkyy ryhmille** - Valitaan ohjeen asetuksissa
3. **Synkronointi** - Client saa vain ryhmilleen kuuluvat ohjeet

### Käyttötapaukset

| Ryhmä | Käyttötarkoitus |
|-------|-----------------|
| Oletus | Yleiset ohjeet kaikille (WordPress-perusteet) |
| WooCommerce | Verkkokauppa-ohjeet (tuotteet, tilaukset, maksut) |
| WPML | Monikielisyysohjeet |
| Premium | Edistyneet ominaisuudet premium-asiakkaille |

---

## WP-CLI komennot (Master)

### Sivustojen massatuonti CSV:stä

```bash
wp sivustamo import sivustot.csv --skip-header
```

#### Tuonti-CSV formaatti (sisään)

```csv
domain,dev_domain,ryhmat,nimi
asiakas1.fi,asiakas1.sivustamo.dev,oletus,Asiakas 1 Oy
asiakas2.fi,,oletus;woocommerce,Asiakas 2 Oy
verkkokauppa.fi,verkkokauppa.dev.local,oletus;woocommerce;premium,Verkkokauppa Oy
```

| Sarake | Kuvaus |
|--------|--------|
| domain | Tuotantoympäristön domain |
| dev_domain | Kehitysympäristön domain (valinnainen) |
| ryhmat | Ryhmien slugit puolipisteillä erotettu |
| nimi | Sivuston nimi |

#### Vienti-CSV formaatti (ulos)

Komento luo automaattisesti `sivustamo-output.csv`:

```csv
domain,api_key,secret,master_url
asiakas1.fi,SVM_A1B2-C3D4-E5F6-G7H8,abc123def456...,https://sivustamo.dev
asiakas2.fi,SVM_I9J0-K1L2-M3N4-O5P6,ghi789jkl012...,https://sivustamo.dev
```

### Ryhmien listaus

```bash
wp sivustamo groups
```

Tuloste:
```
+----+-------------+-------------+-----------+--------+
| ID | Nimi        | Slug        | Sivustoja | Oletus |
+----+-------------+-------------+-----------+--------+
| 10 | Oletus      | oletus      | 45        | Kyllä  |
| 11 | WooCommerce | woocommerce | 12        | -      |
| 12 | Premium     | premium     | 5         | -      |
+----+-------------+-------------+-----------+--------+
```

### Ryhmän luonti

```bash
# Perusluonti
wp sivustamo create-group "WooCommerce-sivustot" --slug=woocommerce

# Oletusryhmä
wp sivustamo create-group "Oletus" --slug=oletus --default

# Kuvauksella
wp sivustamo create-group "Premium" --slug=premium --description="Premium-asiakkaiden lisäohjeet"
```

### API-avainten vienti

```bash
# Vie kaikki sivustot
wp sivustamo export --output=kaikki-sivustot.csv

# Vie vain tietyn ryhmän sivustot
wp sivustamo export --group=woocommerce --output=woocommerce-sivustot.csv

# Vie vain aktiiviset
wp sivustamo export --active-only
```

### Esimerkki: Täysi workflow

```bash
# 1. Luo ryhmät
wp sivustamo create-group "Oletus" --slug=oletus --default
wp sivustamo create-group "WooCommerce" --slug=woocommerce
wp sivustamo create-group "Premium" --slug=premium

# 2. Tarkista ryhmät
wp sivustamo groups

# 3. Tuo sivustot
wp sivustamo import asiakkaat.csv --skip-header

# 4. Vie API-avaimet skriptiä varten
wp sivustamo export --output=api-avaimet.csv
```

---

## Automatisoitu provisiointi

### Skripti wp-config.php päivitykseen

Kun olet tuonut sivustot ja vienyt API-avaimet, voit käyttää esim. tällaista skriptiä:

```bash
#!/bin/bash
# provision-sivustamo.sh

CSV_FILE="api-avaimet.csv"
SITES_ROOT="/var/www/clients"

# Ohita otsikkorivi
tail -n +2 "$CSV_FILE" | while IFS=',' read -r domain api_key secret master_url; do
    WP_CONFIG="$SITES_ROOT/$domain/wp-config.php"

    if [ -f "$WP_CONFIG" ]; then
        # Lisää asetukset ennen "That's all" -kommenttia
        sed -i "/That's all/i\\
// Sivustamo API\\
define('SIVUSTAMO_MASTER_URL', '$master_url');\\
define('SIVUSTAMO_API_KEY', '$api_key');\\
define('SIVUSTAMO_SECRET', '$secret');\\
" "$WP_CONFIG"

        echo "Päivitetty: $domain"
    else
        echo "VAROITUS: wp-config.php ei löydy: $domain"
    fi
done
```

---

## Käyttö

### Ohjeiden näyttäminen

Ohjeet näkyvät automaattisesti osoitteessa:
```
https://sivustosi.fi/sivustamo-ohjeet/
```

### Shortcodet

```php
// Näytä kaikki ohjeet
[sivustamo_ohjeet]

// Näytä tietyn kategorian ohjeet
[sivustamo_ohjeet kategoria="woocommerce"]

// Näytä kategoriat
[sivustamo_kategoriat]

// Upota yksittäinen ohje
[sivustamo_ohje id="123"]
```

### Käyttöoikeudet

Oletuksena ohjeet näkyvät:
- Administrator
- Editor
- Shop Manager (WooCommerce)

Käyttöoikeuksia voi muokata ohje- ja kategoriakohtaisesti.

---

## REST API

### Endpoints (Master)

```
GET  /wp-json/sivustamo/v1/verify
GET  /wp-json/sivustamo/v1/ohjeet
GET  /wp-json/sivustamo/v1/ohje/{id}
GET  /wp-json/sivustamo/v1/kategoriat
GET  /wp-json/sivustamo/v1/media/{id}
POST /wp-json/sivustamo/v1/stats/view
POST /wp-json/sivustamo/v1/stats/feedback
```

### Autentikointi

```
X-Sivustamo-Key: {api_key}
X-Sivustamo-Signature: {hmac_sha256(body + timestamp, secret)}
X-Sivustamo-Timestamp: {unix_timestamp}
```

Domain-validointi: Pyynnön `Origin` tai `Referer` -header tarkistetaan sivuston rekisteröityä domainia vastaan.

---

## Lisäosan poisto

### Deaktivointi
- Ei poista dataa
- Vain poistaa cron-tehtävät

### Poisto (Delete)
- **Client**: Poistaa kaikki synkronoidut JA paikalliset ohjeet, kategoriat, asetukset
- **Master**: Poistaa kaikki ohjeet, sivustot, ryhmät, kategoriat, statistiikka-taulut

---

## Vaatimukset

- WordPress 5.8+
- PHP 7.4+
- WP-CLI (CLI-komennoille)

## Tekijä

**Esko Junnila / Sivustamo Oy**
- https://sivustamo.fi

## Lisenssi

GPL-2.0+
