import type { DeviceConfig } from "../types";

interface DeviceCardProps {
  device: DeviceConfig;
  power: "on" | "off";
  percentage?: number;
  lastSavedAt?: string;
  onToggle?: (isOn: boolean) => void;
  onPercentageChange?: (value: number) => void;
}

export default function DeviceCard({
  device,
  power,
  percentage,
  lastSavedAt,
  onToggle,
  onPercentageChange,
}: DeviceCardProps) {
  const isOn = power === "on";

  return (
    <div className={`
      bg-gradient-to-br rounded-2xl shadow-lg p-6
      transition-all duration-300 hover:shadow-2xl hover:scale-[1.02]
      ${isOn ? "from-emerald-50 to-green-50 border-2 border-emerald-400" : "from-gray-50 to-slate-50 border-2 border-gray-200"}
    `}>
      {/* 상단: 이름 + 상태 배지 */}
      <div className="flex items-center justify-between mb-6">
        <h3 className="text-xl font-bold text-gray-800">{device.name}</h3>
        <div className={`
          flex items-center gap-2 px-4 py-2 rounded-full font-bold text-sm
          ${isOn
            ? "bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg shadow-emerald-300"
            : "bg-gray-300 text-gray-600 shadow-md"}
        `}>
          <span className={`w-2 h-2 rounded-full ${isOn ? "bg-white animate-pulse" : "bg-gray-500"}`}></span>
          {isOn ? "작동중" : "정지"}
        </div>
      </div>

      {/* 중앙: 토글 스위치 또는 슬라이더 */}
      {device.extra?.supportsPercentage ? (
        // 슬라이더 (개폐기) - 더 크고 사용하기 편하게
        <div className="mb-6">
          <div className="flex items-center justify-between mb-3">
            <span className="text-sm font-medium text-gray-600">닫힘</span>
            <div className="flex items-center gap-2">
              <span className="text-4xl font-bold text-emerald-600">
                {percentage ?? 0}
              </span>
              <span className="text-lg font-medium text-gray-500">%</span>
            </div>
            <span className="text-sm font-medium text-gray-600">열림</span>
          </div>
          <div className="relative">
            <input
              type="range"
              min="0"
              max="100"
              value={percentage ?? 0}
              onChange={(e) => onPercentageChange?.(Number(e.target.value))}
              className="w-full h-4 bg-gradient-to-r from-gray-200 via-emerald-200 to-green-400 rounded-full appearance-none cursor-pointer slider-thumb"
              style={{
                background: `linear-gradient(to right, #10b981 0%, #10b981 ${percentage ?? 0}%, #e5e7eb ${percentage ?? 0}%, #e5e7eb 100%)`
              }}
            />
          </div>
        </div>
      ) : (
        // 토글 스위치 (팬, 펌프) - 더 크고 눈에 잘 띄게
        <div className="flex items-center justify-center mb-6">
          <button
            onClick={() => onToggle?.(!isOn)}
            className={`
              relative w-32 h-16 rounded-full transition-all duration-300
              focus:outline-none focus:ring-4 focus:ring-offset-2
              ${isOn
                ? "bg-gradient-to-r from-emerald-500 to-green-600 focus:ring-emerald-300 shadow-lg shadow-emerald-400"
                : "bg-gray-300 focus:ring-gray-300 shadow-md"}
            `}
          >
            <span
              className={`
                absolute top-2 w-12 h-12 bg-white rounded-full shadow-xl
                transition-all duration-300 flex items-center justify-center
                ${isOn ? "left-[4.5rem]" : "left-2"}
              `}
            >
              <span className={`text-2xl ${isOn ? "animate-spin" : ""}`}>
                {isOn ? "⚡" : "○"}
              </span>
            </span>
          </button>
        </div>
      )}

      {/* 하단: 마지막 저장 시간 */}
      <div className="text-center text-sm text-gray-500 pt-4 border-t border-gray-200">
        {lastSavedAt ? (
          <>
            <span className="font-medium">마지막 제어:</span>{" "}
            <span>{new Date(lastSavedAt).toLocaleString("ko-KR", {
              month: "short",
              day: "numeric",
              hour: "2-digit",
              minute: "2-digit"
            })}</span>
          </>
        ) : (
          <span className="text-gray-400">제어 기록 없음</span>
        )}
      </div>
    </div>
  );
}
