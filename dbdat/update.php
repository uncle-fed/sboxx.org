#!/usr/local/bin/php -c /home/sboxx.org/dbdat
<?php

error_reporting(E_ALL|E_STRICT);
date_default_timezone_set('Europe/Kiev');
ini_set('error_log', '/home/sboxx.org/log/php-cli.log');

// define time out values for html pages fetching
define('HTML_CONN_TIMEOUT', 10);
define('HTML_FETCH_TIMEOUT', 20);
define('HTML_UA_STRING', 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:28.0) Gecko/20100101 Firefox/28.0');

// define main files locations
define('SAT_LIST',  '/home/sboxx.org/dbdat/insert_sat.sql');
define('TP_STATIC', '/home/sboxx.org/dbdat/static_tp.sql');
define('TP_OLD',    '/home/sboxx.org/dbdat/old_tp.sql');
define('TP_NEW',    '/home/sboxx.org/dbdat/new_tp.sql');
define('DB_SRC',    '/home/sboxx.org/dbdat/db.empty.dat');
define('DB_DST',    '/home/sboxx.org/dbdat/db.dat');
define('TP_HISTORY','/home/sboxx.org/public_html/dbdat.txt');

// general PHP environment settings to be uniform across various PHP distros
define('USAGE', "\nUsage:\n\t" . basename(__FILE__) . " <sat_id|all>\n\n");

// check if no arguments supplied from command line
if (empty($argv[1]))
{
    fwrite(STDERR, USAGE);
    exit(1);
}

// check what type of update it is: all or just one sat
$user_sat_id = ($argv[1] == 'all') ? -1 : intval($argv[1]);
if (!$user_sat_id)
{
    fwrite(STDERR, USAGE);
    exit(1);
}

// define FEC types -> DB value
$fec_type = array(
    'auto' => 0,
    '1/2' => 1, '2/3' => 2, '3/4' => 3, '3/5' => 4, '4/5' => 5,
    '5/6' => 6, '6/7' => 7, '7/8' => 8, '8/9' => 9, '9/10' => 10
);

$type_fec = array_flip($fec_type);

// also need array of FEC types on their own
$fec_keys = array_keys($fec_type);

// define polarisation types
$pol_type = array(
    'V' => 0,
    'R' => 0,
    'H' => 1,
    'L' => 1
);

// regex to match the necessary sat data from SAT_LIST (insert_sat.sql)
$sat_regex  = "/^\s*INSERT\s+INTO\s+satinfo\s+VALUES\s*\(\s*";
$sat_regex .= "([0-9]+)\s*,\s*'([^']+)'\s*,\s*\-?([0-9]+\s*,\s*){4}";
$sat_regex .= "([0-9]+)\s*,[^;]+;\s*\-\-\s*(.*)$/i";

// read in the Sat list
$sat_list = file(SAT_LIST, FILE_SKIP_EMPTY_LINES);

// read in Static TP list
$static_tp_list = file_get_contents(TP_STATIC);

if (empty($sat_list))
{
    fwrite(STDERR, "Error reading sat list [". SAT_LIST . "]\n");
    exit(1);
}

// the final output will be stored here
$output = '';

