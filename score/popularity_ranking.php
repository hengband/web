<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "db_common.inc";
require_once "web_template.inc";

function print_popularity_table($fp, $stat, $id_name, $name)
{
    fwrite($fp, <<<EOM
<div id="{$id_name}">
<table class="tablesorter score statistics_table">
<thead>
<tr>
<th>$name</th>

EOM
    );
    
    foreach ([
        '計', '男性', '女性', '勝利', '平均スコア', '最大スコア',
    ] as $name) {
        fwrite($fp, "<th>${name}</th>\n");
    }
    fwrite($fp, "</tr>\n".
           "</thead>\n");

    foreach ($stat as $k => $s) {
        $name_link = "<a href='score_ranking.php?{$id_name}={$s['id']}'>{$s['name']}</a></td>";
        fwrite($fp, <<<EOM
<tr>
<td>$name_link</td>
<td class="number">{$s['total_count']}</td>
<td class="number">{$s['male_count']}</td>
<td class="number">{$s['female_count']}</td>
<td class="number">{$s['winner_count']}</td>
<td class="number">{$s['average_score']}</td>
<td class="number">{$s['max_score']}</td>
</tr>

EOM
        );
    }

    fwrite($fp, "</table>\n".
           "</div>\n");
}

function print_realm_popularity_table($fp, $stat, $id_name)
{
    // 魔法領域の統計を職業ごとにグループ分け
    $class_ids = array_unique(array_column($stat, "class_id"));
    $class_realm_stat_list = array_fill_keys($class_ids, []);

    foreach ($stat as $s) {
        $class_realm_stat_list[intval($s["class_id"])][] = $s;
    }

    fwrite($fp, "<div id=\"{$id_name}\">");

    // 職業ごとにテーブルを表示
    foreach ($class_realm_stat_list as $class_id => $class_realm_stat) {
        if (count($class_realm_stat) <= 1) continue; // 領域固定の職業は飛ばす

        $class_name = $class_realm_stat[0]['class_name'];

        fwrite($fp, <<<EOM
<table class="tablesorter score statistics_table" id="${id_name}">
<thead>
<tr>
<th>{$class_name}</th>

EOM
        );
        foreach ([
            '計', '男性', '女性', '勝利', '平均スコア', '最大スコア',
        ] as $th_name) {
            fwrite($fp, "<th>${th_name}</th>\n");
        }
        fwrite($fp, "</tr>\n".
               "</thead>\n");

        foreach ($class_realm_stat as $realm) {
            $name_link = "<a href='score_ranking.php?class_id={$class_id}&{$id_name}={$realm['realm_id']}'>{$realm['realm_name']}</a></td>";
            fwrite($fp, <<<EOM
<tr>
<td>$name_link</td>
<td class="number">{$realm['total_count']}</td>
<td class="number">{$realm['male_count']}</td>
<td class="number">{$realm['female_count']}</td>
<td class="number">{$realm['winner_count']}</td>
<td class="number">{$realm['average_score']}</td>
<td class="number">{$realm['max_score']}</td>
</tr>

EOM
            );
        }

        fwrite($fp, "</table>\n");
    }
    fwrite($fp, "</div>\n");
}

$db = new ScoreDB();

$time_start = microtime(true);

$statistics = $db->get_statistics_tables('total_count');

$query_time = microtime(true) - $time_start;

$wt = new WebTemplate();

$wt->add_head_contents('<meta name="robots" content="none" />');
$wt->add_head_contents('<link rel="stylesheet" type="text/css" href="css/score-table.css">');
$wt->add_head_contents('<link rel="stylesheet" type="text/css" href="tablesorter-theme/style.css">');
$wt->add_head_contents(
    <<<EOM
<script
src="https://code.jquery.com/jquery-3.3.1.min.js"
integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
crossorigin="anonymous"></script>
EOM
);
$wt->add_head_contents('<script src="jquery.tablesorter.min.js" type="text/javascript"></script>');
$wt->add_head_contents('<script src="popularity_ranking.js" type="text/javascript"></script>');
$wt->set_title("変愚蛮怒 スコアランキング 人気のある種族・職業・性格・魔法領域");

$fp = $wt->main_contents_fp();
fwrite($fp, "<h2>人気のある種族・職業・性格・魔法領域</h2>\n");
//fprintf($fp, "<small>(%.2f 秒)</small>", $query_time);
fwrite($fp, <<<EOM
<nobr>[ <a href="javascript:void(0)" class="table_select" id="race_id">種族</a> | <a href="javascript:void(0)" class="table_select" id="class_id">職業</a> | <a href="javascript:void(0)" class="table_select" id="personality_id">性格</a> ] [ <a href="javascript:void(0)" class="table_select" id="realm_id1">魔法領域1</a> | <a href="javascript:void(0)" class="table_select" id="realm_id2">魔法領域2</a> ]</nobr>

EOM

);

print_popularity_table($fp, $statistics['race'], 'race_id', "種族");
print_popularity_table($fp, $statistics['class'], 'class_id', "職業");
print_popularity_table($fp, $statistics['personality'], 'personality_id', "性格");
print_realm_popularity_table($fp, $statistics['realm1'], 'realm_id1');
print_realm_popularity_table($fp, $statistics['realm2'], 'realm_id2');

$wt->print_page();
