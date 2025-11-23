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
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="container mx-auto px-4 max-w-6xl space-y-10">
        {/* 팬 제어 섹션 */}
        <section>
          <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-t-xl flex items-center justify-between">
            <h2 className="text-xl font-semibold flex items-center gap-2">
              🌀 팬 제어
            </h2>
            <span className="text-sm opacity-80">총 {fans.length}개 디바이스</span>
          </header>
          <div className="bg-white shadow-md rounded-b-xl p-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
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
          <div className="bg-white shadow-md rounded-b-xl p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
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
          <div className="bg-white shadow-md rounded-b-xl p-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
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
