import { useState, useEffect, useRef } from "react";
import { getDevicesByType } from "../config/devices";
import { ESP32_CONTROLLERS } from "../config/esp32Controllers";
import type { DeviceDesiredState } from "../types";
import DeviceCard from "../components/DeviceCard";
import { publishCommand, onConnectionChange, getMqttClient } from "../mqtt/mqttClient";

interface DevicesControlProps {
  deviceState: DeviceDesiredState;
  setDeviceState: React.Dispatch<React.SetStateAction<DeviceDesiredState>>;
}

// ESP32 장치별 마지막 heartbeat 시간
interface HeartbeatTimestamps {
  [controllerId: string]: number;
}

export default function DevicesControl({ deviceState, setDeviceState }: DevicesControlProps) {
  const [mqttConnected, setMqttConnected] = useState(false);

  // ESP32 장치별 연결 상태 (12개)
  const [esp32Status, setEsp32Status] = useState<Record<string, boolean>>({});

  // ESP32 장치별 마지막 heartbeat 시간 저장
  const heartbeatTimestamps = useRef<HeartbeatTimestamps>({});

  const fans = getDevicesByType("fan");
  const vents = getDevicesByType("vent");
  const pumps = getDevicesByType("pump");

  // MQTT 연결 상태 감지
  useEffect(() => {
    const unsubscribe = onConnectionChange((connected) => {
      setMqttConnected(connected);

      // MQTT 연결이 끊어지면 모든 ESP32 연결 상태를 OFF로 설정
      if (!connected) {
        setEsp32Status({});
        heartbeatTimestamps.current = {};
      }
    });

    return unsubscribe;
  }, []);

  // ESP32 장치별 연결 상태 모니터링 (heartbeat 기반)
  useEffect(() => {
    const client = getMqttClient();

    const handleMessage = (topic: string, message: Buffer) => {
      // status 토픽 처리
      const controller = ESP32_CONTROLLERS.find((c) => topic === c.statusTopic);
      if (controller) {
        const payload = message.toString().trim();
        const now = Date.now();

        // "online" 메시지를 받으면 연결됨으로 표시
        if (payload.toLowerCase() === "online") {
          heartbeatTimestamps.current[controller.controllerId] = now;
          setEsp32Status((prev) => ({
            ...prev,
            [controller.controllerId]: true,
          }));
          console.log(`✅ ESP32 ${controller.name} (${controller.controllerId}) connected`);
        }
      }

      // 장치별 state 토픽도 heartbeat로 활용
      ESP32_CONTROLLERS.forEach((ctrl) => {
        // 해당 컨트롤러의 모든 state 토픽 체크
        const stateTopicPattern = `tansaeng/${ctrl.controllerId}/`;
        if (topic.startsWith(stateTopicPattern) && topic.endsWith("/state")) {
          const now = Date.now();
          heartbeatTimestamps.current[ctrl.controllerId] = now;
          setEsp32Status((prev) => ({
            ...prev,
            [ctrl.controllerId]: true,
          }));
        }
      });
    };

    client.on("message", handleMessage);

    // 모든 ESP32 status 토픽 구독
    ESP32_CONTROLLERS.forEach((controller) => {
      client.subscribe(controller.statusTopic, { qos: 1 });
    });

    // 모든 장치의 state 토픽도 구독 (heartbeat로 활용)
    ESP32_CONTROLLERS.forEach((controller) => {
      client.subscribe(`tansaeng/${controller.controllerId}/+/state`, { qos: 1 });
    });

    return () => {
      client.off("message", handleMessage);
      ESP32_CONTROLLERS.forEach((controller) => {
        client.unsubscribe(controller.statusTopic);
        client.unsubscribe(`tansaeng/${controller.controllerId}/+/state`);
      });
    };
  }, []);

  // 타임아웃 체크: 30초 동안 heartbeat가 없으면 연결 끊김으로 표시
  useEffect(() => {
    const TIMEOUT_MS = 30000; // 30초

    const checkInterval = setInterval(() => {
      const now = Date.now();
      const newStatus: Record<string, boolean> = {};

      ESP32_CONTROLLERS.forEach((controller) => {
        const lastHeartbeat = heartbeatTimestamps.current[controller.controllerId];

        if (lastHeartbeat && (now - lastHeartbeat < TIMEOUT_MS)) {
          newStatus[controller.controllerId] = true;
        } else {
          newStatus[controller.controllerId] = false;

          // 타임아웃 발생 시 로그
          if (lastHeartbeat && esp32Status[controller.controllerId]) {
            console.log(`⚠️ ESP32 ${controller.name} (${controller.controllerId}) timeout`);
          }
        }
      });

      setEsp32Status(newStatus);
    }, 5000); // 5초마다 체크

    return () => clearInterval(checkInterval);
  }, [esp32Status]);

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

  // 연결된 ESP32 개수 계산
  const connectedCount = Object.values(esp32Status).filter(Boolean).length;
  const totalCount = ESP32_CONTROLLERS.length;

  return (
    <div className="bg-gray-50">
      <div className="max-w-screen-2xl mx-auto p-3">
        {/* ESP32 연결 상태 헤더 */}
        <header className="bg-white border-2 border-farm-500 px-4 py-3 rounded-lg mb-3 shadow-md">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-xl font-bold mb-1 text-gray-900">⚙️ 장치 제어</h1>
              <p className="text-xs text-gray-600">
                팬, 개폐기, 펌프 등 장치를 원격으로 제어합니다
              </p>
            </div>
            {/* ESP32 전체 연결 상태 */}
            <div className="flex items-center gap-2 bg-farm-50 border border-farm-200 px-3 py-1.5 rounded-md">
              <div
                className={`
                w-2.5 h-2.5 rounded-full
                ${mqttConnected && connectedCount > 0 ? "bg-farm-500 animate-pulse" : "bg-red-500"}
              `}
              ></div>
              <span className="text-xs font-medium text-gray-900">
                MQTT {mqttConnected ? "연결됨" : "연결 끊김"} ({connectedCount}/{totalCount} 장치)
              </span>
            </div>
          </div>
        </header>

        {/* ESP32 장치 연결 상태 목록 */}
        <section className="mb-3">
          <header className="bg-farm-500 px-4 py-2.5 rounded-t-lg">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">
              🔌 ESP32 장치 연결 상태
            </h2>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2">
              {ESP32_CONTROLLERS.map((controller) => {
                const isConnected = esp32Status[controller.controllerId] === true;
                const lastHeartbeat = heartbeatTimestamps.current[controller.controllerId];
                const timeSinceHeartbeat = lastHeartbeat ? Math.floor((Date.now() - lastHeartbeat) / 1000) : null;

                return (
                  <div
                    key={controller.id}
                    className={`flex items-center gap-2 px-3 py-2 rounded-md border transition-colors ${
                      isConnected
                        ? "bg-green-50 border-green-300"
                        : "bg-gray-50 border-gray-300"
                    }`}
                  >
                    <div
                      className={`w-2 h-2 rounded-full flex-shrink-0 ${
                        isConnected ? "bg-green-500 animate-pulse" : "bg-gray-400"
                      }`}
                    ></div>
                    <div className="flex-1 min-w-0">
                      <span className="text-xs font-medium text-gray-900 block truncate">
                        {controller.name}
                      </span>
                      <span className="text-xs text-gray-500">
                        {controller.controllerId}
                        {timeSinceHeartbeat !== null && isConnected && (
                          <span className="ml-1">({timeSinceHeartbeat}초 전)</span>
                        )}
                      </span>
                    </div>
                    <span
                      className={`text-xs font-medium flex-shrink-0 ${
                        isConnected ? "text-green-600" : "text-gray-500"
                      }`}
                    >
                      {isConnected ? "ON" : "OFF"}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        </section>

        {/* 팬 제어 섹션 */}
        <section className="mb-3">
          <header className="bg-farm-500 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">
              🌀 팬 제어
            </h2>
            <span className="text-xs text-gray-800">총 {fans.length}개</span>
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
          <header className="bg-farm-500 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">
              🪟 개폐기 제어
            </h2>
            <span className="text-xs text-gray-800">총 {vents.length}개</span>
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
          <header className="bg-farm-500 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">
              💧 펌프 제어
            </h2>
            <span className="text-xs text-gray-800">총 {pumps.length}개</span>
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
