<?php
/**
 * Plugin Name: GPS-module registratie
 * Description: Het endpoint achter het GPS-registratieformulier. Neemt framenummer en IMEI aan, controleert ze, en geeft het door aan de leverancier van de GPS-modules.
 * Version:     1.0.0
 * Requires PHP: 7.4
 *
 * ═══════════════════════════════════════════════════════════════════════════
 *  WAT DIT IS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Het formulier (gps-registratie.html) stuurt twee waarden hierheen. Dit
 * bestand neemt ze aan, controleert ze nog een keer aan de serverkant, en geeft
 * ze door aan de leverancier van de GPS-modules.
 *
 * Installeren: zet dit bestand in wp-content/plugins/gps-registratie/ en zet de
 * plugin aan. Daarna is het adres:
 *
 *     /wp-json/gps/v1/activeer
 *
 * Vul dat adres in bij ENDPOINT bovenin gps-registratie.html en het formulier
 * praat met dit bestand.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 *  WAT ER NOG INGEVULD MOET WORDEN — EEN FUNCTIE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Alles hieronder werkt, BEHALVE de functie `gpsreg_claim_bij_leverancier()`
 * onderaan. Die is de enige plek waar de aanroep naar de leverancier hoort, en
 * die kan niet worden meegeleverd: daarvoor is een account nodig, plus hun
 * documentatie. Zie de opmerking bij die functie voor wat je precies moet
 * opvragen.
 *
 * Zolang die functie niet is ingevuld, antwoordt dit endpoint met
 * ERROR_CLAIM_DEVICE en schrijft het een regel in het WordPress-logboek. Het
 * formulier toont dan netjes "Door een onverwachte fout is het niet mogelijk de
 * GPS-module te koppelen" — dus je kunt alles testen voordat de koppeling er is.
 */

if (!defined('ABSPATH')) {
    exit; // rechtstreeks opvragen van dit bestand heeft geen zin
}

/* ══ De drie uitkomsten die het formulier kent ═══════════════════════════════
   Meer zijn het er niet. Wat de leverancier ook teruggeeft, het wordt naar een
   van deze drie vertaald — precies zoals het bestaande dealerportaal doet.    */

const GPSREG_OK                  = 'OK';
const GPSREG_ERROR_CLAIM_DEVICE  = 'ERROR_CLAIM_DEVICE';
const GPSREG_ERROR_REPEATED_IMEI = 'ERROR_REPEATED_IMEI';

/* ══ Het endpoint aanmelden ═════════════════════════════════════════════════ */

add_action('rest_api_init', function () {
    register_rest_route('gps/v1', '/activeer', [
        'methods'             => 'POST',
        'callback'            => 'gpsreg_verwerk_aanvraag',
        // Openbaar bereikbaar. Staat dit formulier achter een dealerlogin, zet
        // hier dan de controle die daarbij hoort — zie de opmerking onderaan.
        'permission_callback' => '__return_true',
    ]);
});

/* ══ De afhandeling ═════════════════════════════════════════════════════════ */

function gpsreg_verwerk_aanvraag(WP_REST_Request $verzoek)
{
    $frame = trim((string) $verzoek->get_param('frameNumber'));
    $imei  = trim((string) $verzoek->get_param('imei'));

    /*
     * OPNIEUW CONTROLEREN, ook al doet het formulier het al. Wat uit een browser
     * komt kan door iedereen worden nagemaakt; de controle die telt staat hier.
     * Dezelfde regels als in het formulier, zodat de uitkomst niet verschilt.
     */
    if ($frame === '' || !preg_match('/^[0-9a-zA-Z]+$/', $frame) || strlen($frame) < 10) {
        return gpsreg_antwoord_fout(GPSREG_ERROR_CLAIM_DEVICE, 'ongeldig framenummer');
    }
    if ($imei === '' || !preg_match('/^[0-9]+$/', $imei) || strlen($imei) < 15 || strlen($imei) > 17) {
        return gpsreg_antwoord_fout(GPSREG_ERROR_CLAIM_DEVICE, 'ongeldig IMEI-nummer');
    }

    /*
     * EEN EENVOUDIGE REM. Zonder dit kan iemand IMEI-nummers gaan raden door het
     * endpoint duizenden keren aan te roepen. Vijf pogingen per IP per minuut is
     * ruim voor echt gebruik en onbruikbaar voor raden.
     */
    if (!gpsreg_binnen_limiet()) {
        return new WP_REST_Response(['status' => GPSREG_ERROR_CLAIM_DEVICE], 429);
    }

    $uitkomst = gpsreg_claim_bij_leverancier($frame, $imei);

    if ($uitkomst === GPSREG_OK) {
        return new WP_REST_Response(['status' => GPSREG_OK], 200);
    }

    return gpsreg_antwoord_fout($uitkomst, 'leverancier gaf ' . $uitkomst);
}

function gpsreg_antwoord_fout($status, $reden)
{
    error_log('[gps-registratie] ' . $reden);
    return new WP_REST_Response(['status' => $status], 400);
}

