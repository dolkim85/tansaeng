import { useState, useEffect, useRef } from "react";
import { getDevicesByType, DEVICES } from "../config/devices";
import { ESP32_CONTROLLERS } from "../config/esp32Controllers";
import type { DeviceDesiredState } from "../types";
import DeviceCard from "../components/DeviceCard";
import { getMqttClient, onConnectionChange, subscribeToTopic, publishCommand } from "../mqtt/mqttClient";
import { sendDeviceCommand, saveDeviceSettings } from "../api/deviceControl";

// 두 핸들을 한 바 위에서 독립적으로 드래그할 수 있는 슬라이더
function DualRangeSlider({
  min, max, step, low, high, onLowChange, onHighChange, markerPct, isActive,
}: {
  min: number; max: number; step: number;
  low: number; high: number;
  onLowChange: (v: number) => void;
  onHighChange: (v: number) => void;
  markerPct: number | null;
  isActive: boolean;
}) {
  const trackRef = useRef<HTMLDivElement>(null);
  const range = max - min;
  const lowPct  = ((low  - min) / range) * 100;
  const highPct = ((high - min) / range) * 100;

  const pctToVal = (clientX: number) => {
    if (!trackRef.current) return min;
    const rect = trackRef.current.getBoundingClientRect();
    const pct = Math.max(0, Math.min(100, ((clientX - rect.left) / rect.width) * 100));
    const raw = min + (pct / 100) * range;
    return Math.round(raw / step) * step;
  };

  const handleDrag = (
    e: React.PointerEvent<HTMLDivElement>,
    which: "low" | "high"
  ) => {
    (e.currentTarget as HTMLDivElement).setPointerCapture(e.pointerId);
  };

  const handleMove = (
    e: React.PointerEvent<HTMLDivElement>,
    which: "low" | "high"
  ) => {
    if (!(e.currentTarget as HTMLDivElement).hasPointerCapture(e.pointerId)) return;
    const v = pctToVal(e.clientX);
    if (which === "low"  && v <= high - step) onLowChange(v);
    if (which === "high" && v >= low  + step) onHighChange(v);
  };

  return (
    <div ref={trackRef} className="relative h-9 flex items-center select-none mx-3">
      {/* 배경 트랙 */}
      <div className="absolute inset-x-0 h-2 bg-gray-200 rounded-full pointer-events-none">
        {/* 활성 구간 */}
        <div className="absolute h-full bg-orange-400 rounded-full"
          style={{ left: `${lowPct}%`, width: `${Math.max(0, highPct - lowPct)}%` }} />
        {/* 기준(0) 중심선 */}
        <div className="absolute w-px h-5 -top-1.5 bg-gray-500 opacity-40" style={{ left: "50%" }} />
        {/* 현재온도 마커 */}
        {markerPct !== null && (
          <div className="absolute w-1.5 h-7 -top-2.5 rounded-full"
            style={{
              left: `${markerPct}%`,
              transform: "translateX(-50%)",
              background: isActive ? "#22c55e" : "#ef4444",
            }} />
        )}
      </div>
      {/* 하한 핸들 (orange) */}
      <div
        className="absolute w-7 h-7 bg-orange-500 border-2 border-white rounded-full shadow-lg cursor-grab active:cursor-grabbing touch-none"
        style={{ left: `${lowPct}%`, transform: "translateX(-50%)", zIndex: 4 }}
        onPointerDown={(e) => handleDrag(e, "low")}
        onPointerMove={(e) => handleMove(e, "low")}
        onPointerUp={(e) => (e.currentTarget as HTMLDivElement).releasePointerCapture(e.pointerId)}
      >
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="w-1.5 h-3 border-x border-white opacity-70 rounded-sm" />
        </div>
      </div>
      {/* 상한 핸들 (red) */}
      <div
        className="absolute w-7 h-7 bg-red-500 border-2 border-white rounded-full shadow-lg cursor-grab active:cursor-grabbing touch-none"
        style={{ left: `${highPct}%`, transform: "translateX(-50%)", zIndex: 4 }}
        onPointerDown={(e) => handleDrag(e, "high")}
        onPointerMove={(e) => handleMove(e, "high")}
        onPointerUp={(e) => (e.currentTarget as HTMLDivElement).releasePointerCapture(e.pointerId)}
      >
        <div className="absolute inset-0 flex items-center justify-center">
          <div className="w-1.5 h-3 border-x border-white opacity-70 rounded-sm" />
        </div>
      </div>
    </div>
  );
}

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

  // 히트펌프 시스템 상태
  const [hpMode, setHpMode] = useState<"AUTO" | "MANUAL">("AUTO");
  const hpModeRef = useRef<"AUTO" | "MANUAL">("AUTO"); // AUTO 로직에서 stale closure 방지
  const [hpSensors, setHpSensors] = useState<{
    airTemp: number | null;
    airHumidity: number | null;
    waterTemp: number | null;
  }>({ airTemp: null, airHumidity: null, waterTemp: null });
  const [hpDeviceStates, setHpDeviceStates] = useState<Record<string, "ON" | "OFF">>({});

  // 팜 내부 온도/습도 (앞/뒤/천장) — AUTO 기준
  const [farmSensors, setFarmSensors] = useState<{
    front: number | null; back: number | null; top: number | null;
    frontHum: number | null; backHum: number | null; topHum: number | null;
  }>({ front: null, back: null, top: null, frontHum: null, backHum: null, topHum: null });
  const hpAutoDemandRef = useRef<boolean>(false);

  // 장치별 절대온도 범위 (-30~+30°C 내 실제 온도값)
  const [hpDeviceRanges, setHpDeviceRanges] = useState<Record<string, { low: number; high: number }>>({
    hp_pump:   { low: 15, high: 22 },
    hp_heater: { low: 15, high: 22 },
    hp_fan:    { low: 15, high: 22 },
  });
  // AUTO 작동 활성화 여부 (작동시작/작동멈춤 버튼으로 제어)
  const [hpAutoActive, setHpAutoActive] = useState(false);
  // 마지막 전송 명령 추적 (중복 전송 방지)
  const hpDeviceLastCmd = useRef<Record<string, "ON" | "OFF" | null>>({ hp_pump: null, hp_heater: null, hp_fan: null });


  // 히트펌프 전용 장치는 일반 섹션에서 제외
  const fans = getDevicesByType("fan").filter(d => d.esp32Id !== "ctlr-heat-001");
  const vents = getDevicesByType("vent").filter(d => d.esp32Id !== "ctlr-heat-001");
  const pumps = getDevicesByType("pump").filter(d => d.esp32Id !== "ctlr-heat-001");
  const skylights = getDevicesByType("skylight");
  const sidescreens = getDevicesByType("sidescreen");

  // 히트펌프 시스템 장치
  const heatPumpDevices = DEVICES.filter(d => d.esp32Id === "ctlr-heat-001");
  const heatPumpHeaters = heatPumpDevices.filter(d => d.type === "heater");
  const heatPumpPumps   = heatPumpDevices.filter(d => d.type === "pump");
  const heatPumpFans    = heatPumpDevices.filter(d => d.type === "fan");

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

  // 히트펌프 MQTT 센서 & 상태 구독
  useEffect(() => {
    const HP = "ctlr-heat-001";
    const unsubs = [
      // 제어 모드 (공유)
      subscribeToTopic(`tansaeng/${HP}/mode/state`, (v) => {
        if (v === "AUTO" || v === "MANUAL") {
          hpModeRef.current = v;
          setHpMode(v);
        }
      }),
      // 장치제어실 센서
      subscribeToTopic(`tansaeng/${HP}/air/temperature`, (v) => {
        const n = parseFloat(v);
        if (!isNaN(n)) setHpSensors(prev => ({ ...prev, airTemp: n }));
      }),
      subscribeToTopic(`tansaeng/${HP}/air/humidity`, (v) => {
        const n = parseFloat(v);
        if (!isNaN(n)) setHpSensors(prev => ({ ...prev, airHumidity: n }));
      }),
      subscribeToTopic(`tansaeng/${HP}/water/temperature`, (v) => {
        const n = parseFloat(v);
        if (!isNaN(n)) setHpSensors(prev => ({ ...prev, waterTemp: n }));
      }),
      // 개별 장치 상태
      subscribeToTopic(`tansaeng/${HP}/pump/state`, (v) =>
        setHpDeviceStates(prev => ({ ...prev, hp_pump: v as "ON" | "OFF" }))
      ),
      subscribeToTopic(`tansaeng/${HP}/heater/state`, (v) =>
        setHpDeviceStates(prev => ({ ...prev, hp_heater: v as "ON" | "OFF" }))
      ),
      subscribeToTopic(`tansaeng/${HP}/fan/state`, (v) =>
        setHpDeviceStates(prev => ({ ...prev, hp_fan: v as "ON" | "OFF" }))
      ),
    ];
    return () => unsubs.forEach(u => u());
  }, []);

  // 팜 내부 온도 폴링 (앞/뒤/천장 — AUTO 기준)
  useEffect(() => {
    const fetchFarmSensors = async () => {
      try {
        const res = await fetch('/api/smartfarm/get_realtime_sensor_data.php');
        const result = await res.json();
        if (result.success) {
          setFarmSensors({
            front:    result.data.front.temperature,
            back:     result.data.back.temperature,
            top:      result.data.top.temperature,
            frontHum: result.data.front.humidity,
            backHum:  result.data.back.humidity,
            topHum:   result.data.top.humidity,
          });
        }
      } catch {}
    };
    fetchFarmSensors();
    const interval = setInterval(fetchFarmSensors, 5000);
    return () => clearInterval(interval);
  }, []);

  // AUTO 모드 제어 로직 — 평균온도가 설정 범위 안에 있으면 ON, 벗어나면 OFF
  useEffect(() => {
    if (hpModeRef.current !== "AUTO") return;
    if (!hpAutoActive) return; // 작동시작 버튼을 눌러야 활성화

    const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
    if (temps.length === 0) return;

    const avgTemp = temps.reduce((a, b) => a + b, 0) / temps.length;
    const HP = "ctlr-heat-001";

    const deviceMap: Array<{ key: string; mqttId: string }> = [
      { key: "hp_pump",   mqttId: "pump"   },
      { key: "hp_heater", mqttId: "heater" },
      { key: "hp_fan",    mqttId: "fan"    },
    ];

    deviceMap.forEach(({ key, mqttId }) => {
      const range = hpDeviceRanges[key] ?? { low: 15, high: 22 };
      // 평균온도가 [low, high] 안에 있으면 ON, 벗어나면 OFF
      const inRange = avgTemp >= range.low && avgTemp <= range.high;
      const newCmd: "ON" | "OFF" = inRange ? "ON" : "OFF";
      if (hpDeviceLastCmd.current[key] !== newCmd) {
        hpDeviceLastCmd.current[key] = newCmd;
        setHpDeviceStates(prev => ({ ...prev, [key]: newCmd }));
        sendDeviceCommand(HP, mqttId, newCmd);
      }
    });
  }, [farmSensors, hpDeviceRanges, hpAutoActive]);

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

    // HP 장치는 즉시 시각적 업데이트 (MQTT 응답 기다리지 않음)
    if (heatPumpDevices.find(d => d.id === deviceId)) {
      setHpDeviceStates(prev => ({ ...prev, [deviceId]: isOn ? "ON" : "OFF" }));
    }

    const device = [...fans, ...vents, ...pumps, ...heatPumpDevices].find((d) => d.id === deviceId);
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

        // 서버에 팬 설정 저장 (데몬이 읽어서 제어)
        if (device.type === "fan") {
          await saveDeviceSettings({
            fans: {
              [deviceId]: {
                mode: "MANUAL",
                power: isOn ? "on" : "off",
                controllerId: device.esp32Id,
                deviceId: mqttDeviceId,
              }
            }
          });
          console.log(`[SETTINGS] Fan ${deviceId} saved to server`);
        }
      } else {
        console.error(`[API ERROR] ${result.message}`);
      }
    }
  };

  // 천창/측창 제어 핸들러 (OPEN/CLOSE/STOP) - API 호출
  const handleSkylightCommand = async (deviceId: string, command: "OPEN" | "CLOSE" | "STOP") => {
    const device = [...skylights, ...sidescreens].find((d) => d.id === deviceId);
    if (device) {
      console.log(`[SCREEN] ${device.name} - ${command}`);

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

        {/* 히트펌프 시스템 섹션 */}
        <section className="mb-2 sm:mb-3">
          <header className="bg-orange-400 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
              🔥 히트펌프 시스템
            </h2>
            <div className="flex items-center gap-2">
              <span className="text-[10px] sm:text-xs text-gray-800">
                {esp32Status["ctlr-heat-001"] ? "🟢 온라인" : "⚫ 오프라인"}
              </span>
              <span className="text-[10px] sm:text-xs text-gray-800">
                ctlr-heat-001
              </span>
            </div>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-2 sm:p-4">

            {/* 상단: 팜 평균온도 + 모드 + 장치제어실 센서 */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-4 mb-3 sm:mb-4">

              {/* 팜 내부 평균온도/습도 (AUTO 기준) */}
              {(() => {
                const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
                const hums  = [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[];
                const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
                const avgHum  = hums.length > 0  ? hums.reduce((a, b) => a + b, 0)  / hums.length  : null;
                const hRange = hpDeviceRanges.hp_heater ?? { low: 15, high: 22 };
                const autoStatus = avgTemp === null ? "센서 없음"
                  : !hpAutoActive ? "⏸ 작동 대기"
                  : (avgTemp >= hRange.low && avgTemp <= hRange.high) ? "🔥 범위 내 가동"
                  : "범위 외 정지";
                return (
                  <div className="bg-green-50 border border-green-200 rounded-lg p-2 sm:p-3">
                    <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-1.5">
                      팜 내부 평균
                      {hpMode === "AUTO" && <span className="ml-1 text-orange-500">(AUTO 기준)</span>}
                    </p>
                    {/* 평균 온도/습도 */}
                    <div className="grid grid-cols-2 gap-1.5 mb-2">
                      <div className="bg-red-50 border border-red-100 rounded p-1.5 text-center">
                        <div className="text-lg sm:text-2xl font-bold text-red-500">
                          {avgTemp !== null ? `${avgTemp.toFixed(1)}°` : "—"}
                        </div>
                        <div className="text-[9px] text-gray-400">평균온도</div>
                      </div>
                      <div className="bg-blue-50 border border-blue-100 rounded p-1.5 text-center">
                        <div className="text-lg sm:text-2xl font-bold text-blue-500">
                          {avgHum !== null ? `${avgHum.toFixed(0)}%` : "—"}
                        </div>
                        <div className="text-[9px] text-gray-400">평균습도</div>
                      </div>
                    </div>
                    {hpMode === "AUTO" && (
                      <div className="text-center text-[10px] font-semibold text-orange-600 mb-1.5">{autoStatus}</div>
                    )}
                    {/* 개별 센서 */}
                    <div className="grid grid-cols-3 gap-1 text-center">
                      {[
                        { label: "팬 앞", t: farmSensors.front, h: farmSensors.frontHum },
                        { label: "팬 뒤", t: farmSensors.back,  h: farmSensors.backHum },
                        { label: "천장",  t: farmSensors.top,   h: farmSensors.topHum },
                      ].map(({ label, t, h }) => (
                        <div key={label} className="bg-white rounded p-1">
                          <div className="text-[10px] font-semibold text-red-500">{t !== null ? `${t.toFixed(1)}°` : "—"}</div>
                          <div className="text-[10px] text-blue-400">{h !== null ? `${h.toFixed(0)}%` : "—"}</div>
                          <div className="text-[9px] text-gray-400">{label}</div>
                        </div>
                      ))}
                    </div>
                  </div>
                );
              })()}

              {/* 제어 모드 */}
              <div className="bg-gray-50 border border-gray-200 rounded-lg p-2 sm:p-3">
                <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-2">제어 모드</p>
                <div className="flex flex-col gap-1.5">
                  <button
                    onClick={() => {
                      hpModeRef.current = "AUTO";
                      setHpMode("AUTO");
                      hpAutoDemandRef.current = false;
                      setHpAutoActive(false); // 모드 전환 시 작동 초기화
                      hpDeviceLastCmd.current = { hp_pump: null, hp_heater: null, hp_fan: null };
                      getMqttClient().publish("tansaeng/ctlr-heat-001/mode/cmd", "AUTO", { qos: 1, retain: true });
                    }}
                    className={`w-full py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors ${
                      hpMode === "AUTO"
                        ? "bg-orange-500 text-white shadow"
                        : "bg-white border border-orange-300 text-orange-600 hover:bg-orange-50"
                    }`}
                  >
                    자동 (AUTO)
                  </button>
                  <button
                    onClick={() => {
                      hpModeRef.current = "MANUAL";
                      setHpMode("MANUAL");
                      hpAutoDemandRef.current = false;
                      getMqttClient().publish("tansaeng/ctlr-heat-001/mode/cmd", "MANUAL", { qos: 1, retain: true });
                    }}
                    className={`w-full py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors ${
                      hpMode === "MANUAL"
                        ? "bg-gray-700 text-white shadow"
                        : "bg-white border border-gray-300 text-gray-600 hover:bg-gray-50"
                    }`}
                  >
                    수동 (MANUAL)
                  </button>
                </div>
                <p className="text-[10px] text-gray-500 mt-1.5">
                  {hpMode === "AUTO" ? "팜 평균 18°C 이하 → 자동 가동" : "직접 장치 ON/OFF"}
                </p>
              </div>

              {/* 장치제어실 센서 */}
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-2 sm:p-3">
                <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-2">장치제어실 내부</p>
                <div className="grid grid-cols-3 gap-1 sm:gap-2">
                  <div className="text-center">
                    <div className="text-base sm:text-2xl font-bold text-red-500">
                      {hpSensors.airTemp !== null && !isNaN(hpSensors.airTemp) ? `${hpSensors.airTemp.toFixed(1)}°` : "—"}
                    </div>
                    <div className="text-[9px] sm:text-[10px] text-gray-500">공기온도</div>
                  </div>
                  <div className="text-center">
                    <div className="text-base sm:text-2xl font-bold text-blue-500">
                      {hpSensors.airHumidity !== null && !isNaN(hpSensors.airHumidity) ? `${hpSensors.airHumidity.toFixed(0)}%` : "—"}
                    </div>
                    <div className="text-[9px] sm:text-[10px] text-gray-500">공기습도</div>
                  </div>
                  <div className="text-center">
                    <div className="text-base sm:text-2xl font-bold text-cyan-600">
                      {hpSensors.waterTemp !== null && !isNaN(hpSensors.waterTemp) ? `${hpSensors.waterTemp.toFixed(1)}°` : "—"}
                    </div>
                    <div className="text-[9px] sm:text-[10px] text-gray-500">물온도</div>
                  </div>
                </div>
              </div>
            </div>

            {/* AUTO 모드: 장치별 개별 게이지 / MANUAL 모드: 장치 카드 */}
            {hpMode === "AUTO" ? (() => {
              const GMIN = -30, GMAX = 30;
              const gRange = GMAX - GMIN;
              const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
              const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
              // 현재 평균온도의 -30~+30 스케일 상 위치
              const markerPct = avgTemp !== null
                ? Math.max(0, Math.min(100, ((avgTemp - GMIN) / gRange) * 100))
                : null;

              const gaugeItems = [
                { key: "hp_pump",   label: "순환펌프",   icon: "💧" },
                { key: "hp_heater", label: "전기온열기", icon: "🔥" },
                { key: "hp_fan",    label: "팬",         icon: "🌀" },
              ];

              return (
                <div className="space-y-2 sm:space-y-3">

                  {/* 현재온도 + 작동시작/멈춤 버튼 */}
                  <div className="flex items-center justify-between bg-gray-100 rounded-lg px-3 py-2 gap-2">
                    <div className="text-xs text-gray-600">
                      현재 평균온도:{" "}
                      <span className="font-bold text-gray-800">
                        {avgTemp !== null ? `${avgTemp.toFixed(1)}°C` : "—"}
                      </span>
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => {
                          hpDeviceLastCmd.current = { hp_pump: null, hp_heater: null, hp_fan: null };
                          setHpAutoActive(true);
                        }}
                        className={`px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${
                          hpAutoActive
                            ? "bg-green-500 text-white shadow"
                            : "bg-white border border-green-400 text-green-600 hover:bg-green-50"
                        }`}
                      >
                        ▶ 작동시작
                      </button>
                      <button
                        onClick={() => {
                          setHpAutoActive(false);
                          // 모든 HP 장치 정지
                          const HP = "ctlr-heat-001";
                          ["hp_pump", "hp_heater", "hp_fan"].forEach((k, i) => {
                            const mqttId = ["pump", "heater", "fan"][i];
                            hpDeviceLastCmd.current[k] = "OFF";
                            setHpDeviceStates(prev => ({ ...prev, [k]: "OFF" }));
                            sendDeviceCommand(HP, mqttId, "OFF");
                          });
                        }}
                        className={`px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${
                          !hpAutoActive
                            ? "bg-gray-500 text-white shadow"
                            : "bg-white border border-gray-400 text-gray-600 hover:bg-gray-50"
                        }`}
                      >
                        ■ 작동멈춤
                      </button>
                    </div>
                  </div>

                  {/* 장치별 게이지 */}
                  {gaugeItems.map(({ key, label, icon }) => {
                    const range = hpDeviceRanges[key] ?? { low: 15, high: 22 };
                    const isOn = hpDeviceStates[key] === "ON";
                    // 평균온도가 설정 범위 안에 있는지 표시용
                    const inRange = avgTemp !== null && avgTemp >= range.low && avgTemp <= range.high;

                    return (
                      <div key={key} className="bg-orange-50 border border-orange-200 rounded-lg p-2.5 sm:p-3">
                        {/* 헤더: 장치명 + 작동상태 LED */}
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-xs sm:text-sm font-semibold text-gray-700">{icon} {label}</span>
                          <div className="flex items-center gap-1.5">
                            <div className={`w-2.5 h-2.5 rounded-full flex-shrink-0 ${isOn ? 'bg-green-500 animate-pulse' : 'bg-gray-400'}`} />
                            <span className={`text-xs font-bold ${isOn ? 'text-green-600' : 'text-gray-500'}`}>
                              {isOn ? '작동중' : '정지'}
                            </span>
                          </div>
                        </div>

                        {/* 온도 범위 표시 */}
                        <div className="flex justify-between text-xs mb-1 px-3">
                          <span className="text-orange-600 font-bold">{range.low.toFixed(1)}°C</span>
                          <span className="text-[10px] text-gray-400">
                            {inRange ? "✅ 현재온도 범위 내" : "❌ 현재온도 범위 밖"}
                          </span>
                          <span className="text-red-600 font-bold">{range.high.toFixed(1)}°C</span>
                        </div>

                        {/* 듀얼 핸들 슬라이더 */}
                        <DualRangeSlider
                          min={GMIN} max={GMAX} step={0.5}
                          low={range.low} high={range.high}
                          onLowChange={(v) =>
                            setHpDeviceRanges(prev => ({ ...prev, [key]: { ...prev[key] ?? { low: 15, high: 22 }, low: v } }))
                          }
                          onHighChange={(v) =>
                            setHpDeviceRanges(prev => ({ ...prev, [key]: { ...prev[key] ?? { low: 15, high: 22 }, high: v } }))
                          }
                          markerPct={markerPct}
                          isActive={inRange}
                        />
                        {/* 눈금 */}
                        <div className="flex justify-between text-[10px] text-gray-400 mx-3 mt-0.5">
                          <span>-30°C</span><span>0°C</span><span>+30°C</span>
                        </div>
                      </div>
                    );
                  })}
                </div>
              );
            })() : (
              <>
                <p className="text-[10px] sm:text-xs font-semibold text-gray-500 mb-1.5 sm:mb-2">
                  장치 제어 (수동 모드 — 직접 ON/OFF)
                </p>
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-1.5 sm:gap-3">
                  {heatPumpPumps.map((device) => (
                    <DeviceCard key={device.id} device={device}
                      power={hpDeviceStates[device.id] === "ON" ? "on" : "off"}
                      lastSavedAt={deviceState[device.id]?.lastSavedAt}
                      onToggle={(isOn) => handleToggle(device.id, isOn)} />
                  ))}
                  {heatPumpHeaters.map((device) => (
                    <DeviceCard key={device.id} device={device}
                      power={hpDeviceStates[device.id] === "ON" ? "on" : "off"}
                      lastSavedAt={deviceState[device.id]?.lastSavedAt}
                      onToggle={(isOn) => handleToggle(device.id, isOn)} />
                  ))}
                  {heatPumpFans.map((device) => (
                    <DeviceCard key={device.id} device={device}
                      power={hpDeviceStates[device.id] === "ON" ? "on" : "off"}
                      lastSavedAt={deviceState[device.id]?.lastSavedAt}
                      onToggle={(isOn) => handleToggle(device.id, isOn)} />
                  ))}
                </div>
              </>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}
