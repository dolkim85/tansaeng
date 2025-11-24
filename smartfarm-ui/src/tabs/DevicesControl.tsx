import { useState, useEffect } from "react";
import { getDevicesByType } from "../config/devices";
import type { DeviceDesiredState } from "../types";
import DeviceCard from "../components/DeviceCard";
import { publishCommand, onConnectionChange } from "../mqtt/mqttClient";

interface DevicesControlProps {
  deviceState: DeviceDesiredState;
  setDeviceState: React.Dispatch<React.SetStateAction<DeviceDesiredState>>;
}

export default function DevicesControl({ deviceState, setDeviceState }: DevicesControlProps) {
  const [mqttConnected, setMqttConnected] = useState(false);

  const fans = getDevicesByType("fan");
  const vents = getDevicesByType("vent");
  const pumps = getDevicesByType("pump");

  // MQTT 연결 상태 감지
  useEffect(() => {
    const unsubscribe = onConnectionChange((connected) => {
      setMqttConnected(connected);
    });

    return unsubscribe;
  }, []);

  const handleToggle = (deviceId: string, isOn: boolean) => {
    const newState = {
      ...deviceState,
      [deviceId]: {
        ...deviceState[deviceId],
        power: (isOn ? "on" : "off") as "on" | "off",
        lastSavedAt: new Date().toISOString(),
      },
    };
    setDeviceState(newState);

    const device = [...fans, ...vents, ...pumps].find((d) => d.id === deviceId);
    if (device) {
      publishCommand(device.commandTopic, { power: isOn ? "on" : "off" });
    }
  };

  const handlePercentageChange = (deviceId: string, percentage: number) => {
    const newState = {
      ...deviceState,
      [deviceId]: {
        ...deviceState[deviceId],
        targetPercentage: percentage,
        lastSavedAt: new Date().toISOString(),
      },
    };
    setDeviceState(newState);

    const device = vents.find((d) => d.id === deviceId);
    if (device) {
      publishCommand(device.commandTopic, { target: percentage });
    }
  };

  return (
    <div style={{
      background: "#f9fafb"
    }}>
      <div style={{
        maxWidth: "1400px",
        margin: "0 auto",
        padding: "12px"
      }}>
        {/* ESP32 연결 상태 헤더 */}
        <header style={{
          background: "linear-gradient(to right, #10b981, #059669)",
          color: "white",
          padding: "12px 16px",
          borderRadius: "8px",
          marginBottom: "12px"
        }}>
          <div style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between"
          }}>
            <div>
              <h1 style={{
                fontSize: "1.25rem",
                fontWeight: "700",
                margin: "0 0 4px 0"
              }}>⚙️ 장치 제어</h1>
              <p style={{
                fontSize: "0.75rem",
                opacity: "0.8",
                margin: "0"
              }}>
                팬, 개폐기, 펌프 등 장치를 원격으로 제어합니다
              </p>
            </div>
            {/* ESP32 연결 상태 */}
            <div style={{
              display: "flex",
              alignItems: "center",
              gap: "8px",
              background: "rgba(255, 255, 255, 0.2)",
              padding: "6px 12px",
              borderRadius: "6px"
            }}>
              <div style={{
                width: "10px",
                height: "10px",
                borderRadius: "9999px",
                background: mqttConnected ? "#6ee7b7" : "#fca5a5",
                animation: mqttConnected ? "pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite" : "none"
              }}></div>
              <span style={{
                fontSize: "0.75rem",
                fontWeight: "500"
              }}>
                {mqttConnected ? 'ESP32 연결됨' : 'ESP32 연결 끊김'}
              </span>
            </div>
          </div>
        </header>

        {/* 팬 제어 섹션 */}
        <section style={{ marginBottom: "12px" }}>
          <header style={{
            background: "linear-gradient(to right, #10b981, #059669)",
            color: "white",
            padding: "10px 16px",
            borderRadius: "8px 8px 0 0",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between"
          }}>
            <h2 style={{
              fontSize: "1rem",
              fontWeight: "600",
              margin: "0",
              display: "flex",
              alignItems: "center",
              gap: "6px"
            }}>
              🌀 팬 제어
            </h2>
            <span style={{
              fontSize: "0.75rem",
              opacity: "0.8"
            }}>총 {fans.length}개</span>
          </header>
          <div style={{
            background: "white",
            boxShadow: "0 1px 2px 0 rgb(0 0 0 / 0.05)",
            borderRadius: "0 0 8px 8px",
            padding: "12px"
          }}>
            <div style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))",
              gap: "12px"
            }}>
              {fans.map((fan) => (
                <DeviceCard
                  key={fan.id}
                  device={fan}
                  power={deviceState[fan.id]?.power ?? "off"}
                  lastSavedAt={deviceState[fan.id]?.lastSavedAt}
                  onToggle={(isOn) => handleToggle(fan.id, isOn)}
                />
              ))}
            </div>
          </div>
        </section>

        {/* 개폐기 제어 섹션 */}
        <section style={{ marginBottom: "12px" }}>
          <header style={{
            background: "linear-gradient(to right, #10b981, #059669)",
            color: "white",
            padding: "10px 16px",
            borderRadius: "8px 8px 0 0",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between"
          }}>
            <h2 style={{
              fontSize: "1rem",
              fontWeight: "600",
              margin: "0",
              display: "flex",
              alignItems: "center",
              gap: "6px"
            }}>
              🪟 개폐기 제어
            </h2>
            <span style={{
              fontSize: "0.75rem",
              opacity: "0.8"
            }}>총 {vents.length}개</span>
          </header>
          <div style={{
            background: "white",
            boxShadow: "0 1px 2px 0 rgb(0 0 0 / 0.05)",
            borderRadius: "0 0 8px 8px",
            padding: "12px"
          }}>
            <div style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(350px, 1fr))",
              gap: "12px"
            }}>
              {vents.map((vent) => (
                <DeviceCard
                  key={vent.id}
                  device={vent}
                  power={deviceState[vent.id]?.power ?? "off"}
                  percentage={deviceState[vent.id]?.targetPercentage ?? 0}
                  lastSavedAt={deviceState[vent.id]?.lastSavedAt}
                  onPercentageChange={(value) => handlePercentageChange(vent.id, value)}
                />
              ))}
            </div>
          </div>
        </section>

        {/* 펌프 제어 섹션 */}
        <section style={{ marginBottom: "12px" }}>
          <header style={{
            background: "linear-gradient(to right, #10b981, #059669)",
            color: "white",
            padding: "10px 16px",
            borderRadius: "8px 8px 0 0",
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between"
          }}>
            <h2 style={{
              fontSize: "1rem",
              fontWeight: "600",
              margin: "0",
              display: "flex",
              alignItems: "center",
              gap: "6px"
            }}>
              💧 펌프 제어
            </h2>
            <span style={{
              fontSize: "0.75rem",
              opacity: "0.8"
            }}>총 {pumps.length}개</span>
          </header>
          <div style={{
            background: "white",
            boxShadow: "0 1px 2px 0 rgb(0 0 0 / 0.05)",
            borderRadius: "0 0 8px 8px",
            padding: "12px"
          }}>
            <div style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(280px, 1fr))",
              gap: "12px"
            }}>
              {pumps.map((pump) => (
                <DeviceCard
                  key={pump.id}
                  device={pump}
                  power={deviceState[pump.id]?.power ?? "off"}
                  lastSavedAt={deviceState[pump.id]?.lastSavedAt}
                  onToggle={(isOn) => handleToggle(pump.id, isOn)}
                />
              ))}
            </div>
          </div>
        </section>
      </div>
    </div>
  );
}
