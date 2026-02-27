/**
 * 스마트팜 대시보드 탭
 * 장치 현황, 오늘 분무 통계, 24시간 온습도 차트
 */

import { useCallback, useEffect, useRef, useState } from "react";

interface DashboardData {
  devices: { total: number; online: number; offline: number };
  mist_today: { count: number; total_minutes: number };
  chart_24h: Array<{
    hour_label: string;
    avg_temp: string | null;
    avg_humidity: string | null;
  }>;
}

interface ChartPoint {
  label: string;
  value: number;
}

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

    const vals = points.map((p) => p.value);
    const maxV = Math.max(...vals);
    const minV = Math.min(...vals);
    const range = maxV - minV || 1;

    const xOf = (i: number) =>
      pl + (i / (points.length - 1)) * (W - pl - pr);
    const yOf = (v: number) =>
      pt + (1 - (v - minV) / range) * (H - pt - pb);

    // 격자선 + Y 레이블
    [0, 0.5, 1].forEach((ratio) => {
      const v = minV + ratio * range;
      const y = yOf(v);
      ctx.strokeStyle = "#e5e7eb";
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(pl, y);
      ctx.lineTo(W - pr, y);
      ctx.stroke();
      ctx.fillStyle = "#9ca3af";
      ctx.font = "10px sans-serif";
      ctx.textAlign = "right";
      ctx.fillText(v.toFixed(1), pl - 4, y + 4);
    });

    // X 레이블 (최대 5개)
    const step = Math.ceil(points.length / 5);
    ctx.fillStyle = "#9ca3af";
    ctx.font = "10px sans-serif";
    ctx.textAlign = "center";
    points.forEach((p, i) => {
      if (i % step === 0 || i === points.length - 1) {
        ctx.fillText(p.label, xOf(i), H - pb + 12);
      }
    });

    // 라인
    ctx.strokeStyle = color;
    ctx.lineWidth = 2;
    ctx.lineJoin = "round";
    ctx.beginPath();
    points.forEach((p, i) => {
      const x = xOf(i), y = yOf(p.value);
      i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.stroke();

    // 점
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

export default function Dashboard() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [lastUpdated, setLastUpdated] = useState<string>("");

  const load = useCallback(() => {
    fetch("/api/smartfarm/get_admin_dashboard.php")
      .then((r) => r.json())
      .then((d) => {
        if (d.success) {
          setData(d);
          const now = new Date();
          setLastUpdated(
            `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}:${String(now.getSeconds()).padStart(2, "0")}`
          );
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    load();
    const timer = setInterval(load, 60000);
    return () => clearInterval(timer);
  }, [load]);

  const tempPoints: ChartPoint[] =
    data?.chart_24h
      .filter((r) => r.avg_temp !== null)
      .map((r) => ({ label: r.hour_label, value: parseFloat(r.avg_temp!) })) ??
    [];

  const humPoints: ChartPoint[] =
    data?.chart_24h
      .filter((r) => r.avg_humidity !== null)
      .map((r) => ({
        label: r.hour_label,
        value: parseFloat(r.avg_humidity!),
      })) ?? [];

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="text-gray-400 text-sm">데이터 로딩 중...</div>
      </div>
    );
  }

  return (
    <div className="p-4 max-w-screen-xl mx-auto">
      {/* 헤더 */}
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-bold text-gray-800">🏭 스마트팜 대시보드</h2>
        <div className="flex items-center gap-2">
          {lastUpdated && (
            <span className="text-xs text-gray-400">업데이트: {lastUpdated}</span>
          )}
          <button
            onClick={load}
            className="text-xs px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg transition-colors"
          >
            🔄 새로고침
          </button>
        </div>
      </div>

      {/* 통계 카드 */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
          <div className="text-2xl mb-1">🟢</div>
          <div className="text-2xl font-bold text-green-600">
            {data?.devices.online ?? "-"}
          </div>
          <div className="text-xs text-gray-500 mt-1">온라인 장치</div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
          <div className="text-2xl mb-1">🔴</div>
          <div className="text-2xl font-bold text-red-500">
            {data?.devices.offline ?? "-"}
          </div>
          <div className="text-xs text-gray-500 mt-1">오프라인 장치</div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
          <div className="text-2xl mb-1">💧</div>
          <div className="text-2xl font-bold text-blue-600">
            {data ? `${data.mist_today.count}회` : "-"}
          </div>
          <div className="text-xs text-gray-500 mt-1">오늘 분무 횟수</div>
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center">
          <div className="text-2xl mb-1">⏱️</div>
          <div className="text-2xl font-bold text-purple-600">
            {data ? `${data.mist_today.total_minutes}분` : "-"}
          </div>
          <div className="text-xs text-gray-500 mt-1">오늘 총 가동</div>
        </div>
      </div>

      {/* 차트 */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="text-sm font-semibold text-gray-600 mb-3">
            🌡️ 24시간 평균 온도 (°C)
          </div>
          <LineChart points={tempPoints} color="#ef4444" />
        </div>
        <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
          <div className="text-sm font-semibold text-gray-600 mb-3">
            💧 24시간 평균 습도 (%)
          </div>
          <LineChart points={humPoints} color="#3b82f6" />
        </div>
      </div>

      <p className="text-xs text-gray-400 text-center mt-4">
        60초마다 자동 새로고침됩니다
      </p>
    </div>
  );
}
