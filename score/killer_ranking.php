<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "common.inc";
require_once "db_common.inc";
require_once "web_template.inc";

$db = new ScoreDB();

$time_start = microtime(true);

$killers = $db->get_killers_table();

$query_time = microtime(true) - $time_start;

$wt = new WebTemplate();
$wt->add_head_contents('<meta name="robots" content="none" />');
$wt->add_head_contents('<link rel="stylesheet" type="text/css" href="./css/score-table.css">');
$wt->set_title("変愚蛮怒 スコア 死因ランキング");

$fp = $wt->main_contents_fp();
fwrite(
    $fp,
    <<<EOM
<h2>変愚蛮怒 死因ランキング</h2>
<table class="score one_row">
<thead>
<tr>
<th>回数</th>
<th>彫像・<br>麻痺状態</th>
<th>死因</th>
</tr>
</thead>

EOM
);

fwrite($fp, "<tboby>\n");
foreach ($killers as $k) {
    //$freeze = $k['killer_count_freeze'] > 0 ? "(".$k['killer_count_freeze'].")" : "";
    $killer_name = h($k['killer_name']);
    fwrite(
        $fp,
        <<<EOM
<tr>
<td class="number">{$k['killer_count_total']}</td>
<td class="number">{$k['killer_count_freeze']}</td>
<td>$killer_name</td>
</tr>

EOM
    );
}
fwrite($fp, "</tboby>\n");
fwrite($fp, "</table>\n");

$wt->print_page();
