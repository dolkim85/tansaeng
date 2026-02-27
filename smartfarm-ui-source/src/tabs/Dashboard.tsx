/**
 * 스마트팜 대시보드 탭
 * - 분무수경 탭의 mistZones 데이터로 구역 현황 표시
 * - 환경 모니터링 탭과 동일한 get_realtime_sensor_data.php로 현재 온습도 표시
 * - get_admin_dashboard.php로 장치 통계 + 24h 집계 차트
 */

import { useCallback, useEffect, useRef, useState } from "react";
import type { MistZoneConfig } from "../types";

interface DashboardProps {
  mistZones: MistZoneConfig[];
}

interface DashboardData {
  devices: { total: number; online: number; offline: number };
  mist_today: { count: number; total_minutes: number };
  chart_24h: Array<{
    hour_label: string;
    avg_temp: string | null;
    avg_humidity: string | null;
  }>;
}

// 환경 모니터링 탭의 get_realtime_sensor_data.php 응답 타입
interface RealtimeSensorData {
  front: { temperature: number | null; humidity: number | null; lastUpdate: string | null };
  back:  { temperature: number | null; humidity: number | null; lastUpdate: string | null };
  top:   { temperature: number | null; humidity: number | null; lastUpdate: string | null };
}

interface ChartPoint {
  label: string;
  value: number;
}

// ── 캔버스 라인 차트 컴포넌트 ──
function LineChart({ points, color }: { points: ChartPoint[]; color: string }) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const W = canvas.width, H = canvas.height;
    const pl = 44, pr = 12, pt = 12, pb = 26;

    ctx.clearRect(0, 0, W, H);

    if (points.length < 2) {
      ctx.fillStyle = "#9ca3af";
      ctx.font = "12px sans-serif";
      ctx.textAlign = "center";
      ctx.fillText("24시간 내 데이터 없음", W / 2, H / 2);
      return;
    }

    const vals  = points.map((p) => p.value);
    const maxV  = Math.max(...vals);
    const minV  = Math.min(...vals);
    const range = maxV - minV || 1;

    const xOf = (i: number) => pl + (i / (points.length - 1)) * (W - pl - pr);
    const yOf = (v: number) => pt + (1 - (v - minV) / range) * (H - pt - pb);

    [0, 0.5, 1].forEach((ratio) => {
      const v = minV + ratio * range;
      const y = yOf(v);
      ctx.strokeStyle = "#e5e7eb";
      ctx.lineWidth = 1;
      ctx.beginPath(); ctx.moveTo(pl, y); ctx.lineTo(W - pr, y); ctx.stroke();
      ctx.fillStyle = "#9ca3af";
      ctx.font = "10px sans-serif";
      ctx.textAlign = "right";
      ctx.fillText(v.toFixed(1), pl - 4, y + 4);
    });

    const step = Math.ceil(points.length / 5);
    ctx.fillStyle = "#9ca3af";
    ctx.font = "10px sans-serif";
    ctx.textAlign = "center";
    points.forEach((p, i) => {
      if (i % step === 0 || i === points.length - 1)
        ctx.fillText(p.label, xOf(i), H - pb + 12);
    });

    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.lineJoin = "round";
    ctx.beginPath();
    points.forEach((p, i) => {
      const x = xOf(i), y = yOf(p.value);
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.stroke();

    ctx.fillStyle = color;
    points.forEach((p, i) => {
      ctx.beginPath();
      ctx.arc(xOf(i), yOf(p.value), 2.5, 0, Math.PI * 2);
      ctx.fill();
    });
  }, [points, color]);

  return (
    <canvas
      ref={canvasRef}
      width={480}
      height={130}
      style={{ width: "100%", height: "130px", display: "block" }}
    />
  );
}

// ── 센서 위치 레이블 ──
const LOCATION_LABELS: Record<string, string> = {
  front: "앞쪽",
  back:  "뒤쪽",
  top:   "천장",
};

