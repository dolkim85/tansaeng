import { useState, useEffect, useRef } from "react";
import { getDevicesByType } from "../config/devices";
import { ESP32_CONTROLLERS } from "../config/esp32Controllers";
import type { DeviceDesiredState } from "../types";
import DeviceCard from "../components/DeviceCard";
import { getMqttClient, onConnectionChange } from "../mqtt/mqttClient";
import { sendDeviceCommand } from "../api/deviceControl";

interface DevicesControlProps {
  deviceState: DeviceDesiredState;
  setDeviceState: React.Dispatch<React.SetStateAction<DeviceDesiredState>>;
}

export default function DevicesControl({ deviceState, setDeviceState }: DevicesControlProps) {
  // ESP32 장치별 연결 상태
  const [esp32Status, setEsp32Status] = useState<Record<string, boolean>>({});

  // HiveMQ 연결 상태
  const [mqttConnected, setMqttConnected] = useState(false);

  // 천창/측창 퍼센트 입력 임시 상태
  const [percentageInputs, setPercentageInputs] = useState<Record<string, string>>({});

  // 천창/측창 타이머 참조
  const percentageTimers = useRef<Record<string, NodeJS.Timeout>>({});

  // 천창/측창 작동 상태
  const [operationStatus, setOperationStatus] = useState<Record<string, 'idle' | 'running' | 'completed'>>({});

  // 천창/측창 현재 위치 추적 (0~100%)
  const [currentPosition, setCurrentPosition] = useState<Record<string, number>>({});

  const fans = getDevicesByType("fan");
  const vents = getDevicesByType("vent");
  const pumps = getDevicesByType("pump");
  const skylights = getDevicesByType("skylight");
  const sidescreens = getDevicesByType("sidescreen");

  // HiveMQ 연결 상태 모니터링
  useEffect(() => {
    getMqttClient();
    const unsubscribe = onConnectionChange((connected) => {
      setMqttConnected(connected);
    });
    return () => unsubscribe();
  }, []);

  // ESP32 상태 API 폴링
  useEffect(() => {
    const fetchESP32Status = async () => {
      try {
        const response = await fetch("/api/device_status.php");
        const result = await response.json();

        if (result.success) {
          const newStatus: Record<string, boolean> = {};
          Object.entries(result.devices).forEach(([controllerId, info]: [string, any]) => {
            newStatus[controllerId] = info.is_online;
          });
          setEsp32Status(newStatus);
        }
      } catch (error) {
        console.error("[API] Failed to fetch ESP32 status:", error);
      }
    };

    fetchESP32Status();
    const interval = setInterval(fetchESP32Status, 5000);
    return () => clearInterval(interval);
  }, []);

  const handleToggle = async (deviceId: string, isOn: boolean) => {
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
      const topicParts = device.commandTopic.split('/');
      const mqttDeviceId = topicParts[2];
      const command = isOn ? "ON" : "OFF";
      const result = await sendDeviceCommand(device.esp32Id, mqttDeviceId, command);

      if (result.success) {
        console.log(`[API SUCCESS] ${device.name} - ${command}`);
      } else {
        console.error(`[API ERROR] ${result.message}`);
      }
    }
  };

  // 천창/측창 제어 핸들러
  const handleSkylightCommand = async (deviceId: string, command: "OPEN" | "CLOSE" | "STOP") => {
    const device = [...skylights, ...sidescreens].find((d) => d.id === deviceId);
    if (device) {
      const topicParts = device.commandTopic.split('/');
      const mqttDeviceId = topicParts[2];

      if (command === "OPEN" || command === "CLOSE") {
        setOperationStatus(prev => ({ ...prev, [deviceId]: 'running' }));
      } else if (command === "STOP") {
        setOperationStatus(prev => ({ ...prev, [deviceId]: 'idle' }));
      }

      const result = await sendDeviceCommand(device.esp32Id, mqttDeviceId, command);
      if (result.success) {
        console.log(`[API SUCCESS] ${result.message}`);
      } else {
        console.error(`[API ERROR] ${result.message}`);
      }
    }
  };

  // 천창/측창 퍼센트 저장 핸들러
  const handleSavePercentage = (deviceId: string) => {
    const inputValue = percentageInputs[deviceId];
    if (!inputValue) return;

    const percentage = parseInt(inputValue);
    if (isNaN(percentage) || percentage < 0 || percentage > 100) {
      alert('0~100 사이의 숫자를 입력해주세요.');
      return;
    }

    const newState = {
      ...deviceState,
      [deviceId]: {
        ...deviceState[deviceId],
        targetPercentage: percentage,
        lastSavedAt: new Date().toISOString(),
      },
    };
    setDeviceState(newState);
  };

  // 천창/측창 퍼센트 작동 핸들러
  const handleExecutePercentage = async (deviceId: string) => {
    const targetPercentage = deviceState[deviceId]?.targetPercentage ?? 0;
    const currentPos = currentPosition[deviceId] ?? 0;
    const device = [...skylights, ...sidescreens].find((d) => d.id === deviceId);
    if (!device) return;

    const difference = targetPercentage - currentPos;
    if (difference === 0) {
      alert(`이미 ${targetPercentage}% 위치에 있습니다.`);
      return;
    }

    if (percentageTimers.current[deviceId]) {
      clearTimeout(percentageTimers.current[deviceId]);
      delete percentageTimers.current[deviceId];
    }

    setOperationStatus({ ...operationStatus, [deviceId]: 'running' });

    const fullTimeSeconds = device.esp32Id === "ctlr-0012" ? 300 : 120;
    const movementPercentage = Math.abs(difference);
    const targetTimeSeconds = (movementPercentage / 100) * fullTimeSeconds;
    const command = difference > 0 ? "OPEN" : "CLOSE";

    const topicParts = device.commandTopic.split('/');
    const mqttDeviceId = topicParts[2];

    try {
      await sendDeviceCommand(device.esp32Id, mqttDeviceId, command);

      percentageTimers.current[deviceId] = setTimeout(async () => {
        await sendDeviceCommand(device.esp32Id, mqttDeviceId, "STOP");
        delete percentageTimers.current[deviceId];

        setCurrentPosition(prev => ({ ...prev, [deviceId]: targetPercentage }));
        setOperationStatus(prev => ({ ...prev, [deviceId]: 'completed' }));
      }, targetTimeSeconds * 1000);
    } catch (error) {
      console.error(`[EXECUTE ERROR] ${device.name}:`, error);
      setOperationStatus({ ...operationStatus, [deviceId]: 'idle' });
    }
  };

  const connectedCount = Object.values(esp32Status).filter(Boolean).length;
  const totalCount = ESP32_CONTROLLERS.length;

  return (
    <div className="bg-gray-50">
      <div className="max-w-screen-2xl mx-auto p-3">
        {/* 헤더 */}
        <header className="bg-white border-2 border-farm-500 px-4 py-3 rounded-lg mb-3 shadow-md">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-xl font-bold mb-1 text-gray-900">⚙️ 장치 제어</h1>
              <p className="text-xs text-gray-600">
                팬, 개폐기, 펌프 등 장치를 원격으로 제어합니다
              </p>
            </div>
            <div className="flex items-center gap-3">
              <div className="flex items-center gap-2 bg-purple-50 border border-purple-200 px-3 py-1.5 rounded-md">
                <div className={`w-2.5 h-2.5 rounded-full ${mqttConnected ? "bg-green-500 animate-pulse" : "bg-red-500"}`}></div>
                <span className="text-xs font-medium text-gray-900">
                  HiveMQ {mqttConnected ? "연결됨" : "연결 끊김"}
                </span>
              </div>
              <div className="flex items-center gap-2 bg-farm-50 border border-farm-200 px-3 py-1.5 rounded-md">
                <div className={`w-2.5 h-2.5 rounded-full ${connectedCount > 0 ? "bg-farm-500 animate-pulse" : "bg-gray-400"}`}></div>
                <span className="text-xs font-medium text-gray-900">
                  장치 연결 ({connectedCount}/{totalCount})
                </span>
              </div>
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
                return (
                  <div
                    key={controller.id}
                    className={`flex items-center gap-2 px-3 py-2 rounded-md border transition-colors ${
                      isConnected ? "bg-green-50 border-green-300" : "bg-gray-50 border-gray-300"
                    }`}
                  >
                    <div className={`w-2 h-2 rounded-full flex-shrink-0 ${isConnected ? "bg-green-500 animate-pulse" : "bg-gray-400"}`}></div>
                    <div className="flex-1 min-w-0">
                      <span className="text-xs font-medium text-gray-900 block truncate">{controller.name}</span>
                      <span className="text-xs text-gray-500">{controller.controllerId}</span>
                    </div>
                    <span className={`text-xs font-medium flex-shrink-0 ${isConnected ? "text-green-600" : "text-gray-500"}`}>
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
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">🌀 팬 제어</h2>
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

        {/* 천창 스크린 제어 섹션 */}
        <section className="mb-3">
          <header className="bg-amber-400 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">☀️ 천창 스크린 제어</h2>
            <span className="text-xs text-gray-800">총 {skylights.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(350px,1fr))] gap-3">
              {skylights.map((skylight) => (
                <div key={skylight.id} className="bg-white border-2 border-amber-200 rounded-lg p-4 shadow-sm">
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-semibold text-gray-900">{skylight.name}</h3>
                    <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">{skylight.esp32Id}</span>
                  </div>

                  {/* 버튼 제어 */}
                  <div className="mb-4">
                    <div className="flex items-center justify-between mb-2">
                      <p className="text-xs text-gray-600 font-medium">버튼 제어</p>
                      {operationStatus[skylight.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[skylight.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">✓ 완료</span>
                      )}
                    </div>
                    <div className="flex gap-2">
                      <button onClick={() => handleSkylightCommand(skylight.id, "OPEN")} className="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-4 rounded-md transition-colors">▲ 열기</button>
                      <button onClick={() => handleSkylightCommand(skylight.id, "STOP")} className="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-3 px-4 rounded-md transition-colors">■ 정지</button>
                      <button onClick={() => handleSkylightCommand(skylight.id, "CLOSE")} className="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-md transition-colors">▼ 닫기</button>
                    </div>
                  </div>

                  {/* 퍼센트 입력 제어 */}
                  <div>
                    <p className="text-xs text-gray-600 font-medium mb-2">개폐 퍼센트 설정</p>
                    <div className="flex items-center gap-2 mb-2">
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={percentageInputs[skylight.id] ?? (deviceState[skylight.id]?.targetPercentage ?? 0)}
                        onChange={(e) => setPercentageInputs({ ...percentageInputs, [skylight.id]: e.target.value })}
                        className="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="0-100"
                      />
                      <span className="text-sm font-semibold text-gray-900 min-w-[2rem]">%</span>
                      <button onClick={() => handleSavePercentage(skylight.id)} className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-md transition-colors">저장</button>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="flex-1 text-xs text-gray-600 space-y-1">
                        <div>현재 위치: <span className="font-semibold text-gray-800">{currentPosition[skylight.id] ?? 0}%</span></div>
                        <div>저장된 값: <span className="font-semibold text-amber-600">{deviceState[skylight.id]?.targetPercentage ?? 0}%</span></div>
                      </div>
                      <button
                        onClick={() => handleExecutePercentage(skylight.id)}
                        className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-md transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                        disabled={operationStatus[skylight.id] === 'running'}
                      >작동</button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* 측창 스크린 제어 섹션 */}
        <section className="mb-3">
          <header className="bg-blue-400 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">🪟 측창 스크린 제어</h2>
            <span className="text-xs text-gray-800">총 {sidescreens.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(350px,1fr))] gap-3">
              {sidescreens.map((sidescreen) => (
                <div key={sidescreen.id} className="bg-white border-2 border-blue-200 rounded-lg p-4 shadow-sm">
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-semibold text-gray-900">{sidescreen.name}</h3>
                    <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">{sidescreen.esp32Id}</span>
                  </div>

                  {/* 버튼 제어 */}
                  <div className="mb-4">
                    <div className="flex items-center justify-between mb-2">
                      <p className="text-xs text-gray-600 font-medium">버튼 제어</p>
                      {operationStatus[sidescreen.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[sidescreen.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">✓ 완료</span>
                      )}
                    </div>
                    <div className="flex gap-2">
                      <button onClick={() => handleSkylightCommand(sidescreen.id, "OPEN")} className="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-4 rounded-md transition-colors">▲ 열기</button>
                      <button onClick={() => handleSkylightCommand(sidescreen.id, "STOP")} className="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-3 px-4 rounded-md transition-colors">■ 정지</button>
                      <button onClick={() => handleSkylightCommand(sidescreen.id, "CLOSE")} className="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-md transition-colors">▼ 닫기</button>
                    </div>
                  </div>

                  {/* 퍼센트 입력 제어 */}
                  <div>
                    <p className="text-xs text-gray-600 font-medium mb-2">개폐 퍼센트 설정</p>
                    <div className="flex items-center gap-2 mb-2">
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={percentageInputs[sidescreen.id] ?? (deviceState[sidescreen.id]?.targetPercentage ?? 0)}
                        onChange={(e) => setPercentageInputs({ ...percentageInputs, [sidescreen.id]: e.target.value })}
                        className="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="0-100"
                      />
                      <span className="text-sm font-semibold text-gray-900 min-w-[2rem]">%</span>
                      <button onClick={() => handleSavePercentage(sidescreen.id)} className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-md transition-colors">저장</button>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="flex-1 text-xs text-gray-600 space-y-1">
                        <div>현재 위치: <span className="font-semibold text-gray-800">{currentPosition[sidescreen.id] ?? 0}%</span></div>
                        <div>저장된 값: <span className="font-semibold text-blue-600">{deviceState[sidescreen.id]?.targetPercentage ?? 0}%</span></div>
                      </div>
                      <button
                        onClick={() => handleExecutePercentage(sidescreen.id)}
                        className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-md transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                        disabled={operationStatus[sidescreen.id] === 'running'}
                      >작동</button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* 펌프 제어 섹션 */}
        <section className="mb-3">
          <header className="bg-farm-500 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">💧 펌프 제어</h2>
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