// start main loop: go through each line in SAT_LIST
foreach ($sat_list as $sat)
{
    // process only the lines matching the correct regex, with id < 300
    if (
        !preg_match($sat_regex, $sat, $match)  // if regex does not match
        || empty($match[5])                    // or no sat URL is found
        || ($sat_id=intval($match[1]) > 299)   // or sat id is not < 300
    ) continue;

    $sat_id    = intval($match[1]);
    $sat_name  = trim($match[2]);
    $sat_type  = intval($match[4]);
    $sat_urls  = explode('+', trim($match[5]));

    // check if user only wanted to update 1 particular sat
    if ($user_sat_id > 0 && $user_sat_id != $sat_id)
        continue;

    // reset tp list and tp index (for current sat)
    $tp_list = array();
    $tp_index = 0;

    // process each URL (if more than one per SAT)
    foreach ($sat_urls as $sat_url)
    {
        $sat_url = trim($sat_url);
        $ku_band = $sat_type == 2 ? TRUE : FALSE;

        // show what we are about to do
        printf(
            "---[%03d]---[%s]---[%s]---------------\n",
            $sat_id,
            $sat_name,
            $sat_url
        );

        // decide which parser to use and get the list
        if (strpos($sat_url, 'kingofsat'))
        {
            $tp_count = get_kingofsat_details($sat_name, $sat_url, $ku_band, $tp_list, $tp_index);
        }
        elseif (strpos($sat_url, 'lyngsat'))
        {
            $tp_count = get_lyngsat_details($sat_name, $sat_url, $ku_band, $tp_list, $tp_index);
        }
        elseif (strpos($sat_url, 'flysat'))
        {
            $tp_count = get_flysat_details($sat_name, $sat_url, $ku_band, $tp_list, $tp_index);
        }

        // print result
        printf(
            "---<%s>-------------------------------------------------------\n\n\n",
            $tp_count ? sprintf("%03d", $tp_count) : '---'
        );
    }

    // if not a single tp found for this sat, bomb out
    if (empty($tp_list))
    {
        fwrite(STDERR, "Error: TP list cannot be empty!\nCheck that the satellite $sat_name is OK\n");
        exit(1);
    }

    // sort tp list by freq+pol (freq+pol are array keys)
    ksort($tp_list);

    /*
    Prepare SQL statement with the following schema:

     INSERT INTO tpinfo VALUES
     (   id, sat_id, freq, sr, pol, freq_drift, qam, nid, org_id, ts_id, mod, fec, sty, ch_num, pilot );
     ( NULL, sat_id, freq, sr, pol,          0,   0,   0,      0,     0, mod, fec, sty,     '',     0 );

     pol: V = 0, R = 0, H = 1, L = 1
     mod: QPSK = 0, 8PSK = 0
     fec: auto = 0, 1/2 = 1, 2/3 = 2, 3/4 = 3, 3/5 = 4, 4/5 = 5, 5/6 = 6, 6/7 = 7, 7/8 = 8, 8/9 = 9, 9/10 = 10
     sty: DVB-S = 0, DVB-S2 = 1
     */

    // start SQL statement block
    // $output .= "-- $sat_name\n";

    // loop through the tp list for current sat
    foreach ($tp_list as $tp)
    {
        $output .= sprintf(
            "INSERT INTO tpinfo VALUES(NULL,%d,%d,%d,%d,0,0,0,0,0,%d,%d,%d,'',0); -- %s\n",
            $sat_id,
            $tp['freq'],
            $tp['sr'],
            $pol_type[$tp['pol']],
            $tp['mod'] == '8PSK' ? 1 : 0,
            $fec_type[$tp['fec']],
            $tp['type'] == 'DVB-S2' ? 1 : 0,
            $sat_name
        );
    }
    
    // pause to cool off
    usleep(500000);

} // process next sat

if (!$output)
{
    fwrite(STDERR, "Error: transponders SQL is empty.\n");
    exit(1);
}

if (!copy(DB_SRC, DB_DST))
{
    fwrite(STDERR, "Error: cannot copy template db.dat to a new db.dat.\n");
    exit(1);
}

if (file_exists(TP_NEW) && !copy(TP_NEW, TP_OLD))
{
    fwrite(STDERR, "Error: cannot copy template db.dat to a new db.dat.\n");
    exit(1);
}

if (!file_put_contents(TP_NEW, $output))
{
    fwrite(STDERR, "Error: failed to write new transponder file.\n");
    exit(1);
}

if (!$db = new SQLite3(DB_DST))
{
    fwrite(STDERR, "Error: cannot open db.dat for writing.\n");
    exit(1);
}

$date_sql = sprintf("INSERT INTO options VALUES('tpdate','%s');COMMIT;VACUUM", date('Ymd'));

if (!$db->exec(implode('',$sat_list) . $output . $static_tp_list. $date_sql))
{
    fwrite(STDERR, "Error: cannot write to db.dat.\n%s\n", $db->lastErrorMsg());
    exit(1);
}

