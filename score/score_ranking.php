<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

require_once "common.inc";
require_once "db_common.inc";
require_once "dump_file.inc";
require_once "web_template.inc";

ini_set('zlib.output_compression', 'On');


/**
 * ページ情報を計算する
 *
 * @param integer $total_data_count 全データ件数
 * @param integer $start_num GETパラメータで渡された開始データ番号
 * @param integer $data_count_per_page 1ページあたりのデータ件数
 *
 * @return array 計算したページ情報を保持する連想配列
 */
function calc_page_info($total_data_count, $start_num, $data_count_per_page)
{
    $current_page = intval($start_num / $data_count_per_page);
    $last_page = intval(($total_data_count - 1) / $data_count_per_page);
    $navi_page_range_start = ($current_page - 5) > 0 ? $current_page - 5: 0;
    $navi_page_range_count = max(0, min(9, $last_page - $navi_page_range_start));
    $navi_page_list = range($navi_page_range_start, $navi_page_range_start + $navi_page_range_count);

    $pageinfo['current'] = $current_page;
    $pageinfo['last'] = $last_page;
    $pageinfo['navi_list'] = $navi_page_list;

    $pageinfo['total_data_count'] = $total_data_count;
    $pageinfo['data_count_per_page'] = $data_count_per_page;

    return $pageinfo;
}


/**
 * ページナビゲーションテーブルを出力する
 *
 * @param resource $fp 出力先リソースへのハンドル
 * @param array $pageinfo calc_page_info()関数で取得したページ情報を保持する連想配列
 */
function print_navi_page_table($fp, $pageinfo)
{
    if (count($pageinfo['navi_list']) <= 1) return;

    $href_base = filter_input(INPUT_SERVER, 'SCRIPT_NAME')."?"
               .preg_replace('/(&?start=\w+)/', '', filter_input(INPUT_SERVER, 'QUERY_STRING'));
    if (strpos($href_base, "?") === FALSE) {
        $href_base .= "?";
    }

    fwrite($fp, "<table align='center'>\n"
           ."<tr>\n");

    if ($pageinfo['current'] > 0) {
        $href = $href_base . "&start=". ($pageinfo['current'] - 1) * $pageinfo['data_count_per_page'];
        fwrite($fp, "<td><a href={$href}>&lt; 前へ</a></td>\n");
    }

    foreach ($pageinfo['navi_list'] as $page) {
        $page_num = $page + 1;
        $href = $href_base . "&start=". $page * $pageinfo['data_count_per_page'];
        if ($page === $pageinfo['current']) {
            fwrite($fp, "<td>$page_num</td>\n");
        } else {
            fwrite($fp, "<td><a href={$href}>$page_num</a></td>\n");
        }
    }

    if ($pageinfo['current'] < $pageinfo['last']) {
        $href = $href_base . "&start=". ($pageinfo['current'] + 1) * $pageinfo['data_count_per_page'];
        fwrite($fp, "<td><a href={$href}>次へ &gt;</a></td>\n");
    }

    fwrite($fp, "</tr>\n"
           ."</table>\n");
}


/**
 * スコアランキングテーブルを出力する
 *
 * @param resource $fp 出力先リソースへのハンドル
 * @param array $scores スコア
 * @param integer $rank_start 順位の開始番号(0オリジン)
 */
function print_score_table($fp, $scores, $rank_start)
{
    fwrite($fp, <<<EOM
<table class="score">
<thead>
<tr>
<th>順位</th>
<th>スコア</th>
<th>日付</th>
<th>名前</th>
<th>種族</th>
<th>職業</th>
<th>性別</th>
<th>死因</th>
</tr>
</thead>

EOM
    );

    fwrite($fp, "<tbody>\n");
    foreach($scores as $idx => $score) {
        $rank = $rank_start + $idx + 1;
        $date = substr($score['date'], 0, 10); // 日時から日付部分を取り出す
        $sex_str = $score['sex'] ? "男" : "女";
        $depth = !$score['winner'] ? $score['depth']."階, " : "";
        $realms = isset($score['realms_name']) ? "(".$score['realms_name'].")" : "";
        $dumpfile = new DumpFile($score['score_id']);

        $name = h("{$score['personality_name']}{$score['name']}");
        if ($dumpfile->exists('dumps', 'txt')) {
            $name = "<a href=\"show_dump.php?score_id={$score['score_id']}\">{$name}</a>";
        }
        fwrite($fp, <<<EOM
<tr>
<td>$rank</td>
<td class="number">{$score['score']}</td>
<td><nobr>$date</nobr></td>
<td>$name</td>
<td>{$score['race_name']}</td>
<td>{$score['class_name']}$realms</td>
<td>$sex_str</td>

EOM
        );
        $death_reason = h($score['death_reason']);
        if ($dumpfile->exists('screens', 'html')) {
            fwrite($fp, "<td><a href=\"show_screen.php?score_id={$score['score_id']}\">{$death_reason}</a>");
        } else {
            fwrite($fp, "<td>{$death_reason}");
        }
        fwrite($fp, "<br>({$depth}".h($score['version']).")</td>\n".
               "</tr>\n");
    }
    fwrite($fp, "</tbody>\n");
    fwrite($fp, "</table>\n");
}

$db = new ScoreDB();

$start_num = filter_input(INPUT_GET, 'start', FILTER_VALIDATE_INT) ?: 0;
$search_result = $db->search_score($start_num, 50);

$pageinfo = calc_page_info($search_result['total_data_count'], $start_num, 50);


$wt = new WebTemplate();
$wt->set_title("変愚蛮怒 スコアランキング");
$wt->add_head_contents('<meta name="robots" content="none" />');
$wt->add_head_contents('<link rel="stylesheet" type="text/css" href="css/score-table.css">');
$fp = $wt->main_contents_fp();
fprintf($fp, "<h2>変愚蛮怒 歴代スコア (%s)</h2>\n", $db->get_sort_mode_name());
fprintf($fp, <<<EOM
<div align="right">
<small>
件数 %d 件 (%.2f 秒)
</small>
</div>

EOM
        ,$search_result['total_data_count'], $search_result['elapsed_time']
);

print_navi_page_table($fp, $pageinfo);
print_score_table($fp, $search_result['scores'], $pageinfo['current'] * $pageinfo['data_count_per_page']);
print_navi_page_table($fp, $pageinfo);

$wt->print_page();
