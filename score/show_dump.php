<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

require_once "dump_file.inc";

$dump_file = new DumpFile($_GET['score_id']);
$dump_file->show('dumps', 'txt', 'text/plain; charset=UTF-8');
