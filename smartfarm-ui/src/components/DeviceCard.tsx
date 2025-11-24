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
    <div style={{
      background: "white",
      borderRadius: "6px",
      boxShadow: "0 1px 2px 0 rgb(0 0 0 / 0.05)",
      transition: "box-shadow 0.2s",
      padding: "10px",
      border: "1px solid #e5e7eb"
    }}>
      {/* 헤더: 아이콘 + 장치명 + 스위치 */}
      <div style={{
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        marginBottom: "8px"
      }}>
        <div style={{
          display: "flex",
          alignItems: "center",
          gap: "6px"
        }}>
          <div style={{ fontSize: "1.5rem" }}>{getIcon()}</div>
          <div>
            <h3 style={{
              fontSize: "0.875rem",
              fontWeight: "700",
              color: "#1f2937",
              margin: "0"
            }}>{device.name}</h3>
            <p style={{
              fontSize: "0.7rem",
              color: "#6b7280",
              margin: "0"
            }}>
              {device.type === "fan" ? "팬" : device.type === "vent" ? "개폐기" : "펌프"}
            </p>
          </div>
        </div>

        {/* iOS 스타일 토글 스위치 (팬, 펌프만) */}
        {!device.extra?.supportsPercentage && (
          <div style={{
            display: "flex",
            flexDirection: "column",
            alignItems: "flex-end",
            gap: "4px"
          }}>
            <button
              onClick={() => onToggle?.(!isOn)}
              style={{
                position: "relative",
                display: "inline-flex",
                height: "36px",
                width: "72px",
                cursor: "pointer",
                borderRadius: "9999px",
                border: "2px solid transparent",
                transition: "all 0.3s",
                background: isOn ? "#10b981" : "#d1d5db"
              }}
              role="switch"
              aria-checked={isOn}
            >
              <span style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                height: "32px",
                width: "32px",
                transform: isOn ? "translateX(36px)" : "translateX(0)",
                borderRadius: "9999px",
                background: "white",
                boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
                transition: "transform 0.3s"
              }}>
                {isOn ? (
                  <svg style={{ width: "16px", height: "16px", color: "#10b981" }} fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                  </svg>
                ) : (
                  <svg style={{ width: "16px", height: "16px", color: "#9ca3af" }} fill="currentColor" viewBox="0 0 20 20">
                    <path fillRule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clipRule="evenodd" />
                  </svg>
                )}
              </span>
            </button>
            <span style={{
              fontSize: "0.75rem",
              fontWeight: "600",
              color: isOn ? "#10b981" : "#6b7280"
            }}>
              {isOn ? "ON" : "OFF"}
            </span>
          </div>
        )}
      </div>

      {/* 개폐기 슬라이더 (개폐기만) */}
      {device.extra?.supportsPercentage && (
        <div style={{ marginBottom: "12px" }}>
          <div style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between",
            marginBottom: "8px"
          }}>
            <span style={{
              fontSize: "0.75rem",
              fontWeight: "500",
              color: "#4b5563"
            }}>개폐 정도</span>
            <span style={{
              fontSize: "1.5rem",
              fontWeight: "700",
              color: "#10b981"
            }}>{percentage ?? 0}%</span>
          </div>
          <input
            type="range"
            min="0"
            max="100"
            value={percentage ?? 0}
            onChange={(e) => onPercentageChange?.(Number(e.target.value))}
            style={{
              width: "100%",
              height: "8px",
              borderRadius: "9999px",
              appearance: "none",
              cursor: "pointer",
              background: `linear-gradient(to right, #10b981 0%, #10b981 ${percentage ?? 0}%, #e5e7eb ${percentage ?? 0}%, #e5e7eb 100%)`
            }}
          />
          <div style={{
            display: "flex",
            justifyContent: "space-between",
            fontSize: "0.75rem",
            color: "#6b7280",
            marginTop: "4px"
          }}>
            <span>닫힘</span>
            <span>열림</span>
          </div>
        </div>
      )}

      {/* 하단: 상태 + 최종 작동 시간 */}
      <div style={{
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        paddingTop: "8px",
        borderTop: "1px solid #f3f4f6"
      }}>
        <div style={{
          display: "flex",
          alignItems: "center",
          gap: "6px"
        }}>
          <div style={{
            width: "6px",
            height: "6px",
            borderRadius: "9999px",
            background: isOn ? "#10b981" : "#9ca3af",
            animation: isOn ? "pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite" : "none"
          }}></div>
          <span style={{
            fontSize: "0.7rem",
            fontWeight: "600",
            color: isOn ? "#15803d" : "#4b5563"
          }}>
            {isOn ? "작동 중" : "정지"}
          </span>
        </div>
        <div style={{
          fontSize: "0.7rem",
          color: "#9ca3af"
        }}>
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
