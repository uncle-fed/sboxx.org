<?php

error_reporting(E_ALL|E_STRICT);
ini_set('display_errors', 1);
ini_set('error_log', '/var/www/log/php-cli.log');

define('USAGE', "\nUsage:\n\t" . basename(__FILE__) . " <epg.gz> <db.sqlite>\n\n");

$epg_gz = $argv[1] ?? '';
$db_file = $argv[2] ?? 0;

// check if no arguments supplied from command line
if (!$epg_gz || !$db_file)
{
    fwrite(STDERR, USAGE);
    exit(1);
}

$programme_opening_tag = '@';
$programme_closing_tag = '</programme>';

print("\nImporting data into SQLite database...\n");

try
{
    $db = new SQLite3($db_file, SQLITE3_OPEN_READWRITE);
}
catch (Exception $exception)
{
    trigger_error($exception->getMessage());
    die(sprintf("Error: cannot connect to SQLite database\n%s\n", $exception->getMessage()));
}

$tv_guide_file = gzopen($epg_gz, 'r')
    or die("Could not open program guide .gz file\n");

$buffer = '';
$i = 0;

$result = $db->exec('BEGIN ; DELETE FROM epg');

if (!$result)
{
    die(sprintf("Error: cannot clear EPG table\n%s\n", $db->lastErrorMsg()));
}

while (!gzeof($tv_guide_file))
{
    // find opening tag
    while (!($open_tag_position = strpos($buffer, '<programme ')) && !gzeof($tv_guide_file))
    {
        $buffer .= gzgets($tv_guide_file, 4096);
    }

    // if opening tag has been found
    if ($open_tag_position !== FALSE)
    {
        // cut everything before opening tag
        $buffer = substr($buffer, $open_tag_position);

        // find closing tag
        while (!($closing_tag_position = strpos($buffer, '</programme>')) && !gzeof($tv_guide_file))
        {
            $buffer .= gzgets($tv_guide_file, 4096);
        }

        // if closing tag has been found
        if ($closing_tag_position !== FALSE)
        {
            preg_match('@start="(.+)"@U', $buffer, $match);

            $start_datetime = !empty($match[1]) ? $match[1] : '';

            preg_match('@stop="(.+)"@U', $buffer, $match);

            $stop_datetime = !empty($match[1]) ? $match[1] : '';

            preg_match('@channel="(.+)"@U', $buffer, $match);

            $channel_id = !empty($match[1]) ? $match[1] : '';

            preg_match('@<title.*>(.+)</title>@U', $buffer, $match);

            $title = !empty($match[1]) ? $match[1] : '';

            preg_match('@<desc.*>(.+)</desc>@U', $buffer, $match);

            $desc = !empty($match[1]) ? $match[1] : '';

            $buffer = substr($buffer, $closing_tag_position + 12);

            // if all details are in place, stick it into db
            if ($start_datetime && $stop_datetime && $channel_id && $title)
            {
                $start_time = strtotime($start_datetime);
                $stop_time = strtotime($stop_datetime);
                $title = str_replace('&quot;', '"', $title);
                $title = preg_replace('/ *\(. категория\).?/', '', $title);
                $desc = str_replace('&quot;', '"', $desc);

                if ($title{0} == '"' && strpos($title, '".') !== FALSE)
                {
                    $title = str_replace(['"', '".'], '', $title);
                }

                if (substr($title,-1) == '.')
                {
                    $title = substr($title,0,-1);
                }

                $title = $db->escapeString($title);
                $desc = $db->escapeString($desc);
                $channel_id = $db->escapeString($channel_id);

                $_sql = "INSERT INTO epg (ch_id,start_time,end_time,event_name,event_descr) VALUES ('%s',%u,%u,'%s','%s')";

                $sql = sprintf($_sql, $channel_id, $start_time, $stop_time, $title, $desc);

                $result = $db->exec($sql);

                if ($result)
                {
                    $i++;
                }
                else
                {
                    printf("%s\n", $db->lastErrorMsg());
                }
            }
        }
    }
}

$result = $db->exec('COMMIT ; VACUUM');

if (!$result)
{
    die(sprintf("Error: cannot commit EPG data\n%s\n", $db->lastErrorMsg()));
}

printf("%u records imported\nAll done\n", $i);
