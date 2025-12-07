import { useState, useEffect } from "react";
import { getDevicesByType } from "../config/devices";
import { ESP32_CONTROLLERS } from "../config/esp32Controllers";
import type { DeviceDesiredState } from "../types";
import DeviceCard from "../components/DeviceCard";
import { publishCommand, getMqttClient, onConnectionChange } from "../mqtt/mqttClient";

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

// 메인밸브 시간대별 스케줄 설정
interface ValveTimeSlot {
  startTime: string; // HH:mm 형식
  endTime: string; // HH:mm 형식
  openMinutes: number; // 밸브 열림 시간 (분)
  openSeconds: number; // 밸브 열림 시간 (초)
  closeMinutes: number; // 밸브 닫힘 시간 (분)
  closeSeconds: number; // 밸브 닫힘 시간 (초)
}

// 메인밸브 스케줄 설정
interface ValveSchedule {
  enabled: boolean; // 스케줄 활성화 여부
  mode: "manual" | "auto"; // 수동/자동 모드
  timeSlots: ValveTimeSlot[]; // 시간대별 설정 (최대 2개 - 주간/야간)
  useEnvironmentConditions: boolean; // 온도 조건 사용 여부
  maxTemperature: number; // 최대 온도 (°C)
}

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

  // 메인밸브 스케줄 설정
  const [valveSchedule, setValveSchedule] = useState<ValveSchedule>({
    enabled: false,
    mode: "manual",
    timeSlots: [
      {
        startTime: "06:00",
        endTime: "18:00",
        openMinutes: 0,
        openSeconds: 10,
        closeMinutes: 5,
        closeSeconds: 0,
      },
      {
        startTime: "18:00",
        endTime: "06:00",
        openMinutes: 0,
        openSeconds: 10,
        closeMinutes: 10,
        closeSeconds: 0,
      },
    ],
    useEnvironmentConditions: false,
    maxTemperature: 30,
  });

  // 메인밸브 상태
  const [valveCurrentState, setValveCurrentState] = useState<"OPEN" | "CLOSE">("CLOSE");
  const [manualValveState, setManualValveState] = useState<boolean>(false); // 수동 모드 ON/OFF

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

  // 메인밸브 스케줄 저장 (변경 시마다 API 호출)
  useEffect(() => {
    const saveSchedule = async () => {
      try {
        await fetch('/api/smartfarm/save_valve_schedule.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(valveSchedule)
        });
        console.log('[VALVE] Schedule saved to server');
      } catch (error) {
        console.error('[VALVE] Failed to save schedule:', error);
      }
    };

    const timer = setTimeout(saveSchedule, 1000);
    return () => clearTimeout(timer);
  }, [valveSchedule]);

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

  // 자동 제어 로직 (서버에서 가져온 평균값 사용)
  useEffect(() => {
    if (controlMode !== "auto") return;

    // 서버에서 가져온 평균값 사용
    const avgTemp = averageValues.avgTemperature;

    if (avgTemp === null) return;

    // 자동 제어가 활성화된 장치들만 제어
    ESP32_CONTROLLERS.forEach((controller) => {
      const autoControl = deviceAutoControls[controller.controllerId];
      if (!autoControl?.enabled) return;

      const client = getMqttClient();

      // 온도 기반 제어
      if (avgTemp > autoControl.tempMax) {
        // 온도가 높으면 팬 켜기, 천창/측창 스크린 열기

        // 팬 제어
        if (controller.controllerId === "ctlr-0001" || controller.controllerId === "ctlr-0002") {
          publishCommand(`tansaeng/${controller.controllerId}/fan1/cmd`, { power: "on" });
        }

        // 천창 스크린 제어 (ctlr-0011)
        if (controller.controllerId === "ctlr-0011") {
          client.publish("tansaeng/ctlr-0011/windowL/cmd", "OPEN", { qos: 1 });
          client.publish("tansaeng/ctlr-0011/windowR/cmd", "OPEN", { qos: 1 });
          console.log(`[AUTO] 천창 스크린 열기 (온도: ${avgTemp}°C > ${autoControl.tempMax}°C)`);
        }

        // 측창 스크린 제어 (ctlr-0012)
        if (controller.controllerId === "ctlr-0012") {
          publishCommand("tansaeng/esp32-node-2/vent_top_left/cmd", { target: 80 });
          publishCommand("tansaeng/esp32-node-2/vent_top_right/cmd", { target: 80 });
          console.log(`[AUTO] 측창 스크린 열기 (온도: ${avgTemp}°C > ${autoControl.tempMax}°C)`);
        }
      } else if (avgTemp < autoControl.tempMin) {
        // 온도가 낮으면 팬 끄기, 천창/측창 스크린 닫기

        // 팬 제어
        if (controller.controllerId === "ctlr-0001" || controller.controllerId === "ctlr-0002") {
          publishCommand(`tansaeng/${controller.controllerId}/fan1/cmd`, { power: "off" });
        }

        // 천창 스크린 제어 (ctlr-0011)
        if (controller.controllerId === "ctlr-0011") {
          client.publish("tansaeng/ctlr-0011/windowL/cmd", "CLOSE", { qos: 1 });
          client.publish("tansaeng/ctlr-0011/windowR/cmd", "CLOSE", { qos: 1 });
          console.log(`[AUTO] 천창 스크린 닫기 (온도: ${avgTemp}°C < ${autoControl.tempMin}°C)`);
        }

        // 측창 스크린 제어 (ctlr-0012)
        if (controller.controllerId === "ctlr-0012") {
          publishCommand("tansaeng/esp32-node-2/vent_top_left/cmd", { target: 20 });
          publishCommand("tansaeng/esp32-node-2/vent_top_right/cmd", { target: 20 });
          console.log(`[AUTO] 측창 스크린 닫기 (온도: ${avgTemp}°C < ${autoControl.tempMin}°C)`);
        }
      }
    });
  }, [averageValues, controlMode, deviceAutoControls]);

  // 메인밸브 수동 제어
  useEffect(() => {
    if (valveSchedule.mode === "manual") {
      const client = getMqttClient();
      const topic = "tansaeng/ctlr-0004/valve1/cmd";
      const command = manualValveState ? "OPEN" : "CLOSE";
      client.publish(topic, command, { qos: 1 });
      setValveCurrentState(command);
      console.log(`[VALVE MANUAL] ${command}`);
    }
  }, [manualValveState, valveSchedule.mode]);

  // 메인밸브 자동 제어 - PHP 데몬이 담당 (React는 관여하지 않음)
  // 자동 모드에서는 서버의 PHP 데몬이 밸브를 제어합니다.

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

  // 천창 제어 핸들러 (OPEN/CLOSE/STOP)
  const handleSkylightCommand = (deviceId: string, command: "OPEN" | "CLOSE" | "STOP") => {
    const device = skylights.find((d) => d.id === deviceId);
    if (device) {
      const client = getMqttClient();
      client.publish(device.commandTopic, command, { qos: 1 });
      console.log(`[SKYLIGHT] ${device.name} - ${command}`);
    }
  };

  // 천창 퍼센트 제어 핸들러 (슬라이더)
  const handleSkylightPercentageChange = (deviceId: string, percentage: number) => {
    const newState = {
      ...deviceState,
      [deviceId]: {
        ...deviceState[deviceId],
        targetPercentage: percentage,
        lastSavedAt: new Date().toISOString(),
      },
    };
    setDeviceState(newState);

    const device = skylights.find((d) => d.id === deviceId);
    if (device) {
      publishCommand(device.commandTopic, { target: percentage });
      console.log(`[SKYLIGHT] ${device.name} - ${percentage}%`);
    }
  };

  // 측창 버튼 제어 핸들러 (OPEN/CLOSE/STOP)
  const handleVentCommand = (deviceId: string, command: "OPEN" | "CLOSE" | "STOP") => {
    const device = vents.find((d) => d.id === deviceId);
    if (device) {
      const client = getMqttClient();
      client.publish(device.commandTopic, command, { qos: 1 });
      console.log(`[VENT] ${device.name} - ${command}`);
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
                const isMainValve = controller.controllerId === "ctlr-0004";

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

                    {/* 메인밸브 스케줄 설정 (ctlr-0004만 표시) */}
                    {isMainValve && (
                      <div className="mt-4 pt-4 border-t border-gray-300">
                        <div className="mb-3">
                          <div className="flex items-center justify-between mb-3">
                            <h4 className="text-xs font-semibold text-gray-900">
                              메인밸브 스케줄 설정
                            </h4>
                            {/* 실시간 상태 표시 LED */}
                            <div className="flex items-center gap-2">
                              <div className="relative flex items-center">
                                <div className={`w-3 h-3 rounded-full ${valveCurrentState === "OPEN" ? "bg-green-500" : "bg-red-500"}`}>
                                  <div className={`absolute inset-0 rounded-full ${valveCurrentState === "OPEN" ? "bg-green-500" : "bg-red-500"} ${valveCurrentState === "OPEN" ? "animate-ping" : ""} opacity-75`}></div>
                                </div>
                              </div>
                              <span className={`text-xs font-bold ${valveCurrentState === "OPEN" ? "text-green-600" : "text-red-600"}`}>
                                {valveCurrentState === "OPEN" ? "열림" : "닫힘"}
                              </span>
                            </div>
                          </div>

                          {/* 수동/자동 전환 스위치 */}
                          <div className="flex items-center justify-between mb-3 p-3 bg-blue-50 border border-blue-200 rounded">
                            <span className="text-xs font-medium text-gray-900">제어 모드</span>
                            <div className="flex items-center gap-3">
                              <span className={`text-xs font-medium ${valveSchedule.mode === "manual" ? "text-blue-600" : "text-gray-500"}`}>수동</span>
                              <button
                                onClick={() => {
                                  const newMode = valveSchedule.mode === "manual" ? "auto" : "manual";
                                  setValveSchedule({
                                    ...valveSchedule,
                                    mode: newMode,
                                  });
                                  // 자동으로 전환 시 수동 스위치 OFF
                                  if (newMode === "auto") {
                                    setManualValveState(false);
                                  }
                                }}
                                className={`relative inline-flex h-6 w-12 items-center rounded-full transition-colors ${
                                  valveSchedule.mode === "auto" ? "bg-green-500" : "bg-gray-400"
                                }`}
                              >
                                <span
                                  className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                    valveSchedule.mode === "auto" ? "translate-x-7" : "translate-x-1"
                                  }`}
                                />
                              </button>
                              <span className={`text-xs font-medium ${valveSchedule.mode === "auto" ? "text-green-600" : "text-gray-500"}`}>자동</span>
                            </div>
                          </div>

                          {/* 수동 모드 ON/OFF 스위치 */}
                          {valveSchedule.mode === "manual" && (
                            <div className="flex items-center justify-between mb-3 p-3 bg-yellow-50 border border-yellow-200 rounded">
                              <span className="text-xs font-medium text-gray-900">수동 밸브 제어</span>
                              <div className="flex items-center gap-3">
                                <span className={`text-xs font-medium ${!manualValveState ? "text-red-600" : "text-gray-500"}`}>OFF</span>
                                <button
                                  onClick={() => setManualValveState(!manualValveState)}
                                  className={`relative inline-flex h-6 w-12 items-center rounded-full transition-colors ${
                                    manualValveState ? "bg-green-500" : "bg-red-400"
                                  }`}
                                >
                                  <span
                                    className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                      manualValveState ? "translate-x-7" : "translate-x-1"
                                    }`}
                                  />
                                </button>
                                <span className={`text-xs font-medium ${manualValveState ? "text-green-600" : "text-gray-500"}`}>ON</span>
                              </div>
                            </div>
                          )}

                          {/* 스케줄 활성화 스위치 (자동 모드일 때만 표시) */}
                          {valveSchedule.mode === "auto" && (
                            <div className="flex items-center justify-between mb-3 p-3 bg-green-50 border border-green-200 rounded">
                              <span className="text-xs font-medium text-gray-900">스케줄 활성화</span>
                              <button
                                onClick={() =>
                                  setValveSchedule({
                                    ...valveSchedule,
                                    enabled: !valveSchedule.enabled,
                                  })
                                }
                                className={`relative inline-flex h-6 w-12 items-center rounded-full transition-colors ${
                                  valveSchedule.enabled ? "bg-green-500" : "bg-gray-400"
                                }`}
                              >
                                <span
                                  className={`inline-block h-4 w-4 transform rounded-full bg-white transition-transform ${
                                    valveSchedule.enabled ? "translate-x-7" : "translate-x-1"
                                  }`}
                                />
                              </button>
                            </div>
                          )}
                        </div>

                        {/* 자동 모드일 때만 스케줄 설정 표시 */}
                        {valveSchedule.mode === "auto" && (
                          <>
                        {/* 시간대 1 - 주간 */}
                        <div className="mb-3 p-3 bg-yellow-50 border border-yellow-200 rounded">
                          <h5 className="text-xs font-semibold text-yellow-800 mb-2">☀️ 주간 (시간대 1)</h5>
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">시작</label>
                              <input
                                type="time"
                                value={valveSchedule.timeSlots[0].startTime}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[0] = { ...newSlots[0], startTime: e.target.value };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">종료</label>
                              <input
                                type="time"
                                value={valveSchedule.timeSlots[0].endTime}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[0] = { ...newSlots[0], endTime: e.target.value };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">열림 (분)</label>
                              <input
                                type="number"
                                value={valveSchedule.timeSlots[0].openMinutes}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[0] = { ...newSlots[0], openMinutes: parseInt(e.target.value) || 0 };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">열림 (초)</label>
                              <input
                                type="number"
                                value={valveSchedule.timeSlots[0].openSeconds}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[0] = { ...newSlots[0], openSeconds: parseInt(e.target.value) || 0 };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                                max="59"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">닫힘 (분)</label>
                              <input
                                type="number"
                                value={valveSchedule.timeSlots[0].closeMinutes}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[0] = { ...newSlots[0], closeMinutes: parseInt(e.target.value) || 0 };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">닫힘 (초)</label>
                              <input
                                type="number"
                                value={valveSchedule.timeSlots[0].closeSeconds}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[0] = { ...newSlots[0], closeSeconds: parseInt(e.target.value) || 0 };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                                max="59"
                              />
                            </div>
                          </div>
                        </div>

                        {/* 시간대 2 - 야간 */}
                        <div className="p-3 bg-blue-50 border border-blue-200 rounded">
                          <h5 className="text-xs font-semibold text-blue-800 mb-2">🌙 야간 (시간대 2)</h5>
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">시작</label>
                              <input
                                type="time"
                                value={valveSchedule.timeSlots[1].startTime}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[1] = { ...newSlots[1], startTime: e.target.value };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">종료</label>
                              <input
                                type="time"
                                value={valveSchedule.timeSlots[1].endTime}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[1] = { ...newSlots[1], endTime: e.target.value };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">열림 (분)</label>
                              <input
                                type="number"
                                value={valveSchedule.timeSlots[1].openMinutes}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[1] = { ...newSlots[1], openMinutes: parseInt(e.target.value) || 0 };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">열림 (초)</label>
                              <input
                                type="number"
                                value={valveSchedule.timeSlots[1].openSeconds}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[1] = { ...newSlots[1], openSeconds: parseInt(e.target.value) || 0 };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                                max="59"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">닫힘 (분)</label>
                              <input
                                type="number"
                                value={valveSchedule.timeSlots[1].closeMinutes}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[1] = { ...newSlots[1], closeMinutes: parseInt(e.target.value) || 0 };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                              />
                            </div>
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">닫힘 (초)</label>
                              <input
                                type="number"
                                value={valveSchedule.timeSlots[1].closeSeconds}
                                onChange={(e) => {
                                  const newSlots = [...valveSchedule.timeSlots];
                                  newSlots[1] = { ...newSlots[1], closeSeconds: parseInt(e.target.value) || 0 };
                                  setValveSchedule({ ...valveSchedule, timeSlots: newSlots });
                                }}
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                                max="59"
                              />
                            </div>
                          </div>
                        </div>

                        {/* 환경 조건 설정 */}
                        <div className="p-3 bg-gray-50 border border-gray-300 rounded mt-3">
                          <div className="flex items-center justify-between mb-3">
                            <h5 className="text-xs font-semibold text-gray-900">🌡️ 환경 조건 (선택)</h5>
                            <label className="flex items-center gap-2 cursor-pointer">
                              <input
                                type="checkbox"
                                checked={valveSchedule.useEnvironmentConditions}
                                onChange={(e) =>
                                  setValveSchedule({
                                    ...valveSchedule,
                                    useEnvironmentConditions: e.target.checked,
                                  })
                                }
                                className="w-3 h-3 accent-farm-500"
                              />
                              <span className="text-xs text-gray-700">조건 사용</span>
                            </label>
                          </div>

                          {valveSchedule.useEnvironmentConditions && (
                            <div>
                              <label className="text-xs text-gray-700 font-medium mb-1 block">
                                최대 온도 (°C)
                              </label>
                              <input
                                type="number"
                                value={valveSchedule.maxTemperature}
                                onChange={(e) =>
                                  setValveSchedule({
                                    ...valveSchedule,
                                    maxTemperature: parseFloat(e.target.value) || 0,
                                  })
                                }
                                className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                min="0"
                                max="50"
                                step="0.5"
                              />
                              <p className="text-xs text-gray-500 mt-1">
                                이 값 초과 시 밸브 정지
                              </p>
                            </div>
                          )}

                          {!valveSchedule.useEnvironmentConditions && (
                            <p className="text-xs text-gray-500 italic">
                              환경 조건을 체크하면 온도가 최대값을 초과할 때 밸브가 자동으로 정지됩니다.
                            </p>
                          )}
                        </div>

                        {/* 저장 및 초기화 버튼 */}
                        <div className="mt-4 flex gap-2">
                          <button
                            onClick={async () => {
                              try {
                                await fetch('/api/smartfarm/save_valve_schedule.php', {
                                  method: 'POST',
                                  headers: { 'Content-Type': 'application/json' },
                                  body: JSON.stringify(valveSchedule),
                                });
                                alert('스케줄이 저장되었습니다.');
                              } catch (error) {
                                alert('저장 실패');
                              }
                            }}
                            className="flex-1 px-4 py-2 bg-blue-500 text-white text-sm font-medium rounded hover:bg-blue-600"
                          >
                            저장
                          </button>
                          <button
                            onClick={() => {
                              setValveSchedule({
                                enabled: false,
                                mode: "manual",
                                timeSlots: [
                                  {
                                    startTime: "06:00",
                                    endTime: "18:00",
                                    openMinutes: 0,
                                    openSeconds: 10,
                                    closeMinutes: 5,
                                    closeSeconds: 0,
                                  },
                                  {
                                    startTime: "18:00",
                                    endTime: "06:00",
                                    openMinutes: 0,
                                    openSeconds: 10,
                                    closeMinutes: 10,
                                    closeSeconds: 0,
                                  },
                                ],
                                useEnvironmentConditions: false,
                                maxTemperature: 30,
                              });
                            }}
                            className="flex-1 px-4 py-2 bg-gray-500 text-white text-sm font-medium rounded hover:bg-gray-600"
                          >
                            초기화
                          </button>
                        </div>
                        </>
                        )}
                      </div>
                    )}
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
                    <p className="text-xs text-gray-600 font-medium mb-2">버튼 제어</p>
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

                  {/* 슬라이더 제어 */}
                  <div>
                    <p className="text-xs text-gray-600 font-medium mb-2">슬라이더 제어</p>
                    <div className="flex items-center gap-3">
                      <input
                        type="range"
                        min="0"
                        max="100"
                        value={deviceState[skylight.id]?.targetPercentage ?? 0}
                        onChange={(e) => handleSkylightPercentageChange(skylight.id, parseInt(e.target.value))}
                        className="flex-1 h-2 bg-amber-200 rounded-lg appearance-none cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-amber-500 [&::-webkit-slider-thumb]:cursor-pointer"
                      />
                      <span className="text-sm font-semibold text-gray-900 min-w-[3rem] text-right">
                        {deviceState[skylight.id]?.targetPercentage ?? 0}%
                      </span>
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
            <span className="text-xs text-gray-800">총 {vents.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-3">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(350px,1fr))] gap-3">
              {vents.map((vent) => (
                <div
                  key={vent.id}
                  className="bg-white border-2 border-blue-200 rounded-lg p-4 shadow-sm"
                >
                  <div className="flex items-center justify-between mb-3">
                    <h3 className="text-sm font-semibold text-gray-900">
                      {vent.name}
                    </h3>
                    <span className="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                      {vent.esp32Id}
                    </span>
                  </div>

                  {/* 슬라이더 제어 */}
                  <div className="mb-4">
                    <p className="text-xs text-gray-600 font-medium mb-2">슬라이더 제어</p>
                    <div className="flex items-center gap-3">
                      <input
                        type="range"
                        min="0"
                        max="100"
                        value={deviceState[vent.id]?.targetPercentage ?? 0}
                        onChange={(e) => handlePercentageChange(vent.id, parseInt(e.target.value))}
                        className="flex-1 h-2 bg-blue-200 rounded-lg appearance-none cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-blue-500 [&::-webkit-slider-thumb]:cursor-pointer"
                      />
                      <span className="text-sm font-semibold text-gray-900 min-w-[3rem] text-right">
                        {deviceState[vent.id]?.targetPercentage ?? 0}%
                      </span>
                    </div>
                  </div>

                  {/* 버튼 제어 */}
                  <div>
                    <p className="text-xs text-gray-600 font-medium mb-2">버튼 제어</p>
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleVentCommand(vent.id, "OPEN")}
                        className="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ▲ 열기
                      </button>
                      <button
                        onClick={() => handleVentCommand(vent.id, "STOP")}
                        className="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ■ 정지
                      </button>
                      <button
                        onClick={() => handleVentCommand(vent.id, "CLOSE")}
                        className="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-3 px-4 rounded-md transition-colors"
                      >
                        ▼ 닫기
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
