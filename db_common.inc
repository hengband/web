<?php
class ScoreDB
{
    private static $sort_mode_list = ['default' => 'score', 'newcome'];

    public function __construct() {
        $this->dbh = new PDO('sqlite:db/score.db');
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->set_sort_mode(filter_input(INPUT_GET, 'sort'));
    }

    public function get_defines()
    {
        $id_define_array = [];
        $tables = [['race', 'races'], ['class', 'classes'], ['personality', 'personalities'], ['realm', 'realms']];
        foreach ($tables as $t) {
            $id_define_array[$t[0]] = [];
            $stmt = $this->dbh->query("SELECT {$t[0]}_id, {$t[0]}_name FROM {$t[1]} ORDER BY {$t[0]}_id");
            foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
                $id_define_array[$t[0]][intval($row[0])] = $row[1];
            }
        }

        return $id_define_array;
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
     * @return array スコア検索絞り込み用のWHERE句('where')とプレースホルダに渡すパラメータ配列('params')の連想配列
     */
    private function get_search_condition()
    {
        $last_days = filter_input(INPUT_GET, 'last_days', FILTER_VALIDATE_INT);
        $params = [];

        if ($last_days > 0) {
            $wheres[] = "date >= datetime('now', 'localtime', '-{$last_days} days')";
        }

        foreach (['race_id', 'class_id', 'personality_id'] as $key_column) {
            $val = filter_input(INPUT_GET, $key_column, FILTER_VALIDATE_INT); 
            if ($val > 0) {
                $wheres[] = "{$key_column} = :{$key_column}";
                $params[":{$key_column}"] = $val;
            }
        }

        foreach (['name'] as $key_column) {
            $val = filter_input(INPUT_GET, $key_column);
            if ($val !== NULL && strlen($val) > 0) {
                $wheres[] = "{$key_column} = :{$key_column}";
                $params[":{$key_column}"] = $val;
            }
        }

        foreach (['killer'] as $key_column) {
            $val = filter_input(INPUT_GET, $key_column);
            if ($val !== NULL && strlen($val) > 0) {
                $wheres[] = "({$key_column} LIKE :{$key_column} OR {$key_column} = :{$key_column}1 OR {$key_column} = :{$key_column}2)";
                $params[":{$key_column}"] = "%".$val."%";
                $params[":{$key_column}1"] = "麻痺状態で".$val;
                $params[":{$key_column}2"] = "彫像状態で".$val;
            }
        }

        $result['where'] = isset($wheres) ? 'WHERE '.implode(' AND ', $wheres) : '';
        $result['params'] = $params;

        return $result;
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
        $query = <<<EOM
SELECT
  *,
  group_concat(realm_name) AS realms_name,
  CASE
    WHEN killer = 'ripe' THEN '勝利の後引退'
    WHEN killer = 'Seppuku' THEN '勝利の後切腹'
    ELSE killer || 'に殺された'
  END AS death_reason
FROM
 (SELECT
    *
  FROM
    scores
  {$where}
  {$order_by}
  LIMIT {$offset}, {$limit}) AS s
NATURAL INNER JOIN
  races
NATURAL INNER JOIN
  classes
NATURAL INNER JOIN
  personalities
NATURAL LEFT JOIN
  score_realms
NATURAL LEFT JOIN
  realms
GROUP BY
  score_id
{$order_by}
EOM;
        return $query;
    }


