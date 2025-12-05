import { useState, useEffect, useRef } from "react";
import { getDevicesByType } from "../config/devices";
import { ESP32_CONTROLLERS } from "../config/esp32Controllers";
import type { DeviceDesiredState } from "../types";
import DeviceCard from "../components/DeviceCard";
import { publishCommand, getMqttClient } from "../mqtt/mqttClient";

interface DevicesControlProps {
  deviceState: DeviceDesiredState;
  setDeviceState: React.Dispatch<React.SetStateAction<DeviceDesiredState>>;
}

// 장치별 자동 제어 설정
interface DeviceAutoControl {
  enabled: boolean;
  tempMin: number;
  tempMax: number;
  humMin: number;
  humMax: number;
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
  timeSlots: ValveTimeSlot[]; // 시간대별 설정 (최대 2개 - 주간/야간)
  useEnvironmentConditions: boolean; // 온습도 조건 사용 여부
  maxTemperature: number; // 최대 온도 (°C)
  maxHumidity: number; // 최대 습도 (%)
}

export default function DevicesControl({ deviceState, setDeviceState }: DevicesControlProps) {
  // ESP32 장치별 연결 상태 (12개)
  const [esp32Status, setEsp32Status] = useState<Record<string, boolean>>({});

  // 수동/자동 모드
  const [controlMode, setControlMode] = useState<"manual" | "auto">("manual");

  // 각 ESP32 장치별 자동 제어 설정
  const [deviceAutoControls, setDeviceAutoControls] = useState<Record<string, DeviceAutoControl>>(
    ESP32_CONTROLLERS.reduce((acc, controller) => {
      acc[controller.controllerId] = {
        enabled: false,
        tempMin: 18,
        tempMax: 28,
        humMin: 40,
        humMax: 70,
      };
      return acc;
    }, {} as Record<string, DeviceAutoControl>)
  );

  // 메인밸브 스케줄 설정
  const [valveSchedule, setValveSchedule] = useState<ValveSchedule>({
    enabled: false,
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
    maxHumidity: 80,
  });

  // 메인밸브 제어용 타이머
  const valveTimerRef = useRef<NodeJS.Timeout | null>(null);
  const [valveCurrentState, setValveCurrentState] = useState<"OPEN" | "CLOSE">("CLOSE");

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

  // 서버에서 장치 연결 상태 가져오기 (3초마다 폴링)
  useEffect(() => {
    const fetchDeviceStatus = async () => {
      try {
        const response = await fetch("/api/smartfarm/get_device_status.php");
        const result = await response.json();

        if (result.success) {
          const newStatus: Record<string, boolean> = {};
          Object.entries(result.data.devices).forEach(([controllerId, info]: [string, any]) => {
            newStatus[controllerId] = info.connected;
          });
          setEsp32Status(newStatus);
        }
      } catch (error) {
        console.error("Failed to fetch device status:", error);
      }
    };

    // 즉시 실행
    fetchDeviceStatus();

    // 3초마다 갱신
    const interval = setInterval(fetchDeviceStatus, 3000);
    return () => clearInterval(interval);
  }, []);

  // 센서 데이터는 백그라운드 MQTT 데몬이 수집하고 DB에 저장
  // DevicesControl은 서버 API에서 평균값만 읽어옴 (위의 useEffect 참고)

  // 자동 제어 로직 (서버에서 가져온 평균값 사용)
  useEffect(() => {
    if (controlMode !== "auto") return;

    // 서버에서 가져온 평균값 사용
    const avgTemp = averageValues.avgTemperature;
    const avgHum = averageValues.avgHumidity;

    if (avgTemp === null || avgHum === null) return;

    // 자동 제어가 활성화된 장치들만 제어
    ESP32_CONTROLLERS.forEach((controller) => {
      const autoControl = deviceAutoControls[controller.controllerId];
      if (!autoControl?.enabled) return;

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
  }, [averageValues, controlMode, deviceAutoControls]);

  // 메인밸브 자동 제어 (스케줄 기반)
  useEffect(() => {
    if (!valveSchedule.enabled) {
      // 스케줄이 비활성화되면 타이머 정리
      if (valveTimerRef.current) {
        clearTimeout(valveTimerRef.current);
        valveTimerRef.current = null;
      }
      return;
    }

    // 현재 시간에 맞는 시간대 찾기
    const getCurrentTimeSlot = (): ValveTimeSlot | null => {
      const now = new Date();
      const currentTime = now.getHours() * 60 + now.getMinutes(); // 분 단위로 변환

      for (const slot of valveSchedule.timeSlots) {
        const [startHour, startMin] = slot.startTime.split(':').map(Number);
        const [endHour, endMin] = slot.endTime.split(':').map(Number);
        const startMinutes = startHour * 60 + startMin;
        const endMinutes = endHour * 60 + endMin;

        // 시간대가 자정을 넘는 경우 처리 (예: 18:00 ~ 06:00)
        if (startMinutes > endMinutes) {
          if (currentTime >= startMinutes || currentTime < endMinutes) {
            return slot;
          }
        } else {
          if (currentTime >= startMinutes && currentTime < endMinutes) {
            return slot;
          }
        }
      }
      return null;
    };

    // 시간대 겹침 체크
    const checkTimeOverlap = (): boolean => {
      if (valveSchedule.timeSlots.length < 2) return false;

      const slots = valveSchedule.timeSlots;
      for (let i = 0; i < slots.length - 1; i++) {
        for (let j = i + 1; j < slots.length; j++) {
          const slot1 = slots[i];
          const slot2 = slots[j];

          const [s1h, s1m] = slot1.startTime.split(':').map(Number);
          const [e1h, e1m] = slot1.endTime.split(':').map(Number);
          const [s2h, s2m] = slot2.startTime.split(':').map(Number);
          const [e2h, e2m] = slot2.endTime.split(':').map(Number);

          const s1 = s1h * 60 + s1m;
          const e1 = e1h * 60 + e1m;
          const s2 = s2h * 60 + s2m;
          const e2 = e2h * 60 + e2m;

          // 겹침 체크 (복잡한 로직이지만 자정 넘김도 고려)
          const overlap =
            (s1 <= s2 && s2 < e1) ||
            (s1 < e2 && e2 <= e1) ||
            (s2 <= s1 && s1 < e2) ||
            (s2 < e1 && e1 <= e2);

          if (overlap) return true;
        }
      }
      return false;
    };

    // 겹침이 있으면 에러 표시하고 중단
    if (checkTimeOverlap()) {
      console.error('[VALVE] 시간대가 겹칩니다. 스케줄을 확인해주세요.');
      return;
    }

    const currentSlot = getCurrentTimeSlot();
    if (!currentSlot) {
      console.log('[VALVE] 현재 시간대에 맞는 스케줄이 없습니다.');
      return;
    }

    // 밸브 제어 함수
    const controlValve = (command: "OPEN" | "CLOSE") => {
      const client = getMqttClient();
      const topic = "tansaeng/ctlr-0004/valve1/cmd";
      client.publish(topic, command, { qos: 1 });
      setValveCurrentState(command);

      const openTotal = currentSlot.openMinutes * 60 + currentSlot.openSeconds;
      const closeTotal = currentSlot.closeMinutes * 60 + currentSlot.closeSeconds;
      console.log(`[VALVE] ${command} (${openTotal}s open / ${closeTotal}s close)`);
    };

    // 환경 조건 체크 함수
    const checkEnvironmentConditions = (): boolean => {
      if (!valveSchedule.useEnvironmentConditions) {
        return true; // 환경 조건 사용 안 하면 항상 true
      }

      const avgTemp = averageValues.avgTemperature;
      const avgHum = averageValues.avgHumidity;

      if (avgTemp === null || avgHum === null) {
        return true; // 데이터 없으면 일단 허용
      }

      // 온도나 습도가 최대값을 초과하면 밸브 정지
      if (avgTemp > valveSchedule.maxTemperature || avgHum > valveSchedule.maxHumidity) {
        console.log(`[VALVE] 환경 조건 초과 - 온도: ${avgTemp}°C (최대: ${valveSchedule.maxTemperature}°C), 습도: ${avgHum}% (최대: ${valveSchedule.maxHumidity}%)`);
        return false;
      }

      return true;
    };

    // 주기적 제어 로직
    const runValveCycle = () => {
      const slot = getCurrentTimeSlot();
      if (!slot) return;

      // 환경 조건 체크
      if (!checkEnvironmentConditions()) {
        console.log('[VALVE] 환경 조건 미달, 밸브 정지');
        // 환경 조건이 좋아질 때까지 5초 후 재시도
        valveTimerRef.current = setTimeout(runValveCycle, 5000);
        return;
      }

      // 분:초를 총 초로 변환
      const openTotalSeconds = slot.openMinutes * 60 + slot.openSeconds;
      const closeTotalSeconds = slot.closeMinutes * 60 + slot.closeSeconds;

      // 밸브 열기
      controlValve("OPEN");

      // openSeconds 후에 밸브 닫기
      setTimeout(() => {
        controlValve("CLOSE");

        // closeSeconds 후에 다시 사이클 시작
        valveTimerRef.current = setTimeout(runValveCycle, closeTotalSeconds * 1000);
      }, openTotalSeconds * 1000);
    };

    // 최초 실행
    runValveCycle();

    // 클린업
    return () => {
      if (valveTimerRef.current) {
        clearTimeout(valveTimerRef.current);
        valveTimerRef.current = null;
      }
    };
  }, [valveSchedule, averageValues]);

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

  // 연결된 ESP32 개수 계산
  const connectedCount = Object.values(esp32Status).filter(Boolean).length;
  const totalCount = ESP32_CONTROLLERS.length;

  // 천창/측창 스크린 ESP32 필터링
  const skylightControllers = ESP32_CONTROLLERS.filter(c => c.category === "skylight");
  const ventControllers = ESP32_CONTROLLERS.filter(c => c.category === "vent");
  const otherControllers = ESP32_CONTROLLERS.filter(c => !c.category);

  // 천창/측창 연결 개수
  const skylightConnectedCount = skylightControllers.filter(c => esp32Status[c.controllerId] === true).length;
  const ventConnectedCount = ventControllers.filter(c => esp32Status[c.controllerId] === true).length;

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
                ${connectedCount > 0 ? "bg-farm-500 animate-pulse" : "bg-gray-400"}
              `}
              ></div>
              <span className="text-xs font-medium text-gray-900">
                장치 연결 ({connectedCount}/{totalCount})
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
          <div className="bg-white shadow-sm rounded-b-lg p-3 space-y-3">
            {/* 기타 장치 */}
            {otherControllers.length > 0 && (
              <div>
                <h3 className="text-xs font-semibold text-gray-700 mb-2">기타 장치</h3>
                <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2">
                  {otherControllers.map((controller) => {
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
            )}

            {/* 천창 스크린 */}
            {skylightControllers.length > 0 && (
              <div>
                <h3 className="text-xs font-semibold text-amber-700 mb-2">
                  ☀️ 천창 스크린 ({skylightConnectedCount}/{skylightControllers.length})
                </h3>
                <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2">
                  {skylightControllers.map((controller) => {
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
            )}

            {/* 측창 스크린 */}
            {ventControllers.length > 0 && (
              <div>
                <h3 className="text-xs font-semibold text-blue-700 mb-2">
                  🪟 측창 스크린 ({ventConnectedCount}/{ventControllers.length})
                </h3>
                <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2">
                  {ventControllers.map((controller) => {
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
            )}
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
            <div className="mb-4 p-3 bg-farm-50 rounded-lg border border-farm-200">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <span className="text-xs text-gray-600">평균 온도:</span>
                  <span className="ml-2 text-sm font-semibold text-gray-900">
                    {averageValues.avgTemperature !== null
                      ? `${averageValues.avgTemperature.toFixed(1)}°C`
                      : "N/A"}
                  </span>
                </div>
                <div>
                  <span className="text-xs text-gray-600">평균 습도:</span>
                  <span className="ml-2 text-sm font-semibold text-gray-900">
                    {averageValues.avgHumidity !== null
                      ? `${averageValues.avgHumidity.toFixed(1)}%`
                      : "N/A"}
                  </span>
                </div>
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

                    {/* 온습도 범위 설정 */}
                    <div className="grid grid-cols-2 gap-4 mb-3">
                      {/* 온도 범위 */}
                      <div>
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

                      {/* 습도 범위 */}
                      <div>
                        <label className="text-xs text-gray-700 font-medium mb-1.5 block">
                          습도 범위 (%)
                        </label>
                        <div className="flex items-center gap-2">
                          <input
                            type="number"
                            value={autoControl.humMin}
                            onChange={(e) =>
                              setDeviceAutoControls({
                                ...deviceAutoControls,
                                [controller.controllerId]: {
                                  ...autoControl,
                                  humMin: parseFloat(e.target.value),
                                },
                              })
                            }
                            className="flex-1 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-farm-500"
                            step="1"
                            placeholder="최소"
                          />
                          <span className="text-xs text-gray-500">~</span>
                          <input
                            type="number"
                            value={autoControl.humMax}
                            onChange={(e) =>
                              setDeviceAutoControls({
                                ...deviceAutoControls,
                                [controller.controllerId]: {
                                  ...autoControl,
                                  humMax: parseFloat(e.target.value),
                                },
                              })
                            }
                            className="flex-1 px-2 py-1.5 text-xs border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-farm-500"
                            step="1"
                            placeholder="최대"
                          />
                        </div>
                      </div>
                    </div>

                    {/* 메인밸브 스케줄 설정 (ctlr-0004만 표시) */}
                    {isMainValve && (
                      <div className="mt-4 pt-4 border-t border-gray-300">
                        <div className="flex items-center justify-between mb-3">
                          <h4 className="text-xs font-semibold text-gray-900">
                            메인밸브 스케줄 설정
                          </h4>
                          <label className="flex items-center gap-2 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={valveSchedule.enabled}
                              onChange={(e) =>
                                setValveSchedule({
                                  ...valveSchedule,
                                  enabled: e.target.checked,
                                })
                              }
                              className="w-4 h-4 text-farm-500 border-gray-300 rounded focus:ring-farm-500"
                            />
                            <span className="text-xs text-gray-700 font-medium">
                              스케줄 활성화
                            </span>
                          </label>
                        </div>

                        {/* 현재 밸브 상태 */}
                        <div className="mb-3 p-2 bg-gray-100 rounded text-center">
                          <span className="text-xs text-gray-700">현재 상태: </span>
                          <span className={`text-xs font-bold ${valveCurrentState === "OPEN" ? "text-green-600" : "text-red-600"}`}>
                            {valveCurrentState === "OPEN" ? "열림" : "닫힘"}
                          </span>
                        </div>

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
                            <div className="grid grid-cols-2 gap-3">
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
                              <div>
                                <label className="text-xs text-gray-700 font-medium mb-1 block">
                                  최대 습도 (%)
                                </label>
                                <input
                                  type="number"
                                  value={valveSchedule.maxHumidity}
                                  onChange={(e) =>
                                    setValveSchedule({
                                      ...valveSchedule,
                                      maxHumidity: parseFloat(e.target.value) || 0,
                                    })
                                  }
                                  className="w-full px-2 py-1 text-xs border border-gray-300 rounded"
                                  min="0"
                                  max="100"
                                  step="1"
                                />
                                <p className="text-xs text-gray-500 mt-1">
                                  이 값 초과 시 밸브 정지
                                </p>
                              </div>
                            </div>
                          )}

                          {!valveSchedule.useEnvironmentConditions && (
                            <p className="text-xs text-gray-500 italic">
                              환경 조건을 체크하면 온도/습도가 최대값을 초과할 때 밸브가 자동으로 정지됩니다.
                            </p>
                          )}
                        </div>
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
