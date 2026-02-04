<?php
class ContactSettings {
    private static $instance = null;
    private $db;
    private $settings = [];

    // 키 매핑: 기존 키 -> site_settings 테이블의 키
    private $keyMapping = [
        'phone_number' => 'support_phone',
        'phone_hours' => 'support_hours',
        'email_address' => 'support_email',
        'email_response_time' => 'support_hours',
        'online_inquiry_desc' => 'support_notice',
        'online_inquiry_note' => 'support_policy',
        'visit_address' => 'footer_address',
        'visit_note' => 'support_notice',
        'business_hours_weekday' => 'footer_business_hours_weekday',
        'business_hours_lunch' => 'support_hours',
        'business_hours_weekend' => 'footer_business_hours_holiday',
        'email_hours_reception' => 'support_hours',
        'email_hours_response' => 'support_hours',
        'email_hours_urgent' => 'support_phone',
    ];

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
            // site_settings 테이블에서 읽기 (관리자 페이지와 동일한 테이블)
            $results = $this->db->select("SELECT setting_key, setting_value FROM site_settings");
            foreach ($results as $row) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // 데이터베이스 오류시 기본값 사용
            error_log("ContactSettings load error: " . $e->getMessage());
        }
    }

    public function get($key, $default = '') {
        // 먼저 직접 키로 찾기
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }

        // 키 매핑을 통해 찾기
        if (isset($this->keyMapping[$key]) && isset($this->settings[$this->keyMapping[$key]])) {
            return $this->settings[$this->keyMapping[$key]];
        }

        return $default;
    }

    public function getAll() {
        return $this->settings;
    }
}
