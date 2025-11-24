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
    <div className="bg-gray-50">
      <div className="max-w-screen-2xl mx-auto p-3">
        {/* ESP32 연결 상태 헤더 */}
        <header className="bg-gradient-to-r from-farm-500 to-farm-600 text-white px-4 py-3 rounded-lg mb-3 shadow-md">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-xl font-bold mb-1">⚙️ 장치 제어</h1>
              <p className="text-xs opacity-80">
                팬, 개폐기, 펌프 등 장치를 원격으로 제어합니다
              </p>
            </div>
            {/* ESP32 연결 상태 */}
            <div className="flex items-center gap-2 bg-white/20 px-3 py-1.5 rounded-md">
              <div className={`
                w-2.5 h-2.5 rounded-full
                ${mqttConnected ? 'bg-farm-200 animate-pulse' : 'bg-red-300'}
              `}></div>
              <span className="text-xs font-medium">
                {mqttConnected ? 'ESP32 연결됨' : 'ESP32 연결 끊김'}
              </span>
            </div>
          </div>
        </header>

        {/* 팬 제어 섹션 */}
        <section className="mb-3">
          <header className="bg-gradient-to-r from-farm-500 to-farm-600 text-white px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5">
              🌀 팬 제어
            </h2>
            <span className="text-xs opacity-80">총 {fans.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-3">
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
        <section className="mb-3">
          <header className="bg-gradient-to-r from-farm-500 to-farm-600 text-white px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5">
              🪟 개폐기 제어
            </h2>
            <span className="text-xs opacity-80">총 {vents.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(350px,1fr))] gap-3">
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
        <section className="mb-3">
          <header className="bg-gradient-to-r from-farm-500 to-farm-600 text-white px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5">
              💧 펌프 제어
            </h2>
            <span className="text-xs opacity-80">총 {pumps.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-3">
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
