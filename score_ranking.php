<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

require_once "db_common.inc";
require_once "dump_file.inc";

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
 * ページナビゲーションテーブルを表示する
 *
 * @param array $pageinfo calc_page_info()関数で取得したページ情報を保持する連想配列
 */
function print_navi_page_table($pageinfo)
{
    if (count($pageinfo['navi_list']) <= 1) return;

    $href_base = preg_replace('/(&?start=\w+)/', '', filter_input(INPUT_SERVER, 'REQUEST_URI'));
    if (strpos($href_base, "?") === FALSE) {
        $href_base .= "?";
    }

    echo "<table align='center'>\n"
        ."<tr>";

    if ($pageinfo['current'] > 0) {
        $href = $href_base . "&start=". ($pageinfo['current'] - 1) * $pageinfo['data_count_per_page'];
        echo "<td><a href={$href}>&lt; 前へ</a></td>";
    }

    foreach ($pageinfo['navi_list'] as $page) {
        $page_num = $page + 1;
        $href = $href_base . "&start=". $page * $pageinfo['data_count_per_page'];
        if ($page === $pageinfo['current']) {
            echo "<td>$page_num</td>";
        } else {
            echo "<td><a href={$href}>$page_num</a></td>";
        }
    }

    if ($pageinfo['current'] < $pageinfo['last']) {
        $href = $href_base . "&start=". ($pageinfo['current'] + 1) * $pageinfo['data_count_per_page'];
        echo "<td><a href={$href}>次へ &gt;</a></td>";
    }

    echo "</tr>\n"
        ."</table>\n";
}


/**
 * スコアランキングテーブルを表示する
 *
 * @param array $scores スコア
 * @param integer $rank_start 順位の開始番号(0オリジン)
 */
function print_score_table($scores, $rank_start)
{
    echo <<<EOM
<table align='center' border=1>
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

EOM;

    foreach($scores as $idx => $score) {
        $rank = $rank_start + $idx + 1;
        $date = substr($score['date'], 0, 10); // 日時から日付部分を取り出す
        $sex_str = $score['sex'] ? "男" : "女";
        $depth = !$score['winner'] ? $score['depth']."階, " : "";
        $realms = isset($score['realms_name']) ? "(".$score['realms_name'].")" : "";
        $dumpfile = new DumpFile($score['score_id']);

        echo "<tr>\n";
        if ($dumpfile->exists('dumps', 'txt')) {
            $name = "<a href=\"show_dump.php?score_id={$score['score_id']}\">{$score['personality_name']}{$score['name']}</a>\n";
        } else {
            $name = "{$score['personality_name']}{$score['name']}";
        }
        echo <<<EOM
<td>$rank</td>
<td align="right">{$score['score']}</td>
<td><nobr>$date</nobr></td>
<td>$name</td>
<td>{$score['race_name']}</td>
<td>{$score['class_name']}$realms</td>
<td>$sex_str</td>

EOM;
        if ($dumpfile->exists('screens', 'html')) {
            echo "<td><a href=\"show_screen.php?score_id={$score['score_id']}\">{$score['death_reason']}</a>";
        } else {
            echo "<td>{$death_reason}";
        }
        echo "<br>({$depth}{$score['version']})</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

$db = new ScoreDB();

$start_num = filter_input(INPUT_GET, 'start', FILTER_VALIDATE_INT) ?: 0;
$search_result = $db->search_score($start_num, 50);

$pageinfo = calc_page_info($search_result['total_data_count'], $start_num, 50);
?>

<!DOCTYPE html>

<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="ROBOTS" content="NOINDEX, NOFOLLOW">
<title>変愚蛮怒 スコア ランキング</title>
<link rel="alternate" title="変愚蛮怒 新着スコア" href="html/newcome-rss.xml" type="application/rss+xml">
</head>

<body>
<h1>変愚蛮怒 歴代スコア (<?php echo $db->get_sort_mode_name(); ?>)</h1>
<!--3日以内のスコアは<font color=red>赤</font>、10日以内のスコアは<font color=blue>青</font>で表示されます。<br>-->
<!--10日以内のスコアは<strong>強調表示</strong>されます。-->
<!--<br><a href ="html/newcome-rss.xml">新着チェック用RSS</a><small>…スコア受信時に自動生成します。URLをRSSリーダー等に登録すると新着スコアをチェックできます。</small>-->
<small>
<?php
echo sprintf("件数 %d 件 (%.2f 秒)", $search_result['total_data_count'], $search_result['elapsed_time']);
?>
</small>

<hr>

<?php
print_navi_page_table($pageinfo);
print_score_table($search_result['scores'], $pageinfo['current'] * $pageinfo['data_count_per_page']);
print_navi_page_table($pageinfo);
?>
