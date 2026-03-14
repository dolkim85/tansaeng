<?php
// Initialize session
$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Database.php';

// Get download categories and files
$downloads = [];
try {
    $pdo = Database::getInstance()->getConnection();

    $sql = "SELECT * FROM downloads WHERE status = 'active' ORDER BY category, sort_order ASC";
    $stmt = $pdo->query($sql);
    $all_downloads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by category
    foreach ($all_downloads as $download) {
        $category = $download['category'] ?? '기타';
        if (!isset($downloads[$category])) {
            $downloads[$category] = [];
        }
        $downloads[$category][] = $download;
    }

} catch (Exception $e) {
    // If table doesn't exist, create sample data
    $downloads = [
        '제품 매뉴얼' => [
            [
                'id' => 1,
                'title' => '코코피트 배지 사용 가이드',
                'description' => '코코피트 배지의 설치 및 사용법을 안내하는 PDF 가이드입니다.',
                'file_type' => 'PDF',
                'file_size' => '2.5 MB',
                'file_url' => '/downloads/coco-guide.pdf',
                'version' => 'v1.2',
                'created_at' => date('Y-m-d')
            ],
            [
                'id' => 2,
                'title' => '펄라이트 배지 매뉴얼',
                'description' => '펄라이트 배지 제품의 상세 사용 매뉴얼입니다.',
                'file_type' => 'PDF',
                'file_size' => '3.1 MB',
                'file_url' => '/downloads/perlite-manual.pdf',
                'version' => 'v2.0',
                'created_at' => date('Y-m-d')
            ]
        ],
        '기술 자료' => [
            [
                'id' => 3,
                'title' => '수경재배 시스템 설계 가이드',
                'description' => '최적의 수경재배 시스템을 설계하는 방법을 다룹니다.',
                'file_type' => 'PDF',
                'file_size' => '4.8 MB',
                'file_url' => '/downloads/hydroponics-design.pdf',
                'version' => 'v1.0',
                'created_at' => date('Y-m-d')
            ],
            [
                'id' => 4,
                'title' => '식물 영양소 관리 백서',
                'description' => '수경재배에서 필수적인 영양소 관리 지식을 제공합니다.',
                'file_type' => 'PDF',
                'file_size' => '5.2 MB',
                'file_url' => '/downloads/nutrients-whitepaper.pdf',
                'version' => 'v1.1',
                'created_at' => date('Y-m-d')
            ]
        ],
        '카탈로그' => [
            [
                'id' => 5,
                'title' => '탄생 제품 카탈로그 2025',
                'description' => '전체 제품 라인업과 사양이 포함된 종합 카탈로그입니다.',
                'file_type' => 'PDF',
                'file_size' => '8.5 MB',
                'file_url' => '/downloads/catalog-2025.pdf',
                'version' => '2025',
                'created_at' => date('Y-m-d')
            ]
        ],
        '인증서' => [
            [
                'id' => 6,
                'title' => '품질인증서',
                'description' => 'ISO 9001 품질경영시스템 인증서입니다.',
                'file_type' => 'PDF',
                'file_size' => '1.2 MB',
                'file_url' => '/downloads/iso-9001.pdf',
                'version' => '2024',
                'created_at' => date('Y-m-d')
            ]
        ]
    ];
}

$category_icons = [
    '제품 매뉴얼' => '📖',
    '기술 자료' => '🔬',
    '카탈로그' => '📚',
    '인증서' => '🏆',
    '기타' => '📄'
];

function getFileIcon($type) {
    $icons = [
        'PDF' => '📕',
        'DOC' => '📘',
        'XLS' => '📗',
        'ZIP' => '📦',
        'IMG' => '🖼️'
    ];
    return $icons[strtoupper($type)] ?? '📄';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>자료실 - 탄생</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .downloads-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
            min-height: calc(100vh - 200px);
        }

        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }

        .page-description {
            font-size: 1.1rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        .download-category {
            margin-bottom: 50px;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #4CAF50;
        }

        .category-icon {
            font-size: 2rem;
        }

        .category-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
        }

        .download-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .download-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .download-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: #4CAF50;
        }

        .download-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .file-icon {
            font-size: 2.5rem;
            flex-shrink: 0;
        }

        .download-info {
            flex: 1;
        }

        .download-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .download-description {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.5;
        }

        .download-meta {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            font-size: 0.85rem;
            color: #999;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .download-actions {
            margin-top: 15px;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s;
            cursor: pointer;
        }

        .btn-download:hover {
            background-color: #45a049;
        }

        .version-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #e3f2fd;
            color: #1976d2;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .empty-message {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        @media (max-width: 768px) {
            .downloads-container {
                padding: 20px 15px;
            }

            .page-title {
                font-size: 1.8rem;
            }

            .download-grid {
                grid-template-columns: 1fr;
            }

            .download-item {
                padding: 20px;
            }

            .category-header {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main class="downloads-container">
        <div class="page-header">
            <h1 class="page-title">자료실</h1>
            <p class="page-description">
                제품 매뉴얼, 기술 자료, 카탈로그 등 다양한 자료를 다운로드 받으실 수 있습니다.
            </p>
        </div>

        <?php if (empty($downloads)): ?>
            <div class="empty-message">
                <p>등록된 다운로드 자료가 없습니다.</p>
            </div>
        <?php else: ?>
            <?php foreach ($downloads as $category => $items): ?>
                <div class="download-category">
                    <div class="category-header">
                        <span class="category-icon"><?= $category_icons[$category] ?? '📄' ?></span>
                        <h2 class="category-title"><?= htmlspecialchars($category) ?></h2>
                    </div>

                    <div class="download-grid">
                        <?php foreach ($items as $item): ?>
                            <div class="download-item">
                                <div class="download-header">
                                    <span class="file-icon"><?= getFileIcon($item['file_type'] ?? 'PDF') ?></span>
                                    <div class="download-info">
                                        <h3 class="download-title"><?= htmlspecialchars($item['title']) ?></h3>
                                        <?php if (!empty($item['version'])): ?>
                                            <span class="version-badge"><?= htmlspecialchars($item['version']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p class="download-description"><?= htmlspecialchars($item['description']) ?></p>

                                <div class="download-meta">
                                    <span class="meta-item">
                                        📁 <?= htmlspecialchars($item['file_type'] ?? 'PDF') ?>
                                    </span>
                                    <span class="meta-item">
                                        💾 <?= htmlspecialchars($item['file_size'] ?? 'N/A') ?>
                                    </span>
                                    <?php if (!empty($item['created_at'])): ?>
                                        <span class="meta-item">
                                            📅 <?= date('Y-m-d', strtotime($item['created_at'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="download-actions">
                                    <a href="<?= htmlspecialchars($item['file_url'] ?? '#') ?>"
                                       class="btn-download"
                                       download>
                                        <span>⬇️</span>
                                        <span>다운로드</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Help Section -->
        <div style="background: #f8f9fa; padding: 30px; border-radius: 8px; margin-top: 50px; text-align: center;">
            <h3 style="margin-bottom: 15px; color: #333;">필요하신 자료를 찾지 못하셨나요?</h3>
            <p style="color: #666; margin-bottom: 20px;">
                고객지원팀에 문의하시면 필요한 자료를 안내해드리겠습니다.
            </p>
            <a href="/pages/support/contact.php" class="btn-download">
                문의하기
            </a>
        </div>
    </main>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>