function gpsreg_binnen_limiet()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'onbekend';
    $sleutel = 'gpsreg_' . md5($ip);
    $aantal = (int) get_transient($sleutel);

    if ($aantal >= 5) {
        return false;
    }
    set_transient($sleutel, $aantal + 1, MINUTE_IN_SECONDS);
    return true;
}

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  HIER KOMT DE AANROEP NAAR DE LEVERANCIER — DIT IS HET ENIGE DAT ONTBREEKT
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * De GPS-modules komen van een leverancier met een eigen koppeling. Die claimt
 * het apparaat op het IMEI-nummer en koppelt het aan de fiets. Welke leverancier
 * dat is hoor je van Sierk.
 *
 * WAT JE BIJ HEN MOET OPVRAGEN, en dat is een kort lijstje:
 *
 *   1. Het adres van de koppeling en hoe je je aanmeldt (sleutel? token? en zo
 *      ja, waar in het verzoek: een kop, of in het lichaam?).
 *   2. De aanroep om een apparaat te claimen: welk pad, welke velden, en of het
 *      IMEI daar hetzelfde heet.
 *   3. Hoe ze laten weten dat een IMEI al geclaimd is. Dat is de enige fout die
 *      apart getoond wordt, dus die moet herkenbaar zijn — een statuscode, een
 *      foutcode in het lichaam, of allebei.
 *   4. Of het claimen en het koppelen aan een framenummer één aanroep is of twee.
 *
 * Meer heb je niet nodig. Zet de sleutel NIET in dit bestand maar in wp-config.php
 * (`define('GPSREG_SLEUTEL', '...');`) — dan staat hij niet in de broncode en niet
 * in een back-up van de plugin-map.
 *
 * @return string een van de drie GPSREG_-waarden hierboven
 */
function gpsreg_claim_bij_leverancier($frame, $imei)
{
    /* ── ZOLANG DIT NIET IS INGEVULD ─────────────────────────────────────────
       Elke aanvraag wordt netjes afgewezen en er komt een regel in het logboek.
       Het formulier toont dan de melding "Door een onverwachte fout is het niet
       mogelijk de GPS-module te koppelen". Zo kun je de hele keten testen
       voordat de koppeling er is. Haal dit blok weg zodra de aanroep erin staat. */

    if (!defined('GPSREG_SLEUTEL')) {
        error_log('[gps-registratie] nog geen koppeling ingesteld — aanvraag voor frame ' . $frame . ' niet doorgezet');
        return GPSREG_ERROR_CLAIM_DEVICE;
    }

    /* ── DE ECHTE AANROEP ────────────────────────────────────────────────────
       Hieronder staat de VORM, niet de inhoud: het adres, de kop en de veldnamen
       zijn plaatshouders, want die staan in de documentatie van de leverancier.
       Vul ze in en dit werkt. Er is bewust niets ingevuld wat niet vaststaat —
       een verzonnen adres zou stilletjes falen en dat is erger dan leeg laten. */

    $antwoord = wp_remote_post('https://VUL-HET-ADRES-VAN-DE-LEVERANCIER-IN/claim', [
        'timeout' => 20,
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . GPSREG_SLEUTEL,
        ],
        'body' => wp_json_encode([
            'imei'        => $imei,
            'frameNumber' => $frame,
        ]),
    ]);

    if (is_wp_error($antwoord)) {
        error_log('[gps-registratie] leverancier onbereikbaar: ' . $antwoord->get_error_message());
        return GPSREG_ERROR_CLAIM_DEVICE;
    }

    $code    = wp_remote_retrieve_response_code($antwoord);
    $lichaam = json_decode(wp_remote_retrieve_body($antwoord), true);

    if ($code >= 200 && $code < 300) {
        return GPSREG_OK;
    }

    /*
     * DE ENIGE FOUT DIE APART GETOOND WORDT: het IMEI is al geclaimd. Hoe de
     * leverancier dat aangeeft moet je bij hen navragen (punt 3 hierboven).
     * Pas de voorwaarde hieronder aan zodra je dat weet; nu is het een gok en
     * daarom bewust ruim gehouden.
     */
    $melding = isset($lichaam['message']) ? strtolower($lichaam['message']) : '';
    if ($code === 409 || strpos($melding, 'already') !== false || strpos($melding, 'duplicate') !== false) {
        return GPSREG_ERROR_REPEATED_IMEI;
    }

    error_log('[gps-registratie] leverancier gaf ' . $code . ': ' . wp_remote_retrieve_body($antwoord));
    return GPSREG_ERROR_CLAIM_DEVICE;
}

/*
 * ═══════════════════════════════════════════════════════════════════════════
 *  NOG EEN OVERWEGING: WIE MAG ACTIVEREN?
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Zoals het nu staat mag iedereen die de pagina kan bereiken een module
 * activeren. Bij QWIC zit dit scherm achter de dealerlogin.
 *
 * Staat dit formulier op een besloten dealerpagina, vervang dan de
 * permission_callback hierboven door bijvoorbeeld:
 *
 *     'permission_callback' => function () { return is_user_logged_in(); },
 *
 * Staat het openbaar, dan is de rem hierboven het minimum. Overweeg dan ook een
 * bevestiging per e-mail, zodat een geactiveerde module altijd terug te leiden
 * is naar een persoon.
 */