export default function Dashboard({ mistZones }: DashboardProps) {
  const [dashData,   setDashData]   = useState<DashboardData | null>(null);
  const [sensorData, setSensorData] = useState<RealtimeSensorData | null>(null);
  const [loading,    setLoading]    = useState(true);
  const [lastUpdated, setLastUpdated] = useState("");

  // 장치 통계 + 24h 차트 (get_admin_dashboard.php)
  const loadDash = useCallback(() => {
    return fetch("/api/smartfarm/get_admin_dashboard.php")
      .then((r) => r.json())
      .then((d) => { if (d.success) setDashData(d); })
      .catch(() => {});
  }, []);

  // 실시간 온습도 → 환경 모니터링 탭과 동일한 API 사용
  const loadSensor = useCallback(() => {
    return fetch("/api/smartfarm/get_realtime_sensor_data.php")
      .then((r) => r.json())
      .then((d) => { if (d.success) setSensorData(d.data); })
      .catch(() => {});
  }, []);

  const loadAll = useCallback(async () => {
    await Promise.all([loadDash(), loadSensor()]);
    const now = new Date();
    setLastUpdated(
      `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}:${String(now.getSeconds()).padStart(2, "0")}`
    );
    setLoading(false);
  }, [loadDash, loadSensor]);

  useEffect(() => {
    loadAll();
    const timer = setInterval(loadAll, 30000); // 30초 자동 새로고침
    return () => clearInterval(timer);
  }, [loadAll]);

  // ── 24h 차트 데이터 가공 ──
  const tempPoints: ChartPoint[] =
    dashData?.chart_24h
      .filter((r) => r.avg_temp !== null)
      .map((r) => ({ label: r.hour_label, value: parseFloat(r.avg_temp!) })) ?? [];

  const humPoints: ChartPoint[] =
    dashData?.chart_24h
      .filter((r) => r.avg_humidity !== null)
      .map((r) => ({ label: r.hour_label, value: parseFloat(r.avg_humidity!) })) ?? [];

  // ── 분무수경 구역 현황 (mistZones - 분무수경 탭과 동일 데이터) ──
  const runningZones = mistZones.filter((z) => z.isRunning);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-gray-400 text-sm">데이터 로딩 중...</div>
      </div>
    );
  }

  return (
    <div className="p-4 max-w-screen-xl mx-auto space-y-4">

      {/* 헤더 */}
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-bold text-gray-800">🏭 스마트팜 대시보드</h2>
        <div className="flex items-center gap-2">
          {lastUpdated && (
            <span className="text-xs text-gray-400">업데이트 {lastUpdated}</span>
          )}
          <button
            onClick={loadAll}
            className="text-xs px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors"
          >
            🔄 새로고침
          </button>
        </div>
      </div>

      {/* ── 실시간 온습도 (환경 모니터링 탭과 동일 데이터 소스) ── */}
      <div>
        <div className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          📊 현재 온습도 · 환경 모니터링 탭과 동일
        </div>
        <div className="grid grid-cols-3 gap-3">
          {(["front", "back", "top"] as const).map((loc) => {
            const s = sensorData?.[loc];
            const hasData = s && (s.temperature !== null || s.humidity !== null);
            return (
              <div
                key={loc}
                className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center"
              >
                <div className="text-xs text-gray-400 mb-2 font-medium">{LOCATION_LABELS[loc]}</div>
                {hasData ? (
                  <>
                    <div className="text-xl font-bold text-red-500 leading-tight">
                      {s.temperature !== null ? `${s.temperature}°C` : "-"}
                    </div>
                    <div className="text-base font-semibold text-blue-500 mt-0.5">
                      {s.humidity !== null ? `${s.humidity}%` : "-"}
                    </div>
                  </>
                ) : (
                  <div className="text-gray-300 text-sm py-2">수신 없음</div>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* ── 분무수경 구역 현황 (분무수경 탭과 동일 데이터) ── */}
      <div>
        <div className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          💧 분무수경 구역 현황 · 분무수경 탭과 동일
          {runningZones.length > 0 && (
            <span className="ml-2 px-1.5 py-0.5 bg-green-100 text-green-700 rounded text-xs normal-case">
              {runningZones.length}개 작동 중
            </span>
          )}
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
          {mistZones.length === 0 ? (
            <div className="col-span-full bg-white rounded-xl p-4 text-center text-gray-400 text-sm shadow-sm border border-gray-100">
              분무수경 탭에서 구역을 설정해주세요
            </div>
          ) : (
            mistZones.map((zone) => (
              <div
                key={zone.id}
                className={`rounded-xl p-3 border text-center transition-colors ${
                  zone.isRunning
                    ? "bg-blue-50 border-blue-200"
                    : zone.mode === "OFF"
                    ? "bg-gray-50 border-gray-100"
                    : "bg-white border-gray-100"
                }`}
              >
                <div className="text-xs font-medium text-gray-600 mb-1 truncate">{zone.name}</div>
                <div className={`text-xs font-semibold ${
                  zone.isRunning ? "text-blue-600" : "text-gray-400"
                }`}>
                  {zone.isRunning ? "🟢 작동 중" : "⚫ 정지"}
                </div>
                <div className={`text-xs mt-0.5 ${
                  zone.mode === "AUTO"   ? "text-purple-500" :
                  zone.mode === "MANUAL" ? "text-orange-400" : "text-gray-300"
                }`}>
                  {zone.mode}
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* ── 장치 통계 카드 ── */}
      <div>
        <div className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          📈 오늘의 통계
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div className="text-xl mb-1">🟢</div>
            <div className="text-2xl font-bold text-green-600">{dashData?.devices.online ?? "-"}</div>
            <div className="text-xs text-gray-500 mt-1">온라인 장치</div>
          </div>
          <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div className="text-xl mb-1">🔴</div>
            <div className="text-2xl font-bold text-red-500">{dashData?.devices.offline ?? "-"}</div>
            <div className="text-xs text-gray-500 mt-1">오프라인 장치</div>
          </div>
          <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div className="text-xl mb-1">💧</div>
            <div className="text-2xl font-bold text-blue-600">
              {dashData ? `${dashData.mist_today.count}회` : "-"}
            </div>
            <div className="text-xs text-gray-500 mt-1">오늘 분무 횟수</div>
          </div>
          <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
            <div className="text-xl mb-1">⏱️</div>
            <div className="text-2xl font-bold text-purple-600">
              {dashData ? `${dashData.mist_today.total_minutes}분` : "-"}
            </div>
            <div className="text-xs text-gray-500 mt-1">오늘 총 가동</div>
          </div>
        </div>
      </div>

      {/* ── 24h 차트 (sensor_data DB - 환경 탭과 동일 소스) ── */}
      <div>
        <div className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          📉 24시간 추이 · sensor_data DB (환경 모니터링 탭과 동일 소스)
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <div className="text-sm font-semibold text-gray-600 mb-3">🌡️ 24시간 평균 온도 (°C)</div>
            <LineChart points={tempPoints} color="#ef4444" />
          </div>
          <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
            <div className="text-sm font-semibold text-gray-600 mb-3">💧 24시간 평균 습도 (%)</div>
            <LineChart points={humPoints} color="#3b82f6" />
          </div>
        </div>
      </div>

      <p className="text-xs text-gray-400 text-center">30초마다 자동 새로고침됩니다</p>
    </div>
  );
}
