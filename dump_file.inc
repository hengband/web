<?php
class DumpFile
{
    public function __construct($score_id) {
        $this->score_id = $score_id;
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

        if ($content_encoding !== FALSE) {
            header("Content-Encoding: ".$content_encoding);
            echo $contents;
        } else {
            echo gzdecode($contents);
        }
    }


    public function save($dir, $ext, $contents)
    {
        if ($outputs === FALSE) return;

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


    private static function browser_accept_encodings()
    {
        if (!isset($_SERVER["HTTP_ACCEPT_ENCODING"])) return [];

        return array_map('trim', explode(",", $_SERVER["HTTP_ACCEPT_ENCODING"]));
    }


    private static function get_content_encoding()
    {
        $supported_gzip_encodings = array_intersect(
            self::browser_accept_encodings(),
            ['gzip', 'x-gzip']);

        return count($supported_gzip_encodings) > 0 ?
            $supported_gzip_encodings[0] : FALSE;
    }

}
