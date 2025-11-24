import type { MistZoneConfig, MistMode } from "../types";
import { publishCommand } from "../mqtt/mqttClient";

interface MistControlProps {
  zones: MistZoneConfig[];
  setZones: React.Dispatch<React.SetStateAction<MistZoneConfig[]>>;
}

export default function MistControl({ zones, setZones }: MistControlProps) {
  const updateZone = (zoneId: string, updates: Partial<MistZoneConfig>) => {
    setZones((prev) =>
      prev.map((zone) =>
        zone.id === zoneId ? { ...zone, ...updates } : zone
      )
    );
  };

  const handleSaveZone = (zone: MistZoneConfig) => {
    // 검증
    if (zone.mode === "AUTO") {
      if (!zone.intervalMinutes || !zone.spraySeconds) {
        alert("AUTO 모드에서는 분무 주기와 분무 시간을 입력해야 합니다.");
        return;
      }
      if (zone.startTime && zone.endTime && zone.startTime >= zone.endTime) {
        alert("종료 시간은 시작 시간보다 늦어야 합니다.");
        return;
      }
    }

    // MQTT 명령 발행
    publishCommand(`tansaeng/mist/${zone.id}/config`, {
      mode: zone.mode,
      intervalMinutes: zone.intervalMinutes,
      spraySeconds: zone.spraySeconds,
      startTime: zone.startTime,
      endTime: zone.endTime,
      allowNightOperation: zone.allowNightOperation,
    });

    alert(`${zone.name} 설정이 저장되었습니다.`);
  };

  const handleManualSpray = (zone: MistZoneConfig) => {
    publishCommand(`tansaeng/mist/${zone.id}/manual`, { action: "spray" });
    alert(`${zone.name} 즉시 분무 명령을 전송했습니다.`);
  };

  const getModeColor = (mode: MistMode) => {
    if (mode === "OFF") return { bg: "#f3f4f6", text: "#4b5563" };
    if (mode === "MANUAL") return { bg: "#dbeafe", text: "#1e40af" };
    return { bg: "#d1fae5", text: "#065f46" };
  };

  return (
    <div style={{ background: "#f9fafb" }}>
      <div style={{
        maxWidth: "1200px",
        margin: "0 auto",
        padding: "0 16px"
      }}>
        <div style={{
          background: "linear-gradient(to right, #10b981, #059669)",
          borderRadius: "16px",
          padding: "16px 24px",
          marginBottom: "24px"
        }}>
          <h1 style={{
            color: "white",
            fontWeight: "700",
            fontSize: "1.5rem",
            margin: 0
          }}>💧 분무수경 설정</h1>
          <p style={{
            color: "rgba(255, 255, 255, 0.8)",
            fontSize: "0.875rem",
            marginTop: "4px",
            margin: 0
          }}>각 Zone별 분무 인터벌 및 운전 시간대를 설정합니다</p>
        </div>

        {zones.map((zone) => {
          const modeColor = getModeColor(zone.mode);
          return (
            <div key={zone.id} style={{
              background: "white",
              borderRadius: "16px",
              boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
              padding: "24px",
              marginBottom: "24px"
            }}>
              {/* 상단: Zone 이름 + 현재 모드 */}
              <div style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                marginBottom: "16px"
              }}>
                <h2 style={{
                  fontSize: "1.25rem",
                  fontWeight: "600",
                  color: "#1f2937",
                  margin: 0
                }}>{zone.name}</h2>
                <span style={{
                  padding: "4px 12px",
                  borderRadius: "9999px",
                  fontSize: "0.875rem",
                  fontWeight: "500",
                  background: modeColor.bg,
                  color: modeColor.text
                }}>
                  {zone.mode}
                </span>
              </div>

              {/* 모드 선택 */}
              <div style={{ marginBottom: "16px" }}>
                <label style={{
                  display: "block",
                  fontSize: "0.875rem",
                  fontWeight: "500",
                  color: "#374151",
                  marginBottom: "8px"
                }}>
                  운전 모드
                </label>
                <div style={{ display: "flex", gap: "16px" }}>
                  {(["OFF", "MANUAL", "AUTO"] as MistMode[]).map((mode) => (
                    <label key={mode} style={{
                      display: "flex",
                      alignItems: "center",
                      gap: "8px",
                      cursor: "pointer"
                    }}>
                      <input
                        type="radio"
                        name={`mode-${zone.id}`}
                        checked={zone.mode === mode}
                        onChange={() => updateZone(zone.id, { mode })}
                        style={{
                          width: "16px",
                          height: "16px",
                          accentColor: "#10b981"
                        }}
                      />
                      <span style={{ color: "#374151" }}>{mode}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* MANUAL 모드: 즉시 분무 버튼 */}
              {zone.mode === "MANUAL" && (
                <div style={{ marginBottom: "16px" }}>
                  <button
                    onClick={() => handleManualSpray(zone)}
                    style={{
                      width: "100%",
                      background: "#2563eb",
                      color: "white",
                      fontWeight: "500",
                      padding: "12px 16px",
                      borderRadius: "8px",
                      border: "none",
                      cursor: "pointer",
                      transition: "background 0.2s"
                    }}
                    onMouseEnter={(e) => e.currentTarget.style.background = "#1d4ed8"}
                    onMouseLeave={(e) => e.currentTarget.style.background = "#2563eb"}
                  >
                    즉시 분무 실행
                  </button>
                </div>
              )}

              {/* AUTO 모드: 설정 폼 */}
              {zone.mode === "AUTO" && (
                <div>
                  <div style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
                    gap: "16px",
                    marginBottom: "16px"
                  }}>
                    <div>
                      <label style={{
                        display: "block",
                        fontSize: "0.875rem",
                        fontWeight: "500",
                        color: "#374151",
                        marginBottom: "4px"
                      }}>
                        분무 주기 (분)
                      </label>
                      <input
                        type="number"
                        min="1"
                        value={zone.intervalMinutes ?? ""}
                        onChange={(e) =>
                          updateZone(zone.id, {
                            intervalMinutes: Number(e.target.value) || null,
                          })
                        }
                        placeholder="예: 30"
                        style={{
                          width: "100%",
                          padding: "8px 12px",
                          border: "1px solid #d1d5db",
                          borderRadius: "8px",
                          fontSize: "1rem"
                        }}
                      />
                    </div>
                    <div>
                      <label style={{
                        display: "block",
                        fontSize: "0.875rem",
                        fontWeight: "500",
                        color: "#374151",
                        marginBottom: "4px"
                      }}>
                        분무 시간 (초)
                      </label>
                      <input
                        type="number"
                        min="1"
                        value={zone.spraySeconds ?? ""}
                        onChange={(e) =>
                          updateZone(zone.id, {
                            spraySeconds: Number(e.target.value) || null,
                          })
                        }
                        placeholder="예: 10"
                        style={{
                          width: "100%",
                          padding: "8px 12px",
                          border: "1px solid #d1d5db",
                          borderRadius: "8px",
                          fontSize: "1rem"
                        }}
                      />
                    </div>
                  </div>

                  <div style={{
                    display: "grid",
                    gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
                    gap: "16px",
                    marginBottom: "16px"
                  }}>
                    <div>
                      <label style={{
                        display: "block",
                        fontSize: "0.875rem",
                        fontWeight: "500",
                        color: "#374151",
                        marginBottom: "4px"
                      }}>
                        시작 시간
                      </label>
                      <input
                        type="time"
                        value={zone.startTime}
                        onChange={(e) =>
                          updateZone(zone.id, { startTime: e.target.value })
                        }
                        style={{
                          width: "100%",
                          padding: "8px 12px",
                          border: "1px solid #d1d5db",
                          borderRadius: "8px",
                          fontSize: "1rem"
                        }}
                      />
                    </div>
                    <div>
                      <label style={{
                        display: "block",
                        fontSize: "0.875rem",
                        fontWeight: "500",
                        color: "#374151",
                        marginBottom: "4px"
                      }}>
                        종료 시간
                      </label>
                      <input
                        type="time"
                        value={zone.endTime}
                        onChange={(e) =>
                          updateZone(zone.id, { endTime: e.target.value })
                        }
                        style={{
                          width: "100%",
                          padding: "8px 12px",
                          border: "1px solid #d1d5db",
                          borderRadius: "8px",
                          fontSize: "1rem"
                        }}
                      />
                    </div>
                  </div>

                  <div style={{ marginBottom: "16px" }}>
                    <label style={{
                      display: "flex",
                      alignItems: "center",
                      gap: "8px",
                      cursor: "pointer"
                    }}>
                      <input
                        type="checkbox"
                        checked={zone.allowNightOperation}
                        onChange={(e) =>
                          updateZone(zone.id, {
                            allowNightOperation: e.target.checked,
                          })
                        }
                        style={{
                          width: "16px",
                          height: "16px",
                          accentColor: "#10b981"
                        }}
                      />
                      <span style={{ color: "#374151" }}>야간 운전 허용</span>
                    </label>
                  </div>

                  <button
                    onClick={() => handleSaveZone(zone)}
                    style={{
                      width: "100%",
                      background: "#10b981",
                      color: "white",
                      fontWeight: "500",
                      padding: "12px 16px",
                      borderRadius: "8px",
                      border: "none",
                      cursor: "pointer",
                      transition: "background 0.2s"
                    }}
                    onMouseEnter={(e) => e.currentTarget.style.background = "#059669"}
                    onMouseLeave={(e) => e.currentTarget.style.background = "#10b981"}
                  >
                    설정 저장
                  </button>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
