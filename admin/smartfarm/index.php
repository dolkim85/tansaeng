<?php
/**
 * 스마트팜 관리자 대시보드
 * 장치 현황, 분무 통계, 24시간 온습도 그래프
 */

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';

$auth = Auth::getInstance();
$auth->requireAdmin();

$currentUser = $auth->getCurrentUser();

// 허용된 관리자 계정
$allowedAdmins = [
    'korea_tansaeng@naver.com',
    'superjun1985@gmail.com'
];

if (!in_array($currentUser['email'], $allowedAdmins)) {
    header('HTTP/1.1 403 Forbidden');
    die('<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8"><title>접근 권한 없음</title></head>
    <body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;">
    <div style="text-align:center"><h2>접근 권한 없음</h2><p>' . htmlspecialchars($currentUser['email']) . ' 계정은 접근할 수 없습니다.</p>
    <a href="/admin/">대시보드로 돌아가기</a></div></body></html>');
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>스마트팜 대시보드 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .sf-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .page-title {
            font-size: 1.6rem;
            font-weight: bold;
            color: #333;
        }
        .btn-refresh {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-refresh:hover { background: #218838; }

        /* 통계 카드 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-icon { font-size: 2rem; margin-bottom: 8px; }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            line-height: 1;
        }
        .stat-label {
            font-size: 13px;
            color: #888;
            margin-top: 6px;
        }
        .stat-online .stat-value  { color: #28a745; }
        .stat-offline .stat-value { color: #dc3545; }
        .stat-mist .stat-value    { color: #007bff; }
        .stat-time .stat-value    { color: #6f42c1; }

        /* 차트 */
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .chart-title {
            font-size: 14px;
            font-weight: 600;
            color: #555;
            margin-bottom: 12px;
        }
        canvas {
            width: 100% !important;
            height: 160px !important;
            display: block;
        }
        .no-data {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 160px;
            color: #aaa;
            font-size: 13px;
        }

        /* 바로가기 */
        .quick-links {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .quick-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: opacity .15s;
        }
        .quick-link:hover { opacity: .85; }
        .ql-ui       { background: #e8f5e9; color: #2e7d32; }
        .ql-log      { background: #e3f2fd; color: #1565c0; }
        .ql-alert    { background: #fff3e0; color: #e65100; }

        .loading-text { color: #aaa; font-size: 13px; }

        @media (max-width: 768px) {
            .stats-grid  { grid-template-columns: repeat(2, 1fr); }
            .charts-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="sf-container">
                <div class="page-header">
                    <h1 class="page-title">🏭 스마트팜 대시보드</h1>
                    <button class="btn-refresh" onclick="loadDashboard()">🔄 새로고침</button>
                </div>

                <!-- 통계 카드 -->
                <div class="stats-grid">
                    <div class="stat-card stat-online">
                        <div class="stat-icon">🟢</div>
                        <div class="stat-value" id="val-online">-</div>
                        <div class="stat-label">온라인 장치</div>
                    </div>
                    <div class="stat-card stat-offline">
                        <div class="stat-icon">🔴</div>
                        <div class="stat-value" id="val-offline">-</div>
                        <div class="stat-label">오프라인 장치</div>
                    </div>
                    <div class="stat-card stat-mist">
                        <div class="stat-icon">💧</div>
                        <div class="stat-value" id="val-mist-count">-</div>
                        <div class="stat-label">오늘 분무 횟수</div>
                    </div>
                    <div class="stat-card stat-time">
                        <div class="stat-icon">⏱️</div>
                        <div class="stat-value" id="val-mist-time">-</div>
                        <div class="stat-label">오늘 총 가동 시간</div>
                    </div>
                </div>

                <!-- 차트 -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-title">🌡️ 24시간 평균 온도 (°C)</div>
                        <div id="temp-chart-wrap">
                            <div class="no-data loading-text">데이터 로딩 중...</div>
                        </div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-title">💧 24시간 평균 습도 (%)</div>
                        <div id="hum-chart-wrap">
                            <div class="no-data loading-text">데이터 로딩 중...</div>
                        </div>
                    </div>
                </div>

                <!-- 바로가기 -->
                <div class="quick-links">
                    <a href="/smartfarm-ui/" target="_blank" class="quick-link ql-ui">🏭 환경제어 시스템 열기</a>
                    <a href="/admin/smartfarm/mist_logs.php" class="quick-link ql-log">📋 분무 가동 로그</a>
                    <a href="/admin/settings/alert.php" class="quick-link ql-alert">🔔 알림 설정</a>
                </div>
            </div>
        </main>
    </div>

    <script>
    function drawLineChart(canvasId, dataPoints, color, unit) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const W = canvas.width, H = canvas.height;
        const pl = 45, pr = 15, pt = 15, pb = 35;

        ctx.clearRect(0, 0, W, H);

        if (!dataPoints || dataPoints.length < 2) {
            ctx.fillStyle = '#bbb';
            ctx.font = '13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('데이터 없음', W / 2, H / 2);
            return;
        }

        const vals = dataPoints.map(d => d.v);
        const maxV = Math.max(...vals);
        const minV = Math.min(...vals);
        const range = (maxV - minV) || 1;

        const xOf = i => pl + (i / (dataPoints.length - 1)) * (W - pl - pr);
        const yOf = v => pt + (1 - (v - minV) / range) * (H - pt - pb);

        // 축
        ctx.strokeStyle = '#ddd';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(pl, pt); ctx.lineTo(pl, H - pb);
        ctx.lineTo(W - pr, H - pb);
        ctx.stroke();

        // Y 눈금값
        ctx.fillStyle = '#999';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        [0, 0.5, 1].forEach(ratio => {
            const v = minV + ratio * range;
            const y = yOf(v);
            ctx.fillText(v.toFixed(1), pl - 4, y + 4);
            ctx.strokeStyle = '#f0f0f0';
            ctx.beginPath();
            ctx.moveTo(pl, y); ctx.lineTo(W - pr, y);
            ctx.stroke();
        });

        // X 레이블 (최대 6개)
        ctx.fillStyle = '#999';
        ctx.font = '10px sans-serif';
        ctx.textAlign = 'center';
        const step = Math.ceil(dataPoints.length / 6);
        dataPoints.forEach((d, i) => {
            if (i % step === 0 || i === dataPoints.length - 1) {
                ctx.fillText(d.label, xOf(i), H - pb + 14);
            }
        });

        // 라인
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.beginPath();
        dataPoints.forEach((d, i) => {
            const x = xOf(i), y = yOf(d.v);
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.stroke();

        // 포인트
        ctx.fillStyle = color;
        dataPoints.forEach((d, i) => {
            ctx.beginPath();
            ctx.arc(xOf(i), yOf(d.v), 3, 0, Math.PI * 2);
            ctx.fill();
        });
    }

    function renderChart(wrapId, canvasId, dataPoints, color, unit) {
        const wrap = document.getElementById(wrapId);
        if (!dataPoints || dataPoints.length === 0) {
            wrap.innerHTML = '<div class="no-data">24시간 내 데이터 없음</div>';
            return;
        }
        wrap.innerHTML = '<canvas id="' + canvasId + '" width="520" height="160"></canvas>';
        drawLineChart(canvasId, dataPoints, color, unit);
    }

    function loadDashboard() {
        document.getElementById('val-online').textContent  = '...';
        document.getElementById('val-offline').textContent = '...';
        document.getElementById('val-mist-count').textContent = '...';
        document.getElementById('val-mist-time').textContent  = '...';

        fetch('/api/smartfarm/get_admin_dashboard.php')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;

                document.getElementById('val-online').textContent  = data.devices.online;
                document.getElementById('val-offline').textContent = data.devices.offline;
                document.getElementById('val-mist-count').textContent = data.mist_today.count + '회';
                document.getElementById('val-mist-time').textContent  = data.mist_today.total_minutes + '분';

                // 차트 데이터 가공
                const tempPts = data.chart_24h
                    .filter(r => r.avg_temp !== null)
                    .map(r => ({ label: r.hour_label, v: parseFloat(r.avg_temp) }));

                const humPts  = data.chart_24h
                    .filter(r => r.avg_humidity !== null)
                    .map(r => ({ label: r.hour_label, v: parseFloat(r.avg_humidity) }));

                renderChart('temp-chart-wrap', 'tempChart', tempPts, '#ef4444', '°C');
                renderChart('hum-chart-wrap',  'humChart',  humPts,  '#3b82f6', '%');
            })
            .catch(() => {
                document.getElementById('val-online').textContent  = '-';
                document.getElementById('val-offline').textContent = '-';
            });
    }

    // 초기 로드 + 60초 자동 새로고침
    loadDashboard();
    setInterval(loadDashboard, 60000);
    </script>
</body>
</html>
