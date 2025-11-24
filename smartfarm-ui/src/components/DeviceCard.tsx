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
    <div
      className="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 p-6 border border-gray-100"
      style={{
        backgroundColor: '#ffffff',
        borderRadius: '0.75rem',
        boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
        padding: '1.5rem',
        border: '1px solid #f3f4f6',
        display: 'block'
      }}
    >
      {/* 헤더: 아이콘 + 장치명 + 스위치 */}
      <div
        className="flex items-center justify-between mb-6"
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          marginBottom: '1.5rem'
        }}
      >
        <div
          className="flex items-center gap-4"
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '1rem'
          }}
        >
          <div
            className="text-5xl"
            style={{ fontSize: '3rem', lineHeight: 1 }}
          >
            {getIcon()}
          </div>
          <div>
            <h3
              className="text-xl font-bold text-gray-800"
              style={{
                fontSize: '1.25rem',
                fontWeight: '700',
                color: '#1f2937',
                margin: 0
              }}
            >
              {device.name}
            </h3>
            <p
              className="text-sm text-gray-500 mt-1"
              style={{
                fontSize: '0.875rem',
                color: '#6b7280',
                marginTop: '0.25rem'
              }}
            >
              {device.type === "fan" ? "팬" : device.type === "vent" ? "개폐기" : "펌프"}
            </p>
          </div>
        </div>

        {/* iOS 스타일 토글 스위치 (팬, 펌프만) */}
        {!device.extra?.supportsPercentage && (
          <div
            className="flex flex-col items-end gap-2"
            style={{
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'flex-end',
              gap: '0.5rem'
            }}
          >
            <button
              onClick={() => onToggle?.(!isOn)}
              className={`relative inline-flex h-10 w-20 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-300 ease-in-out focus:outline-none focus:ring-4 focus:ring-offset-2 ${isOn ? "bg-green-500 focus:ring-green-300" : "bg-gray-300 focus:ring-gray-200"}`}
              style={{
                position: 'relative',
                display: 'inline-flex',
                height: '2.5rem',
                width: '5rem',
                flexShrink: 0,
                cursor: 'pointer',
                borderRadius: '9999px',
                border: '2px solid transparent',
                backgroundColor: isOn ? '#10b981' : '#d1d5db',
                transition: 'all 0.3s ease-in-out',
                outline: 'none'
              }}
              role="switch"
              aria-checked={isOn}
              aria-label={`${device.name} ${isOn ? "켜짐" : "꺼짐"}`}
            >
              <span
                className={`pointer-events-none inline-block h-9 w-9 transform rounded-full bg-white shadow-lg ring-0 transition-transform duration-300 ease-in-out flex items-center justify-center ${isOn ? "translate-x-10" : "translate-x-0"}`}
                style={{
                  pointerEvents: 'none',
                  display: 'inline-flex',
                  height: '2.25rem',
                  width: '2.25rem',
                  transform: isOn ? 'translateX(2.5rem)' : 'translateX(0)',
                  borderRadius: '9999px',
                  backgroundColor: '#ffffff',
                  boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                  transition: 'transform 0.3s ease-in-out',
                  alignItems: 'center',
                  justifyContent: 'center'
                }}
              >
                {isOn ? (
                  <svg className="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20" style={{ width: '1.25rem', height: '1.25rem', color: '#059669' }}>
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                ) : (
                  <svg className="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20" style={{ width: '1.25rem', height: '1.25rem', color: '#9ca3af' }}>
                    <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                  </svg>
                )}
              </span>
            </button>
            <span
              className={`text-sm font-semibold ${isOn ? "text-green-600" : "text-gray-500"}`}
              style={{
                fontSize: '0.875rem',
                fontWeight: '600',
                color: isOn ? '#059669' : '#6b7280'
              }}
            >
              {isOn ? "ON" : "OFF"}
            </span>
          </div>
        )}
      </div>

      {/* 개폐기 슬라이더 (개폐기만) */}
      {device.extra?.supportsPercentage && (
        <div
          className="space-y-4 mb-6"
          style={{
            marginBottom: '1.5rem'
          }}
        >
          <div
            className="flex items-center justify-between"
            style={{
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'space-between'
            }}
          >
            <span
              className="text-sm font-medium text-gray-600"
              style={{
                fontSize: '0.875rem',
                fontWeight: '500',
                color: '#4b5563'
              }}
            >
              개폐 정도
            </span>
            <span
              className="text-3xl font-bold text-green-600"
              style={{
                fontSize: '1.875rem',
                fontWeight: '700',
                color: '#059669'
              }}
            >
              {percentage ?? 0}%
            </span>
          </div>
          <div className="relative">
            <input
              type="range"
              min="0"
              max="100"
              value={percentage ?? 0}
              onChange={(e) => onPercentageChange?.(Number(e.target.value))}
              style={{
                width: '100%',
                height: '1rem',
                borderRadius: '9999px',
                appearance: 'none',
                cursor: 'pointer',
                background: `linear-gradient(to right, #10b981 0%, #10b981 ${percentage ?? 0}%, #e5e7eb ${percentage ?? 0}%, #e5e7eb 100%)`
              }}
            />
          </div>
          <div
            className="flex justify-between text-xs text-gray-500"
            style={{
              display: 'flex',
              justifyContent: 'space-between',
              fontSize: '0.75rem',
              color: '#6b7280'
            }}
          >
            <span>닫힘</span>
            <span>열림</span>
          </div>
        </div>
      )}

      {/* 하단: 상태 + 최종 작동 시간 */}
      <div
        className="flex items-center justify-between pt-4 border-t border-gray-100"
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          paddingTop: '1rem',
          borderTop: '1px solid #f3f4f6'
        }}
      >
        <div
          className="flex items-center gap-2"
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '0.5rem'
          }}
        >
          <div
            className={`w-3 h-3 rounded-full ${isOn ? "bg-green-500 animate-pulse" : "bg-gray-400"}`}
            style={{
              width: '0.75rem',
              height: '0.75rem',
              borderRadius: '9999px',
              backgroundColor: isOn ? '#10b981' : '#9ca3af',
              animation: isOn ? 'pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite' : 'none'
            }}
          ></div>
          <span
            className={`text-sm font-semibold ${isOn ? "text-green-700" : "text-gray-600"}`}
            style={{
              fontSize: '0.875rem',
              fontWeight: '600',
              color: isOn ? '#15803d' : '#4b5563'
            }}
          >
            {isOn ? "작동 중" : "정지"}
          </span>
        </div>
        <div
          className="text-xs text-gray-400"
          style={{
            fontSize: '0.75rem',
            color: '#9ca3af'
          }}
        >
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
