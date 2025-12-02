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

// 자동 제어 설정 인터페이스
interface AutoControlSettings {
  mode: "manual" | "auto";
  deviceControl: Record<string, boolean>; // 장치별 자동 제어 ON/OFF
  tempMin: number;
  tempMax: number;
  humMin: number;
  humMax: number;
}

// 센서 데이터 인터페이스
interface SensorData {
  temperature: number | null;
  humidity: number | null;
}

export default function DevicesControl({ deviceState, setDeviceState }: DevicesControlProps) {
  const [mqttConnected, setMqttConnected] = useState(false);

  // ESP32 장치별 연결 상태 (12개)
  const [esp32Status, setEsp32Status] = useState<Record<string, boolean>>({});

  // ESP32 장치별 마지막 heartbeat 시간 저장
  const heartbeatTimestamps = useRef<HeartbeatTimestamps>({});

  // 자동 제어 설정
  const [autoControl, setAutoControl] = useState<AutoControlSettings>({
    mode: "manual",
    deviceControl: {},
    tempMin: 18,
    tempMax: 28,
    humMin: 40,
    humMax: 70,
  });

  // 센서 데이터 (평균값 계산용)
  const [sensorData, setSensorData] = useState<{
    front: SensorData;
    back: SensorData;
    top: SensorData;
  }>({
    front: { temperature: null, humidity: null },
    back: { temperature: null, humidity: null },
    top: { temperature: null, humidity: null },
  });

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
      // status 토픽 처리만 사용 (state 토픽은 heartbeat로 사용하지 않음)
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
        // "offline" 메시지를 받으면 연결 끊김으로 표시
        else if (payload.toLowerCase() === "offline") {
          delete heartbeatTimestamps.current[controller.controllerId];
          setEsp32Status((prev) => ({
            ...prev,
            [controller.controllerId]: false,
          }));
          console.log(`❌ ESP32 ${controller.name} (${controller.controllerId}) disconnected`);
        }
      }
    };

    client.on("message", handleMessage);

    // 모든 ESP32 status 토픽만 구독
    ESP32_CONTROLLERS.forEach((controller) => {
      client.subscribe(controller.statusTopic, { qos: 1 });
    });

    return () => {
      client.off("message", handleMessage);
      ESP32_CONTROLLERS.forEach((controller) => {
        client.unsubscribe(controller.statusTopic);
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

  // 센서 데이터 구독 (자동 제어용)
  useEffect(() => {
    const client = getMqttClient();

    const handleSensorMessage = (topic: string, message: Buffer) => {
      const value = parseFloat(message.toString());

      // 내부팬 앞 (ctlr-0001)
      if (topic === "tansaeng/ctlr-0001/dht11/temperature") {
        setSensorData((prev) => ({ ...prev, front: { ...prev.front, temperature: value } }));
      } else if (topic === "tansaeng/ctlr-0001/dht11/humidity") {
        setSensorData((prev) => ({ ...prev, front: { ...prev.front, humidity: value } }));
      }
      // 내부팬 뒤 (ctlr-0002)
      else if (topic === "tansaeng/ctlr-0002/dht22/temperature") {
        setSensorData((prev) => ({ ...prev, back: { ...prev.back, temperature: value } }));
      } else if (topic === "tansaeng/ctlr-0002/dht22/humidity") {
        setSensorData((prev) => ({ ...prev, back: { ...prev.back, humidity: value } }));
      }
      // 천장 (ctlr-0003)
      else if (topic === "tansaeng/ctlr-0003/dht22/temperature") {
        setSensorData((prev) => ({ ...prev, top: { ...prev.top, temperature: value } }));
      } else if (topic === "tansaeng/ctlr-0003/dht22/humidity") {
        setSensorData((prev) => ({ ...prev, top: { ...prev.top, humidity: value } }));
      }
    };

    client.on("message", handleSensorMessage);

    // 센서 토픽 구독
    const sensorTopics = [
      "tansaeng/ctlr-0001/dht11/temperature",
      "tansaeng/ctlr-0001/dht11/humidity",
      "tansaeng/ctlr-0002/dht22/temperature",
      "tansaeng/ctlr-0002/dht22/humidity",
      "tansaeng/ctlr-0003/dht22/temperature",
      "tansaeng/ctlr-0003/dht22/humidity",
    ];

    sensorTopics.forEach((topic) => client.subscribe(topic, { qos: 1 }));

    return () => {
      client.off("message", handleSensorMessage);
      sensorTopics.forEach((topic) => client.unsubscribe(topic));
    };
  }, []);

  // 자동 제어 로직
  useEffect(() => {
    if (autoControl.mode !== "auto") return;

    // 평균 온습도 계산
    const temps = [sensorData.front.temperature, sensorData.back.temperature, sensorData.top.temperature].filter((t) => t !== null) as number[];
    const hums = [sensorData.front.humidity, sensorData.back.humidity, sensorData.top.humidity].filter((h) => h !== null) as number[];

    if (temps.length === 0 || hums.length === 0) return;

    const avgTemp = temps.reduce((a, b) => a + b, 0) / temps.length;
    const avgHum = hums.reduce((a, b) => a + b, 0) / hums.length;

    // 자동 제어가 활성화된 장치들만 제어
    ESP32_CONTROLLERS.forEach((controller) => {
      if (!autoControl.deviceControl[controller.controllerId]) return;

      // 온도 기반 제어
      if (avgTemp > autoControl.tempMax) {
        // 온도가 높으면 팬 켜기, 환기 열기
        publishCommand(`tansaeng/${controller.controllerId}/fan1/cmd`, { power: "on" });
        publishCommand(`tansaeng/${controller.controllerId}/fan2/cmd`, { power: "on" });
        publishCommand(`tansaeng/${controller.controllerId}/vent_side_left/cmd`, { target: 80 });
        publishCommand(`tansaeng/${controller.controllerId}/vent_side_right/cmd`, { target: 80 });
      } else if (avgTemp < autoControl.tempMin) {
        // 온도가 낮으면 팬 끄기, 환기 닫기
        publishCommand(`tansaeng/${controller.controllerId}/fan1/cmd`, { power: "off" });
        publishCommand(`tansaeng/${controller.controllerId}/fan2/cmd`, { power: "off" });
        publishCommand(`tansaeng/${controller.controllerId}/vent_side_left/cmd`, { target: 20 });
        publishCommand(`tansaeng/${controller.controllerId}/vent_side_right/cmd`, { target: 20 });
      }

      // 습도 기반 제어
      if (avgHum > autoControl.humMax) {
        // 습도가 높으면 환기
        publishCommand(`tansaeng/${controller.controllerId}/vent_top_left/cmd`, { target: 80 });
        publishCommand(`tansaeng/${controller.controllerId}/vent_top_right/cmd`, { target: 80 });
      } else if (avgHum < autoControl.humMin) {
        // 습도가 낮으면 환기 닫기
        publishCommand(`tansaeng/${controller.controllerId}/vent_top_left/cmd`, { target: 20 });
        publishCommand(`tansaeng/${controller.controllerId}/vent_top_right/cmd`, { target: 20 });
      }
    });
  }, [sensorData, autoControl]);

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

        {/* 자동 제어 설정 */}
        <section className="mb-3">
          <header className="bg-farm-500 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">
              ⚙️ 자동 제어 설정
            </h2>
            <div className="flex items-center gap-3">
              <span className="text-xs text-gray-800">모드:</span>
              <button
                onClick={() =>
                  setAutoControl({
                    ...autoControl,
                    mode: autoControl.mode === "manual" ? "auto" : "manual",
                  })
                }
                className={`px-4 py-1.5 rounded-md text-xs font-medium transition-colors ${
                  autoControl.mode === "auto"
                    ? "bg-green-500 text-white hover:bg-green-600"
                    : "bg-gray-300 text-gray-700 hover:bg-gray-400"
                }`}
              >
                {autoControl.mode === "auto" ? "자동 모드" : "수동 모드"}
              </button>
            </div>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-4">
            {/* 현재 평균 온습도 표시 */}
            <div className="mb-4 p-3 bg-farm-50 rounded-lg border border-farm-200">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <span className="text-xs text-gray-600">평균 온도:</span>
                  <span className="ml-2 text-sm font-semibold text-gray-900">
                    {(() => {
                      const temps = [
                        sensorData.front.temperature,
                        sensorData.back.temperature,
                        sensorData.top.temperature,
                      ].filter((t) => t !== null) as number[];
                      if (temps.length === 0) return "N/A";
                      const avg = temps.reduce((a, b) => a + b, 0) / temps.length;
                      return `${avg.toFixed(2)}°C`;
                    })()}
                  </span>
                </div>
                <div>
                  <span className="text-xs text-gray-600">평균 습도:</span>
                  <span className="ml-2 text-sm font-semibold text-gray-900">
                    {(() => {
                      const hums = [
                        sensorData.front.humidity,
                        sensorData.back.humidity,
                        sensorData.top.humidity,
                      ].filter((h) => h !== null) as number[];
                      if (hums.length === 0) return "N/A";
                      const avg = hums.reduce((a, b) => a + b, 0) / hums.length;
                      return `${avg.toFixed(2)}%`;
                    })()}
                  </span>
                </div>
              </div>
            </div>

            {autoControl.mode === "auto" && (
              <>
                {/* 온습도 임계값 설정 */}
                <div className="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                  <h3 className="text-sm font-semibold text-gray-900 mb-3">온습도 임계값</h3>
                  <div className="grid grid-cols-2 gap-4">
                    {/* 온도 설정 */}
                    <div>
                      <label className="text-xs text-gray-700 font-medium mb-1 block">
                        온도 범위 (°C)
                      </label>
                      <div className="flex items-center gap-2">
                        <input
                          type="number"
                          value={autoControl.tempMin}
                          onChange={(e) =>
                            setAutoControl({ ...autoControl, tempMin: parseFloat(e.target.value) })
                          }
                          className="w-20 px-2 py-1 text-xs border border-gray-300 rounded"
                          step="0.5"
                        />
                        <span className="text-xs text-gray-500">~</span>
                        <input
                          type="number"
                          value={autoControl.tempMax}
                          onChange={(e) =>
                            setAutoControl({ ...autoControl, tempMax: parseFloat(e.target.value) })
                          }
                          className="w-20 px-2 py-1 text-xs border border-gray-300 rounded"
                          step="0.5"
                        />
                      </div>
                    </div>
                    {/* 습도 설정 */}
                    <div>
                      <label className="text-xs text-gray-700 font-medium mb-1 block">
                        습도 범위 (%)
                      </label>
                      <div className="flex items-center gap-2">
                        <input
                          type="number"
                          value={autoControl.humMin}
                          onChange={(e) =>
                            setAutoControl({ ...autoControl, humMin: parseFloat(e.target.value) })
                          }
                          className="w-20 px-2 py-1 text-xs border border-gray-300 rounded"
                          step="1"
                        />
                        <span className="text-xs text-gray-500">~</span>
                        <input
                          type="number"
                          value={autoControl.humMax}
                          onChange={(e) =>
                            setAutoControl({ ...autoControl, humMax: parseFloat(e.target.value) })
                          }
                          className="w-20 px-2 py-1 text-xs border border-gray-300 rounded"
                          step="1"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                {/* 장치별 자동 제어 ON/OFF */}
                <div>
                  <h3 className="text-sm font-semibold text-gray-900 mb-2">제어 대상 장치</h3>
                  <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2">
                    {ESP32_CONTROLLERS.map((controller) => {
                      const isEnabled = autoControl.deviceControl[controller.controllerId] === true;
                      const isConnected = esp32Status[controller.controllerId] === true;

                      return (
                        <div
                          key={controller.id}
                          className={`flex items-center justify-between px-3 py-2 rounded-md border ${
                            isEnabled
                              ? "bg-green-50 border-green-300"
                              : "bg-gray-50 border-gray-300"
                          }`}
                        >
                          <div className="flex items-center gap-2 flex-1 min-w-0">
                            <div
                              className={`w-2 h-2 rounded-full flex-shrink-0 ${
                                isConnected ? "bg-green-500" : "bg-gray-400"
                              }`}
                            ></div>
                            <span className="text-xs font-medium text-gray-900 truncate">
                              {controller.name}
                            </span>
                          </div>
                          <button
                            onClick={() =>
                              setAutoControl({
                                ...autoControl,
                                deviceControl: {
                                  ...autoControl.deviceControl,
                                  [controller.controllerId]: !isEnabled,
                                },
                              })
                            }
                            className={`px-3 py-1 rounded text-xs font-medium transition-colors flex-shrink-0 ${
                              isEnabled
                                ? "bg-green-500 text-white hover:bg-green-600"
                                : "bg-gray-300 text-gray-700 hover:bg-gray-400"
                            }`}
                          >
                            {isEnabled ? "ON" : "OFF"}
                          </button>
                        </div>
                      );
                    })}
                  </div>
                </div>
              </>
            )}
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
