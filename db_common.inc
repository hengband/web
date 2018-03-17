<?php
class ScoreDB
{
    private static $sort_mode_list = ['default' => 'score', 'newcome'];

    public function __construct() {
        $this->dbh = new PDO('sqlite:db/score.db');
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->set_sort_mode(filter_input(INPUT_GET, 'sort'));
    }

    /**
     * スコア表示モードを設定する
     *
     * @param string $mode 設定する表示モード
     * 'score' - スコア順に表示(デフォルト)
     * 'newcome' - 新着順に表示
     * 存在しない表示モードが指定された場合デフォルトの表示モードが設定される
     */
    public function set_sort_mode($mode)
    {
        if ($mode !== NULL && in_array($mode, self::$sort_mode_list)) {
            $this->sort_mode = $mode;
        } else {
            $this->sort_mode = self::$sort_mode_list['default'];
        }
    }


    /**
     * スコア表示モード名を取得する
     *
     * @return string スコア表示モード名
     */
    public function get_sort_mode_name()
    {
        switch ($this->sort_mode) {
        case 'score':
            return "スコア順";
        case 'newcome':
            return "新着順";
        default:
            return "不明";
        }
    }


    /**
     * スコア検索の絞り込み用WHERE句を取得する
     *
     * @return string スコア検索絞り込み用のWHERE句
     */
    private function get_where()
    {
        $last_days = filter_input(INPUT_GET, 'last_days', FILTER_VALIDATE_INT);
        if ($last_days > 0) {
            $wheres[] = "date >= datetime('now', 'localtime', '-{$last_days} days')";
        }

        foreach (['race_id', 'class_id', 'personality_id'] as $id_str) {
            $id = filter_input(INPUT_GET, $id_str, FILTER_VALIDATE_INT); 
            if ($id > 0) {
                $wheres[] = "{$id_str} = {$id}";
            }
        }

        $where = isset($wheres) ? 'WHERE '.implode(' AND ', $wheres) : '';

        return $where;
    }


    /**
     * スコアソート用のORDER BY句を取得する
     *
     * @return string スコアソート用のORDER BY句
     */
    private function get_order_by()
    {
        switch ($this->sort_mode) {
        case "score":
            $order_by = "ORDER BY score DESC";
            break;
        case "newcome":
            $order_by = 'ORDER BY score_id DESC';
            break;
        }

        return $order_by;
    }


    /**
     * スコア検索用のSQLクエリを取得する
     *
     * @param integer $offset スコア取得開始位置
     * @param integer $limit スコア取得最大件数
     * @param string $where スコア検索に用いるWHERE句
     * @param string $order_by スコアソートに用いるORDER BY句
     * @return string スコア検索用SQLクエリ
     */
    private function get_search_query($offset, $limit, $where, $order_by)
    {
        switch ($this->sort_mode) {
        case "score":
            $query = "SELECT *, group_concat(realm_name) AS realms_name from (select * from scores ${where} {$order_by} LIMIT ${offset}, {$limit}) NATURAL JOIN races NATURAL JOIN classes NATURAL JOIN personalities NATURAL LEFT JOIN score_realms NATURAL LEFT JOIN realms GROUP BY score_id {$order_by}";
            break;
        case "newcome":
            $query = "SELECT *, group_concat(realm_name) AS realms_name from (select * from scores ${where} {$order_by} LIMIT ${offset}, {$limit}) NATURAL JOIN races NATURAL JOIN classes NATURAL JOIN personalities NATURAL LEFT JOIN score_realms NATURAL LEFT JOIN realms GROUP BY score_id {$order_by}";
            break;
        }

        return $query;
    }


    /**
     * 検索でヒットしたスコアの総件数を取得する
     *
     * @param string $where スコア検索に用いるWHERE句
     * @return integer スコア総件数
     */
    private function get_query_data_count($where)
    {
        $stmt = $this->dbh->query("SELECT count(*) AS data_count from scores {$where}");
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return intval($res[0]['data_count']);
    }


    /**
     * スコアを検索する
     *
     * @param integer $start_num 検索するスコアの開始位置
     * @param integer $count 検索するスコア数
     *
     * @return array 検索したスコアの配列と、条件に合致するスコアの総件数の連想配列
     */
    public function search_score($start_num, $count)
    {
        $where = $this->get_where();
        $order_by = $this->get_order_by();
        $query_sql = $this->get_search_query(intval($start_num / $count) * $count, $count, $where, $order_by);

        $search_start_time = microtime(true);
        $this->dbh->beginTransaction();

        $score_stmt = $this->dbh->query($query_sql);
        $result['scores'] = $score_stmt->fetchAll(PDO::FETCH_ASSOC);
        $result['total_data_count'] = $this->get_query_data_count($where);

        $this->dbh->commit();
        $result['elapsed_time'] = microtime(true) - $search_start_time;

        return $result;
    }

    public function get_db_handle() {
        return $this->dbh;
    }

