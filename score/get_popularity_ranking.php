<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

ini_set('zlib.output_compression', 'On');

require_once "db_common.inc";
require_once "web_template.inc";

$db = new ScoreDB();

$time_start = microtime(true);

$statistics = $db->get_statistics_tables('total_count');

$query_time = microtime(true) - $time_start;

echo json_encode($statistics);
