<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "db_common.inc";

function print_popularity_table($stat, $id_name, $name, $current_sort_key)
{
    echo <<<EOM
<table>
<tr>
<th>#</th>
<th>$name</th>
EOM;
    
    foreach ([
        '計' => 'total_count',
        '男性' => 'male_count',
        '女性' => 'female_count',
        '勝利' => 'winner_count',
        '平均スコア' => 'average_score',
        '最大スコア' => 'max_score',
    ] as $name => $sort_key) {
        if ($sort_key !== $current_sort_key)
            echo "<th><a href='popularity_ranking.php?sort_key=${sort_key}'>${name}</a></th>";
        else {
            echo "<th><strong>${name}</strong></th>";
        }
    }
    echo "</tr>\n";

    $rank = 0;
    foreach ($stat as $k => $s) {
        $rank ++;
        $name_link = "<a href='score_ranking.php?{$id_name}={$s['id']}'>{$s['name']}</a></td>";
        $average_score = floor($s['average_score']);
        echo <<<EOM
<tr>
<td>$rank</td>
<td>$name_link</td>
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

$sort_key_column = filter_input(INPUT_GET, 'sort_key') ?: 'total_count';
$statistics = $db->get_statistics_tables($sort_key_column);

$query_time = microtime(true) - $time_start;
?>

<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
<title>変愚蛮怒 人気のある種族・職業・性格</title>
</head>

<small>
<?php
echo sprintf("(%.2f 秒)", $query_time);
?>
</small>
<hr>
<h1>人気のある種族</h1>

<?php
print_popularity_table($statistics['race'], 'race_id', "種族", $sort_key_column);
?>

<hr>
<h1>人気のある職業</h1>

<?php
print_popularity_table($statistics['class'], 'class_id', "職業", $sort_key_column);
?>

<hr>
<h1>人気のある性格</h1>

<?php
print_popularity_table($statistics['personality'], 'personality_id', "性格", $sort_key_column);
?>