if (!$db->close())
{
    fwrite(STDERR, "Error: cannot close db.dat.\n%s\n", $db->lastErrorMsg());
    exit(1);    
}

if (file_exists(TP_OLD))
{
    exec(sprintf("diff -U999999999 %s %s | grep '^[+\-]INSERT'", TP_OLD, TP_NEW), $diff);
    $diff_out = date("d M Y:");
    if (empty($diff))
    {
        $diff_out .= " no change";
    }
    else
    {
        foreach ($diff as $line)
        {
            // +INSERT INTO tpinfo VALUES(NULL,14,11546,2590,1,0,0,0,0,0,0,3,0,'',0); -- EUTELSAT 10°E
            //   id, sat_id, freq, sr, pol, freq_drift, qam, nid, org_id, ts_id, mod, fec, sty, ch_num, pilot
            if(preg_match(
                '/^([+\-])INSERT INTO tpinfo VALUES\(NULL,\d+,(\d+),(\d+),(\d+),\d+,\d+,\d+,\d+,\d+,\d+,(\d+),(\d+),\'\',\d+\); \-\- (.*)/',
                $line,
                $m)
            ){
                $m[7] = str_pad($m[7], 20 - mb_strlen($m[7], 'UTF-8') + strlen($m[7]));
                $diff_out .= sprintf("\n   %s %s %5d/%s %5d %-4s %s", $m[7], $m[1], $m[2], ($m[4] ? 'H': 'V'), $m[3], $type_fec[$m[5]], ($m[6] ? '8PSK': 'QPSK'));
            }
        }
    }
    $diff_out .= "\n-------------------------------------------------\n";
    file_put_contents(TP_HISTORY.'.tmp', $diff_out);
    exec(sprintf('cat %s >> %s && mv %2$s %1$s', TP_HISTORY, TP_HISTORY.'.tmp'));
}

exit(0);

// the end


/**
 * kingofsat.net parser
 *
 * NOTE: $ku_band is discarded in this parser since no sats with Ku band
 *       are normally sourced from kingofsat.net
 */
function get_kingofsat_details($sat_name, $sat_url, $ku_band, &$tp_list, &$tp_index)
{
    global $fec_keys;

    // xpath query to get TP tables
    static $tp_xquery  = "/html/body//table[@class='fl']";

    // xpath query to get TP data row
    static $tp_row_xquery = "../preceding-sibling::table[1][@class='frq']/tr";

    // get HTML source
    fetch_html_page($sat_url, $sat_html);

    if (!$sat_html)
    {
        fwrite(STDERR, sprintf("Error on %s: cannot load URL %s\n", $sat_name, $sat_url));
        exit(1);
    }

    // parse HTML source as a DOM object
    $doc = new DOMDocument();
    @$doc->loadHTML($sat_html);

    if (!is_object($doc))
    {
        fwrite(STDERR, sprintf("Error on %s: cannot parse HTML for %s\n", $sat_name, $sat_url));
        exit(1);
    }

    // init xpath object and get TP rows
    $xpath = new DOMXpath($doc);
    $tp_tables = $xpath->query($tp_xquery);

    // if the result is empty then process next SAT
    if (!$tp_tables || !$tp_tables->length)
    {
        fwrite(STDERR, sprintf("Error on %s: cannot detect any transponders @ %s!\n", $sat_name, $sat_url));
        exit(1);
    }

    // for each TP table...
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
            fwrite(STDERR, sprintf("Error on %s: cannot find TP header @ %s!\n", $sat_name, $sat_url));
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
            continue;

        if (!$tp_freq || !$tp_freq || !$tp_pol || !$tp_stype || !$tp_mod
            || !$tp_sr || !preg_match('/^[VHLR]$/', $tp_pol)){
            fwrite(STDERR, sprintf("Skipped bad data for %s @ %s\n%s\n",
                $sat_name, $sat_url, str_replace("\n", ' ',$tp_row->nodeValue))
            );
            continue;
        }

        // cleanup values
        $tp_freq = round($tp_freq);
        $tp_sr = intval($tp_sr);
        $tp_fec = in_array($tp_fec, $fec_keys) ? $tp_fec : 'auto';
        $tp_id = sprintf("%05d%s", $tp_freq, $tp_pol);

        $tp_list[$tp_id] = array(
            'freq' => $tp_freq,
            'pol'  => $tp_pol,
            'sr'   => $tp_sr,
            'fec'  => $tp_fec,
            'type' => $tp_stype,
            'mod'  => $tp_mod
        );

        // display interim results for current TP
        printf("\t[%03d] ", $tp_index+1);
        vprintf("%5s %s %5s %-4s %-6s %s\n", $tp_list[$tp_id]);
        $tp_index++;
    }

    return count($tp_list);
}


