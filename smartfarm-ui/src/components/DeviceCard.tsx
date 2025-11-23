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
    <div className="bg-white rounded-2xl shadow-md p-4 hover:shadow-lg transition-shadow">
      {/* 상단: 이름 + 상태 */}
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-800">{device.name}</h3>
        <span
          className={`
            px-3 py-1 rounded-full text-sm font-medium
            ${isOn ? "bg-green-100 text-green-700" : "bg-gray-100 text-gray-600"}
          `}
        >
          {isOn ? "ON" : "OFF"}
        </span>
      </div>

      {/* 중앙: 토글 스위치 또는 슬라이더 */}
      {device.extra?.supportsPercentage ? (
        // 슬라이더 (개폐기)
        <div className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm text-gray-600">닫힘 (0%)</span>
            <span className="text-2xl font-bold text-emerald-600">
              {percentage ?? 0}%
            </span>
            <span className="text-sm text-gray-600">열림 (100%)</span>
          </div>
          <input
            type="range"
            min="0"
            max="100"
            value={percentage ?? 0}
            onChange={(e) => onPercentageChange?.(Number(e.target.value))}
            className="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer accent-emerald-600"
          />
        </div>
      ) : (
        // 토글 스위치 (팬, 펌프)
        <div className="flex items-center justify-center mb-4">
          <button
            onClick={() => onToggle?.(!isOn)}
            className={`
              relative w-20 h-10 rounded-full transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2
              ${isOn ? "bg-emerald-600" : "bg-gray-300"}
            `}
          >
            <span
              className={`
                absolute top-1 left-1 w-8 h-8 bg-white rounded-full shadow-md transition-transform duration-300
                ${isOn ? "translate-x-10" : "translate-x-0"}
              `}
            />
          </button>
        </div>
      )}

      {/* 하단: 전원 라벨 + 마지막 저장 시간 */}
      <div className="flex items-center justify-between text-sm">
        <span className="text-gray-600">
          {device.extra?.supportsPercentage ? "개방도" : "전원"}
        </span>
        <span className="text-gray-500">
          마지막 저장: {lastSavedAt ? new Date(lastSavedAt).toLocaleString("ko-KR") : "-"}
        </span>
      </div>
    </div>
  );
}
