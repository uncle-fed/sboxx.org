<?php

/**
 * kingofsat.net parser
 */
function get_kingofsat_details(DOMXPath &$xpath, array &$tp_list, bool $ku_band) : bool
{
    // xpath query to get TP rows
    static $tp_row_xpath = "//table[@class='frq']//tr[td/span[@class='nbc' and text()]]";

    // xpath query to check for video/radio channels
    static $tp_rv_xpath = "ancestor::table[1]/following-sibling::div[1]/table[@class='fl']//tr[td[1]" .
                          "[contains(concat(' ',@class,' '),' v ') or contains(concat(' ',@class,' '),' r ')]]";

    // initialize TP list
    $tp_list = [];

    $tp_rows = $xpath->query($tp_row_xpath);

    // if the result is empty then process next SAT
    if (!$tp_rows || !$tp_rows->length)
    {
        return FALSE;
    }

    foreach ($tp_rows as $tp_row)
    {
        // check if the TP contains any valid video or radio channels
        if (!@$xpath->query($tp_rv_xpath, $tp_row)->length) continue;

        $tp_freq = round(trim(@$xpath->query("td[3]", $tp_row)->item(0)->nodeValue));
        $tp_pol = trim(@$xpath->query("td[4]", $tp_row)->item(0)->nodeValue);
        $tp_stype = trim(@$xpath->query("td[7]", $tp_row)->item(0)->nodeValue);
        $tp_mod = trim(@$xpath->query("td[8]", $tp_row)->item(0)->nodeValue);
        $tp_sr = trim(@$xpath->query("td[9]/a[1]", $tp_row)->item(0)->nodeValue);
        $tp_fec = trim(@$xpath->query("td[9]/a[2]", $tp_row)->item(0)->nodeValue);

        // skip non DVB-S services and services with non-Q/8PSK quietly
        if (!preg_match('/DVB\-S/i', $tp_stype) || !preg_match('/^[Q8]PSK$/', $tp_mod) || !$tp_fec)
        {
            continue;
        }

        if (!$tp_freq || !$tp_freq || !$tp_pol || !$tp_stype || !$tp_mod || !$tp_sr || !preg_match('/^[VHLR]$/', $tp_pol))
        {
            fwrite(STDERR, sprintf("Warning skipping bad data\n%s\n", str_replace("\n", ' ', $tp_row->nodeValue)));
            continue;
        }

        if ($ku_band && ( $tp_freq < 9000 || $tp_pol == 'R' || $tp_pol == 'L' )) continue;
        if (!$ku_band && $tp_freq > 9000 && ( $tp_pol == 'V' || $tp_pol == 'H')) continue;

        $tp_list[] = [
            'freq' => $tp_freq,
            'pol'  => $tp_pol,
            'sr'   => intval($tp_sr),
            'fec'  => $tp_fec,
            'type' => $tp_stype,
            'mod'  => $tp_mod
        ];
    }

    return (bool)count($tp_list);
}
