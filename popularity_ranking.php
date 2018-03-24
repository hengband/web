<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "db_common.inc";

function print_popularity_table($stat, $id_name, $name)
{
    echo <<<EOM
<table class="tablesorter statistics_table" id="${id_name}">
<thead>
<tr>
<th>$name</th>
EOM;
    
    foreach ([
        '計', '男性', '女性', '勝利', '平均スコア', '最大スコア',
    ] as $name) {
        echo "<th>${name}</th>";
    }
    echo "</tr>\n";
    echo "</thead>\n";

    foreach ($stat as $k => $s) {
        $name_link = "<a href='score_ranking.php?{$id_name}={$s['id']}'>{$s['name']}</a></td>";
        $average_score = floor($s['average_score']);
        echo <<<EOM
<tr>
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

$statistics = $db->get_statistics_tables('total_count');

$query_time = microtime(true) - $time_start;
?>

<!DOCTYPE html>

<html lang="jp">
        <head>
                <meta charset="utf-8"/>
                <link rev=made href="mailto:hengband-dev@lists.sourceforge.jp">
                <link rel="stylesheet" type="text/css" href="/hengband.css">
                <link rel="stylesheet" type="text/css" href="tablesorter-theme/style.css">
                <link rel="alternate" title="変愚蛮怒 新着スコア" href="feed/newcome-atom.xml" type="application/atom+xml" />
                <script
                src="https://code.jquery.com/jquery-3.3.1.min.js"
                integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
                crossorigin="anonymous"></script>

                <script src="jquery.tablesorter.min.js" type="text/javascript"></script>
                <script src="popularity_ranking.js" type="text/javascript"></script>
                <title>変愚蛮怒 公式WEB スコアランキング 人気のある種族・職業・性格</title>
        </head>

        <body>

                <header>

                        <section id="title">
                                <img class="tama1" src="/image/tama.gif" alt="tama">
                                <img class="tama2" src="/image/tama.gif" alt="tama">
                                <img class="tama3" src="/image/tama.gif" alt="tama">
                                <img class="tama4" src="/image/tama.gif" alt="tama">
                                <img id="hengTitle" src="/image/hengband_title.png" alt="変愚蛮怒 Hengband">
                                <img class="tama4" src="/image/tama.gif" alt="tama">
                                <img class="tama3" src="/image/tama.gif" alt="tama">
                                <img class="tama2" src="/image/tama.gif" alt="tama">
                                <img class="tama1" src="/image/tama.gif" alt="tama">
                        </section>

                        <section id="mainMenu">
                                <a href="/index.html">トップ</a>
                                <a href="/download.html">ダウンロード</a>
                                <a href="/score.html">スコア</a>
                                <a href="/lists.html">コミュニティ</a>
                                <a href="/history.html">バージョン履歴</a>
                                <a href="/link.html">関連リンク</a>
                                <a href="/jlicense.html">著作権表記</a>
                                <span>English (Coming Soon)</span>
                        </section>

                </header>
 
                <div id="main">
<!--main contents-->
<h2>人気のある種族・職業・性格</h2>
<!--
<small>
<?php
echo sprintf("(%.2f 秒)", $query_time);
?>
</small>
-->
<nobr>[ <a href="javascript:void(0)" class="table_select" id="race_id">種族</a> | <a href="javascript:void(0)" class="table_select" id="class_id">職業</a> | <a href="javascript:void(0)" class="table_select" id="personality_id">性格</a> ]</nobr>

<?php
print_popularity_table($statistics['race'], 'race_id', "種族");
?>
<?php
print_popularity_table($statistics['class'], 'class_id', "職業");
?>
<?php
print_popularity_table($statistics['personality'], 'personality_id', "性格");
?>

                </div>

                <footer>

                        <section>
                                各ページへのリンクは御自由にどうぞ。/ Link Free.<br>
                                2018 Hengband Dev Team. <a href="mailto:hengband-dev@lists.sourceforge.jp">hengband-dev@lists.sourceforge.jp</a><br>
                        </section>

                        <section>
                                Powered by <a href="https://ja.osdn.net/" class="footer_banner">
                                <img src="https://ja.osdn.net/sflogo.php?group_id=541" border="0" alt="OSDN.jp">
                                </a>
                        </section>

                </footer>

        </body>

</html>
