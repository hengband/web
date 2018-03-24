<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "db_common.inc";

$db = new ScoreDB();

$time_start = microtime(true);

$killers = $db->get_killers_table();

$query_time = microtime(true) - $time_start;
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
<title>変愚蛮怒 死因ランキング</title>
</head>

<body>
<h1>変愚蛮怒 死因ランキング</h1>
<small>クエリ時間<?php echo sprintf("%.3f msec", $query_time * 1000) ?></small>
<hr>
<table>
<tr><th>回数(内、彫像・麻痺状態)</th><th>死因</th></tr>
<?php
    foreach ($killers as $k) {
        //$total = $count['knormal'] + $count['freeze'];
        $freeze = $k['killer_count_freeze'] > 0 ? "(".$k['killer_count_freeze'].")" : "";
        echo <<<EOM
<tr><td>{$k['killer_count_total']}$freeze</td><td>{$k['killer_name']}</td></tr>

EOM;
    }
?>
</table>
</body>
</html>