/**
 * lyngsat.com parser
 */
function get_lyngsat_details($sat_name, $sat_url, $ku_band, &$tp_list, &$tp_index)
{
    global $fec_keys;

    // xpath query to get TP tables
    static $tp_xquery  = "/html/body/div/table/tr/td/table/tr[count(td)=8]";

    // get HTML source
    fetch_html_page($sat_url, $sat_html);

    if (!$sat_html)
    {
        fwrite(STDERR, sprintf("Error on %s: cannot load URL %s\n", $sat_name, $sat_url));
        exit(1);
    }

    // parse HTML source as a DOM object
    $doc = new DOMDocument();
    @$doc->loadHTML($sat_html);

    if (!is_object($doc))
    {
        fwrite(STDERR, sprintf("Error on %s: cannot parse HTML for %s\n", $sat_name, $sat_url));
        exit(1);
    }

    // init xpath object and get TP rows
    $xpath = new DOMXpath($doc);
    $tp_rows = $xpath->query($tp_xquery);

    // if the result is empty then process next SAT
    if (empty($tp_rows))
    {
        fwrite(STDERR, sprintf("Error on %s: cannot detect any transponders @ %s!\n", $sat_name, $sat_url));
        exit(1);
    }

    // for each TP table...
    foreach ($tp_rows as $tp_row)
    {

        // get TP frequency and polarisation aggregate
        $tp_freq_pol = @$xpath->query("td[1]//b", $tp_row)->item(0)->nodeValue;


        // get provider info
        $tp_prov = @$xpath->query("td[3]", $tp_row)->item(0)->nodeValue;

        // get service type and modulation aggregate
        $tp_st_mod = @$xpath->query("td[5]", $tp_row)->item(0)->nodeValue;

        // get symbol rate and fec aggregate
        $tp_sr_fec = @$xpath->query("td[6]", $tp_row)->item(0)->nodeValue;

        if (empty($tp_freq_pol) || empty($tp_st_mod) || empty($tp_sr_fec) ||
            preg_match('/feeds/', $tp_prov) || preg_match('/internet/i', $tp_prov) ||
            preg_match('/test card/i', $tp_prov) || preg_match('/@ /', $tp_prov)
        )
            continue;

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
        $tp_fec = in_array($tp_fec, $fec_keys) ? $tp_fec : 'auto';

        // get service type
        if (!preg_match('/DVB(\-S2)?/', $tp_st_mod, $match) || empty($match[0])) continue;
        $tp_stype = $match[0] == 'DVB-S2' ? 'DVB-S2' : 'DVB-S';

        // get modulation
        $tp_mod = preg_match('/8PSK/', $tp_sr_fec) ? '8PSK' : 'QPSK';

        $tp_id = sprintf("%05d%s", $tp_freq, $tp_pol);

        $tp_list[$tp_id] = array(
            'freq' => $tp_freq,
            'pol'  => $tp_pol,
            'sr'   => $tp_sr,
            'fec'  => $tp_fec,
            'type' => $tp_stype,
            'mod'  => $tp_mod
        );

        // display interim results for current TP
        printf("\t[%03d] ", $tp_index+1);
        vprintf("%5s %s %5s %-4s %-6s %s\n", $tp_list[$tp_id]);

        $tp_index++;
    }

    return count($tp_list);

}


