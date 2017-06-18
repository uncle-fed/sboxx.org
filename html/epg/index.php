<?php // PHP v7.1+

try
{
    $database_link = new SQLite3('/var/www/local/epg/db.sqlite', SQLITE3_OPEN_READWRITE);
}
catch (Exception $exception)
{
    trigger_error($exception->getMessage());
    die("ошибка №1");
}

// HTTP POST (save user channel selection via ajax request)
if (!empty($_POST["data"]))
{
    header("Content-Type: text/plain; charset=UTF-8");

    $posted_data = explode(";", rawurldecode($_POST["data"]));

    $dst_option = "";

    // detect if there is an "s" among the submitted values
    if (($index_found = array_search("s", $posted_data)) !== FALSE)
    {
        $dst_option = "s;";
        unset($posted_data[$index_found]);
    }

    // if nothing useful was submitted then throw an error
    sanitise_data($posted_data);
    empty($posted_data) && die("ошибка №2");

    // calculate new hash for submitted data
    sort($posted_data);
    $posted_data = $dst_option . implode(";" , $posted_data);
    $posted_md5 = md5($posted_data);

    // check if we already have such hash in the database
    $sql_query = sprintf("SELECT id FROM saved WHERE md5 = '%s'" , $posted_md5);
    $sql_data = $database_link->querySingle($sql_query);
    $sql_data === FALSE && die("ошибка №3");

    // if such hash already exists, simply display it and stop here
    !empty($sql_data) && die(compress_id($sql_data));

    // insert new data into the db
    $sql_query = sprintf("INSERT INTO saved (md5, channels, date) VALUES ('%s', '%s', datetime('now'))",
        $database_link->escapeString($posted_md5),
        $database_link->escapeString($posted_data)
    );
    $sql_result = $database_link->exec($sql_query);

    // display the end result (new id or an error)
    die($sql_result ? compress_id($database_link->lastInsertRowID()) : "ошибка №4");
}

// HTTP GET (either dump SQL data or display HTML form)
$user_agent  = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : 'unknown';
$pgi_version = isset($_GET["version"]) ? $_GET["version"] : ""; 
$code        = isset($_GET["code"]) ? $_GET["code"] : "";
$code_length = strlen($code);

// test code handling
if ($code == 'ping!' && $user_agent == "Wget")
{
    header("Content-Type: text/plain; charset=UTF-8");
    die("pong($pgi_version)!\n");
}

// get possible saved record from the database
if (preg_match("/^[a-zA-Z0-9\-_]{2,6}$/", $code))
{
    $sql_query = sprintf("SELECT channels FROM saved WHERE id = %d", expand_id($code));
    $sql_data = $database_link->querySingle($sql_query);
}

// populate channel ids requested by user (if there are any)
$channel_ids_list = !empty($sql_data) ? explode(";", $sql_data) : [];
sanitise_data($channel_ids_list);

// get DST setting
$dst_offset = 0;
if (($index_found = array_search("s", $channel_ids_list)) !== FALSE)
{
    $dst_offset = 3600;
    unset($channel_ids_list[$index_found]);
}

