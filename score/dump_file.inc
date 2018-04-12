<?php
class DumpFile
{
    public function __construct($score_id) {
        $this->score_id = $score_id;
        $this->dump_lines = NULL;
    }


    public function get_filename($dir, $ext)
    {
        $dirname = sprintf("%s/%d", $dir, floor($this->score_id / 1000) * 1000);
        return sprintf("%s/%d.%s.gz", $dirname, $this->score_id, $ext);
    }


    public function show($dir, $ext, $content_type)
    {
        $filename = $this->get_filename($dir, $ext);

        if (!file_exists($filename)) {
            http_response_code(404);
            return;
        }

        $contents = file_get_contents($filename);
        $etag = md5($contents);

        if ($etag === filter_input(INPUT_SERVER, 'HTTP_IF_NONE_MATCH')) {
            http_response_code(304);
            return;
        }

        $content_encoding = self::get_content_encoding();
        header("Etag: ".$etag);
        header("Content-Type: ".$content_type);

        if ($content_encoding !== NULL) {
            header("Content-Encoding: ".$content_encoding);
            echo $contents;
        } else {
            echo gzdecode($contents);
        }
    }


    public function save($dir, $ext, $contents)
    {
        if ($contents === FALSE) return;

        umask(2); // Group書き込み権限許可

        $filename = $this->get_filename($dir, $ext);
        $dirname = dirname($filename);

        if (!file_exists($dirname)) {
            mkdir($dirname, 02775, TRUE);
        }

        $zp = gzopen($filename, "w9");

        foreach ($contents as $line) {
            gzwrite($zp, $line);
            gzwrite($zp, "\n");
        }

        gzclose($zp);
    }


    public function exists($dir, $ext)
    {
        $dirname = sprintf("%s/%d", $dir, floor($this->score_id / 1000) * 1000);
        $filename = sprintf("%s/%d.%s.gz", $dirname, $this->score_id, $ext);

        return file_exists($filename);
    }


    /**
     * キャラクタダンプを配列に読み込む
     * 読み込んだ配列はメンバ変数dump_linesに格納
     *
     * @return boolean 読み込みに成功した場合TRUE、失敗した場合FALSE
     */
    private function readlines()
    {
        if ($this->dump_lines !== NULL) {
            return TRUE;
        }

        $lines = gzfile($this->get_filename('dumps', 'txt'));
        if ($lines !== FALSE) {
            $this->dump_lines = $lines;
            return TRUE;
        }

        return FALSE;
    }


    /**
     * キャラクタダンプの死ぬ直前のメッセージもしくは勝利メッセージを取得する
     *
     * @return array 死ぬ直前のメッセージもしくは勝利メッセージを1行1要素にした文字列の配列
     */
    public function get_last_message()
    {
        if ($this->readlines() === FALSE) {
            return [];
        }

        $in_message = FALSE;
        $result = [];
        foreach ($this->dump_lines as $line) {
            if (preg_match('/^\s*\[(.*)\]\s*$/u', $line, $matches)) {
                if ($matches[1] == '*勝利*メッセージ' ||
                    $matches[1] == '死ぬ直前のメッセージ') {
                    $in_message = TRUE;
                }
                else if ($in_message) {
                    break;
                }
            }

            if ($in_message) {
                $result[] = rtrim($line, "\n");
            }
        }

        return $result;
    }


    /**
     * キャラクタダンプから死因の詳細を得る
     *
     * @return string 死因の詳細を表す文字列
     */
    public function get_death_reason_detail()
    {
        if ($this->readlines() === FALSE) {
            return FALSE;
        }

        $death_reason_lines = array_slice($this->dump_lines, 30, 3);
        $death_reason = implode("", array_map('trim', $death_reason_lines));

        if (preg_match("/^…あなたは、?(.+)。/u", $death_reason, $matches)) {
            return $matches[1];
        } else {
            return FALSE;
        }
    }


    /**
     * キャラクタダンプから死んだ場所を得る
     *
     * @return string|FALSE 死んだ場所を表す文字列。
     *                死んだ場所が得られなかった場合FALSE。
     */
    public function get_dead_place()
    {

        $death_reason_detail = $this->get_death_reason_detail();
        if ($death_reason_detail === FALSE) {
            return FALSE;
        }
        $places = implode("|", [
            "階", "荒野", "地上", "街",
            "辺境の地", "モリバント", "アングウィル", "テルモラ", "ズル",
            "クエスト「.+」", "クエスト",
        ]);

        if (preg_match("/^(.*(?:{$places})\s*)で.+$/u",
                        $death_reason_detail, $matches)) {
            return $matches[1];
        } else {
            return FALSE;
        }
    }


    private static function browser_accept_encodings()
    {
        $accept_encoding = filter_input(INPUT_SERVER, 'HTTP_ACCEPT_ENCODING');
        if ($accept_encoding == NULL) return [];

        return array_map('trim', explode(",", $accept_encoding));
    }


    private static function get_content_encoding()
    {
        $supported_gzip_encodings = array_intersect(
            self::browser_accept_encodings(),
            ['gzip', 'x-gzip']);

        return array_shift($supported_gzip_encodings);
    }

}