/**
 * flysat.com parser
 */
function get_flysat_details($sat_name, $sat_url, $ku_band, &$tp_list, &$tp_index)
{

    global $fec_keys;

    // xpath query to get TP rows
    static $tp_xquery  = "/html/body/table/tr[count(td)=10]";

    // get HTML source
    fetch_html_page($sat_url, $sat_html);

    if (!$sat_html)
    {
        fwrite(STDERR, sprintf("Error on %s: cannot load URL %s\n", $sat_name, $sat_url));
        exit(1);
    }

    // parse HTML source as a DOM object
    $doc = new DOMDocument();
    @$doc->loadHTML($sat_html);

    if (!is_object($doc))
    {
        fwrite(STDERR, sprintf("Error on %s: cannot parse HTML for %s\n", $sat_name, $sat_url));
        exit(1);
    }

    // init xpath object and get TP rows
    $xpath = new DOMXpath($doc);
    $tp_rows = $xpath->query($tp_xquery);

    // if the result is empty then process next SAT
    if (empty($tp_rows))
    {
        fwrite(STDERR, sprintf("Error on %s: cannot detect any transponders @ %s!\n", $sat_name, $sat_url));
        exit(1);
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
            continue;

        // get service type and modulation aggregate
        // $tp_st_mod = @$xpath->query("td[5]", $tp_row)->item(0)->nodeValue;

        if (empty($tp_freq_pol_st_mod) || empty($tp_sr_fec) ||
            preg_match('/feed|internet|test[^ ]|data| mobile/i', $tp_prov)
        )
            continue;

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
        $tp_fec = in_array($tp_fec, $fec_keys) ? $tp_fec : 'auto';

        // get service type
        if (!preg_match('/DVB\-S2?/', $tp_freq_pol_st_mod, $match) || empty($match[0])) continue;
        $tp_stype = $match[0];

        // get modulation
        $tp_mod = preg_match('/8PSK/', $tp_freq_pol_st_mod) ? '8PSK' : 'QPSK';

        $tp_id = sprintf("%05d%s", $tp_freq, $tp_pol);

        $tp_list[$tp_id] = array(
            'freq' => $tp_freq,
            'pol'  => $tp_pol,
            'sr'   => $tp_sr,
            'fec'  => $tp_fec,
            'type' => $tp_stype,
            'mod'  => $tp_mod
        );

        // display interim results for current TP
        printf("\t[%03d] ", $tp_index+1);
        vprintf("%5s %s %5s %-4s %-6s %s\n", $tp_list[$tp_id]);
        $tp_index++;
    }

    return count($tp_list);

}


/**
 * flysat.com parser
 */
 function fetch_html_page($page_url, &$page_html)
 {
    static $curl = NULL;

    $page_html = '';

    //check, if a valid url is provided
    if(!filter_var($page_url, FILTER_VALIDATE_URL))
    {
        return FALSE;
    }

    if ($curl === NULL)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_HEADER => FALSE,
            CURLOPT_FAILONERROR => TRUE,
            CURLOPT_FOLLOWLOCATION => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_CONNECTTIMEOUT => HTML_CONN_TIMEOUT,
            CURLOPT_TIMEOUT => HTML_FETCH_TIMEOUT,
            CURLOPT_USERAGENT => HTML_UA_STRING
        ));
    }

    curl_setopt($curl, CURLOPT_URL, $page_url);

    $attempts = 10;
    $last_error = 1;

    while ($last_error && $attempts > 0)
    {
        $page_html = curl_exec($curl);
        $last_error = curl_errno($curl);
        if ($last_error)
        {
            fwrite(STDERR, sprintf("Warning: CURL [%d] on [%s]\n", $last_error, $page_url));
            sleep(13);
        }
        $attempts--;
    }

 }
 
 
/**
 * easy way to debug XML nodes
 */
function xml_dump($node)
{
    echo simplexml_import_dom($node)->asXML() . "\n";
}
