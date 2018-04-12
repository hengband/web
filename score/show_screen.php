<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

require_once "dump_file.inc";

$score_id = filter_input(INPUT_GET, 'score_id', FILTER_VALIDATE_INT);
if ($score_id === false || $score_id === null) {
    http_response_code(404);
} else {
    $dump_file = new DumpFile($score_id);
    $dump_file->show('screens', 'html', 'text/html; charset=UTF-8');
}
