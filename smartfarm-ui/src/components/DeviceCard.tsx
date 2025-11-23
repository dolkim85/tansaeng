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

  // 장치 타입별 아이콘
  const getIcon = () => {
    if (device.type === "fan") return "🌀";
    if (device.type === "vent") return "🪟";
    if (device.type === "pump") return "💧";
    return "⚙️";
  };

  return (
    <div className="rounded-xl shadow-md p-6 bg-white hover:shadow-lg transition-shadow">
      {/* 상단: 아이콘 + 장치명 + 스위치 */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className="text-4xl">{getIcon()}</div>
          <div className="text-lg font-semibold text-gray-800">{device.name}</div>
        </div>

        {/* 토글 스위치 (팬, 펌프) - 오른쪽 배치 */}
        {!device.extra?.supportsPercentage && (
          <button
            onClick={() => onToggle?.(!isOn)}
            className={`
              relative w-32 h-16 rounded-full transition-all duration-300
              focus:outline-none focus:ring-4 focus:ring-offset-2
              ${isOn
                ? "bg-green-600 focus:ring-green-300"
                : "bg-gray-400 focus:ring-gray-300"}
            `}
          >
            {/* ON/OFF 텍스트 */}
            <div className="absolute inset-0 flex items-center justify-between px-3">
              <span className={`font-bold text-sm transition-opacity duration-300 ${isOn ? "text-white opacity-100" : "text-white opacity-40"}`}>
                ON
              </span>
              <span className={`font-bold text-sm transition-opacity duration-300 ${!isOn ? "text-white opacity-100" : "text-white opacity-40"}`}>
                OFF
              </span>
            </div>

            {/* 슬라이더 노브 */}
            <span
              className={`
                absolute top-2 w-12 h-12 bg-white rounded-full shadow-lg
                transition-all duration-300 ease-out
                flex items-center justify-center
                ${isOn ? "left-[4.5rem]" : "left-2"}
              `}
            >
              {isOn ? (
                <svg className="w-7 h-7 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" />
                </svg>
              ) : (
                <svg className="w-7 h-7 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                  <circle cx="10" cy="10" r="6" opacity="0.5"/>
                </svg>
              )}
            </span>
          </button>
        )}
      </div>

      {/* 개폐기 슬라이더 */}
      {device.extra?.supportsPercentage && (
        <div className="space-y-3 mb-6">
          <div className="flex items-center justify-between text-sm text-gray-600">
            <span>닫힘</span>
            <span className="text-2xl font-bold text-green-600">{percentage ?? 0}%</span>
            <span>열림</span>
          </div>
          <input
            type="range"
            min="0"
            max="100"
            value={percentage ?? 0}
            onChange={(e) => onPercentageChange?.(Number(e.target.value))}
            className="w-full h-3 bg-gray-200 rounded-full appearance-none cursor-pointer slider-thumb"
            style={{
              background: `linear-gradient(to right, #10b981 0%, #10b981 ${percentage ?? 0}%, #e5e7eb ${percentage ?? 0}%, #e5e7eb 100%)`
            }}
          />
        </div>
      )}

      {/* 현재 상태 + 마지막 작동 시간 */}
      <div className="flex items-center justify-between text-sm pt-4 border-t border-gray-100">
        <div className={`font-semibold ${isOn ? "text-green-600" : "text-gray-500"}`}>
          {isOn ? "작동중" : "정지"}
        </div>
        <div className="text-xs text-gray-400">
          {lastSavedAt ? (
            <>{new Date(lastSavedAt).toLocaleString("ko-KR", {
              month: "short",
              day: "numeric",
              hour: "2-digit",
              minute: "2-digit"
            })}</>
          ) : (
            <>-</>
          )}
        </div>
      </div>
    </div>
  );
}