    private function update_killers_cache_table()
    {
        try {
            $this->dbh->beginTransaction();

            $this->dbh->exec("DROP TABLE IF EXISTS killers_cache");
            $this->dbh->exec("CREATE TABLE killers_cache (killer_name TEXT PRIMARY KEY, killer_count_total INTEGER, killer_count_normal INTEGER, killer_count_freeze INTEGER)");

            // 通常状態、麻痺・彫像状態で別々に取得し、それぞれの回数と合計回数を計算しkillers_cacheテーブルに格納する
            $killers['normal'] = $this->dbh->query("SELECT killer, count(*) AS killer_count FROM scores WHERE killer NOT LIKE '麻痺状態で%' AND killer NOT LIKE '彫像状態で%' GROUP BY killer")->fetchAll(PDO::FETCH_ASSOC);
            $killers['freeze'] = $this->dbh->query("SELECT killer, count(*) AS killer_count FROM scores WHERE killer LIKE '麻痺状態で%' OR killer LIKE '彫像状態で%' GROUP BY killer")->fetchAll(PDO::FETCH_ASSOC);

            $killer_count = [];
            foreach ($killers['normal'] as $row) {
                $killer = self::killer_normalize($row['killer']);
                $killer_count[$killer]['normal'] = intval($row['killer_count']);
            }

            foreach ($killers['freeze'] as $row) {
                $killer = self::killer_normalize($row['killer']);
                $killer_count[$killer]['freeze'] = intval($row['killer_count']);
            }

            foreach ($killer_count as $k => $v) {
                if (!isset($killer_count[$k]['normal'])) {
                    $killer_count[$k]['normal'] = 0;
                }
                if (!isset($killer_count[$k]['freeze'])) {
                    $killer_count[$k]['freeze'] = 0;
                }
            }
            foreach ($killer_count as $k => $v) {
                $killer_count[$k]['total'] = $v['normal'] + $v['freeze'];
            }

            $insert_stmt = $this->dbh->prepare("INSERT INTO killers_cache VALUES(?, ?, ?, ?)");
            foreach ($killer_count as $k => $v) {
                $insert_stmt->execute([$k, $v['total'], $v['normal'], $v['freeze']]);
            }

            $this->dbh->commit();

            return TRUE;
        } catch (PDOException $e) {
            $this->dbh->rollBack();

            return FALSE;
        }
    }

    public function get_killers_table()
    {
        //$this->update_cache_status('killers_cache', 0);
        if (!$this->get_cache_status('killers_cache')) {
            $this->update_killers_cache_table();
            $this->update_cache_status('killers_cache', 1);
        }

        $killers = $this->dbh->query("SELECT * FROM killers_cache ORDER BY killer_count_total DESC")->fetchAll(PDO::FETCH_ASSOC);

        return $killers;
    }

    private function update_statistics_tables() {
        $statistics_list = ['race', 'class', 'personality'];

        try {
            foreach ($statistics_list as $stat) {
                $table_name = $stat."_statistics";
                $this->dbh->exec("DROP TABLE IF EXISTS ".$table_name);
                $stmt = $this->dbh->query("CREATE TABLE ".$table_name." AS SELECT ".$stat."_id, count(sex=1 or NULL) AS male_count, count(sex=0 or NULL) AS female_count, count(*) AS total_count, count(winner=1 OR NULL) AS winner_count, avg(score) AS average_score, max(score) AS max_score from scores GROUP BY ".$stat."_id");
            }

            return TRUE;
        } catch (PDOException $e) {
            return FALSE;
        }
    }

    public function get_statistics_tables() {
        if (!$this->get_cache_status('statistics_cache')) {
            $this->update_statistics_tables();
            $this->update_cache_status('statistics_cache', 1);
        }

        $stat = [];

        $this->dbh->beginTransaction();
        $stmt = $this->dbh->query("SELECT race_name AS name, * from race_statistics NATURAL INNER JOIN races ORDER BY total_count DESC");
        $stat['race'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->dbh->query("SELECT class_name AS name, * from class_statistics NATURAL INNER JOIN classes ORDER BY total_count DESC");
        $stat['class'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->dbh->query("SELECT personality_name AS name, * from personality_statistics NATURAL INNER JOIN personalities ORDER BY total_count DESC");
        $stat['personality'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->dbh->commit();

        return $stat;
    }

    public function table_exists($table_name)
    {
        $stmt = $this->dbh->prepare("SELECT count(*) AS table_exists FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table_name]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return intval($res[0]['table_exists']) === 1;
    }


    public function get_cache_status($cache_table_name)
    {
        if (!$this->table_exists('cache_status_table')) {
            $this->create_cache_status_table();
        }

        $stmt = $this->dbh->prepare("SELECT cached FROM cache_status_table WHERE table_name=?");
        $stmt->execute([$cache_table_name]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return count($res) === 1 ? intval($res[0]['cached']) : 0;
    }

    public function update_cache_status($cache_table_name, $status)
    {
        if (!$this->table_exists('cache_status_table')) {
            $this->create_cache_status_table();
        }

        $stmt = $this->dbh->prepare("INSERT OR REPLACE INTO cache_status_table VALUES(?, ?)");
        $stmt->execute([$cache_table_name, $status]);

        return $stmt->rowCount();
    }

    public function create_cache_status_table()
    {
        $this->dbh->exec(<<<EOM
CREATE TABLE cache_status_table
  (table_name TEXT PRIMARY KEY,
   cached BOOLEAN)
EOM
        );
    }

    private static function killer_normalize($killer)
    {
        if ($killer === "ripe") {
            return "引退";
        } else if ($killer === "Seppuku") {
            return "切腹";
        }

        $n = str_replace("麻痺状態で", "", $killer);
        $n = str_replace("彫像状態で", "", $n);

        return $n;
    }

}
