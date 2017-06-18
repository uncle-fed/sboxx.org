<?php

/**
 * kingofsat.net parser
 */
function get_kingofsat_details(DOMXPath &$xpath, array &$tp_list) : bool
{
    // xpath query to get TP tables
    static $tp_xquery  = "//html/body/div/table[@class='fl']";

    // xpath query to get TP data row
    static $tp_row_xquery = "../preceding-sibling::table[1][@class='frq']/tr";

    // initialize TP list
    $tp_list = [];

    $tp_tables = $xpath->query($tp_xquery);

    // if the result is empty then process next SAT
    if (!$tp_tables || !$tp_tables->length)
    {
        return FALSE;
    }

    foreach ($tp_tables as $tp_table)
    {
        // check if the table has any video or radio services
        if(!$xpath->query("tr/td[@class='v' or @class='r']", $tp_table)->length)
        {
            // skip TPs with no tv or radio services silently
            continue;
        }

        $tp_row = $xpath->query($tp_row_xquery, $tp_table)->item(0);

        if (!$tp_row)
        {
            // skip malformed TP headers
            fwrite(STDERR, sprintf("Warning: skipping malformed TP header!\n"));
            continue;
        }

        $tp_freq = trim(@$xpath->query("td[3]", $tp_row)->item(0)->nodeValue);
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

        $tp_list[] = [
            'freq' => round($tp_freq),
            'pol'  => $tp_pol,
            'sr'   => intval($tp_sr),
            'fec'  => $tp_fec,
            'type' => $tp_stype,
            'mod'  => $tp_mod
        ];
    }

    return (bool)count($tp_list);
}
