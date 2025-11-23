import { getDevicesByType } from "../config/devices";
import type { DeviceDesiredState } from "../types";
import DeviceCard from "../components/DeviceCard";
import { publishCommand } from "../mqtt/mqttClient";

interface DevicesControlProps {
  deviceState: DeviceDesiredState;
  setDeviceState: React.Dispatch<React.SetStateAction<DeviceDesiredState>>;
}

export default function DevicesControl({ deviceState, setDeviceState }: DevicesControlProps) {
  const fans = getDevicesByType("fan");
  const vents = getDevicesByType("vent");
  const pumps = getDevicesByType("pump");

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

    // MQTT 명령 발행
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

    // MQTT 명령 발행
    const device = vents.find((d) => d.id === deviceId);
    if (device) {
      publishCommand(device.commandTopic, { target: percentage });
    }
  };

  return (
    <div className="container mx-auto px-4 py-6 space-y-8">
      {/* 섹션 1: 팬 제어 */}
      <section>
        <div className="bg-gradient-to-r from-emerald-500 to-green-600 rounded-t-2xl px-4 py-3 flex items-center justify-between">
          <h2 className="text-white font-semibold text-lg">🌬️ 팬 제어</h2>
          <span className="bg-white/20 text-white px-3 py-1 rounded-full text-sm">
            총 {fans.length}개 디바이스
          </span>
        </div>
        <div className="bg-gray-50 rounded-b-2xl p-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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

      {/* 섹션 2: 개폐기 제어 */}
      <section>
        <div className="bg-gradient-to-r from-emerald-500 to-green-600 rounded-t-2xl px-4 py-3 flex items-center justify-between">
          <h2 className="text-white font-semibold text-lg">🪟 개폐기 제어</h2>
          <span className="bg-white/20 text-white px-3 py-1 rounded-full text-sm">
            총 {vents.length}개 디바이스
          </span>
        </div>
        <div className="bg-gray-50 rounded-b-2xl p-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 gap-4">
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

      {/* 섹션 3: 펌프 제어 */}
      <section>
        <div className="bg-gradient-to-r from-emerald-500 to-green-600 rounded-t-2xl px-4 py-3 flex items-center justify-between">
          <h2 className="text-white font-semibold text-lg">💧 펌프 제어</h2>
          <span className="bg-white/20 text-white px-3 py-1 rounded-full text-sm">
            총 {pumps.length}개 디바이스
          </span>
        </div>
        <div className="bg-gray-50 rounded-b-2xl p-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
  );
}
