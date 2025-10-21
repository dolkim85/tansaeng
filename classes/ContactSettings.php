<?php
class ContactSettings {
    private static $instance = null;
    private $db;
    private $settings = [];

    private function __construct() {
        $this->db = Database::getInstance();
        $this->loadSettings();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadSettings() {
        try {
            $results = $this->db->select("SELECT setting_key, setting_value FROM contact_settings");
            foreach ($results as $row) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // 데이터베이스 오류시 기본값 사용
            error_log("ContactSettings load error: " . $e->getMessage());
        }
    }

    public function get($key, $default = '') {
        return $this->settings[$key] ?? $default;
    }

    public function getAll() {
        return $this->settings;
    }
}
