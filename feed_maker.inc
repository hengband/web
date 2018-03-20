<?php

require_once 'dump_file.inc';
require_once 'FeedWriter/vendor/autoload.php';

use \FeedWriter\ATOM;

class FeedMaker
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * ATOM形式の新着スコア一覧のフィードを生成する
     *
     * @param $feed_path フィードを書き込むファイルのパス
     */
    public function make_atom_feed($feed_path)
    {
        $base_url = "http://". filter_input(INPUT_SERVER, 'SERVER_NAME') . dirname(filter_input(INPUT_SERVER, 'SCRIPT_NAME'));

        $feed = new ATOM();

        $feed->setTitle('変愚蛮怒 スコアランキング (新着順)');
        $feed->setDescription('変愚蛮怒新着スコア');
        $feed->setLink("{$base_url}/score_ranking.php?mode=newcome");
        $feed->setDate(new DateTime());
        $feed->setImage("http://".filter_input(INPUT_SERVER, 'SERVER_NAME')."/tama.gif");
        $feed->setSelfLink("{$base_url}/$feed_path");

        $this->db->set_sort_mode('newcome');
        $search_result = $this->db->search_score(0, 30); // 最新30件を取得

        foreach ($search_result['scores'] as $s) {
            $item = $feed->createNewItem();

            $dump_url = "${base_url}/show_dump.php?score_id={$s['score_id']}";
            $item->setTitle("{$s['personality_name']}{$s['name']} Score:{$s['score']} {$s['race_name']} {$s['class_name']} {$s['death_reason']} @{$s['depth']}F");
            $item->setLink($dump_url);
            $item->setDate($s['date']);

            $dump_file = new DumpFile($s['score_id']);
            $contents = implode(
                '',
                array_map(function ($v) {
                    return htmlentities($v).'<br>';
                }, $dump_file->get_last_message())
            );
            $item->setContent($contents);

            $feed->addItem($item);
        }

        return file_put_contents($feed_path, $feed->generateFeed());
    }
}