// if accessing with wget, then dump saved channels
if ($user_agent == "Wget" || isset($_GET['dump']))
{
    // ini_set("zlib.output_compression", "On");
    header("Content-Type: text/plain; charset=UTF-8");

    // if there are no channels to look at, then throw an error
    empty($channel_ids_list) && die("ошибка №5 - запрошен несуществующий код каналов\n");

    // if PGI version does not make sense, then throw an error
    !preg_match("/^[0-9]$/", $pgi_version) && die("ошибка №6 - не указана версия PGI\n");

    // get ONID/TSID/SID data for all required channels
    $sql_query = sprintf("SELECT DISTINCT c.id id, ch_id epg_id, angle, s.name sat_name, c.name ch_name, org_network_id, ts_id, service_id
        FROM chref c, sat s WHERE active = 1 AND sat_angle = angle AND c.id IN (%s)", implode(",", $channel_ids_list));

    $sql_result = $database_link->query($sql_query) or die("ошибка №7 - сбой базы данных, повторите запрос...\n");

    while ($sql_data = $sql_result->fetchArray(SQLITE3_ASSOC))
    {
        $channel_info[$sql_data['id']] = [
            'epg_src_id'          => $sql_data['epg_id'],
            'satellite_name'      => $sql_data['sat_name'],
            'satellite_point'     => ($sql_data['angle'] < 0) ? "W" : "E",
            'satellite_angle'     => abs($sql_data['angle']/10),
            'channel_name'        => $sql_data['ch_name'],
            'original_network_id' => $sql_data['org_network_id'],
            'transponder_id'      => $sql_data['ts_id'],
            'service_id'          => $sql_data['service_id'],
        ];

        $epg_src_ids[] = $sql_data['epg_id'];
    }
    $sql_result->finalize();

    empty($channel_info) && die("ошибка №8 - сбой базы данных...");

    // get all EPG info for all required channels
    $sql_query = sprintf("SELECT ch_id, start_time, end_time, event_name, event_descr FROM epg
        WHERE ch_id IN ('%s') %s ORDER BY ch_id ASC, start_time ASC",
        implode("','", $epg_src_ids),
        (isset($_GET['dump']) && $_GET['dump']!="now" ? "" : "AND end_time > strftime('%s','now')")
    );

    $sql_result = $database_link->query($sql_query) or die("ошибка №9 - сбой базы данных, повторите запрос...\n");

    while ($sql_data = $sql_result->fetchArray(SQLITE3_ASSOC))
    {
        $epg_data[$sql_data['ch_id']][] = $sql_data;
    }
    $sql_result->finalize();

    empty($epg_data) && die("ошибка №10 - нет данных ТВ гида, повторите запрос позже...\n");

    // dump EPG as SQLite SQL
    printf("BEGIN;\n");

    // $previous_channel_id = "";
    $event_id = 100;

    foreach ($channel_info as $channel_data)
    {
        if (empty($epg_data[$channel_data['epg_src_id']]))
        {
            continue;
        }

        // print header for each channel
        printf("\n-- %s @ %s %s°%s\n",
            $channel_data['channel_name'],
            $channel_data['satellite_name'],
            $channel_data['satellite_angle'],
            $channel_data['satellite_point']
        );

        printf("DELETE FROM epg WHERE org_network_id = %u AND ts_id = %u AND service_id = %u;\n",
            $channel_data['original_network_id'],
            $channel_data['transponder_id'],
            $channel_data['service_id']
        );

        // print EPG data
        foreach ($epg_data[$channel_data['epg_src_id']] as $epg)
        {
            // for PGI 1.3+
            if ($pgi_version > 2)
            {
                printf("INSERT INTO epg VALUES (NULL,%u,%u,%u,%u,%u,%u,0,'',NULL,NULL,'%s','%s','');\n",
                    $channel_data['original_network_id'],
                    $channel_data['transponder_id'],
                    $channel_data['service_id'],
                    $epg["start_time"]+$dst_offset,
                    ($epg["end_time"] - $epg["start_time"]),  // duration
                    $epg["end_time"]+$dst_offset,
                    SQLite3::escapeString($epg["event_name"]),
                    SQLite3::escapeString($epg["event_descr"])
                );
            }

            // for PGI 1.2
            else if ($pgi_version == 2)
            {
                printf("INSERT INTO epg VALUES (NULL,%u,%u,%u,%u,%u,%u,%u,0,'',65535,65535,'%s','%s','');\n",
                    $event_id++,
                    $channel_data['original_network_id'],
                    $channel_data['transponder_id'],
                    $channel_data['service_id'],
                    $epg["start_time"]+$dst_offset,
                    ($epg["end_time"] - $epg["start_time"]),
                    $epg["end_time"]+$dst_offset,
                    SQLite3::escapeString($epg["event_name"]),
                    SQLite3::escapeString($epg["event_descr"])
                );
            }

            // for PGI 1.0 & PGI 1.1
            else
            {
                printf("INSERT INTO epg VALUES (NULL,%u,%u,%u,%u,%u,%u,%u,0,'',65535,65535);\n",
                    $event_id++,
                    $channel_data['original_network_id'],
                    $channel_data['transponder_id'],
                    $channel_data['service_id'],
                    $epg["start_time"]+$dst_offset,
                    ($epg["end_time"] - $epg["start_time"]),
                    $epg["end_time"]+$dst_offset
                );

                printf("INSERT INTO text VALUES (last_insert_rowid(),'%s','%s','');\n",
                    SQLite3::escapeString($epg["event_name"]),
                    SQLite3::escapeString($epg["event_descr"])
                );
            }
        }
    }

    printf("\nCOMMIT;\n");
}
// display HTML page
else
{
    header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8" />
    <title>ТВ Гид для PGI</title>
    <link rel="stylesheet" href="/epg/epg.css" />
    <script src="http://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
    <script src="/epg/epg.js"></script>
</head>
<body>
    <section class="top">
        <header>
            <p><b>Выберите каналы и нажмите кнопку </b><button>Получить код</button> <span id="result">Ваш код: <code></code></span></p>
            <p>Использовать летнее время: <?php printf('<input id="ch-s" type="checkbox" %s/>', ($dst_offset ? 'class="checked" ' : "")) ?></p>
        </header>
        <small>
            ВНИМАНИЕ: настоятельно рекомендуется выбирать только нужные для просмотра каналы!
        </small>
    </section>
<?php

    $channel_reference = $satellite_angle = $channels_per_satellite = [];

    $sql_query = "SELECT c.id, angle, s.name sat_name, c.name ch_name, c.org_network_id onid, c.ts_id tsid, c.service_id sid
        FROM chref c, sat s WHERE active = 1 AND sat_angle = angle ORDER BY c.org_network_id, c.ts_id, c.service_id, c.name ASC";
    $sql_result = $database_link->query($sql_query);

    if ($sql_result)
    {
        while ($row = $sql_result->fetchArray(SQLITE3_ASSOC))
        {
            $channel_reference[] = $row;
            $satellite_angle[$row["sat_name"]] = $row["angle"];
            @$channels_per_satellite[$row["sat_name"]]++;
            $index = sprintf("%d-%d-%d", $row["onid"],  $row["tsid"], $row["sid"]);
            $onid_tsid_sid[$index][] = $row["id"];
        }
        $sql_result->finalize();
    }

    empty($channels_per_satellite) && die;

    // sort satellites by number of their channels (in descending order)
    arsort($channels_per_satellite);

    foreach ($channels_per_satellite as $satellite_name => $channels_count)
    {
        $angle = $satellite_angle[$satellite_name];
        $point = ($angle < 0) ? "W" : "E";
        $angle = abs($angle/10);
?>
    <section>
        <header>
            <p><?php
                printf("<b>%s°%s</b> ", $angle, $point);
                printf('<a href="#%s%s">%s</a>', $angle, $point, $satellite_name);
            ?></p>
        </header>
        <ul>
<?php
        foreach ($channel_reference as $channel_info)
        {
            if ($channel_info["sat_name"] == $satellite_name)
            {
                $index = sprintf("%d-%d-%d", $channel_info["onid"],  $channel_info["tsid"], $channel_info["sid"]);
                $conflicting = count($onid_tsid_sid[$index]) > 1 ? array_diff($onid_tsid_sid[$index], (array)$channel_info["id"]) : [];

                printf("\t\t\t<li><input id=\"ch-%u\" type=\"checkbox\" %s%s/><label for=\"ch-%u\">%s <em>(%d-%d-%d)</em></label></li>\n",
                    $channel_info["id"],
                    (!empty($conflicting) ? sprintf('data-conflict="[%s]" ', implode(",", $conflicting)) : ""),
                    (in_array($channel_info["id"], $channel_ids_list) ? 'class="checked" ' : ""),
                    $channel_info["id"],
                    $channel_info["ch_name"],
                    $channel_info["onid"],
                    $channel_info["tsid"],
                    $channel_info["sid"]
                );
            }
        }
?>
        </ul>
    </section>
<?php
    }
?>
</body>
</html>
<?php
}

// convert values of an array to integers and drop the invalid ones
function sanitise_data(&$data)
{
    foreach ($data as $key => &$value)
    {
        $value = intval($value);

        if ($value < 1 || $value > 255)
        {
            unset($value);
        }
    }
}

function compress_id($id)
{
    static $alphabet="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-";

    $short = "";

    while($id)
    {
        $id = ($id - ($r=$id%64) ) / 64;
        $short = $alphabet{$r} . $short;
    }

    return $short;
}

function expand_id($id)
{
    static $alphabet = [
        "0"=>0,  "1"=>1,  "2"=>2,  "3"=>3,  "4"=>4,  "5"=>5,  "6"=>6,  "7"=>7,  "8"=>8,  "9"=>9,  "_"=>62, "-"=>63,
        "a"=>10, "b"=>11, "c"=>12, "d"=>13, "e"=>14, "f"=>15, "g"=>16, "h"=>17, "i"=>18, "j"=>19, "k"=>20, "l"=>21, "m"=>22,
        "n"=>23, "o"=>24, "p"=>25, "q"=>26, "r"=>27, "s"=>28, "t"=>29, "u"=>30, "v"=>31, "w"=>32, "x"=>33, "y"=>34, "z"=>35,
        "A"=>36, "B"=>37, "C"=>38, "D"=>39, "E"=>40, "F"=>41, "G"=>42, "H"=>43, "I"=>44, "J"=>45, "K"=>46, "L"=>47, "M"=>48,
        "N"=>49, "O"=>50, "P"=>51, "Q"=>52, "R"=>53, "S"=>54, "T"=>55, "U"=>56, "V"=>57, "W"=>58, "X"=>59, "Y"=>60, "Z"=>61
    ];

    $long = 0;
    $len = strlen($id);

    for ($i = 0; $i < $len; $i++)
    {
        $long += pow(64, $len - $i - 1) * $alphabet[$id{$i}];
    }

    return $long;
}
