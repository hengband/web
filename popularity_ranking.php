<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

include "db_common.inc";

function print_popularity_table($stat, $name)
{
    echo <<< EOM
        <table>
    <tr>
    <th>#
    <th>$name
    <th>計
    <th>男性
    <th>女性
    <th>勝利
    <th>平均スコア
    <th>最大スコア
    </tr>
EOM;

    $rank = 0;
    foreach ($stat as $s) {
        $rank ++;
        $average_score = floor($s['average_score']);
        echo <<< EOM
<tr>
            <td>$rank</td>
        <td>{$s['name']}</td>
        <td>{$s['total_count']}</td>
        <td>{$s['male_count']}</td>
        <td>{$s['female_count']}</td>
        <td>{$s['winner_count']}</td>
        <td>$average_score</td>
        <td>{$s['max_score']}</td>
        </tr>
EOM;
    }

    echo "</table>";
}


$db = new ScoreDB();

$time_start = microtime(true);

$statistics = $db->get_statistics_tables();

$query_time = microtime(true) - $time_start;
?>

<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
<title>変愚蛮怒 人気のある種族・職業・性格</title>
</head>

<small>クエリ時間<?php echo sprintf("%.3f msec", $query_time * 1000) ?></small>
<hr>
<h1>人気のある種族</h1>

<?php
print_popularity_table($statistics['race'], "種族");
?>

<hr>
<h1>人気のある職業</h1>

<?php
print_popularity_table($statistics['class'], "職業");
?>

<hr>
<h1>人気のある性格</h1>

<?php
print_popularity_table($statistics['personality'], "性格");
?>
