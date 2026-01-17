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
  // ESP32 장치별 연결 상태 (12개)
  const [esp32Status, setEsp32Status] = useState<Record<string, boolean>>({});

  // HiveMQ 연결 상태
  const [mqttConnected, setMqttConnected] = useState(false);

  // 천창/측창 퍼센트 입력 임시 상태
  const [percentageInputs, setPercentageInputs] = useState<Record<string, string>>({});

  // 천창/측창 타이머 참조 (작동 중 타이머를 추적하여 취소 가능)
  const percentageTimers = useRef<Record<string, NodeJS.Timeout>>({});

  // 천창/측창 작동 상태 (idle: 대기중, running: 작동중, completed: 완료)
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
    // MQTT 클라이언트 초기화
    getMqttClient();

    // 연결 상태 변경 감지
    const unsubscribe = onConnectionChange((connected) => {
      setMqttConnected(connected);
      console.log(`[MQTT] Connection status: ${connected ? 'Connected' : 'Disconnected'}`);
    });

    // 클린업
    return () => {
      unsubscribe();
    };
  }, []);

  // ESP32 상태 API 폴링 (데몬이 수집한 상태 조회)
  useEffect(() => {
    const fetchESP32Status = async () => {
      try {
        const response = await fetch("/api/device_status.php");
        const result = await response.json();

        if (result.success) {
          // 데몬이 수집한 상태로 업데이트
          const newStatus: Record<string, boolean> = {};
          Object.entries(result.devices).forEach(([controllerId, info]: [string, any]) => {
            newStatus[controllerId] = info.is_online;
          });
          setEsp32Status(newStatus);
          console.log("[API] ESP32 상태 업데이트:", newStatus);
        }
      } catch (error) {
        console.error("[API] Failed to fetch ESP32 status:", error);
      }
    };

    // 즉시 실행
    fetchESP32Status();

    // 5초마다 갱신 (데몬이 실시간으로 수집하므로 빠르게 폴링)
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
      // commandTopic에서 실제 MQTT deviceId 추출
      // 예: "tansaeng/ctlr-0001/fan1/cmd" → "fan1"
      const topicParts = device.commandTopic.split('/');
      const mqttDeviceId = topicParts[2];

      // API를 통해 명령 전송
      const command = isOn ? "ON" : "OFF";
      const result = await sendDeviceCommand(device.esp32Id, mqttDeviceId, command);

      if (result.success) {
        console.log(`[API SUCCESS] ${device.name} - ${command}`);
      } else {
        console.error(`[API ERROR] ${result.message}`);
      }
    }
  };

  // 천창 제어 핸들러 (OPEN/CLOSE/STOP) - API 호출
  const handleSkylightCommand = async (deviceId: string, command: "OPEN" | "CLOSE" | "STOP") => {
    const device = skylights.find((d) => d.id === deviceId);
    if (device) {
      console.log(`[SKYLIGHT] ${device.name} - ${command}`);

      // commandTopic에서 실제 MQTT deviceId 추출
      // 예: "tansaeng/ctlr-0011/windowL/cmd" → "windowL"
      const topicParts = device.commandTopic.split('/');
      const mqttDeviceId = topicParts[2]; // windowL 또는 windowR

      // API를 통해 명령 전송 (데몬이 MQTT 발행)
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

    // 상태 저장
    const newState = {
      ...deviceState,
      [deviceId]: {
        ...deviceState[deviceId],
        targetPercentage: percentage,
        lastSavedAt: new Date().toISOString(),
      },
    };
    setDeviceState(newState);
    console.log(`[SAVE] ${deviceId} - ${percentage}% 저장됨`);
  };

  // 천창/측창 퍼센트 작동 핸들러 (절대 위치 기반)
  const handleExecutePercentage = async (deviceId: string) => {
    const targetPercentage = deviceState[deviceId]?.targetPercentage ?? 0;
    const currentPos = currentPosition[deviceId] ?? 0;

    // 천창과 측창 모두에서 장치 찾기
    const device = [...skylights, ...sidescreens].find((d) => d.id === deviceId);
    if (!device) return;

    // 이동해야 할 거리 계산 (목표 - 현재)
    const difference = targetPercentage - currentPos;

    // 이미 목표 위치에 있으면 작동하지 않음
    if (difference === 0) {
      alert(`이미 ${targetPercentage}% 위치에 있습니다.`);
      return;
    }

    // 이전 타이머가 있으면 취소
    if (percentageTimers.current[deviceId]) {
      clearTimeout(percentageTimers.current[deviceId]);
      delete percentageTimers.current[deviceId];
    }

    // 작동 시작 - 상태를 "작동중"으로 변경
    setOperationStatus({
      ...operationStatus,
      [deviceId]: 'running'
    });

    // 전체 시간 설정 (0% → 100%)
    // ctlr-0012: 천창 스크린 = 5분 = 300초
    // ctlr-0021: 측창 스크린 = 2분 = 120초
    const fullTimeSeconds = device.esp32Id === "ctlr-0012" ? 300 : 120;

    // 이동 거리(절대값)에 따른 시간 계산 (초)
    const movementPercentage = Math.abs(difference);
    const targetTimeSeconds = (movementPercentage / 100) * fullTimeSeconds;

    // 열기 또는 닫기 결정
    const command = difference > 0 ? "OPEN" : "CLOSE";
    const action = difference > 0 ? "열기" : "닫기";

    console.log(`[EXECUTE] ${device.name} - 현재: ${currentPos}%, 목표: ${targetPercentage}%, 이동: ${difference > 0 ? '+' : ''}${difference}% (${targetTimeSeconds.toFixed(1)}초 ${action})`);

    // commandTopic에서 실제 MQTT deviceId 추출
    const topicParts = device.commandTopic.split('/');
    const mqttDeviceId = topicParts[2]; // windowL, windowR, sideL, sideR

    try {
      // 명령 전송
      await sendDeviceCommand(device.esp32Id, mqttDeviceId, command);
      console.log(`[EXECUTE] ${device.name} - ${targetTimeSeconds.toFixed(1)}초 동안 ${action} 시작`);

      // 목표 시간만큼 작동 후 자동 정지
      percentageTimers.current[deviceId] = setTimeout(async () => {
        await sendDeviceCommand(device.esp32Id, mqttDeviceId, "STOP");
        console.log(`[EXECUTE] ${device.name} - ${targetPercentage}% 위치에서 정지`);
        delete percentageTimers.current[deviceId];

        // 현재 위치를 목표 위치로 업데이트
        setCurrentPosition(prev => ({
          ...prev,
          [deviceId]: targetPercentage
        }));

        // 작동 완료 - 상태를 "완료"로 변경
        setOperationStatus(prev => ({
          ...prev,
          [deviceId]: 'completed'
        }));
      }, targetTimeSeconds * 1000);
    } catch (error) {
      console.error(`[EXECUTE ERROR] ${device.name}:`, error);

      // 에러 발생 시 상태 초기화
      setOperationStatus({
        ...operationStatus,
        [deviceId]: 'idle'
      });
    }
  };

  // 연결된 ESP32 개수 계산
  const connectedCount = Object.values(esp32Status).filter(Boolean).length;
  const totalCount = ESP32_CONTROLLERS.length;

  return (
    <div className="bg-gray-50 min-h-screen">
      <div className="max-w-screen-2xl mx-auto p-2 sm:p-3">
        {/* ESP32 연결 상태 헤더 */}
        <header className="bg-white border-2 border-farm-500 px-3 sm:px-4 py-2 sm:py-3 rounded-lg mb-2 sm:mb-3 shadow-md">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
              <h1 className="text-lg sm:text-xl font-bold mb-0.5 sm:mb-1 text-gray-900">장치 제어</h1>
              <p className="text-xs text-gray-600 hidden sm:block">
                팬, 개폐기, 펌프 등 장치를 원격으로 제어합니다
              </p>
            </div>
            {/* 연결 상태 표시 */}
            <div className="flex items-center gap-2 sm:gap-3">
              {/* HiveMQ 연결 상태 */}
              <div className="flex items-center gap-1.5 sm:gap-2 bg-purple-50 border border-purple-200 px-2 sm:px-3 py-1 sm:py-1.5 rounded-md">
                <div
                  className={`
                  w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full flex-shrink-0
                  ${mqttConnected ? "bg-green-500 animate-pulse" : "bg-red-500"}
                `}
                ></div>
                <span className="text-xs font-medium text-gray-900 whitespace-nowrap">
                  <span className="hidden sm:inline">HiveMQ </span>{mqttConnected ? "연결됨" : "끊김"}
                </span>
              </div>
              {/* ESP32 전체 연결 상태 */}
              <div className="flex items-center gap-1.5 sm:gap-2 bg-farm-50 border border-farm-200 px-2 sm:px-3 py-1 sm:py-1.5 rounded-md">
                <div
                  className={`
                  w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full flex-shrink-0
                  ${connectedCount > 0 ? "bg-farm-500 animate-pulse" : "bg-gray-400"}
                `}
                ></div>
                <span className="text-xs font-medium text-gray-900 whitespace-nowrap">
                  <span className="hidden sm:inline">장치 </span>{connectedCount}/{totalCount}
                </span>
              </div>
            </div>
          </div>
        </header>

        {/* ESP32 장치 연결 상태 목록 */}
        <section className="mb-2 sm:mb-3">
          <header className="bg-farm-500 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
              ESP32 연결 상태
            </h2>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-2 sm:p-3">
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-1.5 sm:gap-2">
              {ESP32_CONTROLLERS.map((controller) => {
                const isConnected = esp32Status[controller.controllerId] === true;

                return (
                  <div
                    key={controller.id}
                    className={`flex items-center gap-1.5 sm:gap-2 px-2 sm:px-3 py-1.5 sm:py-2 rounded-md border transition-colors ${
                      isConnected
                        ? "bg-green-50 border-green-300"
                        : "bg-gray-50 border-gray-300"
                    }`}
                  >
                    <div
                      className={`w-1.5 sm:w-2 h-1.5 sm:h-2 rounded-full flex-shrink-0 ${
                        isConnected ? "bg-green-500 animate-pulse" : "bg-gray-400"
                      }`}
                    ></div>
                    <div className="flex-1 min-w-0">
                      <span className="text-[10px] sm:text-xs font-medium text-gray-900 block truncate">
                        {controller.name}
                      </span>
                      <span className="text-[10px] sm:text-xs text-gray-500 hidden sm:block">
                        {controller.controllerId}
                      </span>
                    </div>
                    <span
                      className={`text-[10px] sm:text-xs font-medium flex-shrink-0 ${
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
        <section className="mb-2 sm:mb-3">
          <header className="bg-farm-500 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
              팬 제어
            </h2>
            <span className="text-[10px] sm:text-xs text-gray-800">{fans.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-1.5 sm:p-3">
            <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-1.5 sm:gap-3">
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
        <section className="mb-2 sm:mb-3">
          <header className="bg-amber-400 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
              천창 스크린
            </h2>
            <span className="text-[10px] sm:text-xs text-gray-800">{skylights.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-2 sm:p-3">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-2 sm:gap-3">
              {skylights.map((skylight) => (
                <div
                  key={skylight.id}
                  className="bg-white border-2 border-amber-200 rounded-lg p-2 sm:p-4 shadow-sm"
                >
                  <div className="flex items-center justify-between mb-2 sm:mb-3">
                    <h3 className="text-xs sm:text-sm font-semibold text-gray-900">
                      {skylight.name}
                    </h3>
                    <span className="text-[10px] sm:text-xs text-gray-500 bg-gray-100 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded">
                      {skylight.esp32Id}
                    </span>
                  </div>

                  {/* 버튼 제어 */}
                  <div className="mb-2 sm:mb-4">
                    <p className="text-[10px] sm:text-xs text-gray-600 font-medium mb-1.5 sm:mb-2">버튼 제어</p>
                    <div className="flex gap-1.5 sm:gap-2">
                      <button
                        onClick={() => handleSkylightCommand(skylight.id, "OPEN")}
                        className="flex-1 bg-green-500 hover:bg-green-600 active:bg-green-700 text-white font-semibold py-2 sm:py-3 px-2 sm:px-4 rounded-md transition-colors text-xs sm:text-sm"
                      >
                        열기
                      </button>
                      <button
                        onClick={() => handleSkylightCommand(skylight.id, "STOP")}
                        className="flex-1 bg-yellow-500 hover:bg-yellow-600 active:bg-yellow-700 text-white font-semibold py-2 sm:py-3 px-2 sm:px-4 rounded-md transition-colors text-xs sm:text-sm"
                      >
                        정지
                      </button>
                      <button
                        onClick={() => handleSkylightCommand(skylight.id, "CLOSE")}
                        className="flex-1 bg-red-500 hover:bg-red-600 active:bg-red-700 text-white font-semibold py-2 sm:py-3 px-2 sm:px-4 rounded-md transition-colors text-xs sm:text-sm"
                      >
                        닫기
                      </button>
                    </div>
                  </div>

                  {/* 퍼센트 입력 제어 */}
                  <div>
                    <div className="flex items-center justify-between mb-1.5 sm:mb-2">
                      <p className="text-[10px] sm:text-xs text-gray-600 font-medium">개폐 퍼센트 설정</p>
                      {/* 작동 상태 표시 */}
                      {operationStatus[skylight.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 bg-blue-100 text-blue-700 text-[10px] sm:text-xs font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[skylight.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 bg-green-100 text-green-700 text-[10px] sm:text-xs font-semibold rounded-full">
                          완료
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-1.5 sm:gap-2 mb-1.5 sm:mb-2">
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={percentageInputs[skylight.id] ?? (deviceState[skylight.id]?.targetPercentage ?? 0)}
                        onChange={(e) => setPercentageInputs({
                          ...percentageInputs,
                          [skylight.id]: e.target.value
                        })}
                        className="flex-1 px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="0-100"
                      />
                      <span className="text-xs sm:text-sm font-semibold text-gray-900">%</span>
                      <button
                        onClick={() => handleSavePercentage(skylight.id)}
                        className="px-2 sm:px-4 py-1.5 sm:py-2 bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white text-xs sm:text-sm font-medium rounded-md transition-colors"
                      >
                        저장
                      </button>
                    </div>
                    <div className="flex items-center gap-1.5 sm:gap-2">
                      <div className="flex-1 text-[10px] sm:text-xs text-gray-600 space-y-0.5 sm:space-y-1">
                        <div>
                          현재: <span className="font-semibold text-gray-800">
                            {currentPosition[skylight.id] ?? 0}%
                          </span>
                        </div>
                        <div>
                          저장: <span className="font-semibold text-amber-600">
                            {deviceState[skylight.id]?.targetPercentage ?? 0}%
                          </span>
                        </div>
                      </div>
                      <button
                        onClick={() => handleExecutePercentage(skylight.id)}
                        className="px-2 sm:px-4 py-1.5 sm:py-2 bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white text-xs sm:text-sm font-semibold rounded-md transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                        disabled={operationStatus[skylight.id] === 'running'}
                      >
                        작동
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* 측창 스크린 제어 섹션 */}
        <section className="mb-2 sm:mb-3">
          <header className="bg-blue-400 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
              측창 스크린
            </h2>
            <span className="text-[10px] sm:text-xs text-gray-800">{sidescreens.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-2 sm:p-3">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-2 sm:gap-3">
              {sidescreens.map((sidescreen) => (
                <div
                  key={sidescreen.id}
                  className="bg-white border-2 border-blue-200 rounded-lg p-2 sm:p-4 shadow-sm"
                >
                  <div className="flex items-center justify-between mb-2 sm:mb-3">
                    <h3 className="text-xs sm:text-sm font-semibold text-gray-900">
                      {sidescreen.name}
                    </h3>
                    <span className="text-[10px] sm:text-xs text-gray-500 bg-gray-100 px-1.5 sm:px-2 py-0.5 sm:py-1 rounded">
                      {sidescreen.esp32Id}
                    </span>
                  </div>

                  {/* 버튼 제어 */}
                  <div className="mb-2 sm:mb-4">
                    <p className="text-[10px] sm:text-xs text-gray-600 font-medium mb-1.5 sm:mb-2">버튼 제어</p>
                    <div className="flex gap-1.5 sm:gap-2">
                      <button
                        onClick={() => handleSkylightCommand(sidescreen.id, "OPEN")}
                        className="flex-1 bg-green-500 hover:bg-green-600 active:bg-green-700 text-white font-semibold py-2 sm:py-3 px-2 sm:px-4 rounded-md transition-colors text-xs sm:text-sm"
                      >
                        열기
                      </button>
                      <button
                        onClick={() => handleSkylightCommand(sidescreen.id, "STOP")}
                        className="flex-1 bg-yellow-500 hover:bg-yellow-600 active:bg-yellow-700 text-white font-semibold py-2 sm:py-3 px-2 sm:px-4 rounded-md transition-colors text-xs sm:text-sm"
                      >
                        정지
                      </button>
                      <button
                        onClick={() => handleSkylightCommand(sidescreen.id, "CLOSE")}
                        className="flex-1 bg-red-500 hover:bg-red-600 active:bg-red-700 text-white font-semibold py-2 sm:py-3 px-2 sm:px-4 rounded-md transition-colors text-xs sm:text-sm"
                      >
                        닫기
                      </button>
                    </div>
                  </div>

                  {/* 퍼센트 입력 제어 */}
                  <div>
                    <div className="flex items-center justify-between mb-1.5 sm:mb-2">
                      <p className="text-[10px] sm:text-xs text-gray-600 font-medium">개폐 퍼센트 설정</p>
                      {/* 작동 상태 표시 */}
                      {operationStatus[sidescreen.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 bg-blue-100 text-blue-700 text-[10px] sm:text-xs font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[sidescreen.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-1.5 sm:px-2 py-0.5 sm:py-1 bg-green-100 text-green-700 text-[10px] sm:text-xs font-semibold rounded-full">
                          완료
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-1.5 sm:gap-2 mb-1.5 sm:mb-2">
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={percentageInputs[sidescreen.id] ?? (deviceState[sidescreen.id]?.targetPercentage ?? 0)}
                        onChange={(e) => setPercentageInputs({
                          ...percentageInputs,
                          [sidescreen.id]: e.target.value
                        })}
                        className="flex-1 px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="0-100"
                      />
                      <span className="text-xs sm:text-sm font-semibold text-gray-900">%</span>
                      <button
                        onClick={() => handleSavePercentage(sidescreen.id)}
                        className="px-2 sm:px-4 py-1.5 sm:py-2 bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white text-xs sm:text-sm font-medium rounded-md transition-colors"
                      >
                        저장
                      </button>
                    </div>
                    <div className="flex items-center gap-1.5 sm:gap-2">
                      <div className="flex-1 text-[10px] sm:text-xs text-gray-600 space-y-0.5 sm:space-y-1">
                        <div>
                          현재: <span className="font-semibold text-gray-800">
                            {currentPosition[sidescreen.id] ?? 0}%
                          </span>
                        </div>
                        <div>
                          저장: <span className="font-semibold text-blue-600">
                            {deviceState[sidescreen.id]?.targetPercentage ?? 0}%
                          </span>
                        </div>
                      </div>
                      <button
                        onClick={() => handleExecutePercentage(sidescreen.id)}
                        className="px-2 sm:px-4 py-1.5 sm:py-2 bg-blue-500 hover:bg-blue-600 active:bg-blue-700 text-white text-xs sm:text-sm font-semibold rounded-md transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                        disabled={operationStatus[sidescreen.id] === 'running'}
                      >
                        작동
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* 펌프 제어 섹션 */}
        <section className="mb-2 sm:mb-3">
          <header className="bg-farm-500 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
              펌프 제어
            </h2>
            <span className="text-[10px] sm:text-xs text-gray-800">{pumps.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-1.5 sm:p-3">
            <div className="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-1.5 sm:gap-3">
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
