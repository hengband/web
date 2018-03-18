<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

require_once "db_common.inc";
require_once "dump_file.inc";
require_once "feed_maker.inc";

// 登録が成功しない場合、HTTPレスポンスコード 400 Bad Request を返す
http_response_code(400);

/**
 * 送信されてきたスコアのマルチバイト文字エンコーディングを取得する
 *
 * Content-Typeヘッダからエンコーディングを取得する。
 * 同時に変愚蛮怒が送ってきているはずのContent-Typeヘッダと一致しているかチェックする。
 *
 * @return string|false 送られてきたスコアの、PHPのマルチバイト文字処理における文字エンコーディングを表す文字列
 *                      取得できなかった場合はFALSE
 */
function get_mb_encoding(){
    $content_type = filter_input(INPUT_SERVER, 'CONTENT_TYPE');
    if ($content_type == NULL) return FALSE;

    $s = explode(';', $content_type);

    if (count($s) != 2 ||
        $s[0] !== "text/plain" ||
        strpos(trim($s[1]), "charset=") !== 0) return FALSE;

    $c = explode('=', $s[1]);

    switch (strtolower(trim($c[1]))) {
    case "euc-jp":
        return "EUC-JP";
    case "shift_jis":
        return "SJIS";
    case "utf-8":
        return "UTF-8";
    default:
        return FALSE;
    }
}


/**
 * 受信したスコアデータを分割する
 *
 * 受信したスコアデータを、キャラクタ情報部分・キャラクタダンプ部分・スクリーンショット部分の3つに分割する
 * それぞれの部分は1行毎の文字列の配列とする
 *
 * @param string $recv_contents 受信したスコアデータ
 * @return array|false キャラクタ情報・キャラクタダンプ・スクリーンショットのそれぞれの部分のデータの配列
 *                     分割できなかった場合はFALSE
 */
function split_recv_contents($recv_contents)
{
    $recv_lines = explode("\n", $recv_contents);

    $dump_info_end_line = array_search('-----charcter dump-----', $recv_lines);
    $dump_txt_end_line = array_search('-----screen shot-----', $recv_lines);

    if ($dump_info_end_line === FALSE) return FALSE;

    $info_lines = array_slice($recv_lines, 0, $dump_info_end_line);
    $dump_lines = array_slice(
        $recv_lines,
        $dump_info_end_line + 1,
        $dump_txt_end_line ? $dump_txt_end_line - $dump_info_end_line - 1: NULL
    );
    $screen_lines = $dump_txt_end_line ?
                  array_slice(
                      $recv_lines,
                      $dump_txt_end_line + 1,
                      NULL
                  ) : FALSE;

    return [$info_lines, $dump_lines, $screen_lines];
}


/**
 * キャラクタ情報をパースする
 *
 * スコアデータのキャラクタ情報をパースし、情報名=>値の連想配列を得る
 * ex) name: Hoge を ['name' => 'Hoge'] のようにする
 *
 * @param string $info_liens 受信したスコアデータのキャラクタ情報(1要素1行の配列)
 * @return array|false キャラクタ情報・キャラクタダンプ・スクリーンショットのそれぞれの部分のデータの配列
 *                     分割できなかった場合はFALSE
 */
function parse_character_info($info_lines)
{
    $info = [];
    foreach ($info_lines as $l) {
        $splitpos = strpos($l, ':');
        if ($splitpos !== FALSE) {
            $key = substr($l, 0, $splitpos);
            $val = substr($l, $splitpos + 1);
            $info[$key] = trim($val);
        }
    }

    return $info;
}


/**
 * キャラクタ情報からDBへのスコア登録用パラメータを生成する
 *
 * @param array $info キャラクタ情報の連想配列
 * @return array DBへのスコア登録用パラメータ('character_info'と'realm_info'の連想配列)
 */
function create_db_insert_score_data($info)
{
    $character_info_array = [
        'version' => $info['version'],
        'score' => $info['score'],
        'name' => $info['name'],
        'race' => $info['race'],
        'class' => $info['class'],
        'personality' => $info['seikaku'],
        'sex' => $info['sex'],
        'level' => $info['level'],
        'depth' => $info['depth'],
        'maxlv' => $info['maxlv'],
        'maxdp' => $info['maxdp'],
        'au' => $info['au'],
        'turns' => $info['turns'],
        'winner' => $info['killer'] == 'ripe' || $info['killer'] == 'Seppuku',
        'killer' => $info['killer'],
    ];

    $realm_info_array = array_values(
        array_filter(
            [$info['realm1'], $info['realm2']],
            function($v) {return $v !== '魔法なし';})
    );

    return [
        'character_info' => $character_info_array,
        'realm_info' => $realm_info_array,
    ];
}


$recv_encoding = get_mb_encoding();
if ($recv_encoding === FALSE) {
    exit;
}

$recv_contents = file_get_contents('php://input');
if (strlen($recv_contents) !== filter_input(INPUT_SERVER, 'CONTENT_LENGTH', FILTER_VALIDATE_INT)) {
    exit;
}

$recv_contents = mb_convert_encoding($recv_contents, "UTF-8", $recv_encoding);

$split_contents = split_recv_contents($recv_contents);
if ($split_contents === FALSE) {
    exit;
}

$char_info = parse_character_info($split_contents[0]);

$db = new ScoreDB();
$score_id = $db->register_new_score(create_db_insert_score_data($char_info));

if ($score_id === FALSE) {
    exit;
}

$dumpfile = new DumpFile($score_id);
$dumpfile->save('dumps', 'txt', $split_contents[1]);
$dumpfile->save('screens', 'html', $split_contents[2]);

// 登録成功、HTTPレスポンスコード 200 OK を返す
http_response_code(200);

$feed_maker = new FeedMaker($db);
$feed_maker->make_atom_feed("feed/newcome-atom.xml");
