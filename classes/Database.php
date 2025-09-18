<?php
require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage() . " SQL: " . $sql . " Params: " . json_encode($params));
            throw new Exception("데이터베이스 쿼리 실행에 실패했습니다: " . $e->getMessage());
        }
    }

    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function selectOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function insert($table, $data) {
        $keys = array_keys($data);
        $fields = implode(',', $keys);
        $placeholders = ':' . implode(', :', $keys);
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params);
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }

    public function count($table, $where = '', $params = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $result = $this->selectOne($sql, $params);
        return (int) $result['count'];
    }

    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollback();
    }

    public function inTransaction() {
        return $this->pdo->inTransaction();
    }

    public function createTables() {
        $sqlFile = __DIR__ . '/../sql/install.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("SQL 설치 파일을 찾을 수 없습니다.");
        }

        $sql = file_get_contents($sqlFile);
        $statements = explode(';', $sql);

        $this->beginTransaction();
        try {
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;
                
                $this->pdo->exec($statement);
            }
            $this->commit();
            return true;
        } catch (Exception $e) {
            $this->rollback();
            throw new Exception("데이터베이스 테이블 생성 실패: " . $e->getMessage());
        }
    }
}
?>