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

  const getIcon = () => {
    if (device.type === "fan") return "🌀";
    if (device.type === "vent") return "🪟";
    if (device.type === "pump") return "💧";
    return "⚙️";
  };

  return (
    <div className="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 p-6 border border-gray-100">
      {/* 헤더: 아이콘 + 장치명 + 스위치 */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-4">
          <div className="text-5xl">{getIcon()}</div>
          <div>
            <h3 className="text-xl font-bold text-gray-800">{device.name}</h3>
            <p className="text-sm text-gray-500 mt-1">{device.type === "fan" ? "팬" : device.type === "vent" ? "개폐기" : "펌프"}</p>
          </div>
        </div>

        {/* iOS 스타일 토글 스위치 (팬, 펌프만) */}
        {!device.extra?.supportsPercentage && (
          <div className="flex flex-col items-end gap-2">
            <button
              onClick={() => onToggle?.(!isOn)}
              className={`
                relative inline-flex h-10 w-20 shrink-0 cursor-pointer rounded-full
                border-2 border-transparent transition-colors duration-300 ease-in-out
                focus:outline-none focus:ring-4 focus:ring-offset-2
                ${isOn
                  ? "bg-green-500 focus:ring-green-300"
                  : "bg-gray-300 focus:ring-gray-200"
                }
              `}
              role="switch"
              aria-checked={isOn}
              aria-label={`${device.name} ${isOn ? "켜짐" : "꺼짐"}`}
            >
              <span
                className={`
                  pointer-events-none inline-block h-9 w-9 transform rounded-full
                  bg-white shadow-lg ring-0 transition-transform duration-300 ease-in-out
                  flex items-center justify-center
                  ${isOn ? "translate-x-10" : "translate-x-0"}
                `}
              >
                {isOn ? (
                  <svg className="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                ) : (
                  <svg className="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                  </svg>
                )}
              </span>
            </button>
            <span className={`text-sm font-semibold ${isOn ? "text-green-600" : "text-gray-500"}`}>
              {isOn ? "ON" : "OFF"}
            </span>
          </div>
        )}
      </div>

      {/* 개폐기 슬라이더 (개폐기만) */}
      {device.extra?.supportsPercentage && (
        <div className="space-y-4 mb-6">
          <div className="flex items-center justify-between">
            <span className="text-sm font-medium text-gray-600">개폐 정도</span>
            <span className="text-3xl font-bold text-green-600">{percentage ?? 0}%</span>
          </div>
          <div className="relative">
            <input
              type="range"
              min="0"
              max="100"
              value={percentage ?? 0}
              onChange={(e) => onPercentageChange?.(Number(e.target.value))}
              className="w-full h-4 bg-gray-200 rounded-full appearance-none cursor-pointer
                [&::-webkit-slider-thumb]:appearance-none
                [&::-webkit-slider-thumb]:w-6
                [&::-webkit-slider-thumb]:h-6
                [&::-webkit-slider-thumb]:rounded-full
                [&::-webkit-slider-thumb]:bg-green-500
                [&::-webkit-slider-thumb]:cursor-pointer
                [&::-webkit-slider-thumb]:shadow-lg
                [&::-webkit-slider-thumb]:hover:bg-green-600
                [&::-moz-range-thumb]:w-6
                [&::-moz-range-thumb]:h-6
                [&::-moz-range-thumb]:rounded-full
                [&::-moz-range-thumb]:bg-green-500
                [&::-moz-range-thumb]:cursor-pointer
                [&::-moz-range-thumb]:border-0
                [&::-moz-range-thumb]:shadow-lg
                [&::-moz-range-thumb]:hover:bg-green-600"
              style={{
                background: `linear-gradient(to right, #10b981 0%, #10b981 ${percentage ?? 0}%, #e5e7eb ${percentage ?? 0}%, #e5e7eb 100%)`
              }}
            />
          </div>
          <div className="flex justify-between text-xs text-gray-500">
            <span>닫힘</span>
            <span>열림</span>
          </div>
        </div>
      )}

      {/* 하단: 상태 + 최종 작동 시간 */}
      <div className="flex items-center justify-between pt-4 border-t border-gray-100">
        <div className="flex items-center gap-2">
          <div className={`w-3 h-3 rounded-full ${isOn ? "bg-green-500 animate-pulse" : "bg-gray-400"}`}></div>
          <span className={`text-sm font-semibold ${isOn ? "text-green-700" : "text-gray-600"}`}>
            {isOn ? "작동 중" : "정지"}
          </span>
        </div>
        <div className="text-xs text-gray-400">
          {lastSavedAt ? (
            new Date(lastSavedAt).toLocaleString("ko-KR", {
              month: "short",
              day: "numeric",
              hour: "2-digit",
              minute: "2-digit"
            })
          ) : (
            "—"
          )}
        </div>
      </div>
    </div>
  );
}
