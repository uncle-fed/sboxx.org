<?php

/**
 * flysat.com parser
 */
function get_flysat_details(DOMXPath &$xpath, array &$tp_list, bool $ku_band) : bool
{
    // xpath query to get TP rows
    static $tp_xquery  = "/html/body/table/tr[count(td)=10]";

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
        // get TP frequency, polarisation, service type and modulation aggregate
        $tp_freq_pol_st_mod = @$xpath->query("td[2]", $tp_row)->item(0)->nodeValue;

        // get symbol rate and fec aggregate
        $tp_sr_fec = @$xpath->query("td[3]", $tp_row)->item(0)->nodeValue;

        // get provider info
        $tp_prov = preg_replace('/[\x0a\x09\xc2\xa0]/', '', @$xpath->query("td[4]", $tp_row)->item(0)->nodeValue);

        // if provider info is empty, check if there are actually any services on this TP
        if (!$tp_prov && (!isset($tp_row->nextSibling) || !isset($tp_row->nextSibling->childNodes) || $tp_row->nextSibling->childNodes->length < 10))
        {
            continue;
        }

        // get service type and modulation aggregate
        // $tp_st_mod = @$xpath->query("td[5]", $tp_row)->item(0)->nodeValue;

        if (empty($tp_freq_pol_st_mod) || empty($tp_sr_fec) || preg_match('/feed|internet|test[^ ]|data| mobile/i', $tp_prov))
        {
            continue;
        }

        // get freq and polarisation
        if (!preg_match('/([0-9]+)[^VHLR]+([VHLR])/', $tp_freq_pol_st_mod, $match)) continue;
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
        if (!preg_match('/DVB\-S2?/', $tp_freq_pol_st_mod, $match) || empty($match[0])) continue;
        $tp_stype = $match[0];

        // get modulation
        $tp_mod = preg_match('/8PSK/', $tp_freq_pol_st_mod) ? '8PSK' : 'QPSK';

        $tp_list[] = array(
            'freq' => $tp_freq,
            'pol'  => $tp_pol,
            'sr'   => $tp_sr,
            'fec'  => $tp_fec,
            'type' => $tp_stype,
            'mod'  => $tp_mod
        );

    }

    return (bool)count($tp_list);
}
