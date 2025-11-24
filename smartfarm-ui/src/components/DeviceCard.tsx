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

        {/* iOS 스타일 토글 스위치 (팬, 펌프) */}
        {!device.extra?.supportsPercentage && (
          <button
            onClick={() => onToggle?.(!isOn)}
            className={`
              relative inline-flex h-8 w-14 shrink-0 cursor-pointer rounded-full
              border-2 border-transparent transition-colors duration-200 ease-in-out
              focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2
              ${isOn ? "bg-green-500" : "bg-gray-300"}
            `}
            role="switch"
            aria-checked={isOn}
          >
            <span
              className={`
                pointer-events-none inline-block h-7 w-7 transform rounded-full
                bg-white shadow-lg ring-0 transition duration-200 ease-in-out
                ${isOn ? "translate-x-6" : "translate-x-0"}
              `}
            />
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
