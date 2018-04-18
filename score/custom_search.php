<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "db_common.inc";
require_once "web_template.inc";

$db = new ScoreDb();
$defines = $db->get_defines();

function print_select_form($fp, $id_defines, $form_name)
{
    fwrite($fp, "<select name='{$form_name}'>\n");
    fwrite($fp, "<option value='0' selected>すべて</option>\n");
    foreach ($id_defines as $num => $name) {
        if ($num > 0 && strpos($name, '不明') !== 0) {
            fwrite($fp, "<option value='{$num}'>{$name}</option>\n");
        }
    }
    fwrite($fp, "</select>\n");
}


$wt = new WebTemplate();
$wt->set_title("変愚蛮怒 スコア カスタム検索");
$fp = $wt->main_contents_fp();

fwrite(
    $fp,
    <<<EOM
<h2>変愚蛮怒 スコア カスタム検索</h2>
<form action="score_ranking.php" method="GET">
<p>

EOM
);

fwrite($fp, "<label for=\"race_id\">種族:</label>");
print_select_form($fp, $defines['race'], 'race_id');
fwrite($fp, " <label for=\"class_id\">職業:</label>");
print_select_form($fp, $defines['class'], 'class_id');
fwrite($fp, " <label for=\"personality_id\">性格:</label>");
print_select_form($fp, $defines['personality'], 'personality_id');

fwrite($fp, "</p>\n");

fwrite($fp, "<p>\n");
fwrite($fp, "<label for=\"realm1_id\">領域1:</label>");
print_select_form($fp, $defines['realm'], 'realm_id1');
fwrite($fp, " <label for=\"realm2_id\">領域2:</label>");
print_select_form($fp, $defines['realm'], 'realm_id2');
fwrite($fp, "</p>\n");

fwrite(
    $fp,
    <<<EOM
<p>
<label for="name">キャラクター名:</label>
<input type="text" name="name">
<label for="name_match_strict">
<input type="radio" name="name_match" value="strict" id="name_match_strict" checked="checked">完全一致
</label>
<label for="name_match_partial">
<input type="radio" name="name_match" value="partial" id="name_match_partial">部分一致
</label>
</p>
<p>
<label for="sex">性別:</label>
<select name="sex" id="sex">
<option value="">すべて</option>
<option value="1">男性</option>
<option value="0">女性</option>
</select>
</p>
<p>
<label for="name">死因:</label>
<input type="text" name="killer" placeholder="死因を入力(例：デスソード)">※部分一致のみ、「勝利の後引退」は'ripe'、「勝利の後切腹」は'Seppuku'と入力
</p>
<p>
<label for="sort">ソート順:</label>
<select name="sort" id="sort">
<option value="socre">スコア順</option>
<option value="newcome">新着順</option>
</select>
</p>
<p>
<input type="submit" value="検索"></input>
</p>
</form>
EOM
           );

$wt->print_page();
