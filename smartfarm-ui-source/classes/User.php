<?php
require_once 'Database.php';
require_once __DIR__ . '/../config/config.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function register($data) {
        if ($this->emailExists($data['email'])) {
            throw new Exception('이미 등록된 이메일 주소입니다.');
        }

        $userData = [
            'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'name' => htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8'),
            'phone' => isset($data['phone']) ? htmlspecialchars($data['phone'], ENT_QUOTES, 'UTF-8') : null,
            'address' => isset($data['address']) ? htmlspecialchars($data['address'], ENT_QUOTES, 'UTF-8') : null,
            'user_level' => USER_LEVEL_GENERAL,
            'plant_analysis_permission' => 0,
            'email_verified' => 0
        ];

        $userId = $this->db->insert('users', $userData);
        
        if ($userId) {
            $this->logActivity($userId, 'user_registered', ['email' => $userData['email']]);
            return $userId;
        }
        
        throw new Exception('회원가입에 실패했습니다.');
    }

    public function login($email, $password, $ipAddress = null, $userAgent = null) {
        $user = $this->getUserByEmail($email);
        
        $loginData = [
            'user_id' => $user ? $user['id'] : null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'success' => 0
        ];

        if (!$user || !password_verify($password, $user['password'])) {
            if ($user) {
                $this->db->insert('user_login_logs', $loginData);
            }
            throw new Exception('이메일 또는 비밀번호가 올바르지 않습니다.');
        }

        if (isset($user['is_active']) && !$user['is_active']) {
            throw new Exception('비활성화된 계정입니다. 관리자에게 문의하세요.');
        }

        $loginData['success'] = 1;
        $this->db->insert('user_login_logs', $loginData);

        try {
            $this->db->update('users', 
                ['last_login' => date('Y-m-d H:i:s')], 
                'id = :id', 
                ['id' => $user['id']]
            );
        } catch (Exception $e) {
            // Ignore last_login update errors for now
        }

        $this->logActivity($user['id'], 'user_login', ['ip' => $ipAddress]);

        unset($user['password']);
        return $user;
    }

    public function getUserById($id) {
        $sql = "SELECT * FROM users WHERE id = :id AND (is_active = 1 OR is_active IS NULL)";
        return $this->db->selectOne($sql, ['id' => $id]);
    }

    public function getUserByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = :email";
        return $this->db->selectOne($sql, ['email' => $email]);
    }

    public function getUserByFirebaseUid($firebaseUid) {
        $sql = "SELECT * FROM users WHERE firebase_uid = :firebase_uid";
        return $this->db->selectOne($sql, ['firebase_uid' => $firebaseUid]);
    }

    public function updateFirebaseUid($userId, $firebaseUid) {
        return $this->db->update('users', 
            ['firebase_uid' => $firebaseUid], 
            'id = :id', 
            ['id' => $userId]
        );
    }

    public function createUser($data) {
        $allowedFields = ['name', 'email', 'password', 'phone', 'address', 'user_level', 
                         'plant_analysis_permission', 'firebase_uid', 'avatar_url', 
                         'email_verified', 'created_at'];
        $userData = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['name', 'phone', 'address'])) {
                    $userData[$field] = htmlspecialchars($data[$field], ENT_QUOTES, 'UTF-8');
                } elseif ($field === 'email') {
                    $userData[$field] = filter_var($data[$field], FILTER_SANITIZE_EMAIL);
                } else {
                    $userData[$field] = $data[$field];
                }
            }
        }

        $userId = $this->db->insert('users', $userData);
        
        if ($userId) {
            $this->logActivity($userId, 'user_created', ['email' => $userData['email'], 'source' => 'firebase']);
            return $userId;
        }
        
        throw new Exception('사용자 생성에 실패했습니다.');
    }

    public function emailExists($email) {
        return $this->db->exists('users', 'email = :email', ['email' => $email]);
    }

    public function getAllUsers($page = 1, $limit = 20, $search = null) {
        $offset = ($page - 1) * $limit;
        $params = [];
        
        // Cast to integers to avoid string binding issues
        $limit = (int) $limit;
        $offset = (int) $offset;
        
        $sql = "SELECT id, email, name, phone, user_level, plant_analysis_permission, 
                       created_at, last_login, is_active 
                FROM users";
        
        if ($search) {
            $sql .= " WHERE (name LIKE :search OR email LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        
        // Use direct string interpolation for LIMIT and OFFSET to avoid binding issues
        $sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function getUserCount($search = null) {
        $params = [];
        $where = "";
        
        if ($search) {
            $where = "WHERE (name LIKE :search OR email LIKE :search)";
            $params['search'] = '%' . $search . '%';
        }
        
        return $this->db->count('users', $where, $params);
    }

    public function updateUser($id, $data) {
        $allowedFields = ['name', 'phone', 'address', 'email', 'avatar_url', 'last_login'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['name', 'phone', 'address'])) {
                    $updateData[$field] = htmlspecialchars($data[$field], ENT_QUOTES, 'UTF-8');
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (empty($updateData)) {
            throw new Exception('업데이트할 데이터가 없습니다.');
        }

        if (isset($updateData['email']) && $this->emailExistsExcludingUser($updateData['email'], $id)) {
            throw new Exception('이미 사용 중인 이메일 주소입니다.');
        }

        return $this->db->update('users', $updateData, 'id = :id', ['id' => $id]);
    }

    public function updatePassword($id, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $result = $this->db->update('users', 
            ['password' => $hashedPassword], 
            'id = :id', 
            ['id' => $id]
        );
        
        if ($result) {
            $this->logActivity($id, 'password_changed');
        }
        
        return $result;
    }

    public function emailExistsExcludingUser($email, $userId) {
        return $this->db->exists('users', 'email = :email AND id != :id', ['email' => $email, 'id' => $userId]);
    }

    public function grantPlantAnalysisPermission($userId, $grantedBy) {
        $this->db->beginTransaction();
        try {
            $this->db->update('users', 
                ['plant_analysis_permission' => 1, 'user_level' => USER_LEVEL_PLANT_ANALYSIS], 
                'id = :id', 
                ['id' => $userId]
            );

            $this->db->insert('user_permissions', [
                'user_id' => $userId,
                'permission_type' => 'plant_analysis',
                'granted_by' => $grantedBy,
                'granted_at' => date('Y-m-d H:i:s')
            ]);

            $this->logActivity($userId, 'plant_permission_granted', ['granted_by' => $grantedBy]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function revokePlantAnalysisPermission($userId, $revokedBy) {
        $this->db->beginTransaction();
        try {
            $this->db->update('users', 
                ['plant_analysis_permission' => 0, 'user_level' => USER_LEVEL_GENERAL], 
                'id = :id', 
                ['id' => $userId]
            );

            $this->db->update('user_permissions', 
                ['revoked_at' => date('Y-m-d H:i:s'), 'is_active' => 0], 
                'user_id = :user_id AND permission_type = :type AND is_active = 1', 
                ['user_id' => $userId, 'type' => 'plant_analysis']
            );

            $this->logActivity($userId, 'plant_permission_revoked', ['revoked_by' => $revokedBy]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function deactivateUser($userId) {
        $result = $this->db->update('users', ['is_active' => 0], 'id = :id', ['id' => $userId]);
        if ($result) {
            $this->logActivity($userId, 'user_deactivated');
        }
        return $result;
    }

    public function activateUser($userId) {
        $result = $this->db->update('users', ['is_active' => 1], 'id = :id', ['id' => $userId]);
        if ($result) {
            $this->logActivity($userId, 'user_activated');
        }
        return $result;
    }

    public function deleteUser($userId) {
        $this->db->beginTransaction();
        try {
            $this->logActivity($userId, 'user_deleted');
            $this->db->delete('users', 'id = :id', ['id' => $userId]);
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function hasPlantAnalysisPermission($userId) {
        $user = $this->getUserById($userId);
        return $user && $user['plant_analysis_permission'] == 1;
    }

    public function getUserLoginHistory($userId, $limit = 10) {
        $sql = "SELECT * FROM user_login_logs WHERE user_id = :user_id 
                ORDER BY login_time DESC LIMIT :limit";
        return $this->db->select($sql, ['user_id' => $userId, 'limit' => $limit]);
    }

    public function getUserPermissionHistory($userId) {
        $sql = "SELECT up.*, u.name as granted_by_name 
                FROM user_permissions up
                LEFT JOIN users u ON up.granted_by = u.id
                WHERE up.user_id = :user_id 
                ORDER BY up.granted_at DESC";
        return $this->db->select($sql, ['user_id' => $userId]);
    }

    private function logActivity($userId, $action, $details = null) {
        $logData = [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ];
        
        $this->db->insert('plant_analysis_logs', $logData);
    }
}
?>