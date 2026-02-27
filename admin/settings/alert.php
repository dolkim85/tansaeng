<?php
/**
 * 알림 설정 - 관리자 페이지
 * Telegram 봇 토큰, 챗 ID, 온도/습도 임계값, 쿨다운 등 설정
 */

$base_path = dirname(dirname(__DIR__));
require_once $base_path . '/classes/Auth.php';

$auth = Auth::getInstance();
$auth->requireAdmin();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>알림 설정 - 탄생 관리자</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .alert-container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .page-title { font-size: 1.6rem; font-weight: bold; color: #333; margin-bottom: 24px; }

        .settings-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            padding: 30px;
        }
        .section {
            margin-bottom: 32px;
            padding-bottom: 28px;
            border-bottom: 1px solid #f1f3f5;
        }
        .section:last-of-type { border-bottom: none; margin-bottom: 0; }
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #444; margin-bottom: 6px; }
        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-control:focus { border-color: #007bff; outline: none; }
        .form-help { font-size: 12px; color: #888; margin-top: 4px; }
        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .checkbox-row input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
        .checkbox-row label { font-size: 14px; color: #444; cursor: pointer; }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f1f3f5;
        }
        .btn { padding: 10px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-primary:disabled { background: #aaa; cursor: not-allowed; }

        #save-status { font-size: 14px; font-weight: 600; padding: 8px 16px; border-radius: 6px; display: none; }
        .status-ok  { background: #d4edda; color: #155724; }
        .status-err { background: #f8d7da; color: #721c24; }

        .loading-indicator {
            text-align: center;
            padding: 40px;
            color: #aaa;
            font-size: 14px;
        }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include '../includes/admin_header.php'; ?>

    <div class="admin-layout">
        <?php include '../includes/admin_sidebar.php'; ?>

        <main class="admin-main">
            <div class="alert-container">
                <h1 class="page-title">🔔 알림 설정</h1>

                <div id="form-area">
                    <div class="loading-indicator">설정 로딩 중...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
    let currentConfig = {};

    function renderForm(cfg) {
        currentConfig = cfg;
        const tg  = cfg.telegram || {};
        const checked = v => v ? 'checked' : '';
        const val = (v, def) => (v !== undefined && v !== null) ? v : def;

        document.getElementById('form-area').innerHTML = `
        <form class="settings-form" id="alertForm">

            <!-- 텔레그램 -->
            <div class="section">
                <div class="section-title">📨 텔레그램 알림</div>
                <div class="checkbox-row">
                    <input type="checkbox" id="tg_enabled" ${checked(tg.enabled)}>
                    <label for="tg_enabled">텔레그램 알림 활성화</label>
                </div>
                <div class="form-group">
                    <label class="form-label">봇 토큰</label>
                    <input type="text" id="tg_token" class="form-control"
                           value="${escHtml(tg.bot_token || '')}"
                           placeholder="XXXXXXXXX:AAXXXX..." autocomplete="off">
                    <p class="form-help">@BotFather에서 발급받은 봇 토큰을 입력하세요.</p>
                </div>
                <div class="form-group">
                    <label class="form-label">챗 ID</label>
                    <input type="text" id="tg_chat" class="form-control"
                           value="${escHtml(tg.chat_id || '')}"
                           placeholder="예: 8616971661">
                    <p class="form-help">@userinfobot 에서 본인 챗 ID를 확인하세요.</p>
                </div>
            </div>

            <!-- 온도 알림 -->
            <div class="section">
                <div class="section-title">🌡️ 온도 임계값 알림</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">저온 경보 기준 (°C)</label>
                        <input type="number" id="temp_low" class="form-control"
                               value="${val(cfg.temp_alert_low, 5)}" step="0.5" min="-20" max="40">
                        <p class="form-help">이 온도 이하면 저온 경보 발송</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">고온 경보 기준 (°C)</label>
                        <input type="number" id="temp_high" class="form-control"
                               value="${val(cfg.temp_alert_high, 28)}" step="0.5" min="-20" max="60">
                        <p class="form-help">이 온도 이상이면 고온 경보 발송</p>
                    </div>
                </div>
            </div>

            <!-- 습도 알림 -->
            <div class="section">
                <div class="section-title">💧 습도 임계값 알림</div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">건조 경보 기준 (%)</label>
                        <input type="number" id="hum_low" class="form-control"
                               value="${val(cfg.humidity_alert_low, 30)}" step="1" min="0" max="100">
                        <p class="form-help">이 습도 이하면 건조 경보 발송</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">고습도 경보 기준 (%)</label>
                        <input type="number" id="hum_high" class="form-control"
                               value="${val(cfg.humidity_alert_high, 95)}" step="1" min="0" max="100">
                        <p class="form-help">이 습도 이상이면 곰팡이 경보 발송</p>
                    </div>
                </div>
            </div>

            <!-- 시스템 설정 -->
            <div class="section">
                <div class="section-title">⚙️ 시스템 알림 설정</div>
                <div class="form-group">
                    <label class="form-label">알림 쿨다운 (분)</label>
                    <input type="number" id="cooldown" class="form-control" style="max-width:160px"
                           value="${val(cfg.cooldown_minutes, 30)}" min="1" max="1440">
                    <p class="form-help">동일 알림을 이 시간 간격으로 제한 (스팸 방지)</p>
                </div>
                <div class="checkbox-row">
                    <input type="checkbox" id="alert_offline" ${checked(cfg.alert_on_device_offline)}>
                    <label for="alert_offline">장치 오프라인 시 알림</label>
                </div>
                <div class="checkbox-row">
                    <input type="checkbox" id="alert_restart" ${checked(cfg.alert_on_daemon_restart)}>
                    <label for="alert_restart">데몬 재시작 시 알림</label>
                </div>
                <div class="form-group" style="margin-top:12px">
                    <label class="form-label">밸브 장기 열림 감지 (분)</label>
                    <input type="number" id="valve_stuck" class="form-control" style="max-width:160px"
                           value="${val(cfg.alert_on_valve_stuck_minutes, 10)}" min="1" max="120">
                    <p class="form-help">밸브가 이 시간 이상 열려 있으면 알림</p>
                </div>
            </div>

            <div class="form-actions">
                <span id="save-status"></span>
                <button type="button" id="saveBtn" class="btn btn-primary" onclick="saveConfig()">💾 저장</button>
            </div>
        </form>`;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showStatus(msg, ok) {
        const el = document.getElementById('save-status');
        el.textContent = msg;
        el.className   = ok ? 'status-ok' : 'status-err';
        el.style.display = 'inline-block';
        setTimeout(() => { el.style.display = 'none'; }, 3000);
    }

    function saveConfig() {
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.textContent = '저장 중...';

        const payload = {
            telegram: {
                enabled:   document.getElementById('tg_enabled').checked,
                bot_token: document.getElementById('tg_token').value.trim(),
                chat_id:   document.getElementById('tg_chat').value.trim(),
            },
            temp_alert_low:               parseFloat(document.getElementById('temp_low').value),
            temp_alert_high:              parseFloat(document.getElementById('temp_high').value),
            humidity_alert_low:           parseFloat(document.getElementById('hum_low').value),
            humidity_alert_high:          parseFloat(document.getElementById('hum_high').value),
            cooldown_minutes:             parseInt(document.getElementById('cooldown').value),
            alert_on_device_offline:      document.getElementById('alert_offline').checked,
            alert_on_daemon_restart:      document.getElementById('alert_restart').checked,
            alert_on_valve_stuck_minutes: parseInt(document.getElementById('valve_stuck').value),
        };

        fetch('/api/smartfarm/save_alert_config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showStatus('✅ 저장됨', true);
                if (data.config) currentConfig = data.config;
            } else {
                showStatus('❌ 저장 실패: ' + (data.message || '알 수 없는 오류'), false);
            }
        })
        .catch(() => showStatus('❌ 네트워크 오류', false))
        .finally(() => {
            btn.disabled = false;
            btn.textContent = '💾 저장';
        });
    }

    // 페이지 로드 시 현재 설정 불러오기
    fetch('/api/smartfarm/get_alert_config.php')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.config) {
                renderForm(data.config);
            } else {
                document.getElementById('form-area').innerHTML =
                    '<div style="color:#dc3545;padding:20px">설정을 불러올 수 없습니다.</div>';
            }
        })
        .catch(() => {
            document.getElementById('form-area').innerHTML =
                '<div style="color:#dc3545;padding:20px">서버 오류로 설정을 불러올 수 없습니다.</div>';
        });
    </script>
</body>
</html>
