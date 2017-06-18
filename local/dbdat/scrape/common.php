<?php

// define time out values for html pages fetching
define('HTML_CONN_TIMEOUT', 10);
define('HTML_FETCH_TIMEOUT', 20);
define('HTML_FETCH_RETRY_ATTEMPTS', 10);
define('HTML_FETCH_RETRY_PAUSE', 10);
define('HTML_UA_STRING', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/59.0.3071.86 Safari/537.36');


function fetch_html_xpath(string $page_url, &$xpath_obj) : bool
{
    static $curl = NULL;

    $xpath_obj = NULL;

    //check, if a valid url is provided
    if(!filter_var($page_url, FILTER_VALIDATE_URL))
    {
        fwrite(STDERR, sprintf("Error: invalid URL %s\n", $page_url));
        return FALSE;
    }

    if ($curl === NULL)
    {

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_HEADER => FALSE,
            CURLOPT_FAILONERROR => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CONNECTTIMEOUT => HTML_CONN_TIMEOUT,
            CURLOPT_TIMEOUT => HTML_FETCH_TIMEOUT,
            CURLOPT_USERAGENT => HTML_UA_STRING
        ]);
    }

    curl_setopt($curl, CURLOPT_URL, $page_url);

    $attempts = HTML_FETCH_RETRY_ATTEMPTS;

    $last_error = 1;

    while ($last_error && $attempts > 0)
    {
        $page_html = curl_exec($curl);
        $last_error = curl_errno($curl);

        if ($last_error)
        {
            fwrite(STDERR, sprintf("Warning: CURL [%d] on [%s]\n", $last_error, $page_url));
            sleep(HTML_FETCH_RETRY_PAUSE);
        }

        $attempts--;
    }

    if (!$page_html)
    {
         return FALSE;
    }

    // parse HTML source as a DOM object
    $doc = new DOMDocument();
    @$doc->loadHTML($page_html);

    if (!is_object($doc))
    {
        fwrite(STDERR, sprintf("Error: cannot parse HTML for %s!\n", $page_url));
        return FALSE;
    }

    // init xpath object and get TP rows
    $xpath_obj = new DOMXpath($doc);

    return TRUE;
}


/**
 * Prepare SQL statement with the following schema:
 *
 *  INSERT INTO tpinfo
 *         (   id, sat_id, freq, sr, pol, freq_drift, qam, nid, org_id, ts_id, mod, fec, sty, ch_num, pilot )
 *  VALUES ( NULL, sat_id, freq, sr, pol,          0,   0,   0,      0,     0, mod, fec, sty,     '',     0 );
 *
 */
function create_sql(string $sat_name, int $sat_id, array &$tp_list, string &$output) : void
{
    static $fec_type = [
        'auto' => 0,
        '1/2' => 1, '2/3' => 2, '3/4' => 3, '3/5' => 4, '4/5' => 5,
        '5/6' => 6, '6/7' => 7, '7/8' => 8, '8/9' => 9, '9/10' => 10
    ];

    static $pol_type = [
        'V' => 0,
        'R' => 0,
        'H' => 1,
        'L' => 1
    ];

    static $srv_type = [
        'DVB-S' => 0,
        'DVB-S2' => 1
    ];

    static $mod_type = [
        'QPSK' => 0,
        '8PSK' => 1
    ];

    // loop through the tp list
    foreach ($tp_list as $tp)
    {
        $output .= sprintf(
            "INSERT INTO tpinfo VALUES(NULL,%d,%d,%d,%d,0,0,0,0,0,%d,%d,%d,'',0); -- %s\n",
            $sat_id,
            $tp['freq'],
            $tp['sr'],
            isset($pol_type[$tp['pol']]) ? $pol_type[$tp['pol']] : 0,
            isset($mod_type[$tp['mod']]) ? $mod_type[$tp['mod']] : 0,
            isset($fec_type[$tp['fec']]) ? $fec_type[$tp['fec']] : 0,
            isset($srv_type[$tp['type']]) ? $srv_type[$tp['type']] : 0,
            $sat_name
        );
    }
}
