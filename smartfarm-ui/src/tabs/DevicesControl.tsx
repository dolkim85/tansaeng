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
    <div className="!min-h-screen !bg-gray-50 !overflow-y-auto">
      <div className="!container !mx-auto !px-4 !max-w-7xl !py-6 !space-y-6">
        {/* ESP32 연결 상태 헤더 */}
        <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-xl">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold">⚙️ 장치 제어</h1>
              <p className="text-sm opacity-80 mt-1">
                팬, 개폐기, 펌프 등 장치를 원격으로 제어합니다
              </p>
            </div>
            {/* ESP32 연결 상태 */}
            <div className="flex items-center gap-2 bg-white bg-opacity-20 px-4 py-2 rounded-lg">
              <div className={`w-3 h-3 rounded-full ${mqttConnected ? 'bg-green-300 animate-pulse' : 'bg-red-300'}`}></div>
              <span className="text-sm font-medium">
                {mqttConnected ? 'ESP32 연결됨' : 'ESP32 연결 끊김'}
              </span>
            </div>
          </div>
        </header>

        {/* 팬 제어 섹션 */}
        <section>
          <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h2 className="text-xl font-semibold flex items-center gap-2">
              🌀 팬 제어
            </h2>
            <span className="text-sm opacity-80">총 {fans.length}개 디바이스</span>
          </header>
          <div className="!bg-white !shadow-md !rounded-b-xl !p-4">
            <div className="!grid !grid-cols-1 lg:!grid-cols-2 xl:!grid-cols-3 !gap-4">
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
        <section>
          <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h2 className="text-xl font-semibold flex items-center gap-2">
              🪟 개폐기 제어
            </h2>
            <span className="text-sm opacity-80">총 {vents.length}개 디바이스</span>
          </header>
          <div className="!bg-white !shadow-md !rounded-b-xl !p-4">
            <div className="!grid !grid-cols-1 xl:!grid-cols-2 !gap-4">
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
        <section>
          <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h2 className="text-xl font-semibold flex items-center gap-2">
              💧 펌프 제어
            </h2>
            <span className="text-sm opacity-80">총 {pumps.length}개 디바이스</span>
          </header>
          <div className="!bg-white !shadow-md !rounded-b-xl !p-4">
            <div className="!grid !grid-cols-1 lg:!grid-cols-2 xl:!grid-cols-3 !gap-4">
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
