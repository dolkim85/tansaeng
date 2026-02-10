<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ||
          (isset($_SESSION['user_level']) && $_SESSION['user_level'] == 9);

if (!$isAdmin) {
    header('Location: /pages/auth/login.php?redirect=/admin/pages/edit_products.php');
    exit;
}

// 현재 콘텐츠 불러오기
$configFile = __DIR__ . '/../../config/products_page_content.json';
$content = [];
if (file_exists($configFile)) {
    $content = json_decode(file_get_contents($configFile), true) ?: [];
}

$header = $content['header'] ?? ['title' => '', 'subtitle' => ''];
$products = $content['products'] ?? [];
$comparison = $content['comparison'] ?? ['title' => '', 'columns' => [], 'rows' => []];
$cta = $content['cta'] ?? ['title' => '', 'subtitle' => '', 'button1_text' => '', 'button1_link' => '', 'button2_text' => '', 'button2_link' => ''];
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>배지설명 페이지 관리 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .cms-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .cms-section h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #34495e;
            margin-bottom: 5px;
            font-size: 0.9em;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95em;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-row-3 {
            display: grid;
            grid-template-columns: 80px 1fr 1fr;
            gap: 15px;
        }

        /* 제품 카드 편집 */
        .product-edit-card {
            border: 1px solid #ecf0f1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            background: #fafbfc;
            position: relative;
        }
        .product-edit-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .product-edit-card .card-number {
            background: #3498db;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85em;
        }
        .btn-remove-product {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
        }
        .btn-add {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            margin-top: 10px;
        }
        .btn-add:hover {
            background: #2980b9;
        }
        .features-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .feature-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .feature-row input {
            flex: 1;
            padding: 7px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .feature-row input:focus {
            outline: none;
            border-color: #3498db;
        }
        .btn-remove-feature {
            background: #e74c3c;
            color: white;
            border: none;
            width: 26px;
            height: 26px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            flex-shrink: 0;
        }
        .btn-add-feature {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            margin-top: 5px;
            align-self: flex-start;
        }

        /* 비교표 */
        .comparison-edit table {
            width: 100%;
            border-collapse: collapse;
        }
        .comparison-edit th, .comparison-edit td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .comparison-edit th {
            background: #ecf0f1;
        }
        .comparison-edit input {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 0.9em;
            box-sizing: border-box;
        }
        .comparison-edit input:focus {
            outline: none;
            border-color: #3498db;
        }
        .btn-remove-row {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.75em;
        }

        /* 툴바 */
        .sticky-toolbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #f8f9fa;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 1px solid #dee2e6;
        }
        .toolbar-inner {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .btn-save {
            background: #2ecc71;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
        }
        .btn-save:hover { background: #27ae60; }
        .btn-save:disabled { background: #95a5a6; cursor: not-allowed; }
        .btn-back {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
        }
        .btn-preview {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
        }
        .save-msg {
            font-size: 0.9em;
            margin-left: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-container">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-content">
                <div class="settings-header">
                    <h1>배지설명 페이지 콘텐츠 관리</h1>
                    <p>배지설명 메인 페이지의 각 섹션을 폼으로 편집할 수 있습니다</p>
                </div>

                <div id="alertSuccess" class="alert alert-success"></div>
                <div id="alertError" class="alert alert-error"></div>

                <div class="sticky-toolbar">
                    <div class="toolbar-inner">
                        <button type="button" class="btn-save" id="btnSave" onclick="saveAll()">저장하기</button>
                        <a href="index.php" class="btn-back">목록으로</a>
                        <a href="/pages/products/" target="_blank" class="btn-preview">미리보기</a>
                        <span class="save-msg" id="saveMsg"></span>
                    </div>
                </div>

                <!-- 1. 페이지 헤더 -->
                <div class="cms-section">
                    <h2>페이지 헤더</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>제목</label>
                            <input type="text" id="header_title" value="<?= htmlspecialchars($header['title']) ?>">
                        </div>
                        <div class="form-group">
                            <label>부제목</label>
                            <input type="text" id="header_subtitle" value="<?= htmlspecialchars($header['subtitle']) ?>">
                        </div>
                    </div>
                </div>

                <!-- 2. 제품 카드 -->
                <div class="cms-section">
                    <h2>제품 카드</h2>
                    <div id="productsContainer">
                        <?php foreach ($products as $i => $p): ?>
                        <div class="product-edit-card" data-index="<?= $i ?>">
                            <div class="card-header">
                                <span class="card-number"><?= $i + 1 ?></span>
                                <button type="button" class="btn-remove-product" onclick="removeProduct(this)">삭제</button>
                            </div>
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label>이모지</label>
                                    <input type="text" class="p-emoji" value="<?= htmlspecialchars($p['emoji']) ?>" style="font-size:1.5em; text-align:center;">
                                </div>
                                <div class="form-group">
                                    <label>제품명</label>
                                    <input type="text" class="p-title" value="<?= htmlspecialchars($p['title']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>링크 URL</label>
                                    <input type="text" class="p-link" value="<?= htmlspecialchars($p['link']) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>설명</label>
                                <textarea class="p-desc" rows="2"><?= htmlspecialchars($p['description']) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>버튼 텍스트</label>
                                <input type="text" class="p-link-text" value="<?= htmlspecialchars($p['link_text'] ?? '제품 보기') ?>">
                            </div>
                            <div class="form-group">
                                <label>특징 목록</label>
                                <div class="features-list">
                                    <?php foreach ($p['features'] as $feat): ?>
                                    <div class="feature-row">
                                        <input type="text" class="p-feature" value="<?= htmlspecialchars($feat) ?>">
                                        <button type="button" class="btn-remove-feature" onclick="this.parentElement.remove()">X</button>
                                    </div>
                                    <?php endforeach; ?>
                                    <button type="button" class="btn-add-feature" onclick="addFeature(this)">+ 특징 추가</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn-add" onclick="addProduct()">+ 제품 카드 추가</button>
                </div>

                <!-- 3. 비교표 -->
                <div class="cms-section">
                    <h2>배지별 특성 비교표</h2>
                    <div class="form-group">
                        <label>비교표 제목</label>
                        <input type="text" id="comp_title" value="<?= htmlspecialchars($comparison['title']) ?>">
                    </div>

                    <div class="comparison-edit" id="comparisonTable">
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach ($comparison['columns'] as $ci => $col): ?>
                                    <th><input type="text" class="comp-col" value="<?= htmlspecialchars($col) ?>"></th>
                                    <?php endforeach; ?>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comparison['rows'] as $row): ?>
                                <tr>
                                    <?php foreach ($row as $cell): ?>
                                    <td><input type="text" class="comp-cell" value="<?= htmlspecialchars($cell) ?>"></td>
                                    <?php endforeach; ?>
                                    <td><button type="button" class="btn-remove-row" onclick="this.closest('tr').remove()">삭제</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn-add" onclick="addComparisonRow()" style="margin-top:10px;">+ 행 추가</button>
                </div>

                <!-- 4. CTA 섹션 -->
                <div class="cms-section">
                    <h2>하단 CTA (Call to Action)</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>제목</label>
                            <input type="text" id="cta_title" value="<?= htmlspecialchars($cta['title']) ?>">
                        </div>
                        <div class="form-group">
                            <label>부제목</label>
                            <input type="text" id="cta_subtitle" value="<?= htmlspecialchars($cta['subtitle']) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>버튼1 텍스트</label>
                            <input type="text" id="cta_btn1_text" value="<?= htmlspecialchars($cta['button1_text']) ?>">
                        </div>
                        <div class="form-group">
                            <label>버튼1 링크</label>
                            <input type="text" id="cta_btn1_link" value="<?= htmlspecialchars($cta['button1_link']) ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>버튼2 텍스트</label>
                            <input type="text" id="cta_btn2_text" value="<?= htmlspecialchars($cta['button2_text']) ?>">
                        </div>
                        <div class="form-group">
                            <label>버튼2 링크</label>
                            <input type="text" id="cta_btn2_link" value="<?= htmlspecialchars($cta['button2_link']) ?>">
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // 제품 카드 추가
        function addProduct() {
            var container = document.getElementById('productsContainer');
            var count = container.children.length + 1;
            var html = '<div class="product-edit-card">'
                + '<div class="card-header"><span class="card-number">' + count + '</span>'
                + '<button type="button" class="btn-remove-product" onclick="removeProduct(this)">삭제</button></div>'
                + '<div class="form-row-3">'
                + '<div class="form-group"><label>이모지</label><input type="text" class="p-emoji" value="" style="font-size:1.5em;text-align:center;"></div>'
                + '<div class="form-group"><label>제품명</label><input type="text" class="p-title" value=""></div>'
                + '<div class="form-group"><label>링크 URL</label><input type="text" class="p-link" value="/pages/store/"></div>'
                + '</div>'
                + '<div class="form-group"><label>설명</label><textarea class="p-desc" rows="2"></textarea></div>'
                + '<div class="form-group"><label>버튼 텍스트</label><input type="text" class="p-link-text" value="제품 보기"></div>'
                + '<div class="form-group"><label>특징 목록</label>'
                + '<div class="features-list">'
                + '<div class="feature-row"><input type="text" class="p-feature" value=""><button type="button" class="btn-remove-feature" onclick="this.parentElement.remove()">X</button></div>'
                + '<button type="button" class="btn-add-feature" onclick="addFeature(this)">+ 특징 추가</button>'
                + '</div></div></div>';
            container.insertAdjacentHTML('beforeend', html);
            renumberProducts();
        }

        function removeProduct(btn) {
            if (!confirm('이 제품 카드를 삭제하시겠습니까?')) return;
            btn.closest('.product-edit-card').remove();
            renumberProducts();
        }

        function renumberProducts() {
            var cards = document.querySelectorAll('#productsContainer .product-edit-card');
            cards.forEach(function(card, i) {
                card.querySelector('.card-number').textContent = i + 1;
            });
        }

        function addFeature(btn) {
            var row = document.createElement('div');
            row.className = 'feature-row';
            row.innerHTML = '<input type="text" class="p-feature" value=""><button type="button" class="btn-remove-feature" onclick="this.parentElement.remove()">X</button>';
            btn.parentElement.insertBefore(row, btn);
        }

        // 비교표 행 추가
        function addComparisonRow() {
            var tbody = document.querySelector('#comparisonTable tbody');
            var colCount = document.querySelectorAll('#comparisonTable .comp-col').length;
            var tr = document.createElement('tr');
            for (var i = 0; i < colCount; i++) {
                tr.innerHTML += '<td><input type="text" class="comp-cell" value=""></td>';
            }
            tr.innerHTML += '<td><button type="button" class="btn-remove-row" onclick="this.closest(\'tr\').remove()">삭제</button></td>';
            tbody.appendChild(tr);
        }

        // 데이터 수집
        function collectData() {
            var data = {};

            // 헤더
            data.header = {
                title: document.getElementById('header_title').value.trim(),
                subtitle: document.getElementById('header_subtitle').value.trim()
            };

            // 제품
            data.products = [];
            document.querySelectorAll('#productsContainer .product-edit-card').forEach(function(card) {
                var features = [];
                card.querySelectorAll('.p-feature').forEach(function(f) {
                    var v = f.value.trim();
                    if (v) features.push(v);
                });
                data.products.push({
                    emoji: card.querySelector('.p-emoji').value.trim(),
                    title: card.querySelector('.p-title').value.trim(),
                    description: card.querySelector('.p-desc').value.trim(),
                    features: features,
                    link: card.querySelector('.p-link').value.trim(),
                    link_text: card.querySelector('.p-link-text').value.trim() || '제품 보기'
                });
            });

            // 비교표
            var columns = [];
            document.querySelectorAll('#comparisonTable .comp-col').forEach(function(c) {
                columns.push(c.value.trim());
            });
            var rows = [];
            document.querySelectorAll('#comparisonTable tbody tr').forEach(function(tr) {
                var cells = [];
                tr.querySelectorAll('.comp-cell').forEach(function(c) {
                    cells.push(c.value.trim());
                });
                if (cells.length > 0) rows.push(cells);
            });
            data.comparison = {
                title: document.getElementById('comp_title').value.trim(),
                columns: columns,
                rows: rows
            };

            // CTA
            data.cta = {
                title: document.getElementById('cta_title').value.trim(),
                subtitle: document.getElementById('cta_subtitle').value.trim(),
                button1_text: document.getElementById('cta_btn1_text').value.trim(),
                button1_link: document.getElementById('cta_btn1_link').value.trim(),
                button2_text: document.getElementById('cta_btn2_text').value.trim(),
                button2_link: document.getElementById('cta_btn2_link').value.trim()
            };

            return data;
        }

        // 저장
        async function saveAll() {
            var btn = document.getElementById('btnSave');
            var msg = document.getElementById('saveMsg');
            btn.disabled = true;
            btn.textContent = '저장 중...';
            msg.textContent = '';
            hideAlerts();

            var data = collectData();

            try {
                var response = await fetch('/admin/api/save_products_content.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                var result = await response.json();
                if (result.success) {
                    showAlert('success', result.message);
                    msg.textContent = '저장 완료';
                    msg.style.color = '#2ecc71';
                    setTimeout(function() { msg.textContent = ''; }, 3000);
                } else {
                    showAlert('error', result.message || '저장 실패');
                    msg.textContent = '저장 실패';
                    msg.style.color = '#e74c3c';
                }
            } catch (e) {
                showAlert('error', '서버 통신 오류');
                msg.textContent = '통신 오류';
                msg.style.color = '#e74c3c';
            } finally {
                btn.disabled = false;
                btn.textContent = '저장하기';
            }
        }

        function showAlert(type, message) {
            hideAlerts();
            var el = document.getElementById(type === 'success' ? 'alertSuccess' : 'alertError');
            el.textContent = message;
            el.style.display = 'block';
            if (type === 'success') setTimeout(function() { el.style.display = 'none'; }, 5000);
        }
        function hideAlerts() {
            document.getElementById('alertSuccess').style.display = 'none';
            document.getElementById('alertError').style.display = 'none';
        }

        // Ctrl+S
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveAll();
            }
        });
    </script>
</body>
</html>
