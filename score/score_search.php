<?php
//ini_set('display_errors', 'On');
date_default_timezone_set('UTC');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "db_common.inc";
$db = new ScoreDb();
$defines = $db->get_defines();

function print_select_form($id_defines, $form_name)
{
    echo "<select name='{$form_name}'>";
    echo "<option value='0' selected>すべて</option>";
    foreach ($id_defines as $num => $name) {
        if ($num > 0 && strpos($name, '不明') !== 0) {
            echo "<option value='{$num}'>{$name}</option>";
        }
    }
    echo "</select>";
}
?>

<!DOCTYPE html>

<html lang="jp">
	<head>
		<meta charset="utf-8"/>
		<link rev=made href="mailto:hengband-dev@lists.sourceforge.jp">
		<link rel="stylesheet" type="text/css" href="/hengband.css">
		<title>変愚蛮怒 公式WEB</title>
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
<h2>変愚蛮怒スコア 詳細検索</h2>
<form action="score_ranking.php" method="GET">
<p>
<label for="race_id">種族:</label>
<?php print_select_form($defines['race'], 'race_id'); ?>
<label for="class_id">職業:</label>
<?php print_select_form($defines['class'], 'class_id'); ?>
<label for="class_id">性格:</label>
<?php print_select_form($defines['personality'], 'personality_id'); ?>
</p>
<p>
<label for="realm_id1">領域1:</label>
<?php print_select_form($defines['realm'], 'realm_id1'); ?>
<label for="realm_id2">領域2:</label>
<?php print_select_form($defines['realm'], 'realm_id2'); ?>
</p>
<p>
<label for="name">キャラクター名</label>
<input type="text" name="name">
<label for="name_match_strict">
<input type="radio" name="name_match" value="strict" id="name_match_strict" checked="checked">完全一致
</label>
<label for="name_match_partial">
<input type="radio" name="name_match" value="partial" id="name_match_partial">部分一致
</label>
</p>
<p>
<label for="name">死因</label>
<input type="text" name="killer" placeholder="死因を入力(例：デスソード)">※部分一致のみ、「勝利の後引退」は'ripe'、「勝利の後切腹」は'Seppuku'と入力
</p>
<p>
<label for="sort">ソート順</label>
<select name="sort" id="sort">
<option value="socre">スコア順</option>
<option value="newcome">新着順</option>
</select>
</p>
<p>
<input type="submit" value="検索"></input>
</p>
</form>
      <!--main contents-->
		</div>

		<footer>

		<section>
		各ページへのリンクは御自由にどうぞ。/ Link Free.<br>
		2018 Hengband Dev Team. <a href="mailto:hengband-dev@lists.sourceforge.jp">hengband-dev@lists.sourceforge.jp</a><br>
		</section>

		<section>
		Powered by <a href="https://osdn.net/" class="footer_banner">
		<img src="//osdn.net/sflogo.php?group_id=541" border="0" alt="OSDN">
		</a>
		</section>

		</footer>

	</body>

</html>

