<?php
/**
 * Products Main Page - 배지설명
 * 탄생 스마트팜 배지 제품 소개
 * 콘텐츠는 config/products_page_content.json에서 로드
 */

$currentUser = null;
try {
    require_once __DIR__ . '/../../classes/Auth.php';
    $auth = Auth::getInstance();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

// JSON 설정에서 콘텐츠 로드
$configFile = __DIR__ . '/../../config/products_page_content.json';
$content = [];
if (file_exists($configFile)) {
    $content = json_decode(file_get_contents($configFile), true) ?: [];
}

$header = $content['header'] ?? ['title' => '배지설명', 'subtitle' => ''];
$products = $content['products'] ?? [];
$comparison = $content['comparison'] ?? ['title' => '', 'columns' => [], 'rows' => []];
$cta = $content['cta'] ?? ['title' => '', 'subtitle' => '', 'button1_text' => '', 'button1_link' => '', 'button2_text' => '', 'button2_link' => ''];

$pageTitle = htmlspecialchars($header['title']) . " - 탄생";
$pageDescription = "고품질 수경재배 배지 제품군을 소개합니다. 코코피트, 펄라이트, 혼합배지 등 다양한 제품을 확인하세요.";
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= $pageDescription ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <style>
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 3rem 0;
            background: linear-gradient(135deg, #E8F5E8 0%, #C8E6C9 100%);
            border-radius: 12px;
        }
        .page-header h1 {
            font-size: 2.5rem;
            color: #2E7D32;
            margin-bottom: 1rem;
        }
        .page-header p {
            font-size: 1.1rem;
            color: #555;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 60px 0;
        }
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.15);
        }
        .product-image {
            height: 200px;
            background: linear-gradient(45deg, #4CAF50, #2E7D32);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
        }
        .product-content {
            padding: 30px;
        }
        .product-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2E7D32;
            margin-bottom: 15px;
        }
        .product-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .product-features {
            list-style: none;
            padding: 0;
            margin-bottom: 25px;
        }
        .product-features li {
            padding: 5px 0;
            color: #333;
        }
        .product-features li:before {
            content: "\2713 ";
            color: #4CAF50;
            font-weight: bold;
        }
        .btn-learn-more {
            background: #2E7D32;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: background 0.3s ease;
        }
        .btn-learn-more:hover {
            background: #1B5E20;
            color: white;
        }
        .comparison-section {
            background: #f8f9fa;
            padding: 60px 0;
        }
        .comparison-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .comparison-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .comparison-table th {
            background: #2E7D32;
            color: white;
            padding: 20px;
            text-align: center;
            font-weight: 600;
        }
        .comparison-table td {
            padding: 15px 20px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        .comparison-table tr:hover {
            background: #f5f5f5;
        }
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem 1rem !important;
            }
            .page-header h1 {
                font-size: 1.5rem !important;
            }
            .page-header p {
                font-size: 0.9rem !important;
            }
            .products-grid {
                grid-template-columns: 1fr;
                padding: 40px 0;
            }
            .comparison-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <main>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><?= htmlspecialchars($header['title']) ?></h1>
            <p><?= htmlspecialchars($header['subtitle']) ?></p>
        </div>
    </div>

    <!-- Products Grid -->
    <?php if (!empty($products)): ?>
    <section class="products-grid">
        <div class="container">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
                <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <div class="product-image"><?= $product['emoji'] ?></div>
                    <div class="product-content">
                        <h3 class="product-title"><?= htmlspecialchars($product['title']) ?></h3>
                        <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                        <?php if (!empty($product['features'])): ?>
                        <ul class="product-features">
                            <?php foreach ($product['features'] as $feat): ?>
                            <li><?= htmlspecialchars($feat) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($product['link']) ?>" class="btn-learn-more"><?= htmlspecialchars($product['link_text'] ?? '제품 보기') ?></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Comparison Section -->
    <?php if (!empty($comparison['columns']) && !empty($comparison['rows'])): ?>
    <section class="comparison-section">
        <div class="container">
            <h2 style="text-align: center; margin-bottom: 40px; color: #2E7D32;"><?= htmlspecialchars($comparison['title']) ?></h2>
            <div class="comparison-table">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($comparison['columns'] as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparison['rows'] as $row): ?>
                        <tr>
                            <?php foreach ($row as $ci => $cell): ?>
                            <td><?= $ci === 0 ? '<strong>' . htmlspecialchars($cell) . '</strong>' : htmlspecialchars($cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <?php if (!empty($cta['title'])): ?>
    <section style="background: #2E7D32; color: white; padding: 60px 0; text-align: center;">
        <div class="container">
            <h2><?= htmlspecialchars($cta['title']) ?></h2>
            <p style="margin: 20px 0; font-size: 1.1rem;"><?= htmlspecialchars($cta['subtitle']) ?></p>
            <div style="margin-top: 30px;">
                <?php if (!empty($cta['button1_text'])): ?>
                <a href="<?= htmlspecialchars($cta['button1_link']) ?>" class="btn-learn-more" style="background: white; color: #2E7D32; margin-right: 15px;"><?= htmlspecialchars($cta['button1_text']) ?></a>
                <?php endif; ?>
                <?php if (!empty($cta['button2_text'])): ?>
                <a href="<?= htmlspecialchars($cta['button2_link']) ?>" class="btn-learn-more" style="background: transparent; border: 2px solid white;"><?= htmlspecialchars($cta['button2_text']) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    </main>

    <?php include '../../includes/footer.php'; ?>

    <script src="/assets/js/main.js"></script>
</body>
</html>
