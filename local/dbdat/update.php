<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);
ini_set('error_log', '/var/local/log/php-cli.log');

define('USAGE', "\nUsage:\n\t" . basename(__FILE__) . " <sat_list_file.sql> <sat_id|all>\n\n");

$sat_list = $argv[1] ?? '';
$user_sat_id = $argv[2] ?? 0;

// check if no arguments supplied from command line
if (!$sat_list || !$user_sat_id || ($user_sat_id != 'all' && !intval($user_sat_id)))
{
    fwrite(STDERR, USAGE);
    exit(1);
}

require 'scrape/common.php';
require 'scrape/kingofsat.php';
require 'scrape/lyngsat.php';
require 'scrape/flysat.php';

// regex to match the necessary sat data from $sat_list
$sat_regex  = "/^\s*INSERT\s+INTO\s+satinfo\s+VALUES\s*\(\s*" .
              "([0-9]+)\s*,\s*'([^']+)'\s*,\s*\-?([0-9]+\s*,\s*){4}" .
              "([0-9]+)\s*,[^;]+;\s*\-\-\s*(http.*)$/i";

// read in the Sat list
$sat_list = file($sat_list, FILE_SKIP_EMPTY_LINES);

if (empty($sat_list))
{
    fwrite(STDERR, "Error reading sat list [". $sat_list . "]\n");
    exit(1);
}

// the final output will be stored here
$output = '';

// start main loop: go through each line in $sat_list
foreach ($sat_list as $sat)
{
    // process only the lines matching the correct regex, with id < 300
    if (!preg_match($sat_regex, $sat, $match) || $match[1] > 299)
    {
        continue;
    }

    $sat_id   = intval($match[1]);
    $sat_name = trim($match[2]);
    $ku_band  = $match[4] == 2 ? TRUE : FALSE;
    $sat_url  = trim($match[5]);

    // check if we only wanted to update 1 particular sat
    if ($user_sat_id != 'all' && $user_sat_id != $sat_id)
    {
        continue;
    }

    // reset tp list and tp index (for current sat)
    $tp_list = [];

    // show what we are about to do
    fwrite(STDERR, sprintf("---[%03d]---%'--20s---[%s]---%'--60s---\n",
        $sat_id, "[$sat_name]", $ku_band ? "K" : "C", "[$sat_url]"));

    if (!fetch_html_xpath($sat_url, $xpath))
    {
        exit(1);
    }

    // decide which parser to use and get the list
    if (strpos($sat_url, 'kingofsat'))
    {
        $tp_count = get_kingofsat_details($xpath, $tp_list, $ku_band);
    }
    elseif (strpos($sat_url, 'lyngsat'))
    {
        $tp_count = get_lyngsat_details($xpath, $tp_list, $ku_band);
    }
    elseif (strpos($sat_url, 'flysat'))
    {
        $tp_count = get_flysat_details($xpath, $tp_list, $ku_band);
    }

    foreach ($tp_list as $i => $tp)
    {
        fwrite(STDERR, sprintf("\t[%03d] ", $i+1));
        fwrite(STDERR, vsprintf("%5s %s %5s %-4s %-6s %s\n", $tp));
    }

    // if not a single tp found for this sat, bomb out
    if (empty($tp_list))
    {
        fwrite(STDERR,
            "Error: TP list cannot be empty!\n" .
            "Check that the URL $sat_url is OK\n"
        );

        exit(1);
    }

    create_sql($sat_name, $sat_id, $tp_list, $output);

    // pause to cool off
    usleep(500000);

} // process next sat

if (!$output)
{
    fwrite(STDERR, "Error: transponders SQL is empty.\n");
    exit(1);
}

print($output);

exit(0);
