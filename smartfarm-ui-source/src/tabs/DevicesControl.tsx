import { useState, useEffect, useRef } from "react";
import { getDevicesByType } from "../config/devices";
import { ESP32_CONTROLLERS } from "../config/esp32Controllers";
import type { DeviceDesiredState } from "../types";
import DeviceCard from "../components/DeviceCard";
import CollapsibleSection from "../components/CollapsibleSection";
import { getMqttClient, onConnectionChange } from "../mqtt/mqttClient";
import { sendDeviceCommand } from "../api/deviceControl";

interface DevicesControlProps {
  deviceState: DeviceDesiredState;
  setDeviceState: React.Dispatch<React.SetStateAction<DeviceDesiredState>>;
}

export default function DevicesControl({ deviceState, setDeviceState }: DevicesControlProps) {
  const [esp32Status, setEsp32Status] = useState<Record<string, boolean>>({});
  const [mqttConnected, setMqttConnected] = useState(false);
  const [percentageInputs, setPercentageInputs] = useState<Record<string, string>>({});
  const percentageTimers = useRef<Record<string, NodeJS.Timeout>>({});
  const [operationStatus, setOperationStatus] = useState<Record<string, 'idle' | 'running' | 'completed'>>({});
  const [currentPosition, setCurrentPosition] = useState<Record<string, number>>({});

  const fans = getDevicesByType("fan");
  const pumps = getDevicesByType("pump");
  const skylights = getDevicesByType("skylight");
  const sidescreens = getDevicesByType("sidescreen");

  useEffect(() => {
    getMqttClient();
    const unsubscribe = onConnectionChange((connected) => setMqttConnected(connected));
    return () => unsubscribe();
  }, []);

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
      [deviceId]: { ...deviceState[deviceId], power: (isOn ? "on" : "off") as "on" | "off", lastSavedAt: new Date().toISOString() },
    };
    setDeviceState(newState);

    const device = [...fans, ...pumps].find((d) => d.id === deviceId);
    if (device) {
      const topicParts = device.commandTopic.split('/');
      const mqttDeviceId = topicParts[2];
      await sendDeviceCommand(device.esp32Id, mqttDeviceId, isOn ? "ON" : "OFF");
    }
  };

  const handleSkylightCommand = async (deviceId: string, command: "OPEN" | "CLOSE" | "STOP") => {
    const device = [...skylights, ...sidescreens].find((d) => d.id === deviceId);
    if (device) {
      const topicParts = device.commandTopic.split('/');
      const mqttDeviceId = topicParts[2];
      if (command === "OPEN" || command === "CLOSE") {
        setOperationStatus(prev => ({ ...prev, [deviceId]: 'running' }));
      } else {
        setOperationStatus(prev => ({ ...prev, [deviceId]: 'idle' }));
      }
      await sendDeviceCommand(device.esp32Id, mqttDeviceId, command);
    }
  };

  const handleSavePercentage = (deviceId: string) => {
    const inputValue = percentageInputs[deviceId];
    if (!inputValue) return;
    const percentage = parseInt(inputValue);
    if (isNaN(percentage) || percentage < 0 || percentage > 100) {
      alert('0~100 사이의 숫자를 입력해주세요.');
      return;
    }
    setDeviceState({
      ...deviceState,
      [deviceId]: { ...deviceState[deviceId], targetPercentage: percentage, lastSavedAt: new Date().toISOString() },
    });
  };

  const handleExecutePercentage = async (deviceId: string) => {
    const targetPercentage = deviceState[deviceId]?.targetPercentage ?? 0;
    const currentPos = currentPosition[deviceId] ?? 0;
    const device = [...skylights, ...sidescreens].find((d) => d.id === deviceId);
    if (!device) return;

    const difference = targetPercentage - currentPos;
    if (difference === 0) return;

    if (percentageTimers.current[deviceId]) {
      clearTimeout(percentageTimers.current[deviceId]);
    }

    setOperationStatus({ ...operationStatus, [deviceId]: 'running' });

    const fullTimeSeconds = device.esp32Id === "ctlr-0012" ? 300 : 120;
    const targetTimeSeconds = (Math.abs(difference) / 100) * fullTimeSeconds;
    const command = difference > 0 ? "OPEN" : "CLOSE";
    const topicParts = device.commandTopic.split('/');
    const mqttDeviceId = topicParts[2];

    await sendDeviceCommand(device.esp32Id, mqttDeviceId, command);

    percentageTimers.current[deviceId] = setTimeout(async () => {
      await sendDeviceCommand(device.esp32Id, mqttDeviceId, "STOP");
      setCurrentPosition(prev => ({ ...prev, [deviceId]: targetPercentage }));
      setOperationStatus(prev => ({ ...prev, [deviceId]: 'completed' }));
    }, targetTimeSeconds * 1000);
  };

  const connectedCount = Object.values(esp32Status).filter(Boolean).length;
  const totalCount = ESP32_CONTROLLERS.length;

  // 컴팩트 스크린 카드 렌더링
  const renderScreenCard = (device: any, borderColor: string, accentColor: string) => (
    <div key={device.id} className={`bg-white border-2 ${borderColor} rounded-lg p-3 shadow-sm`}>
      <div className="flex items-center justify-between mb-2">
        <h3 className="text-sm font-semibold text-gray-900">{device.name}</h3>
        {operationStatus[device.id] === 'running' && (
          <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
            <span className="animate-pulse">●</span> 작동중
          </span>
        )}
      </div>

      {/* 컴팩트 버튼 - 한 줄 */}
      <div className="flex gap-1.5 mb-2">
        <button onClick={() => handleSkylightCommand(device.id, "OPEN")} className="flex-1 bg-green-500 active:bg-green-600 text-white font-bold py-2.5 rounded text-sm">▲</button>
        <button onClick={() => handleSkylightCommand(device.id, "STOP")} className="flex-1 bg-yellow-500 active:bg-yellow-600 text-white font-bold py-2.5 rounded text-sm">■</button>
        <button onClick={() => handleSkylightCommand(device.id, "CLOSE")} className="flex-1 bg-red-500 active:bg-red-600 text-white font-bold py-2.5 rounded text-sm">▼</button>
      </div>

      {/* 퍼센트 설정 - 컴팩트 */}
      <div className="flex items-center gap-1.5">
        <input
          type="number"
          min="0"
          max="100"
          value={percentageInputs[device.id] ?? (deviceState[device.id]?.targetPercentage ?? 0)}
          onChange={(e) => setPercentageInputs({ ...percentageInputs, [device.id]: e.target.value })}
          className="w-16 px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-farm-500"
        />
        <span className="text-sm text-gray-600">%</span>
        <button onClick={() => handleSavePercentage(device.id)} className="px-2 py-1.5 bg-gray-500 text-white text-xs rounded">저장</button>
        <button
          onClick={() => handleExecutePercentage(device.id)}
          className={`px-3 py-1.5 ${accentColor} text-white text-xs font-bold rounded disabled:bg-gray-400`}
          disabled={operationStatus[device.id] === 'running'}
        >실행</button>
        <span className="text-xs text-gray-500 ml-auto">{currentPosition[device.id] ?? 0}%</span>
      </div>
    </div>
  );

  return (
    <div className="bg-gray-50 min-h-full">
      <div className="p-2">
        {/* 컴팩트 상태 바 */}
        <div className="flex items-center justify-between bg-white rounded-lg px-3 py-2 mb-2 shadow-sm">
          <div className="flex items-center gap-2">
            <div className={`w-2.5 h-2.5 rounded-full ${mqttConnected ? "bg-green-500 animate-pulse" : "bg-red-500"}`}></div>
            <span className="text-xs font-medium text-gray-700">MQTT</span>
          </div>
          <div className="flex items-center gap-2">
            <div className={`w-2.5 h-2.5 rounded-full ${connectedCount > 0 ? "bg-farm-500 animate-pulse" : "bg-gray-400"}`}></div>
            <span className="text-xs font-medium text-gray-700">장치 {connectedCount}/{totalCount}</span>
          </div>
        </div>

        {/* ESP32 상태 - 접이식 */}
        <CollapsibleSection title="ESP32 연결 상태" icon="🔌" badge={`${connectedCount}/${totalCount}`} defaultOpen={false}>
          <div className="grid grid-cols-2 gap-1.5">
            {ESP32_CONTROLLERS.map((controller) => {
              const isConnected = esp32Status[controller.controllerId] === true;
              return (
                <div
                  key={controller.id}
                  className={`flex items-center gap-1.5 px-2 py-1.5 rounded border text-xs ${
                    isConnected ? "bg-green-50 border-green-300" : "bg-gray-50 border-gray-200"
                  }`}
                >
                  <div className={`w-2 h-2 rounded-full ${isConnected ? "bg-green-500" : "bg-gray-400"}`}></div>
                  <span className="truncate flex-1 text-gray-700">{controller.name}</span>
                </div>
              );
            })}
          </div>
        </CollapsibleSection>

        {/* 팬 제어 - 접이식 */}
        <CollapsibleSection title="팬 제어" icon="🌀" badge={fans.length} defaultOpen={true}>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
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
        </CollapsibleSection>

        {/* 천창 스크린 - 접이식 */}
        <CollapsibleSection title="천창 스크린" icon="☀️" badge={skylights.length} headerColor="bg-amber-400" defaultOpen={false}>
          <div className="grid grid-cols-1 gap-2">
            {skylights.map((skylight) => renderScreenCard(skylight, "border-amber-200", "bg-amber-500"))}
          </div>
        </CollapsibleSection>

        {/* 측창 스크린 - 접이식 */}
        <CollapsibleSection title="측창 스크린" icon="🪟" badge={sidescreens.length} headerColor="bg-blue-400" defaultOpen={false}>
          <div className="grid grid-cols-1 gap-2">
            {sidescreens.map((sidescreen) => renderScreenCard(sidescreen, "border-blue-200", "bg-blue-500"))}
          </div>
        </CollapsibleSection>

        {/* 펌프 제어 - 접이식 */}
        <CollapsibleSection title="펌프 제어" icon="💧" badge={pumps.length} defaultOpen={false}>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
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
        </CollapsibleSection>
      </div>
    </div>
  );
}
