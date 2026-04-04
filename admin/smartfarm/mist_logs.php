<?php
/**
 * 분무수경 가동 로그 - 관리자 페이지
 * 날짜별 조회 및 CSV 다운로드
 */

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';
require_once $base_path . '/classes/Database.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$currentUser = $auth->getCurrentUser();

$allowedAdmins = [
    'korea_tansaeng@naver.com',
    'superjun1985@gmail.com'
];

if (!in_array($currentUser['email'], $allowedAdmins)) {
    header('HTTP/1.1 403 Forbidden');
    die('<p>접근 권한 없음</p>');
}

date_default_timezone_set('Asia/Seoul');

$db = Database::getInstance();

// 파라미터
$selectedDate = $_GET['date']  ?? date('Y-m-d');
$selectedZone = $_GET['zone']  ?? '';
$export       = $_GET['export'] ?? '';

// 날짜 유효성
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// 로그 쿼리
$params = [$selectedDate];
$zoneSql = '';
if ($selectedZone !== '') {
    $zoneSql = " AND zone_id = ?";
    $params[] = $selectedZone;
}

$logs = [];
$summary = ['start_count' => 0, 'total_minutes' => 0];

try {
    $logs = $db->select(
        "SELECT id, zone_id, zone_name, event_type, mode, created_at
         FROM mist_logs
         WHERE DATE(created_at) = ?{$zoneSql}
         ORDER BY created_at ASC",
        $params
    );

    // 총 가동 시간 계산 (시작/정지 쌍)
    $starts = $totalSec = 0;
    $zoneStarts = [];
    foreach ($logs as $log) {
        $zId = $log['zone_id'];
        if ($log['event_type'] === 'start') {
            $starts++;
            $zoneStarts[$zId] = strtotime($log['created_at']);
        } elseif ($log['event_type'] === 'stop' && isset($zoneStarts[$zId])) {
            $totalSec += strtotime($log['created_at']) - $zoneStarts[$zId];
            unset($zoneStarts[$zId]);
        }
    }
    $summary = [
        'start_count'   => $starts,
        'total_minutes' => (int) round($totalSec / 60),
    ];
} catch (Exception $e) {
    $error = '로그를 불러오는데 실패했습니다: ' . $e->getMessage();
}

// ── CSV 내보내기 ──
if ($export === 'csv') {
    $filename = 'mist_logs_' . $selectedDate . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM (Excel 한글)
    echo "시간,구역ID,구역명,이벤트,모드\n";
    foreach ($logs as $log) {
        $time      = htmlspecialchars_decode($log['created_at']);
        $zoneId    = $log['zone_id'];
        $zoneName  = $log['zone_name'];
        $event     = $log['event_type'] === 'start' ? '시작' : '정지';
        $mode      = $log['mode'];
        echo "\"{$time}\",\"{$zoneId}\",\"{$zoneName}\",\"{$event}\",\"{$mode}\"\n";
    }
    exit;
}

// 구역 목록 (필터용)
$zones = [];
try {
    $zones = $db->select("SELECT DISTINCT zone_id, zone_name FROM mist_logs ORDER BY zone_id");
} catch (Exception $e) {
    // 무시
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>분무 가동 로그 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .log-container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .page-title { font-size: 1.6rem; font-weight: bold; color: #333; }
        .filter-bar {
            background: white;
            border-radius: 8px;
            padding: 16px 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        .filter-bar label { font-size: 13px; color: #555; font-weight: 600; }
        .filter-bar input[type="date"],
        .filter-bar select {
            padding: 7px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn { padding: 8px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: .85; }

        .summary-bar {
            background: #e8f5e9;
            border-radius: 8px;
            padding: 12px 20px;
            display: flex;
            gap: 24px;
            font-size: 14px;
            font-weight: 600;
            color: #2e7d32;
            margin-bottom: 16px;
        }

        .log-table-wrap {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa;
            padding: 12px 16px;
            text-align: left;
            font-size: 13px;
            color: #555;
            border-bottom: 2px solid #dee2e6;
        }
        td {
            padding: 11px 16px;
            font-size: 13px;
            border-bottom: 1px solid #f1f3f5;
        }
        tr:hover td { background: #f8f9fa; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-start  { background: #d4edda; color: #155724; }
        .badge-stop   { background: #f8d7da; color: #721c24; }
        .badge-auto   { background: #d1ecf1; color: #0c5460; }
        .badge-manual { background: #fff3cd; color: #856404; }
        .empty-state { text-align: center; padding: 40px; color: #aaa; font-size: 14px; }

        .alert-danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="log-container">
                <div class="page-header">
                    <h1 class="page-title">💧 분무 가동 로그</h1>
                    <a href="?date=<?= htmlspecialchars($selectedDate) ?>&zone=<?= htmlspecialchars($selectedZone) ?>&export=csv"
                       class="btn btn-success">📥 CSV 다운로드</a>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <!-- 필터 -->
                <form method="GET" class="filter-bar">
                    <label>날짜</label>
                    <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" max="<?= date('Y-m-d') ?>">
                    <label>구역</label>
                    <select name="zone">
                        <option value="">전체</option>
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= htmlspecialchars($z['zone_id']) ?>"
                                    <?= $selectedZone === $z['zone_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($z['zone_name'] ?: $z['zone_id']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">조회</button>
                </form>

                <!-- 요약 -->
                <div class="summary-bar">
                    <span>📅 <?= htmlspecialchars($selectedDate) ?></span>
                    <span>💧 총 <?= $summary['start_count'] ?>회 가동</span>
                    <span>⏱️ 총 <?= $summary['total_minutes'] ?>분 운영</span>
                    <span>📄 <?= count($logs) ?>건 기록</span>
                </div>

                <!-- 로그 테이블 -->
                <div class="log-table-wrap">
                    <?php if (empty($logs)): ?>
                        <div class="empty-state">
                            <p>해당 날짜에 분무 로그가 없습니다.</p>
                            <p style="font-size:12px;margin-top:4px;">분무 작동/정지 시 자동으로 기록됩니다.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>시간</th>
                                    <th>구역</th>
                                    <th>이벤트</th>
                                    <th>모드</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(substr($log['created_at'], 11, 8)) ?></td>
                                        <td><?= htmlspecialchars($log['zone_name'] ?: $log['zone_id']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $log['event_type'] === 'start' ? 'start' : 'stop' ?>">
                                                <?= $log['event_type'] === 'start' ? '🟢 시작' : '🔴 정지' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= strtolower($log['mode']) === 'auto' ? 'auto' : 'manual' ?>">
                                                <?= htmlspecialchars($log['mode']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
