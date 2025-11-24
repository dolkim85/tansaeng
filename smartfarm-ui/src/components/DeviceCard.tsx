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
    <div className="bg-white rounded-lg shadow-card hover:shadow-card-hover transition-all duration-200 p-3 border border-gray-200">
      {/* 헤더: 아이콘 + 장치명 + 스위치 */}
      <div className="flex items-center justify-between mb-2">
        <div className="flex items-center gap-1.5">
          <div className="text-2xl">{getIcon()}</div>
          <div>
            <h3 className="text-sm font-bold text-gray-800 m-0">
              {device.name}
            </h3>
            <p className="text-2xs text-gray-500 m-0">
              {device.type === "fan" ? "팬" : device.type === "vent" ? "개폐기" : "펌프"}
            </p>
          </div>
        </div>

        {/* iOS 스타일 토글 스위치 (팬, 펌프만) */}
        {!device.extra?.supportsPercentage && (
          <div className="flex flex-col items-end gap-1">
            <button
              onClick={() => onToggle?.(!isOn)}
              className={`
                relative inline-flex h-9 w-18 cursor-pointer rounded-full
                border-2 border-transparent transition-all duration-300
                ${isOn ? 'bg-farm-500' : 'bg-gray-300'}
              `}
              role="switch"
              aria-checked={isOn}
            >
              <span className={`
                flex items-center justify-center h-8 w-8 rounded-full
                bg-white shadow-md transition-transform duration-300
                ${isOn ? 'translate-x-9' : 'translate-x-0'}
              `}>
                {isOn ? (
                  <svg className="w-4 h-4 text-farm-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                ) : (
                  <svg className="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                  </svg>
                )}
              </span>
            </button>
            <span className={`text-xs font-semibold ${isOn ? 'text-farm-500' : 'text-gray-500'}`}>
              {isOn ? "ON" : "OFF"}
            </span>
          </div>
        )}
      </div>

      {/* 개폐기 슬라이더 (개폐기만) */}
      {device.extra?.supportsPercentage && (
        <div className="mb-3">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-medium text-gray-600">개폐 정도</span>
            <span className="text-2xl font-bold text-farm-500">{percentage ?? 0}%</span>
          </div>
          <input
            type="range"
            min="0"
            max="100"
            value={percentage ?? 0}
            onChange={(e) => onPercentageChange?.(Number(e.target.value))}
            className="slider-thumb w-full h-2 rounded-full appearance-none cursor-pointer"
            style={{
              background: `linear-gradient(to right, #10b981 0%, #10b981 ${percentage ?? 0}%, #e5e7eb ${percentage ?? 0}%, #e5e7eb 100%)`
            }}
          />
          <div className="flex justify-between text-xs text-gray-500 mt-1">
            <span>닫힘</span>
            <span>열림</span>
          </div>
        </div>
      )}

      {/* 하단: 상태 + 최종 작동 시간 */}
      <div className="flex items-center justify-between pt-2 border-t border-gray-100">
        <div className="flex items-center gap-1.5">
          <div className={`
            w-1.5 h-1.5 rounded-full
            ${isOn ? 'bg-farm-500 animate-pulse' : 'bg-gray-400'}
          `}></div>
          <span className={`
            text-2xs font-semibold
            ${isOn ? 'text-farm-700' : 'text-gray-600'}
          `}>
            {isOn ? "작동 중" : "정지"}
          </span>
        </div>
        <div className="text-2xs text-gray-400">
          {lastSavedAt
            ? new Date(lastSavedAt).toLocaleString("ko-KR", {
                month: "short",
                day: "numeric",
                hour: "2-digit",
                minute: "2-digit"
              })
            : "—"}
        </div>
      </div>
    </div>
  );
}
