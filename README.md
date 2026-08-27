# GPS-module registreren

Een formulier waarmee een dealer een GPS-module aan een fiets koppelt. Eén bestand: opmaak, stijl
en gedrag zitten er allemaal in. Geen bibliotheken, geen bouwstap, geen npm.

**▶ [Bekijk de werkende demo](https://sierkvdv.github.io/brekrgpsregistratie/)** — met een
nagebootste server, zodat elke uitkomst te zien is zonder dat er iets geactiveerd wordt.

| bestand | wat het is |
|---|---|
| [`gps-registratie.html`](gps-registratie.html) | **dit plak je op de pagina** — het formulier zelf |
| [`wordpress-endpoint.php`](wordpress-endpoint.php) | **dit zet je als plugin neer** — het endpoint erachter |
| [`index.html`](index.html) | de demo hierboven; de nagebootste server hoort er niet bij |

---

## Plakken

Sleep in Elementor een widget **HTML** op de pagina en plak `gps-registratie.html` er in zijn
geheel in. Dat is de hele handeling.

Werkt net zo goed in een Custom-HTML-blok van Gutenberg, in een pagesjabloon, of vanuit een
shortcode die de HTML teruggeeft.

Eén ding invullen — bovenin het `<script>`-blok staat:

```js
var ENDPOINT = '';
```

Daar komt het adres van jullie eigen endpoint. Zolang het leeg is valideert het formulier wel,
maar verstuurt het niets. Handig om eerst de opmaak in te passen.

## In de huisstijl zetten

Bovenin het `<style>`-blok staat alles als variabele:

```css
--gps-kleur: #111111;        /* accent: knop en focusrand */
--gps-kleur-rand: #cccccc;
--gps-kleur-fout: #c0392b;
--gps-radius: 4px;
--gps-max-breedte: 32rem;
```

Het lettertype erft van het thema (`font-family: inherit`). Alle klassenamen beginnen met
`gps-widget__`, dus ze botsen niet met Elementor of met het thema.

---

## Het endpoint

Er ligt een kant-en-klare WordPress-plugin: [`wordpress-endpoint.php`](wordpress-endpoint.php).
Zet hem in `wp-content/plugins/gps-registratie/`, zet de plugin aan, en het adres is
`/wp-json/gps/v1/activeer`. Vul dat adres in bij `ENDPOINT` in het formulier en de twee praten
met elkaar.

Die plugin doet alles: de invoer opnieuw controleren aan de serverkant, een rem tegen het raden
van IMEI-nummers, en het vertalen van het antwoord naar de drie uitkomsten die het formulier
kent. **Eén functie is bewust leeg gelaten**: de aanroep naar de leverancier van de GPS-modules.
Daarvoor is een account en hun documentatie nodig, en die kan hier niet in.

Zolang die functie leeg is, wijst het endpoint elke aanvraag netjes af en schrijft het een regel
in het logboek — zodat je de hele keten kunt testen voordat de koppeling er is.

Wat je bij de leverancier moet opvragen staat in de plugin zelf; het is een lijstje van vier
vragen.

### De vorm tussen formulier en endpoint

Het formulier praat verder met niets.

**Verzoek**

```
POST <jullie endpoint>
Content-Type: application/json

{ "frameNumber": "EFY8053687", "imei": "351756051523999" }
```

**Geslaagd** — statuscode `200`. Het lichaam wordt niet gelezen.

**Mislukt** — een statuscode buiten de 200-reeks, met een `status`-veld in het lichaam:

```json
{ "status": "ERROR_REPEATED_IMEI" }
```

| `status` | wat de gebruiker ziet |
|---|---|
| `ERROR_CLAIM_DEVICE` | Door een onverwachte fout is het niet mogelijk de GPS-module te koppelen |
| `ERROR_REPEATED_IMEI` | Dubbele IMEI. Het IMEI-nummer bestaat al |
| *iets anders, of geen `status`* | Er is iets fout gegaan. Probeer het opnieuw. |

De veldnaam `imei` is met kleine letters — zo staat hij ook in het bestaande dealerportaal.

Welke leverancier er achter dit endpoint hoort en hoe je daar een account krijgt, hoor je van
Sierk. Dat staat bewust niet in deze openbare repository.

---

## De validatieregels

Per veld verschijnt de **eerste** regel die niet klopt. Een melding verschijnt pas nadat de
gebruiker het veld heeft verlaten, of nadat er op Indienen is gedrukt.

**Framenummer** — maximaal 50 tekens invoerbaar

1. verplicht → *Dit veld is verplicht*
2. alleen cijfers en letters, `^[0-9a-zA-Z]+$` → *Voer alleen cijfers en letters in*
3. minimaal 10 tekens → *Voer minimaal 10 tekens in*

**IMEI** — maximaal 17 tekens invoerbaar

1. verplicht → *Dit veld is verplicht*
2. alleen cijfers, `^[0-9]+$` → *Voer alleen cijfers in*
3. 15 tot en met 17 cijfers → *Het IMEI-nummer moet tussen de 15 en 17 cijfers lang zijn*

## Andere talen

Het formulier staat in het Nederlands. Vervang de waarden in het `TEKST`-object om te wisselen,
of vul ze uit jullie eigen vertaalmechanisme.

| | Nederlands | Engels |
|---|---|---|
| titel | Registreer GPS-module | Register GPS module |
| verplicht | Dit veld is verplicht | This field is required |
| alleen letters/cijfers | Voer alleen cijfers en letters in | Enter only digits and letters |
| minimaal 10 | Voer minimaal 10 tekens in | Enter at least 10 characters |
| alleen cijfers | Voer alleen cijfers in | Enter digits only |
| IMEI-lengte | Het IMEI-nummer moet tussen de 15 en 17 cijfers lang zijn | The IMEI number must be between 15 and 17 digits |
| geslaagd | De aanvraag is geslaagd | The request was successful |
| algemene fout | Er is iets fout gegaan. Probeer het opnieuw. | Something went wrong. Please try again. |
| `ERROR_CLAIM_DEVICE` | Door een onverwachte fout is het niet mogelijk de GPS-module te koppelen | Impossible to pair the GPS module due to an unexpected error |
| `ERROR_REPEATED_IMEI` | Dubbele IMEI. Het IMEI-nummer bestaat al | Duplicate IMEI. The IMEI number already exists |

Duits en Frans zijn op dezelfde manier beschikbaar; vraag ze op als je ze nodig hebt.

---

## Twee dingen die er bewust niet in zitten

**Geen inlogcontrole.** Dit is alleen het formulier. Staat het op een openbare pagina, dan hoort
de controle wie er mag activeren aan de serverkant te gebeuren — in het endpoint, niet hier.

**Geen modelnamen in de uitleg.** Welke modellen een GPS-module ondersteunen verschilt per merk;
die zin is daarom neutraal gehouden. Vul hem aan zodra dat vaststaat.
