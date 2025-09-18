<?php
/**
 * Plant Analysis Class
 * Handles AI plant analysis requests and results
 */

require_once __DIR__ . '/../config/database.php';

class PlantAnalysis {
    private $db;

    public function __construct() {
        $this->db = DatabaseConfig::getConnection();
    }

    /**
     * Create new plant analysis request
     */
    public function createAnalysisRequest($userId, $imageFile) {
        try {
            // Generate unique request ID
            $requestId = $this->generateRequestId();

            // Handle image upload
            $uploadResult = $this->uploadPlantImage($imageFile, $requestId);
            if (!$uploadResult['success']) {
                return $uploadResult;
            }

            // Insert analysis request
            $stmt = $this->db->prepare("
                INSERT INTO plant_analysis (
                    user_id, request_id, plant_image, status
                ) VALUES (?, ?, ?, 'pending')
            ");

            $result = $stmt->execute([
                $userId,
                $requestId,
                $uploadResult['filename']
            ]);

            if ($result) {
                $analysisId = $this->db->lastInsertId();

                // Trigger AI analysis (placeholder - would integrate with actual AI service)
                $this->processAnalysis($analysisId);

                return [
                    'success' => true,
                    'analysis_id' => $analysisId,
                    'request_id' => $requestId,
                    'message' => '식물 분석 요청이 접수되었습니다.'
                ];
            } else {
                return ['success' => false, 'message' => '분석 요청 생성에 실패했습니다.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => '분석 요청 처리 중 오류가 발생했습니다.'];
        }
    }

    /**
     * Generate unique request ID
     */
    private function generateRequestId() {
        return 'PA_' . date('YmdHis') . '_' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Upload plant image
     */
    private function uploadPlantImage($imageFile, $requestId) {
        try {
            $uploadDir = __DIR__ . '/../uploads/analysis/';

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Validate file
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array($imageFile['type'], $allowedTypes)) {
                return ['success' => false, 'message' => '지원하지 않는 이미지 형식입니다.'];
            }

            // Check file size (max 5MB)
            if ($imageFile['size'] > 5 * 1024 * 1024) {
                return ['success' => false, 'message' => '이미지 크기가 너무 큽니다. (최대 5MB)'];
            }

            // Generate filename
            $extension = pathinfo($imageFile['name'], PATHINFO_EXTENSION);
            $filename = $requestId . '.' . $extension;
            $filepath = $uploadDir . $filename;

            // Move uploaded file
            if (move_uploaded_file($imageFile['tmp_name'], $filepath)) {
                // Create thumbnail
                $this->createThumbnail($filepath, $uploadDir . 'thumb_' . $filename);

                return ['success' => true, 'filename' => $filename];
            } else {
                return ['success' => false, 'message' => '이미지 업로드에 실패했습니다.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => '이미지 처리 중 오류가 발생했습니다.'];
        }
    }

    /**
     * Create image thumbnail
     */
    private function createThumbnail($source, $destination, $width = 300, $height = 300) {
        try {
            $imageInfo = getimagesize($source);
            if (!$imageInfo) return false;

            $sourceWidth = $imageInfo[0];
            $sourceHeight = $imageInfo[1];
            $sourceType = $imageInfo[2];

            // Calculate dimensions
            $ratio = min($width / $sourceWidth, $height / $sourceHeight);
            $newWidth = (int)($sourceWidth * $ratio);
            $newHeight = (int)($sourceHeight * $ratio);

            // Create image resources
            switch ($sourceType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($source);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($source);
                    break;
                case IMAGETYPE_WEBP:
                    $sourceImage = imagecreatefromwebp($source);
                    break;
                default:
                    return false;
            }

            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG and WebP
            if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_WEBP) {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Resize image
            imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

            // Save thumbnail
            switch ($sourceType) {
                case IMAGETYPE_JPEG:
                    imagejpeg($thumbnail, $destination, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($thumbnail, $destination);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($thumbnail, $destination, 85);
                    break;
            }

            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Process AI analysis (placeholder)
     */
    private function processAnalysis($analysisId) {
        try {
            // Update status to processing
            $stmt = $this->db->prepare("
                UPDATE plant_analysis
                SET status = 'processing'
                WHERE id = ?
            ");
            $stmt->execute([$analysisId]);

            // Simulate AI processing with mock data
            sleep(2); // Simulate processing time

            // Mock analysis results
            $mockResults = $this->generateMockResults();

            // Update with results
            $stmt = $this->db->prepare("
                UPDATE plant_analysis
                SET status = 'completed',
                    plant_type = ?,
                    health_status = ?,
                    disease_detected = ?,
                    confidence_score = ?,
                    recommended_products = ?,
                    analysis_data = ?,
                    processed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");

            $stmt->execute([
                $mockResults['plant_type'],
                $mockResults['health_status'],
                $mockResults['disease_detected'],
                $mockResults['confidence_score'],
                json_encode($mockResults['recommended_products']),
                json_encode($mockResults['analysis_data']),
                $analysisId
            ]);

        } catch (Exception $e) {
            // Mark as failed
            $stmt = $this->db->prepare("
                UPDATE plant_analysis
                SET status = 'failed'
                WHERE id = ?
            ");
            $stmt->execute([$analysisId]);
        }
    }

    /**
     * Generate mock analysis results
     */
    private function generateMockResults() {
        $plantTypes = ['토마토', '상추', '바질', '시금치', '케일', '파슬리'];
        $healthStatuses = ['healthy', 'sick', 'dying'];
        $diseases = ['잎마름병', '뿌리썩음병', '흰가루병', '진딧물', '영양결핍'];

        $healthStatus = $healthStatuses[array_rand($healthStatuses)];
        $diseaseDetected = $healthStatus === 'healthy' ? null : $diseases[array_rand($diseases)];

        return [
            'plant_type' => $plantTypes[array_rand($plantTypes)],
            'health_status' => $healthStatus,
            'disease_detected' => $diseaseDetected,
            'confidence_score' => rand(75, 98) + (rand(0, 99) / 100),
            'recommended_products' => $this->getRecommendedProducts($healthStatus),
            'analysis_data' => [
                'leaf_color' => $healthStatus === 'healthy' ? 'green' : 'yellow',
                'growth_stage' => 'mature',
                'soil_condition' => 'good',
                'lighting_condition' => 'adequate',
                'recommendations' => [
                    '적정 습도 유지',
                    '규칙적인 급수',
                    '충분한 광량 제공'
                ]
            ]
        ];
    }

    /**
     * Get recommended products based on analysis
     */
    private function getRecommendedProducts($healthStatus) {
        try {
            $sql = "SELECT id, name, price FROM products WHERE status = 'active' ORDER BY RAND() LIMIT 3";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get analysis by ID
     */
    public function getAnalysis($analysisId) {
        try {
            $stmt = $this->db->prepare("
                SELECT pa.*, u.name as user_name, u.email
                FROM plant_analysis pa
                LEFT JOIN users u ON pa.user_id = u.id
                WHERE pa.id = ?
            ");
            $stmt->execute([$analysisId]);
            $analysis = $stmt->fetch();

            if ($analysis) {
                // Decode JSON fields
                if ($analysis['recommended_products']) {
                    $analysis['recommended_products'] = json_decode($analysis['recommended_products'], true);
                }
                if ($analysis['analysis_data']) {
                    $analysis['analysis_data'] = json_decode($analysis['analysis_data'], true);
                }
            }

            return $analysis;

        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Get analysis by request ID
     */
    public function getAnalysisByRequestId($requestId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM plant_analysis WHERE request_id = ?");
            $stmt->execute([$requestId]);
            $analysis = $stmt->fetch();

            if ($analysis) {
                if ($analysis['recommended_products']) {
                    $analysis['recommended_products'] = json_decode($analysis['recommended_products'], true);
                }
                if ($analysis['analysis_data']) {
                    $analysis['analysis_data'] = json_decode($analysis['analysis_data'], true);
                }
            }

            return $analysis;

        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Get user's analysis history
     */
    public function getUserAnalyses($userId, $limit = 20, $offset = 0) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM plant_analysis
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get all analyses with pagination
     */
    public function getAllAnalyses($limit = 20, $offset = 0, $filters = []) {
        try {
            $whereClause = "";
            $params = [];

            // Build WHERE clause based on filters
            if (!empty($filters['status'])) {
                $whereClause .= " WHERE status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['health_status'])) {
                $whereClause .= empty($whereClause) ? " WHERE" : " AND";
                $whereClause .= " health_status = ?";
                $params[] = $filters['health_status'];
            }

            $sql = "
                SELECT pa.*, u.name as user_name, u.email
                FROM plant_analysis pa
                LEFT JOIN users u ON pa.user_id = u.id
                $whereClause
                ORDER BY pa.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();

        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Delete analysis
     */
    public function deleteAnalysis($analysisId) {
        try {
            // Get analysis info first
            $analysis = $this->getAnalysis($analysisId);
            if (!$analysis) {
                return ['success' => false, 'message' => '분석 데이터를 찾을 수 없습니다.'];
            }

            // Delete image files
            $uploadDir = __DIR__ . '/../uploads/analysis/';
            $imagePath = $uploadDir . $analysis['plant_image'];
            $thumbPath = $uploadDir . 'thumb_' . $analysis['plant_image'];

            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }

            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM plant_analysis WHERE id = ?");
            $result = $stmt->execute([$analysisId]);

            if ($result) {
                return ['success' => true, 'message' => '분석 데이터가 삭제되었습니다.'];
            } else {
                return ['success' => false, 'message' => '삭제 처리에 실패했습니다.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => '삭제 처리 중 오류가 발생했습니다.'];
        }
    }

    /**
     * Get analysis statistics
     */
    public function getStatistics($startDate = null, $endDate = null) {
        try {
            $whereClause = "";
            $params = [];

            if ($startDate && $endDate) {
                $whereClause = "WHERE created_at BETWEEN ? AND ?";
                $params = [$startDate, $endDate];
            }

            // Total analyses
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM plant_analysis $whereClause");
            $stmt->execute($params);
            $totalAnalyses = $stmt->fetch()['total'];

            // Status breakdown
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count
                FROM plant_analysis
                $whereClause
                GROUP BY status
            ");
            $stmt->execute($params);
            $statusBreakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Health status breakdown
            $stmt = $this->db->prepare("
                SELECT health_status, COUNT(*) as count
                FROM plant_analysis
                WHERE health_status IS NOT NULL $whereClause
                GROUP BY health_status
            ");
            $stmt->execute($params);
            $healthBreakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'total_analyses' => $totalAnalyses,
                'status_breakdown' => $statusBreakdown,
                'health_breakdown' => $healthBreakdown
            ];

        } catch (PDOException $e) {
            return [
                'total_analyses' => 0,
                'status_breakdown' => [],
                'health_breakdown' => []
            ];
        }
    }
}
?>