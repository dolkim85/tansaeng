/**
 * 분무수경 가동 로그 탭
 * 날짜/구역 필터 조회 및 CSV 다운로드
 */

import { useEffect, useState } from "react";

interface MistLog {
  id: number;
  zone_id: string;
  zone_name: string;
  event_type: "start" | "stop";
  mode: string;
  created_at: string;
}

interface Zone {
  zone_id: string;
  zone_name: string;
}

interface MistLogsData {
  date: string;
  logs: MistLog[];
  summary: { start_count: number; total_minutes: number };
  zones: Zone[];
}

const formatLocalDate = (date: Date): string => {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
};

export default function MistLogs() {
  const [selectedDate, setSelectedDate] = useState(formatLocalDate(new Date()));
  const [selectedZone, setSelectedZone] = useState("");
  const [data, setData] = useState<MistLogsData | null>(null);
  const [loading, setLoading] = useState(false);

  const fetchLogs = () => {
    setLoading(true);
    const params = new URLSearchParams({ date: selectedDate });
    if (selectedZone) params.append("zone_id", selectedZone);

    fetch(`/api/smartfarm/get_mist_logs.php?${params}`)
      .then((r) => r.json())
      .then((d) => {
        if (d.success) setData(d);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  // 날짜/구역 변경 시 자동 조회
  useEffect(() => {
    fetchLogs();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedDate, selectedZone]);

  const downloadCSV = () => {
    if (!data) return;
    const header = ["시간", "구역ID", "구역명", "이벤트", "모드"];
    const rows = data.logs.map((l) => [
      l.created_at.substring(11, 19),
      l.zone_id,
      l.zone_name,
      l.event_type === "start" ? "시작" : "정지",
      l.mode,
    ]);
    const csv =
      "\uFEFF" +
      [header, ...rows].map((r) => r.map((c) => `"${c}"`).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `mist_logs_${selectedDate}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const zones = data?.zones ?? [];
  const logs = data?.logs ?? [];
  const summary = data?.summary ?? { start_count: 0, total_minutes: 0 };

  return (
    <div className="p-4 max-w-screen-xl mx-auto">
      {/* 헤더 */}
      <div className="flex items-center justify-between mb-4">
        <h2 className="text-lg font-bold text-gray-800">💧 분무 가동 로그</h2>
        <button
          onClick={downloadCSV}
          disabled={logs.length === 0}
          className="text-xs px-3 py-1.5 bg-green-500 hover:bg-green-600 disabled:bg-gray-300 text-white rounded-lg transition-colors"
        >
          📥 CSV 다운로드
        </button>
      </div>

      {/* 필터 */}
      <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100 mb-4">
        <div className="flex flex-wrap items-center gap-3">
          <div className="flex items-center gap-2">
            <label className="text-sm font-medium text-gray-600">날짜</label>
            <input
              type="date"
              value={selectedDate}
              max={formatLocalDate(new Date())}
              onChange={(e) => setSelectedDate(e.target.value)}
              className="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-blue-400"
            />
          </div>
          <div className="flex items-center gap-2">
            <label className="text-sm font-medium text-gray-600">구역</label>
            <select
              value={selectedZone}
              onChange={(e) => setSelectedZone(e.target.value)}
              className="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-blue-400"
            >
              <option value="">전체</option>
              {zones.map((z) => (
                <option key={z.zone_id} value={z.zone_id}>
                  {z.zone_name || z.zone_id}
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* 요약 */}
      <div className="grid grid-cols-3 gap-3 mb-4">
        <div className="bg-blue-50 rounded-xl p-3 text-center border border-blue-100">
          <div className="text-xl font-bold text-blue-700">{summary.start_count}회</div>
          <div className="text-xs text-blue-500 mt-0.5">총 가동 횟수</div>
        </div>
        <div className="bg-purple-50 rounded-xl p-3 text-center border border-purple-100">
          <div className="text-xl font-bold text-purple-700">{summary.total_minutes}분</div>
          <div className="text-xs text-purple-500 mt-0.5">총 가동 시간</div>
        </div>
        <div className="bg-gray-50 rounded-xl p-3 text-center border border-gray-100">
          <div className="text-xl font-bold text-gray-700">{logs.length}건</div>
          <div className="text-xs text-gray-500 mt-0.5">기록 수</div>
        </div>
      </div>

      {/* 로그 테이블 */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center h-32 text-gray-400 text-sm">
            로딩 중...
          </div>
        ) : logs.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-32 text-gray-400">
            <div className="text-3xl mb-2">📭</div>
            <div className="text-sm">해당 날짜에 분무 로그가 없습니다</div>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 border-b border-gray-100">
                  <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500">시간</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500">구역</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500">이벤트</th>
                  <th className="text-left px-4 py-3 text-xs font-semibold text-gray-500">모드</th>
                </tr>
              </thead>
              <tbody>
                {logs.map((log) => (
                  <tr
                    key={log.id}
                    className="border-b border-gray-50 hover:bg-gray-50 transition-colors"
                  >
                    <td className="px-4 py-3 font-mono text-gray-700">
                      {log.created_at.substring(11, 19)}
                    </td>
                    <td className="px-4 py-3 text-gray-700">
                      {log.zone_name || log.zone_id}
                    </td>
                    <td className="px-4 py-3">
                      {log.event_type === "start" ? (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                          🟢 시작
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">
                          🔴 정지
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${
                          log.mode === "AUTO"
                            ? "bg-blue-100 text-blue-700"
                            : "bg-yellow-100 text-yellow-700"
                        }`}
                      >
                        {log.mode}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
