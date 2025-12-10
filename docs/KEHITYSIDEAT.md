# Sivustamo Ohjeet - Kehitysideat

## Tulevat ominaisuudet

### 1. WHMCS Knowledge Base -integraatio
**Prioriteetti:** Keskitaso
**Status:** Suunnitteilla

WHMCS:ssä ei ole suoraa Knowledge Base API:a, joten toteutus vaatii oman addon-moduulin.

**Toteutussuunnitelma:**
- Luo WHMCS addon-moduuli (`sivustamo_kb`)
- Moduuli synkronoi artikkelit suoraan `tblknowledgebase` -tauluun
- Kategoriat → `tblknowledgebasecats`
- Synkronointi voidaan ajastaa cronilla tai manuaalisesti

**WHMCS tietokantataulut:**
```sql
tblknowledgebase:
- id, catid, title, article, views, useful, votes, private, order, language

tblknowledgebasecats:
- id, parentid, name, description, hidden, catorder, language
```

**Hyödyt:**
- Ohjeet näkyvät WHMCS-portaalissa asiakkaille
- SEO-hyödyt (Google-indeksointi)
- Yhtenäinen ohjeistus kaikissa kanavissa

---

### 2. Automaattinen sivustojen provisiointi
**Prioriteetti:** Korkea
**Status:** Työn alla

**Tavoite:** Massojen sivustojen lisäys ja API-avainten jakelu.

**Toteutus:**
1. WP-CLI komento masteriin CSV-tuontia varten
2. Ympäristömuuttujatuki client-lisäosaan
3. Skripti joka lukee CSV:n ja päivittää wp-config.php tiedostot

**CSV-tuonti formaatti (sisään):**
```csv
domain,dev_domain,ryhmat,nimi
asiakas1.fi,asiakas1.sivustamo.dev,oletus,Asiakas 1 Oy
asiakas2.fi,,oletus;woocommerce,Asiakas 2 Oy
```

**CSV-vienti formaatti (ulos):**
```csv
domain,api_key,secret,master_url
asiakas1.fi,SVM_XXXX-XXXX-XXXX-XXXX,abc123...,https://sivustamo.dev
```

---

### 3. Lisäosakohtainen ohjeiden jako
**Prioriteetti:** Matala
**Status:** Idea

Client-lisäosa voisi tunnistaa asennetut lisäosat ja ilmoittaa masterille, jolloin ohjeet voidaan kohdentaa automaattisesti.

**Esimerkki:**
- WooCommerce asennettu → WooCommerce-ohjeet näkyvät
- WPML asennettu → Monikielisyysohjeet näkyvät

---

### 4. Hakutoiminto
**Prioriteetti:** Keskitaso
**Status:** Idea

Lisää hakutoiminto ohjeiden frontend-näkymään.

---

### 5. Ohjeiden versiohistoria
**Prioriteetti:** Matala
**Status:** Idea

Näytä clientille kun ohje on päivitetty ja mitä muutoksia tehtiin.

---

## Tekniset parannukset

### Välimuisti (Cache)
- Lisää transient-välimuisti API-vastauksille
- Vähentää kuormaa masterilla

### Webhook-tuki
- Master lähettää webhookin kun ohje päivittyy
- Client päivittää välittömästi (ei odota cronia)

### Multisite-tuki
- Testaa ja dokumentoi WordPress Multisite -yhteensopivuus
