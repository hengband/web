<?php
/**
 * Webページテンプレートを扱うクラス
 * 使用方法：
 * 1. インスタンス生成
 * 2. 必要ならばset_title()でタイトル設定
 * 3. 必要ならばadd_head_contentsでheadタグの内容を追加
 * 4. main_contents_fp()で得たリソースにコンテンツを書き込み
 * 5. print_page()でWebページ出力
 */
class WebTemplate
{
    private static $template_dir = "../template";

    public function __construct() {
        $this->template = file(self::$template_dir."/template.html");
        $this->head = file(self::$template_dir."/head.html");
        $this->header = file(self::$template_dir."/header.html");
        $this->footer = file(self::$template_dir."/footer.html");

        $this->main_contents_fp = fopen('php://temp', 'r+');
    }

    /**
     * Webページのメインコンテンツを書き込むリソースのポインタを得る
     *
     * @return resource Webページのメインコンテンツを書き込むリソースのポインタ
     */
    public function main_contents_fp()
    {
        return $this->main_contents_fp;
    }


    /**
     * Webページのhead内のtilteタグの内容をセットする
     *
     * @param string title セットするタイトルの文字列
     */
    public function set_title($title)
    {
        foreach ($this->head as &$line) {
            $line = preg_replace('/<title>.*<\/title>/', "<title>{$title}</title>", $line);
        }
    }


    /**
     * Webページのhead内にコンテンツを追加する
     * javasciprtやCSSを読み込ませるタグの追加に使用
     *
     * @param string contents 追加するコンテンツの文字列
     */
    public function add_head_contents($contents)
    {
        $end_line = array_pop($this->head);
        array_push($this->head, $contents."\n", $end_line);
    }


    /**
     * Webページを出力する
     */
    public function print_page() {
        rewind($this->main_contents_fp);
        $this->main_contents = [stream_get_contents($this->main_contents_fp)];
        foreach ($this->template as $line) {
            echo $line;
            switch (trim($line)) {
            case "<!--head-->":
                self::print_page_sub($this->head);
                break;
            case "<!--header-->":
                self::print_page_sub($this->header);
                break;
            case "<!--main contents-->":
                self::print_page_sub($this->main_contents);
                break;
            case "<!--footer-->":
                self::print_page_sub($this->footer);
                break;
            }
        }
    }

    private static function print_page_sub($lines) {
        foreach ($lines as $line){
            echo $line;
        } 
    }
}
