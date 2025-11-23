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
    <div className="rounded-xl shadow-md p-6 bg-white text-center space-y-4 hover:shadow-lg transition-shadow">
      {/* 아이콘 */}
      <div className="text-4xl">{getIcon()}</div>

      {/* 장치명 */}
      <div className="text-lg font-semibold text-gray-800">{device.name}</div>

      {/* 현재 상태 */}
      <div className={`text-sm font-semibold ${isOn ? "text-green-600" : "text-gray-500"}`}>
        {isOn ? "ON" : "OFF"}
      </div>

      {/* 컨트롤 영역 */}
      {device.extra?.supportsPercentage ? (
        // 슬라이더 (개폐기)
        <div className="space-y-3">
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
      ) : (
        // 토글 스위치 (팬, 펌프)
        <div className="flex items-center justify-center">
          <button
            onClick={() => onToggle?.(!isOn)}
            className={`
              relative w-28 h-14 rounded-full transition-all duration-300
              focus:outline-none focus:ring-2 focus:ring-offset-2
              ${isOn
                ? "bg-green-600 focus:ring-green-500"
                : "bg-gray-400 focus:ring-gray-400"}
            `}
          >
            {/* ON/OFF 텍스트 */}
            <div className="absolute inset-0 flex items-center justify-between px-3">
              <span className={`font-bold text-xs transition-opacity duration-300 ${isOn ? "text-white opacity-100" : "text-white opacity-40"}`}>
                ON
              </span>
              <span className={`font-bold text-xs transition-opacity duration-300 ${!isOn ? "text-white opacity-100" : "text-white opacity-40"}`}>
                OFF
              </span>
            </div>

            {/* 슬라이더 노브 */}
            <span
              className={`
                absolute top-1 w-12 h-12 bg-white rounded-full shadow-lg
                transition-all duration-300 ease-out
                flex items-center justify-center
                ${isOn ? "left-[3.5rem]" : "left-1"}
              `}
            >
              {isOn ? (
                <svg className="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" />
                </svg>
              ) : (
                <svg className="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                  <circle cx="10" cy="10" r="6" opacity="0.5"/>
                </svg>
              )}
            </span>
          </button>
        </div>
      )}

      {/* 마지막 작동 시간 */}
      <div className="text-xs text-gray-400 pt-2 border-t border-gray-100">
        {lastSavedAt ? (
          <>마지막 작동: {new Date(lastSavedAt).toLocaleString("ko-KR", {
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
  );
}
