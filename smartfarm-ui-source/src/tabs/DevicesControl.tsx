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

// 장치별 자동 제어 설정
interface DeviceAutoControl {
  enabled: boolean;
  tempMin: number;
  tempMax: number;
}

// 메인밸브(ctlr-0004)는 분무수경 탭에서 제어 - 여기서는 제외

export default function DevicesControl({ deviceState, setDeviceState }: DevicesControlProps) {
  // ESP32 장치별 연결 상태 (12개)
  const [esp32Status, setEsp32Status] = useState<Record<string, boolean>>({});

  // HiveMQ 연결 상태
  const [mqttConnected, setMqttConnected] = useState(false);

  // 수동/자동 모드
  const [controlMode, setControlMode] = useState<"manual" | "auto">("manual");

  // 각 ESP32 장치별 자동 제어 설정
  const [deviceAutoControls, setDeviceAutoControls] = useState<Record<string, DeviceAutoControl>>(
    ESP32_CONTROLLERS.reduce((acc, controller) => {
      acc[controller.controllerId] = {
        enabled: false,
        tempMin: 18,
        tempMax: 28,
      };
      return acc;
    }, {} as Record<string, DeviceAutoControl>)
  );

  // 메인밸브(ctlr-0004)는 분무수경 탭에서 제어

  // 천창/측창 퍼센트 입력 임시 상태
  const [percentageInputs, setPercentageInputs] = useState<Record<string, string>>({});

  // 천창/측창 타이머 참조 (작동 중 타이머를 추적하여 취소 가능)
  const percentageTimers = useRef<Record<string, NodeJS.Timeout>>({});

  // 천창/측창 작동 상태 (idle: 대기중, running: 작동중, completed: 완료)
  const [operationStatus, setOperationStatus] = useState<Record<string, 'idle' | 'running' | 'completed'>>({});

  // 천창/측창 현재 위치 추적 (0~100%)
  const [currentPosition, setCurrentPosition] = useState<Record<string, number>>({});


  // 자동 제어 설정 저장 (변경 시마다 API 호출)
  useEffect(() => {
    const saveSettings = async () => {
      try {
        await fetch('/api/smartfarm/save_auto_control.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            enabled: controlMode === 'auto',
            devices: deviceAutoControls
          })
        });
        console.log('[AUTO] Settings saved to server');
      } catch (error) {
        console.error('[AUTO] Failed to save settings:', error);
      }
    };

    // 초기 로딩이 아닐 때만 저장 (debounce 효과)
    const timer = setTimeout(saveSettings, 1000);
    return () => clearTimeout(timer);
  }, [controlMode, deviceAutoControls]);


  // 서버에서 가져온 평균 온습도 (5분 평균)
  const [averageValues, setAverageValues] = useState<{
    avgTemperature: number | null;
    avgHumidity: number | null;
  }>({
    avgTemperature: null,
    avgHumidity: null,
  });

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

  // 서버에서 평균 온습도 가져오기 (3초마다)
  useEffect(() => {
    const fetchAverageValues = async () => {
      try {
        const response = await fetch('/api/smartfarm/get_average_values.php');
        const data = await response.json();

        if (data.success) {
          setAverageValues({
            avgTemperature: data.data.avgTemperature,
            avgHumidity: data.data.avgHumidity,
          });
        }
      } catch (error) {
        console.error('Failed to fetch average values:', error);
      }
    };

    // 즉시 실행
    fetchAverageValues();

    // 3초마다 갱신
    const interval = setInterval(fetchAverageValues, 3000);
    return () => clearInterval(interval);
  }, []);

  // 센서 데이터는 백그라운드 MQTT 데몬이 수집하고 DB에 저장
  // DevicesControl은 서버 API에서 평균값만 읽어옴 (위의 useEffect 참고)

  // 자동 제어 로직 (서버에서 가져온 평균값 사용) - API 호출
  useEffect(() => {
    if (controlMode !== "auto") return;

    // 서버에서 가져온 평균값 사용
    const avgTemp = averageValues.avgTemperature;

    if (avgTemp === null) return;

    // 자동 제어가 활성화된 장치들만 제어
    ESP32_CONTROLLERS.forEach(async (controller) => {
      const autoControl = deviceAutoControls[controller.controllerId];
      if (!autoControl?.enabled) return;

      // 온도 기반 제어
      if (avgTemp > autoControl.tempMax) {
        // 온도가 높으면 팬 켜기, 천창/측창 스크린 열기

        // 팬 제어
        if (controller.controllerId === "ctlr-0001" || controller.controllerId === "ctlr-0002") {
          await sendDeviceCommand(controller.controllerId, "fan1", "ON");
          console.log(`[AUTO] ${controller.name} 팬 ON (온도: ${avgTemp}°C > ${autoControl.tempMax}°C)`);
        }

        // 천창 스크린 제어 (ctlr-0012)
        if (controller.controllerId === "ctlr-0012") {
          await sendDeviceCommand("ctlr-0012", "windowL", "OPEN");
          await sendDeviceCommand("ctlr-0012", "windowR", "OPEN");
          console.log(`[AUTO] 천창 스크린 열기 (온도: ${avgTemp}°C > ${autoControl.tempMax}°C)`);
        }

        // 측창 스크린 제어 (ctlr-0021)
        if (controller.controllerId === "ctlr-0021") {
          await sendDeviceCommand("ctlr-0021", "sideL", "OPEN");
          await sendDeviceCommand("ctlr-0021", "sideR", "OPEN");
          console.log(`[AUTO] 측창 스크린 열기 (온도: ${avgTemp}°C > ${autoControl.tempMax}°C)`);
        }
      } else if (avgTemp < autoControl.tempMin) {
        // 온도가 낮으면 팬 끄기, 천창/측창 스크린 닫기

        // 팬 제어
        if (controller.controllerId === "ctlr-0001" || controller.controllerId === "ctlr-0002") {
          await sendDeviceCommand(controller.controllerId, "fan1", "OFF");
          console.log(`[AUTO] ${controller.name} 팬 OFF (온도: ${avgTemp}°C < ${autoControl.tempMin}°C)`);
        }

        // 천창 스크린 제어 (ctlr-0012)
        if (controller.controllerId === "ctlr-0012") {
          await sendDeviceCommand("ctlr-0012", "windowL", "CLOSE");
          await sendDeviceCommand("ctlr-0012", "windowR", "CLOSE");
          console.log(`[AUTO] 천창 스크린 닫기 (온도: ${avgTemp}°C < ${autoControl.tempMin}°C)`);
        }

        // 측창 스크린 제어 (ctlr-0021)
        if (controller.controllerId === "ctlr-0021") {
          await sendDeviceCommand("ctlr-0021", "sideL", "CLOSE");
          await sendDeviceCommand("ctlr-0021", "sideR", "CLOSE");
          console.log(`[AUTO] 측창 스크린 닫기 (온도: ${avgTemp}°C < ${autoControl.tempMin}°C)`);
        }
      }
    });
  }, [averageValues, controlMode, deviceAutoControls]);

  // 메인밸브(ctlr-0004)는 분무수경 탭에서만 제어

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

  // 천창/측창 제어 핸들러 (OPEN/CLOSE/STOP) - API 호출
  const handleSkylightCommand = async (deviceId: string, command: "OPEN" | "CLOSE" | "STOP") => {
    // 천창과 측창 모두에서 장치 찾기
    const device = [...skylights, ...sidescreens].find((d) => d.id === deviceId);
    if (device) {
      console.log(`[WINDOW] ${device.name} - ${command}`);

      // commandTopic에서 실제 MQTT deviceId 추출
      // 예: "tansaeng/ctlr-0011/windowL/cmd" → "windowL"
      // 예: "tansaeng/ctlr-0021/sideL/cmd" → "sideL"
      const topicParts = device.commandTopic.split('/');
      const mqttDeviceId = topicParts[2]; // windowL, windowR, sideL, sideR

      // 작동 상태 업데이트
      if (command === "OPEN" || command === "CLOSE") {
        // 열기/닫기 명령 시 작동중 상태로 변경
        setOperationStatus(prev => ({
          ...prev,
          [deviceId]: 'running'
        }));
      } else if (command === "STOP") {
        // 정지 명령 시 idle 상태로 변경
        setOperationStatus(prev => ({
          ...prev,
          [deviceId]: 'idle'
        }));
      }

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
            {/* 연결 상태 표시 */}
            <div className="flex items-center gap-3">
              {/* HiveMQ 연결 상태 */}
              <div className="flex items-center gap-2 bg-purple-50 border border-purple-200 px-3 py-1.5 rounded-md">
                <div
                  className={`
                  w-2.5 h-2.5 rounded-full
                  ${mqttConnected ? "bg-green-500 animate-pulse" : "bg-red-500"}
                `}
                ></div>
                <span className="text-xs font-medium text-gray-900">
                  HiveMQ {mqttConnected ? "연결됨" : "연결 끊김"}
                </span>
              </div>
              {/* ESP32 전체 연결 상태 */}
              <div className="flex items-center gap-2 bg-farm-50 border border-farm-200 px-3 py-1.5 rounded-md">
                <div
                  className={`
                  w-2.5 h-2.5 rounded-full
                  ${connectedCount > 0 ? "bg-farm-500 animate-pulse" : "bg-gray-400"}
                `}
                ></div>
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
            {/* 모드 토글 스위치 */}
            <div className="flex items-center gap-3">
              <span className="text-xs text-gray-800 font-medium">수동</span>
              <button
                onClick={() => setControlMode(controlMode === "manual" ? "auto" : "manual")}
                className={`relative inline-flex h-6 w-12 items-center rounded-full transition-colors ${
                  controlMode === "auto" ? "bg-green-500" : "bg-gray-300"
                }`}
              >
                <span
                  className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                    controlMode === "auto" ? "translate-x-7" : "translate-x-1"
                  }`}
                />
              </button>
              <span className="text-xs text-gray-800 font-medium">자동</span>
            </div>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-4">
            {/* 현재 평균 온습도 표시 */}
            <div className="mb-4 grid grid-cols-2 gap-3">
              <div className="p-3 bg-farm-50 rounded-lg border border-farm-200">
                <span className="text-xs text-gray-600">평균 온도:</span>
                <span className="ml-2 text-sm font-semibold text-gray-900">
                  {averageValues.avgTemperature !== null
                    ? `${averageValues.avgTemperature.toFixed(1)}°C`
                    : "N/A"}
                </span>
              </div>
              <div className="p-3 bg-farm-50 rounded-lg border border-farm-200">
                <span className="text-xs text-gray-600">평균 습도:</span>
                <span className="ml-2 text-sm font-semibold text-gray-900">
                  {averageValues.avgHumidity !== null
                    ? `${averageValues.avgHumidity.toFixed(1)}%`
                    : "N/A"}
                </span>
              </div>
            </div>

            {/* 장치별 자동 제어 설정 - 항상 표시 */}
            <div className="space-y-4">
              <h3 className="text-sm font-semibold text-gray-900 mb-3">ESP32 장치별 자동 제어</h3>

              {ESP32_CONTROLLERS.map((controller) => {
                const autoControl = deviceAutoControls[controller.controllerId];
                const isConnected = esp32Status[controller.controllerId] === true;

                return (
                  <div
                    key={controller.id}
                    className="p-4 bg-gray-50 rounded-lg border border-gray-200"
                  >
                    {/* 장치 헤더 */}
                    <div className="flex items-center justify-between mb-3">
                      <div className="flex items-center gap-2">
                        <div
                          className={`w-2 h-2 rounded-full flex-shrink-0 ${
                            isConnected ? "bg-green-500 animate-pulse" : "bg-gray-400"
                          }`}
                        ></div>
                        <span className="text-sm font-semibold text-gray-900">
                          {controller.name}
                        </span>
                        <span className="text-xs text-gray-500">
                          ({controller.controllerId})
                        </span>
                      </div>

                      {/* 자동 제어 ON/OFF 토글 */}
                      <button
                        onClick={() =>
                          setDeviceAutoControls({
                            ...deviceAutoControls,
                            [controller.controllerId]: {
                              ...autoControl,
                              enabled: !autoControl.enabled,
                            },
                          })
                        }
                        className={`px-4 py-1.5 rounded-md text-xs font-medium transition-colors ${
                          autoControl.enabled
                            ? "bg-green-500 text-white hover:bg-green-600"
                            : "bg-gray-300 text-gray-700 hover:bg-gray-400"
                        }`}
                      >
                        {autoControl.enabled ? "제어 ON" : "제어 OFF"}
                      </button>
                    </div>

                    {/* 온도 범위 설정 */}
                    <div className="mb-3">
                      <label className="text-xs text-gray-700 font-medium mb-1.5 block">
                        온도 범위 (°C)
                      </label>
                      <div className="flex items-center gap-2">
                        <input
                          type="number"
                          value={autoControl.tempMin}
                          onChange={(e) =>
                            setDeviceAutoControls({
                              ...deviceAutoControls,
                              [controller.controllerId]: {
                                ...autoControl,
                                tempMin: parseFloat(e.target.value),
                              },
                            })
                          }
                          className="flex-1 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-farm-500"
                          step="0.5"
                          placeholder="최소"
                        />
                        <span className="text-xs text-gray-500">~</span>
                        <input
                          type="number"
                          value={autoControl.tempMax}
                          onChange={(e) =>
                            setDeviceAutoControls({
                              ...deviceAutoControls,
                              [controller.controllerId]: {
                                ...autoControl,
                                tempMax: parseFloat(e.target.value),
                              },
                            })
                          }
                          className="flex-1 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-farm-500"
                          step="0.5"
                          placeholder="최대"
                        />
                      </div>
                    </div>

                    {/* 메인밸브(ctlr-0004)는 분무수경 탭에서 제어 */}
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

        {/* 천창 스크린 제어 섹션 */}
        <section className="mb-3">
          <header className="bg-amber-400 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">
              ☀️ 천창 스크린 제어
            </h2>
            <span className="text-xs text-gray-800">총 {skylights.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(350px,1fr))] gap-3">
              {skylights.map((skylight) => (
                <div
                  key={skylight.id}
                  className="bg-white border-2 border-amber-200 rounded-lg p-4 shadow-sm"
                >
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-semibold text-gray-900">
                      {skylight.name}
                    </h3>
                    <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                      {skylight.esp32Id}
                    </span>
                  </div>

                  {/* 버튼 제어 */}
                  <div className="mb-4">
                    <div className="flex items-center justify-between mb-2">
                      <p className="text-xs text-gray-600 font-medium">버튼 제어</p>
                      {/* 작동 상태 표시 */}
                      {operationStatus[skylight.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[skylight.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                          ✓ 완료
                        </span>
                      )}
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleSkylightCommand(skylight.id, "OPEN")}
                        className="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ▲ 열기
                      </button>
                      <button
                        onClick={() => handleSkylightCommand(skylight.id, "STOP")}
                        className="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ■ 정지
                      </button>
                      <button
                        onClick={() => handleSkylightCommand(skylight.id, "CLOSE")}
                        className="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ▼ 닫기
                      </button>
                    </div>
                  </div>

                  {/* 퍼센트 입력 제어 */}
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <p className="text-xs text-gray-600 font-medium">개폐 퍼센트 설정</p>
                      {/* 작동 상태 표시 */}
                      {operationStatus[skylight.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[skylight.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                          ✓ 완료
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-2 mb-2">
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={percentageInputs[skylight.id] ?? (deviceState[skylight.id]?.targetPercentage ?? 0)}
                        onChange={(e) => setPercentageInputs({
                          ...percentageInputs,
                          [skylight.id]: e.target.value
                        })}
                        className="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="0-100"
                      />
                      <span className="text-sm font-semibold text-gray-900 min-w-[2rem]">%</span>
                      <button
                        onClick={() => handleSavePercentage(skylight.id)}
                        className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-md transition-colors"
                      >
                        저장
                      </button>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="flex-1 text-xs text-gray-600 space-y-1">
                        <div>
                          현재 위치: <span className="font-semibold text-gray-800">
                            {currentPosition[skylight.id] ?? 0}%
                          </span>
                        </div>
                        <div>
                          저장된 값: <span className="font-semibold text-amber-600">
                            {deviceState[skylight.id]?.targetPercentage ?? 0}%
                          </span>
                        </div>
                      </div>
                      <button
                        onClick={() => handleExecutePercentage(skylight.id)}
                        className="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-md transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
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
        <section className="mb-3">
          <header className="bg-blue-400 px-4 py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-base font-semibold flex items-center gap-1.5 text-gray-900">
              🪟 측창 스크린 제어
            </h2>
            <span className="text-xs text-gray-800">총 {sidescreens.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(350px,1fr))] gap-3">
              {sidescreens.map((sidescreen) => (
                <div
                  key={sidescreen.id}
                  className="bg-white border-2 border-blue-200 rounded-lg p-4 shadow-sm"
                >
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-semibold text-gray-900">
                      {sidescreen.name}
                    </h3>
                    <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                      {sidescreen.esp32Id}
                    </span>
                  </div>

                  {/* 버튼 제어 */}
                  <div className="mb-4">
                    <div className="flex items-center justify-between mb-2">
                      <p className="text-xs text-gray-600 font-medium">버튼 제어</p>
                      {/* 작동 상태 표시 */}
                      {operationStatus[sidescreen.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[sidescreen.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                          ✓ 완료
                        </span>
                      )}
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleSkylightCommand(sidescreen.id, "OPEN")}
                        className="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ▲ 열기
                      </button>
                      <button
                        onClick={() => handleSkylightCommand(sidescreen.id, "STOP")}
                        className="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ■ 정지
                      </button>
                      <button
                        onClick={() => handleSkylightCommand(sidescreen.id, "CLOSE")}
                        className="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ▼ 닫기
                      </button>
                    </div>
                  </div>

                  {/* 퍼센트 입력 제어 */}
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <p className="text-xs text-gray-600 font-medium">개폐 퍼센트 설정</p>
                      {/* 작동 상태 표시 */}
                      {operationStatus[sidescreen.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[sidescreen.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">
                          ✓ 완료
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-2 mb-2">
                      <input
                        type="number"
                        min="0"
                        max="100"
                        value={percentageInputs[sidescreen.id] ?? (deviceState[sidescreen.id]?.targetPercentage ?? 0)}
                        onChange={(e) => setPercentageInputs({
                          ...percentageInputs,
                          [sidescreen.id]: e.target.value
                        })}
                        className="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="0-100"
                      />
                      <span className="text-sm font-semibold text-gray-900 min-w-[2rem]">%</span>
                      <button
                        onClick={() => handleSavePercentage(sidescreen.id)}
                        className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-md transition-colors"
                      >
                        저장
                      </button>
                    </div>
                    <div className="flex items-center gap-2">
                      <div className="flex-1 text-xs text-gray-600 space-y-1">
                        <div>
                          현재 위치: <span className="font-semibold text-gray-800">
                            {currentPosition[sidescreen.id] ?? 0}%
                          </span>
                        </div>
                        <div>
                          저장된 값: <span className="font-semibold text-blue-600">
                            {deviceState[sidescreen.id]?.targetPercentage ?? 0}%
                          </span>
                        </div>
                      </div>
                      <button
                        onClick={() => handleExecutePercentage(sidescreen.id)}
                        className="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold rounded-md transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
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
