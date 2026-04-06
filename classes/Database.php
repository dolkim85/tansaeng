<?php
require_once __DIR__ . "/../config/database.php";

class Database {
    private static $instance = null;
    private $pdo;
    private $lastPingTime = 0;
    private const PING_INTERVAL = 60; // 60초마다 연결 확인

    private function __construct() {
        $maxRetries = 10;
        $retryDelay = 5; // 초
        for ($i = 1; $i <= $maxRetries; $i++) {
            try {
                $this->pdo = DatabaseConfig::getConnection();
                $this->lastPingTime = time();
                error_log("[Database] MySQL 연결 성공 (시도 {$i}/{$maxRetries})");
                return;
            } catch (Exception $e) {
                error_log("[Database] MySQL 연결 실패 (시도 {$i}/{$maxRetries}): " . $e->getMessage());
                if ($i < $maxRetries) {
                    error_log("[Database] " . $retryDelay . "초 후 재시도...");
                    sleep($retryDelay);
                }
            }
        }
        throw new Exception("MySQL에 {$maxRetries}번 연결 시도했으나 모두 실패했습니다.");
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function ensureConnection() {
        $now = time();
        if ($now - $this->lastPingTime >= self::PING_INTERVAL) {
            $this->lastPingTime = $now;
            try {
                $this->pdo->query("SELECT 1");
            } catch (PDOException $e) {
                $this->reconnect();
            }
        }
    }

    private function reconnect() {
        $maxRetries = 5;
        $retryDelay = 5;
        for ($i = 1; $i <= $maxRetries; $i++) {
            try {
                $this->pdo = DatabaseConfig::getConnection();
                $this->lastPingTime = time();
                error_log("[Database] Reconnected to MySQL successfully (시도 {$i})");
                return;
            } catch (Exception $e) {
                error_log("[Database] 재연결 실패 (시도 {$i}/{$maxRetries}): " . $e->getMessage());
                if ($i < $maxRetries) sleep($retryDelay);
            }
        }
        error_log("[Database] 재연결 최종 실패");
    }

    public function getConnection() {
        $this->ensureConnection();
        return $this->pdo;
    }

    public function query($sql, $params = []) {
        $this->ensureConnection();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            if ($this->isConnectionError($e)) {
                error_log("[Database] Connection lost, attempting reconnect...");
                $this->reconnect();
                try {
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute($params);
                    return $stmt;
                } catch (PDOException $retryException) {
                    error_log("Database query failed after reconnect: " . $retryException->getMessage() . " SQL: " . $sql . " Params: " . json_encode($params));
                    throw new Exception("데이터베이스 쿼리 실행에 실패했습니다: " . $retryException->getMessage());
                }
            }
            error_log("Database query failed: " . $e->getMessage() . " SQL: " . $sql . " Params: " . json_encode($params));
            throw new Exception("데이터베이스 쿼리 실행에 실패했습니다: " . $e->getMessage());
        }
    }

    private function isConnectionError(PDOException $e): bool {
        $connectionErrors = [
            "server has gone away",
            "Lost connection",
            "Connection refused",
            "Connection timed out",
            "no connection to the server",
            "No such file or directory",
            "decryption failed or bad record mac",
            "SSL connection has been closed unexpectedly",
            "Error while sending",
            "Cannot assign requested address",
        ];
        $errorMessage = strtolower($e->getMessage());
        foreach ($connectionErrors as $needle) {
            if (strpos($errorMessage, strtolower($needle)) !== false) return true;
        }
        if (in_array($e->getCode(), ["HY000", 2002, 2006, 2013])) return true;
        return false;
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
        $fields = implode(",", $keys);
        $placeholders = ":" . implode(", :", $keys);
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        $setClause = implode(", ", $setClause);
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        return $this->query($sql, $params);
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }

    public function count($table, $where = "", $params = []) {
        $sql = "SELECT COUNT(*) as count FROM {$table}";
        if ($where) $sql .= " WHERE {$where}";
        $result = $this->selectOne($sql, $params);
        return (int) $result["count"];
    }

    public function exists($table, $where, $params = []) {
        return $this->count($table, $where, $params) > 0;
    }

    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollback(); }
    public function inTransaction() { return $this->pdo->inTransaction(); }

    public function createTables() {
        $sqlFile = __DIR__ . "/../sql/install.sql";
        if (!file_exists($sqlFile)) throw new Exception("SQL 설치 파일을 찾을 수 없습니다.");
        $sql = file_get_contents($sqlFile);
        $statements = explode(";", $sql);
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
