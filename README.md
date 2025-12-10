# Sivustamo Ohjeet

Keskitetty ohjeiden hallintajärjestelmä WordPress-sivustoille.

## Yleiskatsaus

Järjestelmä koostuu kahdesta WordPress-lisäosasta:

1. **Sivustamo Master** - Hallintalisäosa master-sivustolle
2. **Sivustamo** - Client-lisäosa asiakassivustoille

## Ominaisuudet

### Master-lisäosa
- Ohjeiden ja kategorioiden hallinta
- Sivustojen/lisenssien hallinta (API-avaimet)
- REST API ohjeiden jakeluun
- Statistiikka ja palautteiden seuranta
- Dashboard widget

### Client-lisäosa
- Automaattinen synkronointi masterilta (kerran päivässä)
- Manuaalinen synkronointi
- Paikalliset ohjeet
- Käyttöoikeushallinta (admin, editor, shop_manager)
- Responsiivinen frontend (`/sivustamo-ohjeet/`)
- Palautelomake (peukut, tähdet, kommentti)
- Dashboard widget
- Shortcodet

## Asennus

### Master-sivusto

1. Kopioi `sivustamo-master/` kansioon `wp-content/plugins/`
2. Aktivoi lisäosa WordPressin hallintapaneelista
3. Luo kategorioita: **Sivustamo → Kategoriat**
4. Luo ohjeita: **Sivustamo → Ohjeet**
5. Luo sivustoja: **Sivustamo → Sivustot** (saat API-avaimen)

### Client-sivusto

1. Kopioi `sivustamo/` kansioon `wp-content/plugins/`
2. Aktivoi lisäosa
3. Mene: **Sivustamo Ohjeet → Asetukset**
4. Syötä:
   - Master URL (esim. `https://sivustamo.dev`)
   - API-avain
   - Secret
5. Klikkaa "Testaa yhteys" ja "Synkronoi ohjeet"

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
X-Sivustamo-Signature: {hmac_sha256}
X-Sivustamo-Timestamp: {unix_timestamp}
```

## Vaatimukset

- WordPress 5.8+
- PHP 7.4+

## Tekijä

**Esko Junnila / Sivustamo Oy**
- https://sivustamo.fi

## Lisenssi

GPL-2.0+