    /**
     * 検索でヒットしたスコアの総件数を取得する
     *
     * @param string $where スコア検索に用いるWHERE句
     * @return integer スコア総件数
     */
    private function get_query_data_count($where, $params)
    {
        $stmt = $this->dbh->prepare("SELECT count(*) AS data_count from scores {$where}");
        $stmt->execute($params);
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
        $cond = $this->get_search_condition();
        $order_by = $this->get_order_by();
        $query_sql = $this->get_search_query(intval($start_num / $count) * $count, $count, $cond['where'], $order_by);

        $search_start_time = microtime(true);
        $this->dbh->beginTransaction();

        $score_stmt = $this->dbh->prepare($query_sql);
        $score_stmt->execute($cond['params']);
        $result['scores'] = $score_stmt->fetchAll(PDO::FETCH_ASSOC);
        $result['total_data_count'] = $this->get_query_data_count($cond['where'], $cond['params']);

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
            $this->dbh->exec(<<<EOM
CREATE TABLE
  killers_cache
AS
SELECT
  killer_name,
  count(*) AS killer_count_total,
  count(killed_status = 0 OR NULL) AS killer_count_normal,
  count(killed_status != 0 OR NULL) AS killer_count_freeze
FROM
 (SELECT
    CASE
      WHEN killer LIKE '麻痺状態で%' THEN substr(killer, 6)
      WHEN killer LIKE '彫像状態で%' THEN substr(killer, 6)
      WHEN killer = 'ripe' THEN '引退'
      WHEN killer = 'Seppuku' THEN '切腹'
      ELSE killer
    END AS killer_name,
    CASE
      WHEN killer LIKE '麻痺状態で%' THEN 1
      WHEN killer LIKE '彫像状態で%' THEN 2
      ELSE 0
    END AS killed_status
  FROM
    scores
 )
GROUP BY
  killer_name
ORDER BY
  killer_count_total DESC
EOM
            );
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


    /**
     * 統計情報のキャッシュテーブルを更新する
     *
     * 種族・職業・性格について各種統計情報を取得しキャッシュテーブルに保存する
     * 通常の統計情報の取得はこのキャッシュテーブルから行う
     *
     * @return boolean 生成に成功したらTRUE、失敗したらFALSE
     */
    private function update_statistics_cache_tables() {
        $statistics_list = ['race', 'class', 'personality'];

        try {
            foreach ($statistics_list as $stat) {
                $table_name = $stat."_statistics";
                $this->dbh->exec("DROP TABLE IF EXISTS ".$table_name);
                $stmt = $this->dbh->query(
                    <<<EOM
CREATE TABLE $table_name AS
  SELECT
    {$stat}_id,
    count(sex=1 or NULL) AS male_count,
    count(sex=0 or NULL) AS female_count,
    count(*) AS total_count,
    count(winner=1 OR NULL) AS winner_count,
    avg(score) AS average_score,
    max(score) AS max_score
  FROM scores
GROUP BY ${stat}_id
EOM
                );
            }

            return TRUE;
        } catch (PDOException $e) {
            return FALSE;
        }
    }


    /**
     * 統計情報を取得する
     *
     * @param integer $sort_key_column 表示順序のキーとするカラムの名称
     *
     * @return array 統計情報
     */
    public function get_statistics_tables($sort_key_column) {
        if (!$this->get_cache_status('statistics_cache')) {
            $result = $this->update_statistics_cache_tables();
            $this->update_cache_status('statistics_cache', $result ? 1 : 0);
        }

        $stat = [];

        $this->dbh->beginTransaction();
        foreach ([['race', 'races'], ['class', 'classes'], ['personality', 'personalities']] as $kind) {
            $stmt = $this->dbh->query("SELECT ${kind[0]}_id AS id, ${kind[0]}_name AS name, * FROM ${kind[0]}_statistics NATURAL JOIN ${kind[1]} ORDER BY ${sort_key_column} DESC");
            $stat[$kind[0]] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
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

    public function register_new_score($score_data)
    {
        $insert_stmt = $this->dbh->prepare(
            <<<EOM
INSERT INTO scores
 (version, score, name,
  race_id, class_id, personality_id,
  sex, level, depth, maxlv, maxdp,
  au, turns, winner, killer)
SELECT
 :version, :score, :name,
 race_id, class_id, personality_id,
 :sex, :level, :depth, :maxlv, :maxdp,
 :au, :turns, :winner, :killer
FROM
  races, classes, personalities
WHERE
  race_name = :race AND
  class_name = :class AND
  personality_name = :personality
EOM
        );
 
        $realm_insert_stmt = $this->dbh->prepare(
            <<<EOM
INSERT INTO score_realms
 (score_id, realm_id)
SELECT
  ?, realm_id
FROM
  realms
WHERE
  realm_name = ?
EOM
        );

        try {
            $this->dbh->beginTransaction();
            if ($insert_stmt->execute($score_data['character_info']) === FALSE ||
                $insert_stmt->rowCount() !== 1) {
                $dbh->rollBack();
                return FALSE;
            }

            // NOTE: score_idはINTEGER PRIMARY KEYなのでROWIDを参照している
            //       したがってlastInsertIdで追加されたスコアのscore_idを取得可能
            $score_id = $this->dbh->lastInsertId();

            foreach ($score_data['realm_info'] as $realm_name) {
                if ($realm_insert_stmt->execute([$score_id, $realm_name]) === FALSE ||
                    $realm_insert_stmt->rowCount() !== 1) {
                    $dbh->rollBack();
                    return FALSE;
                }
            }

            // スコアが追加されたので死因ランキング・人気ランキングのキャッシュをクリア
            $this->update_cache_status('killers_cache', 0);
            $this->update_cache_status('statistics_cache', 0);
            $this->dbh->commit();

            return $score_id;
        } catch (PDOException $e) {
            $dbh->rollBack();
        }

        return FALSE;
    }
}
