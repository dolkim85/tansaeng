import type { FarmSettings } from "../types";
import { DEVICES } from "../config/devices";

interface SettingsProps {
  farmSettings: FarmSettings;
  setFarmSettings: React.Dispatch<React.SetStateAction<FarmSettings>>;
}

export default function Settings({ farmSettings, setFarmSettings }: SettingsProps) {
  const mqttHost = import.meta.env.VITE_MQTT_HOST || "미설정";
  const mqttPort = import.meta.env.VITE_MQTT_WS_PORT || "미설정";
  const mqttUsername = import.meta.env.VITE_MQTT_USERNAME || "미설정";

  const getDeviceTypeColor = (type: string) => {
    if (type === "fan") return { bg: "#dbeafe", text: "#1e40af" };
    if (type === "vent") return { bg: "#d1fae5", text: "#065f46" };
    if (type === "pump") return { bg: "#f3e8ff", text: "#6b21a8" };
    if (type === "camera") return { bg: "#fed7aa", text: "#c2410c" };
    return { bg: "#f3f4f6", text: "#4b5563" };
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
          }}>⚙️ 설정</h1>
          <p style={{
            color: "rgba(255, 255, 255, 0.8)",
            fontSize: "0.875rem",
            marginTop: "4px",
            margin: 0
          }}>
            MQTT 설정, 디바이스 레지스트리, 농장 기본 정보를 관리합니다
          </p>
        </div>

        {/* MQTT 설정 요약 */}
        <div style={{
          background: "white",
          borderRadius: "16px",
          boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
          padding: "24px",
          marginBottom: "24px"
        }}>
          <h2 style={{
            fontSize: "1.125rem",
            fontWeight: "600",
            color: "#1f2937",
            marginBottom: "16px"
          }}>
            MQTT 연결 설정 (읽기 전용)
          </h2>
          <div style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fit, minmax(250px, 1fr))",
            gap: "16px"
          }}>
            <div>
              <label style={{
                display: "block",
                fontSize: "0.875rem",
                fontWeight: "500",
                color: "#4b5563",
                marginBottom: "4px"
              }}>
                HiveMQ Cloud Host
              </label>
              <div style={{
                padding: "8px 12px",
                background: "#f9fafb",
                border: "1px solid #e5e7eb",
                borderRadius: "8px",
                color: "#374151"
              }}>
                {mqttHost}
              </div>
            </div>
            <div>
              <label style={{
                display: "block",
                fontSize: "0.875rem",
                fontWeight: "500",
                color: "#4b5563",
                marginBottom: "4px"
              }}>
                WebSocket Port
              </label>
              <div style={{
                padding: "8px 12px",
                background: "#f9fafb",
                border: "1px solid #e5e7eb",
                borderRadius: "8px",
                color: "#374151"
              }}>
                {mqttPort}
              </div>
            </div>
            <div>
              <label style={{
                display: "block",
                fontSize: "0.875rem",
                fontWeight: "500",
                color: "#4b5563",
                marginBottom: "4px"
              }}>
                Username
              </label>
              <div style={{
                padding: "8px 12px",
                background: "#f9fafb",
                border: "1px solid #e5e7eb",
                borderRadius: "8px",
                color: "#374151"
              }}>
                {mqttUsername}
              </div>
            </div>
            <div>
              <label style={{
                display: "block",
                fontSize: "0.875rem",
                fontWeight: "500",
                color: "#4b5563",
                marginBottom: "4px"
              }}>
                Password
              </label>
              <div style={{
                padding: "8px 12px",
                background: "#f9fafb",
                border: "1px solid #e5e7eb",
                borderRadius: "8px",
                color: "#374151"
              }}>
                ●●●●●●●●
              </div>
            </div>
          </div>
          <p style={{
            fontSize: "0.875rem",
            color: "#6b7280",
            marginTop: "16px",
            marginBottom: 0
          }}>
            MQTT 설정을 변경하려면 .env 파일을 수정하세요.
          </p>
        </div>

        {/* 디바이스 레지스트리 */}
        <div style={{
          background: "white",
          borderRadius: "16px",
          boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
          padding: "24px",
          marginBottom: "24px"
        }}>
          <h2 style={{
            fontSize: "1.125rem",
            fontWeight: "600",
            color: "#1f2937",
            marginBottom: "16px"
          }}>
            디바이스 레지스트리
          </h2>
          <p style={{
            fontSize: "0.875rem",
            color: "#4b5563",
            marginBottom: "16px"
          }}>
            총 {DEVICES.length}개 장치가 등록되어 있습니다.
          </p>
          <div style={{ overflowX: "auto" }}>
            <table style={{
              width: "100%",
              fontSize: "0.875rem",
              borderCollapse: "collapse"
            }}>
              <thead style={{ background: "#f9fafb" }}>
                <tr>
                  <th style={{
                    padding: "8px 16px",
                    textAlign: "left",
                    color: "#4b5563",
                    fontWeight: "500"
                  }}>
                    ID
                  </th>
                  <th style={{
                    padding: "8px 16px",
                    textAlign: "left",
                    color: "#4b5563",
                    fontWeight: "500"
                  }}>
                    이름
                  </th>
                  <th style={{
                    padding: "8px 16px",
                    textAlign: "left",
                    color: "#4b5563",
                    fontWeight: "500"
                  }}>
                    타입
                  </th>
                  <th style={{
                    padding: "8px 16px",
                    textAlign: "left",
                    color: "#4b5563",
                    fontWeight: "500"
                  }}>
                    ESP32 노드
                  </th>
                  <th style={{
                    padding: "8px 16px",
                    textAlign: "left",
                    color: "#4b5563",
                    fontWeight: "500"
                  }}>
                    Command Topic
                  </th>
                </tr>
              </thead>
              <tbody>
                {DEVICES.map((device, index) => {
                  const typeColor = getDeviceTypeColor(device.type);
                  return (
                    <tr
                      key={device.id}
                      style={{
                        background: index % 2 === 0 ? "white" : "#f9fafb"
                      }}
                    >
                      <td style={{
                        padding: "8px 16px",
                        color: "#374151"
                      }}>{device.id}</td>
                      <td style={{
                        padding: "8px 16px",
                        color: "#374151"
                      }}>{device.name}</td>
                      <td style={{ padding: "8px 16px" }}>
                        <span style={{
                          padding: "2px 8px",
                          borderRadius: "4px",
                          fontSize: "0.75rem",
                          fontWeight: "500",
                          background: typeColor.bg,
                          color: typeColor.text
                        }}>
                          {device.type}
                        </span>
                      </td>
                      <td style={{
                        padding: "8px 16px",
                        color: "#4b5563",
                        fontSize: "0.75rem"
                      }}>
                        {device.esp32Id}
                      </td>
                      <td style={{
                        padding: "8px 16px",
                        color: "#6b7280",
                        fontSize: "0.75rem",
                        fontFamily: "monospace"
                      }}>
                        {device.commandTopic}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p style={{
            fontSize: "0.875rem",
            color: "#6b7280",
            marginTop: "16px",
            marginBottom: 0
          }}>
            디바이스를 추가/수정하려면 src/config/devices.ts 파일을 편집하세요.
          </p>
        </div>

        {/* 농장 기본 정보 */}
        <div style={{
          background: "white",
          borderRadius: "16px",
          boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
          padding: "24px",
          marginBottom: "24px"
        }}>
          <h2 style={{
            fontSize: "1.125rem",
            fontWeight: "600",
            color: "#1f2937",
            marginBottom: "16px"
          }}>
            농장 기본 정보
          </h2>
          <div>
            <div style={{ marginBottom: "16px" }}>
              <label style={{
                display: "block",
                fontSize: "0.875rem",
                fontWeight: "500",
                color: "#374151",
                marginBottom: "4px"
              }}>
                농장 이름
              </label>
              <input
                type="text"
                value={farmSettings.farmName}
                onChange={(e) =>
                  setFarmSettings({ ...farmSettings, farmName: e.target.value })
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
            <div style={{ marginBottom: "16px" }}>
              <label style={{
                display: "block",
                fontSize: "0.875rem",
                fontWeight: "500",
                color: "#374151",
                marginBottom: "4px"
              }}>
                관리자 이름
              </label>
              <input
                type="text"
                value={farmSettings.adminName}
                onChange={(e) =>
                  setFarmSettings({ ...farmSettings, adminName: e.target.value })
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
            <div style={{ marginBottom: "16px" }}>
              <label style={{
                display: "block",
                fontSize: "0.875rem",
                fontWeight: "500",
                color: "#374151",
                marginBottom: "4px"
              }}>
                비고
              </label>
              <textarea
                value={farmSettings.notes}
                onChange={(e) =>
                  setFarmSettings({ ...farmSettings, notes: e.target.value })
                }
                rows={4}
                style={{
                  width: "100%",
                  padding: "8px 12px",
                  border: "1px solid #d1d5db",
                  borderRadius: "8px",
                  fontSize: "1rem",
                  resize: "vertical"
                }}
              />
            </div>
            <button
              style={{
                width: "100%",
                background: "#10b981",
                color: "white",
                fontWeight: "500",
                padding: "8px 16px",
                borderRadius: "8px",
                border: "none",
                cursor: "pointer",
                transition: "background 0.2s"
              }}
              onMouseEnter={(e) => e.currentTarget.style.background = "#059669"}
              onMouseLeave={(e) => e.currentTarget.style.background = "#10b981"}
            >
              저장
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
