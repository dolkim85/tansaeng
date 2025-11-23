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
      rounded-xl shadow-md p-4
      transition-all duration-300 hover:shadow-lg
      ${isOn
        ? "bg-white border-2 border-green-600"
        : "bg-white border border-gray-300"}
    `}>
      {/* 상단: 이름 + 상태 배지 */}
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-base font-bold text-gray-800">{device.name}</h3>
        <div className={`
          flex items-center gap-1.5 px-3 py-1 rounded-full font-semibold text-xs
          ${isOn
            ? "bg-green-600 text-white"
            : "bg-gray-400 text-white"}
        `}>
          <span className={`w-1.5 h-1.5 rounded-full ${isOn ? "bg-white animate-pulse" : "bg-gray-200"}`}></span>
          {isOn ? "ON" : "OFF"}
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
        // 토글 스위치 (팬, 펌프) - 컴팩트하고 실용적인 디자인
        <div className="flex items-center justify-center mb-4">
          <button
            onClick={() => onToggle?.(!isOn)}
            className={`
              relative w-28 h-14 rounded-full transition-all duration-300
              focus:outline-none focus:ring-2 focus:ring-offset-2
              cursor-pointer
              ${isOn
                ? "bg-green-600 focus:ring-green-500"
                : "bg-gray-400 focus:ring-gray-400"}
            `}
          >
            {/* ON/OFF 텍스트 */}
            <div className="absolute inset-0 flex items-center justify-between px-3">
              <span className={`
                font-bold text-xs transition-opacity duration-300
                ${isOn ? "text-white opacity-100" : "text-white opacity-40"}
              `}>
                ON
              </span>
              <span className={`
                font-bold text-xs transition-opacity duration-300
                ${!isOn ? "text-white opacity-100" : "text-white opacity-40"}
              `}>
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

      {/* 하단: 마지막 저장 시간 */}
      <div className="text-center text-xs text-gray-500 pt-3 border-t border-gray-200">
        {lastSavedAt ? (
          <span>{new Date(lastSavedAt).toLocaleString("ko-KR", {
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit"
          })}</span>
        ) : (
          <span className="text-gray-400">-</span>
        )}
      </div>
    </div>
  );
}
