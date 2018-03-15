<?php
class ScoreDB
{
    public function __construct() {
        $this->dbh = new PDO('sqlite:db/score.db');
        $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
