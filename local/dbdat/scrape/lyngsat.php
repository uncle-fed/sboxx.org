<?php

/**
 * lyngsat.com parser
 */
function get_lyngsat_details(DOMXPath &$xpath, array &$tp_list, bool $ku_band) : bool
{
    // xpath query to get TP tables
    static $tp_xquery  = "/html/body/div/table/tr/td/table/tr[count(td)=9]";

    // initialize TP list
    $tp_list = [];

    $tp_rows = $xpath->query($tp_xquery);

    // if the result is empty then process next SAT
    if (!$tp_rows || !$tp_rows->length)
    {
        return FALSE;
    }

    // for each TP table...
    foreach ($tp_rows as $tp_row)
    {
        // discard 'middle east' only transponders
        $tp_info = trim(@$xpath->query("td[2]", $tp_row)->item(0)->nodeValue);
        if (preg_match('/middle east/i', $tp_info) && !preg_match('/europe/i', $tp_info)) continue;

        // get TP frequency and polarisation aggregate
        $tp_freq_pol = @$xpath->query("td[2]//b", $tp_row)->item(0)->nodeValue;

        // get provider info
        $tp_prov = @$xpath->query("td[4]", $tp_row)->item(0)->nodeValue;

        // get service type and modulation aggregate
        $tp_st_mod = @$xpath->query("td[6]", $tp_row)->item(0)->nodeValue;

        // get symbol rate and fec aggregate
        $tp_sr_fec = @$xpath->query("td[7]", $tp_row)->item(0)->nodeValue;

        if (empty($tp_freq_pol) || empty($tp_st_mod) || empty($tp_sr_fec) ||
            preg_match('/feeds/', $tp_prov) || preg_match('/internet/i', $tp_prov) ||
            preg_match('/test card/i', $tp_prov) || preg_match('/@ /', $tp_prov) ||
            preg_match('/multistream/i', $tp_st_mod)
        )
        {
            continue;
        }

        // get freq and polarisation
        if (!preg_match('/([0-9]+)[^VHLR]+([VHLR])/', $tp_freq_pol, $match)) continue;
        if (empty($match[1]) || empty($match[2])) continue;
        $tp_freq = $match[1];
        $tp_pol = $match[2];

        if ($ku_band && ( $tp_freq < 9000 || $tp_pol == 'R' || $tp_pol == 'L' ) ) continue;
        if (!$ku_band && $tp_freq > 9000 && ( $tp_pol == 'V' || $tp_pol == 'H') ) continue;

        // get symbol rate and fec
        if (!preg_match('@([0-9]+)[^0-9\?]+([1-9]/[1-9]0?|\?)@', $tp_sr_fec, $match)) continue;
        if (empty($match[1]) || empty($match[2])) continue;
        $tp_sr = trim($match[1]);
        $tp_fec = trim($match[2]);

        // get service type
        if (!preg_match('/DVB-S2?/', $tp_st_mod, $match) || empty($match[0])) continue;
        $tp_stype = $match[0] == 'DVB-S2' ? 'DVB-S2' : 'DVB-S';

        // get modulation
        $tp_mod = preg_match('/8PSK/', $tp_sr_fec) ? '8PSK' : 'QPSK';

        $tp_list[] = [
            'freq' => $tp_freq,
            'pol'  => $tp_pol,
            'sr'   => $tp_sr,
            'fec'  => $tp_fec,
            'type' => $tp_stype,
            'mod'  => $tp_mod
        ];
    }

    return (bool)count($tp_list);
}
