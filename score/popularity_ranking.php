<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "web_template.inc";

$wt = new WebTemplate();

$wt->add_head_contents('<meta name="robots" content="none" />');
$wt->add_head_contents('<link rel="stylesheet" type="text/css" href="./css/score-table.css">');
$wt->add_head_contents(
    <<<EOM
<script crossorigin src="https://unpkg.com/react@16/umd/react.production.min.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@16/umd/react-dom.production.min.js"></script>
EOM
);
//$wt->add_head_contents('<script src="react-tutorial/js/bundle.js" type="text/javascript"></script>');
$wt->set_title("変愚蛮怒 スコアランキング 人気のある種族・職業・性格・魔法領域");

$fp = $wt->main_contents_fp();
fwrite(
    $fp,
    <<<EOM
<h2>人気のある種族・職業・性格・魔法領域</h2>
<div id="content"></div>
<script src="js/popularity_ranking.bundle.js" type="text/javascript"></script>
EOM
);
$wt->print_page();
