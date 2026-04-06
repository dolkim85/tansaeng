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
  const [hpMode, setHpMode] = useState<"AUTO" | "MANUAL">("MANUAL");
  const hpModeRef = useRef<"AUTO" | "MANUAL">("MANUAL"); // AUTO 로직에서 stale closure 방지
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

  // 장치별 온도 범위 — DB에서 로드 (빈 상태로 시작, ?? fallback으로 기본값 표시)
  const [hpDeviceRanges, setHpDeviceRanges] = useState<Record<string, { low: number; high: number }>>({});
  const [hpAutoActive, setHpAutoActive] = useState(false);
  const hpDeviceLastCmd = useRef<Record<string, "ON" | "OFF" | null>>({ hp_pump: null, hp_heater: null, hp_fan: null });
  const hpDeviceRangesFromMqttRef = useRef(false);

  // 팬 AUTO 제어 상태
  const [fanMode, setFanMode] = useState<"AUTO" | "MANUAL">("MANUAL");
  const fanModeRef = useRef<"AUTO" | "MANUAL">("MANUAL");
  const [fanDeviceRanges, setFanDeviceRanges] = useState<Record<string, { low: number; high: number }>>({});
  const [fanAutoActive, setFanAutoActive] = useState(false);
  const fanDeviceLastCmd = useRef<Record<string, "ON" | "OFF" | null>>({});
  const fanRangesFromMqttRef = useRef(false);
  const [fanAutoSensor, setFanAutoSensor] = useState<"temp" | "humi">("temp");
  const fanAutoSensorRef = useRef<"temp" | "humi">("temp");
  const [fanHumRanges, setFanHumRanges] = useState<Record<string, { low: number; high: number }>>({});
  const fanHumRangesFromMqttRef = useRef(false);

  // 게이지별 마지막 저장 시각 (DB)
  const [rangesSavedAt, setRangesSavedAt] = useState<Record<string, string>>({});

  // 천창 AUTO 제어 상태
  const [skyMode, setSkyMode] = useState<"AUTO" | "MANUAL">("MANUAL");
  const skyModeRef = useRef<"AUTO" | "MANUAL">("MANUAL");
  const [skyAutoActive, setSkyAutoActive] = useState(false);
  const [skyTempPoints, setSkyTempPoints] = useState<Array<{ temp: number; rate: number }>>([
    { temp: 20, rate: 10 },
    { temp: 23, rate: 30 },
    { temp: 28, rate: 100 },
  ]);
  const skyTempPointsFromMqttRef = useRef(false);
  const skyTempPointsFirstRunRef = useRef(true);
  const skyLastTargetRef = useRef<Record<string, number | null>>({});
  const [skyAutoType, setSkyAutoType] = useState<"temp" | "time" | "combined">("temp");
  const skyAutoTypeRef = useRef<"temp" | "time" | "combined">("temp");
  const skyAutoTypeFromMqttRef = useRef(false);
  const skyAutoTypeFirstRunRef = useRef(true); // 첫 렌더 시 기본값 publish 방지
  const [skyTimePoints, setSkyTimePoints] = useState<Array<{ time: string; rate: number }>>([
    { time: "08:00", rate: 30 },
    { time: "12:00", rate: 80 },
    { time: "18:00", rate: 0 },
  ]);
  const skyTimePointsFromMqttRef = useRef(false);
  const skyTimePointsFirstRunRef = useRef(true); // 첫 렌더 시 기본값 publish 방지
  const [currentMinute, setCurrentMinute] = useState(() => {
    const now = new Date(); return now.getHours() * 60 + now.getMinutes();
  });

  // 측창 AUTO 제어 상태
  const [sideMode, setSideMode] = useState<"AUTO" | "MANUAL">("MANUAL");
  const sideModeRef = useRef<"AUTO" | "MANUAL">("MANUAL");
  const [sideAutoActive, setSideAutoActive] = useState(false);
  const [sideAutoSensor, setSideAutoSensor] = useState<"temp" | "humi">("temp");
  const sideAutoSensorRef = useRef<"temp" | "humi">("temp");
  // 온도-개도율 매핑 포인트 (온도 오름차순 유지)
  const [sideTempPoints, setSideTempPoints] = useState<Array<{ temp: number; rate: number }>>([
    { temp: 20, rate: 10 },
    { temp: 23, rate: 30 },
    { temp: 28, rate: 100 },
  ]);
  const sideTempPointsFromMqttRef = useRef(false);
  const sideTempPointsFirstRunRef = useRef(true);
  // 습도-개도율 매핑 포인트
  const [sideHumPoints, setSideHumPoints] = useState<Array<{ humi: number; rate: number }>>([
    { humi: 60, rate: 10 },
    { humi: 70, rate: 30 },
    { humi: 80, rate: 100 },
  ]);
  const sideHumPointsFromMqttRef = useRef(false);
  const sideHumPointsFirstRunRef = useRef(true);
  // 마지막 전송 개도율 추적 (중복/히스테리시스 방지)
  const sideLastTargetRef = useRef<Record<string, number | null>>({});

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

  // 1분마다 currentMinute 갱신 (시간 기반 AUTO 제어 트리거용)
  useEffect(() => {
    const interval = setInterval(() => {
      const now = new Date();
      setCurrentMinute(now.getHours() * 60 + now.getMinutes());
    }, 60000);
    return () => clearInterval(interval);
  }, []);

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

  // ── DB에서 장치 온도 범위 로드 (마운트 시 1회) ─────────────────────────────
  useEffect(() => {
    fetch("/api/smartfarm/get_device_ranges.php")
      .then(r => r.json())
      .then(d => {
        if (!d.success) return;
        const dbRanges = d.ranges as Record<string, { low: number; high: number; updated_at: string }>;

        // HP 범위 복원
        const hpKeys = ["hp_pump", "hp_heater", "hp_fan"];
        const hpLoaded: Record<string, { low: number; high: number }> = {};
        hpKeys.forEach(k => {
          if (dbRanges[k]) hpLoaded[k] = { low: dbRanges[k].low, high: dbRanges[k].high };
        });
        if (Object.keys(hpLoaded).length > 0) {
          hpDeviceRangesFromMqttRef.current = true; // MQTT publish 방지
          setHpDeviceRanges(hpLoaded);
          getMqttClient().publish("tansaeng/hp-control/ranges", JSON.stringify(hpLoaded), { qos: 1, retain: true });
        }

        // 팬 범위 복원
        const fanLoaded: Record<string, { low: number; high: number }> = {};
        Object.entries(dbRanges).forEach(([k, v]) => {
          if (!hpKeys.includes(k)) fanLoaded[k] = { low: v.low, high: v.high };
        });
        if (Object.keys(fanLoaded).length > 0) {
          fanRangesFromMqttRef.current = true;
          setFanDeviceRanges(fanLoaded);
          getMqttClient().publish("tansaeng/fan-control/ranges", JSON.stringify(fanLoaded), { qos: 1, retain: true });
        }

        // 저장 시각 복원
        const savedAt: Record<string, string> = {};
        Object.entries(dbRanges).forEach(([k, v]) => { savedAt[k] = v.updated_at; });
        setRangesSavedAt(savedAt);
      })
      .catch(() => {});
  }, []);

  // ── 게이지 범위 저장 함수 (DB + MQTT retain) ──────────────────────────────
  const saveDeviceRange = async (
    deviceKey: string,
    deviceName: string,
    low: number,
    high: number,
    onSuccess?: (updatedAt: string) => void
  ) => {
    try {
      const res = await fetch("/api/smartfarm/save_device_range.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ device_key: deviceKey, device_name: deviceName, range_low: low, range_high: high }),
      });
      const d = await res.json();
      if (d.success) {
        setRangesSavedAt(prev => ({ ...prev, [deviceKey]: d.updated_at }));
        onSuccess?.(d.updated_at);
      } else {
        alert(`저장 실패: ${d.message}`);
      }
    } catch {
      alert("네트워크 오류");
    }
  };

  // 히트펌프 MQTT 센서 & 상태 구독
  useEffect(() => {
    const HP = "ctlr-heat-001";
    const unsubs = [
      // mode/state는 구독하되 UI 모드에 반영 안 함 — ESP32는 항상 MANUAL, UI 모드는 버튼으로만 제어
      subscribeToTopic(`tansaeng/${HP}/mode/state`, (_v) => { /* display only */ }),
      // UI 모드 retain 복원 (재접속 시 이전 설정 유지)
      subscribeToTopic("tansaeng/hp-control/mode", (v) => {
        if (v === "AUTO" || v === "MANUAL") {
          hpModeRef.current = v;
          setHpMode(v);
        }
      }),
      subscribeToTopic("tansaeng/hp-control/autoActive", (v) => {
        setHpAutoActive(v === "true");
      }),
      subscribeToTopic("tansaeng/hp-control/ranges", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (parsed && typeof parsed === "object") {
            hpDeviceRangesFromMqttRef.current = true;
            setHpDeviceRanges(parsed);
          }
        } catch {}
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

  // 팬 제어 상태 MQTT 구독 (retain 메시지로 다른 브라우저 상태 복원)
  useEffect(() => {
    const unsubs = [
      subscribeToTopic("tansaeng/fan-control/mode", (v) => {
        if (v === "AUTO" || v === "MANUAL") {
          fanModeRef.current = v;
          setFanMode(v);
        }
      }),
      subscribeToTopic("tansaeng/fan-control/autoActive", (v) => {
        setFanAutoActive(v === "true");
      }),
      subscribeToTopic("tansaeng/fan-control/ranges", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (parsed && typeof parsed === "object") {
            fanRangesFromMqttRef.current = true; // MQTT 복원임을 표시
            setFanDeviceRanges(parsed);
          }
        } catch {}
      }),
      subscribeToTopic("tansaeng/fan-control/autoSensor", (v) => {
        if (v === "temp" || v === "humi") { fanAutoSensorRef.current = v; setFanAutoSensor(v); }
      }),
      subscribeToTopic("tansaeng/fan-control/humRanges", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (parsed && typeof parsed === "object") { fanHumRangesFromMqttRef.current = true; setFanHumRanges(parsed); }
        } catch {}
      }),
      // 수동 ON/OFF 상태 복원 (다른 기기 동기화)
      subscribeToTopic("tansaeng/fan-control/manualStates", (v) => {
        try {
          const parsed = JSON.parse(v) as Record<string, string>;
          if (parsed && typeof parsed === "object") {
            setDeviceState(prev => {
              const next = { ...prev };
              Object.entries(parsed).forEach(([fanId, power]) => {
                if (power === "on" || power === "off") {
                  next[fanId] = { ...next[fanId], power: power as "on" | "off" };
                }
              });
              return next;
            });
          }
        } catch {}
      }),
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
    // state + ref 이중 체크 (MANUAL 모드에서 절대 실행 안 되도록)
    if (hpMode !== "AUTO") return;
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
        const deviceName = { hp_pump: "히트펌프 순환펌프", hp_heater: "전기온열기", hp_fan: "장치실 팬" }[key];
        sendDeviceCommand(HP, mqttId, newCmd).then(result => {
          if (result.success) console.log(`[API SUCCESS] ${deviceName} - ${newCmd}`);
          else console.error(`[API ERROR] ${deviceName} - ${newCmd}: ${result.message}`);
        });
      }
    });
  }, [farmSensors, hpDeviceRanges, hpAutoActive, hpMode]);

  // 팬 AUTO 모드 제어 로직 — 선택된 센서(온도/습도) 범위 안에 있으면 ON, 벗어나면 OFF
  useEffect(() => {
    if (fanModeRef.current !== "AUTO") return;
    if (!fanAutoActive) return;
    const useHumi = fanAutoSensorRef.current === "humi";
    const values = useHumi
      ? [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[]
      : [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
    if (values.length === 0) return;
    const avgValue = values.reduce((a, b) => a + b, 0) / values.length;
    fans.forEach((fan) => {
      const range = useHumi
        ? (fanHumRanges[fan.id] ?? { low: 60, high: 80 })
        : (fanDeviceRanges[fan.id] ?? { low: 15, high: 22 });
      const inRange = avgValue >= range.low && avgValue <= range.high;
      const newCmd: "ON" | "OFF" = inRange ? "ON" : "OFF";
      if (fanDeviceLastCmd.current[fan.id] !== newCmd) {
        fanDeviceLastCmd.current[fan.id] = newCmd;
        setDeviceState(prev => ({
          ...prev,
          [fan.id]: { ...prev[fan.id], power: inRange ? "on" : "off", lastSavedAt: new Date().toISOString() }
        }));
        const mqttDeviceId = fan.commandTopic.split('/')[2];
        sendDeviceCommand(fan.esp32Id, mqttDeviceId, newCmd);
      }
    });
  }, [farmSensors, fanDeviceRanges, fanHumRanges, fanAutoActive, fanAutoSensor]);

  // 팬 온도 범위 변경 시 MQTT retain 발행 (MQTT 복원에서 온 변경은 재발행 안함)
  useEffect(() => {
    if (fanRangesFromMqttRef.current) { fanRangesFromMqttRef.current = false; return; }
    if (Object.keys(fanDeviceRanges).length === 0) return;
    getMqttClient().publish("tansaeng/fan-control/ranges", JSON.stringify(fanDeviceRanges), { qos: 1, retain: true });
  }, [fanDeviceRanges]);

  // 팬 습도 범위 변경 시 MQTT retain 발행
  useEffect(() => {
    if (fanHumRangesFromMqttRef.current) { fanHumRangesFromMqttRef.current = false; return; }
    if (Object.keys(fanHumRanges).length === 0) return;
    getMqttClient().publish("tansaeng/fan-control/humRanges", JSON.stringify(fanHumRanges), { qos: 1, retain: true });
  }, [fanHumRanges]);

  // HP 온도 범위 변경 시 MQTT retain 발행 (팬 제어와 동일 패턴)
  useEffect(() => {
    if (hpDeviceRangesFromMqttRef.current) { hpDeviceRangesFromMqttRef.current = false; return; }
    if (Object.keys(hpDeviceRanges).length === 0) return; // 빈 초기값 → skip
    getMqttClient().publish("tansaeng/hp-control/ranges", JSON.stringify(hpDeviceRanges), { qos: 1, retain: true });
  }, [hpDeviceRanges]);

  // 측창 습도 포인트 변경 시 MQTT retain 발행
  useEffect(() => {
    if (sideHumPointsFirstRunRef.current) { sideHumPointsFirstRunRef.current = false; return; }
    if (sideHumPointsFromMqttRef.current) { sideHumPointsFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/side-control/humPoints", JSON.stringify(sideHumPoints), { qos: 1, retain: true });
  }, [sideHumPoints]);

  // 천창 MQTT 상태 구독 (retain으로 다른 브라우저 동기화)
  useEffect(() => {
    const unsubs = [
      subscribeToTopic("tansaeng/sky-control/mode", (v) => {
        if (v === "AUTO" || v === "MANUAL") {
          skyModeRef.current = v;
          setSkyMode(v);
        }
      }),
      subscribeToTopic("tansaeng/sky-control/autoActive", (v) => {
        setSkyAutoActive(v === "true");
      }),
      subscribeToTopic("tansaeng/sky-control/tempPoints", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (Array.isArray(parsed) && parsed.length > 0) {
            skyTempPointsFromMqttRef.current = true;
            setSkyTempPoints(parsed);
          }
        } catch {}
      }),
      subscribeToTopic("tansaeng/sky-control/windowL/currentPos", (v) => {
        const n = Number(v);
        if (!isNaN(n)) setCurrentPosition(prev => ({ ...prev, skylight_left: n }));
      }),
      subscribeToTopic("tansaeng/sky-control/windowR/currentPos", (v) => {
        const n = Number(v);
        if (!isNaN(n)) setCurrentPosition(prev => ({ ...prev, skylight_right: n }));
      }),
      subscribeToTopic("tansaeng/sky-control/autoType", (v) => {
        if (v === "temp" || v === "time" || v === "combined") {
          skyAutoTypeFromMqttRef.current = true;
          skyAutoTypeRef.current = v;
          setSkyAutoType(v);
        }
      }),
      subscribeToTopic("tansaeng/sky-control/timePoints", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (Array.isArray(parsed) && parsed.length > 0) {
            skyTimePointsFromMqttRef.current = true;
            setSkyTimePoints(parsed);
          }
        } catch {}
      }),
    ];
    return () => unsubs.forEach(u => u());
  }, []);

  // 천창 온도 포인트 변경 시 MQTT retain 발행 (첫 렌더 기본값으로 덮어쓰기 방지)
  useEffect(() => {
    if (skyTempPointsFirstRunRef.current) { skyTempPointsFirstRunRef.current = false; return; }
    if (skyTempPointsFromMqttRef.current) { skyTempPointsFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/sky-control/tempPoints", JSON.stringify(skyTempPoints), { qos: 1, retain: true });
  }, [skyTempPoints]);

  // 천창 autoType 변경 시 MQTT retain 발행 (첫 렌더 기본값으로 덮어쓰기 방지)
  useEffect(() => {
    if (skyAutoTypeFirstRunRef.current) { skyAutoTypeFirstRunRef.current = false; return; }
    if (skyAutoTypeFromMqttRef.current) { skyAutoTypeFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/sky-control/autoType", skyAutoType, { qos: 1, retain: true });
  }, [skyAutoType]);

  // 천창 시간 포인트 변경 시 MQTT retain 발행 (첫 렌더 기본값으로 덮어쓰기 방지)
  useEffect(() => {
    if (skyTimePointsFirstRunRef.current) { skyTimePointsFirstRunRef.current = false; return; }
    if (skyTimePointsFromMqttRef.current) { skyTimePointsFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/sky-control/timePoints", JSON.stringify(skyTimePoints), { qos: 1, retain: true });
  }, [skyTimePoints]);

  // 천창 AUTO 제어 로직 — 평균온도→개도율 계산 후 자동 이동
  useEffect(() => {
    if (skyModeRef.current !== "AUTO") return;
    if (!skyAutoActive) return;

    let targetRate = 0;

    if (skyAutoType === "time" || skyAutoTypeRef.current === "time") {
      // 시간 기반 제어: 현재 시각→개도율 선형 보간
      const toMin = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
      const nowMin = currentMinute;
      const sorted = [...skyTimePoints].sort((a, b) => toMin(a.time) - toMin(b.time));
      if (sorted.length === 0) return;
      if (nowMin < toMin(sorted[0].time)) {
        targetRate = sorted[0].rate;
      } else if (nowMin >= toMin(sorted[sorted.length - 1].time)) {
        targetRate = sorted[sorted.length - 1].rate;
      } else {
        for (let i = 0; i < sorted.length - 1; i++) {
          const s = toMin(sorted[i].time), e = toMin(sorted[i + 1].time);
          if (nowMin >= s && nowMin < e) {
            const ratio = (nowMin - s) / (e - s);
            targetRate = Math.round(sorted[i].rate + ratio * (sorted[i + 1].rate - sorted[i].rate));
            break;
          }
        }
      }
    } else if (skyAutoType === "combined" || skyAutoTypeRef.current === "combined") {
      // combined: min(시간허용치, 온도기준) — 햇빛 확보 + 환기 균형
      const toMin2 = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
      const nowMin2 = currentMinute;
      const timeSorted2 = [...skyTimePoints].sort((a, b) => toMin2(a.time) - toMin2(b.time));
      let timeRate = 0;
      if (timeSorted2.length > 0) {
        if (nowMin2 < toMin2(timeSorted2[0].time)) timeRate = timeSorted2[0].rate;
        else if (nowMin2 >= toMin2(timeSorted2[timeSorted2.length - 1].time)) timeRate = timeSorted2[timeSorted2.length - 1].rate;
        else {
          for (let i = 0; i < timeSorted2.length - 1; i++) {
            const s2 = toMin2(timeSorted2[i].time), e2 = toMin2(timeSorted2[i + 1].time);
            if (nowMin2 >= s2 && nowMin2 < e2) {
              const ratio2 = (nowMin2 - s2) / (e2 - s2);
              timeRate = Math.round(timeSorted2[i].rate + ratio2 * (timeSorted2[i + 1].rate - timeSorted2[i].rate));
              break;
            }
          }
        }
      }
      const temps2 = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
      if (temps2.length === 0) return;
      const avgTemp2 = temps2.reduce((a, b) => a + b, 0) / temps2.length;
      const tempSorted2 = [...skyTempPoints].sort((a, b) => a.temp - b.temp);
      let tempRate = 0;
      if (avgTemp2 >= tempSorted2[tempSorted2.length - 1].temp) {
        tempRate = 100;
      } else if (avgTemp2 >= tempSorted2[0].temp) {
        for (let i = 0; i < tempSorted2.length - 1; i++) {
          if (avgTemp2 >= tempSorted2[i].temp && avgTemp2 < tempSorted2[i + 1].temp) {
            const ratio2 = (avgTemp2 - tempSorted2[i].temp) / (tempSorted2[i + 1].temp - tempSorted2[i].temp);
            tempRate = Math.round(tempSorted2[i].rate + ratio2 * (tempSorted2[i + 1].rate - tempSorted2[i].rate));
            break;
          }
        }
      }
      targetRate = Math.min(timeRate, tempRate);
    } else {
      // 온도 기반 제어 (기존)
      const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
      if (temps.length === 0) return;
      const avgTemp = temps.reduce((a, b) => a + b, 0) / temps.length;
      const sorted = [...skyTempPoints].sort((a, b) => a.temp - b.temp);
      if (avgTemp < sorted[0].temp) {
        targetRate = 0;
      } else if (avgTemp >= sorted[sorted.length - 1].temp) {
        targetRate = 100;
      } else {
        for (let i = 0; i < sorted.length - 1; i++) {
          if (avgTemp >= sorted[i].temp && avgTemp < sorted[i + 1].temp) {
            const ratio = (avgTemp - sorted[i].temp) / (sorted[i + 1].temp - sorted[i].temp);
            targetRate = Math.round(sorted[i].rate + ratio * (sorted[i + 1].rate - sorted[i].rate));
            break;
          }
        }
      }
    }

    skylights.forEach((skylight) => {
      const lastTarget = skyLastTargetRef.current[skylight.id] ?? null;
      if (lastTarget !== null && Math.abs(targetRate - lastTarget) < 2) return;

      const currentPos = currentPosition[skylight.id] ?? 0;
      const difference = targetRate - currentPos;
      if (Math.abs(difference) < 1) return;

      skyLastTargetRef.current[skylight.id] = targetRate;

      if (percentageTimers.current[skylight.id]) {
        clearTimeout(percentageTimers.current[skylight.id]);
        delete percentageTimers.current[skylight.id];
      }

      setOperationStatus(prev => ({ ...prev, [skylight.id]: 'running' }));

      const fullTimeSeconds = 300;
      const targetTimeSeconds = (Math.abs(difference) / 100) * fullTimeSeconds;
      const command = difference > 0 ? "OPEN" : "CLOSE";
      const mqttDeviceId = skylight.commandTopic.split('/')[2];

      console.log(`[SKY AUTO] ${skylight.name} (${skyAutoTypeRef.current}) →${targetRate}% (현재:${currentPos}%, ${targetTimeSeconds.toFixed(1)}초 ${command})`);

      sendDeviceCommand(skylight.esp32Id, mqttDeviceId, command).then(() => {
        percentageTimers.current[skylight.id] = setTimeout(async () => {
          await sendDeviceCommand(skylight.esp32Id, mqttDeviceId, "STOP");
          delete percentageTimers.current[skylight.id];
          setCurrentPosition(prev => ({ ...prev, [skylight.id]: targetRate }));
          setOperationStatus(prev => ({ ...prev, [skylight.id]: 'completed' }));
          console.log(`[SKY AUTO] ${skylight.name} → ${targetRate}% 완료`);
        }, targetTimeSeconds * 1000);
      });
    });
  }, [farmSensors, skyTempPoints, skyAutoActive, skyTimePoints, currentMinute, skyAutoType]);

  // 측창 MQTT 상태 구독 (retain으로 다른 브라우저 동기화)
  useEffect(() => {
    const unsubs = [
      subscribeToTopic("tansaeng/side-control/mode", (v) => {
        if (v === "AUTO" || v === "MANUAL") {
          sideModeRef.current = v;
          setSideMode(v);
        }
      }),
      subscribeToTopic("tansaeng/side-control/autoActive", (v) => {
        setSideAutoActive(v === "true");
      }),
      subscribeToTopic("tansaeng/side-control/tempPoints", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (Array.isArray(parsed) && parsed.length > 0) {
            sideTempPointsFromMqttRef.current = true;
            setSideTempPoints(parsed);
          }
        } catch {}
      }),
      subscribeToTopic("tansaeng/side-control/sideL/currentPos", (v) => {
        const n = Number(v);
        if (!isNaN(n)) setCurrentPosition(prev => ({ ...prev, sidescreen_left: n }));
      }),
      subscribeToTopic("tansaeng/side-control/sideR/currentPos", (v) => {
        const n = Number(v);
        if (!isNaN(n)) setCurrentPosition(prev => ({ ...prev, sidescreen_right: n }));
      }),
      subscribeToTopic("tansaeng/side-control/autoSensor", (v) => {
        if (v === "temp" || v === "humi") { sideAutoSensorRef.current = v; setSideAutoSensor(v); }
      }),
      subscribeToTopic("tansaeng/side-control/humPoints", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (Array.isArray(parsed) && parsed.length > 0) { sideHumPointsFromMqttRef.current = true; setSideHumPoints(parsed); }
        } catch {}
      }),
    ];
    return () => unsubs.forEach(u => u());
  }, []);

  // 측창 온도 포인트 변경 시 MQTT retain 발행 (첫 렌더 기본값으로 덮어쓰기 방지)
  useEffect(() => {
    if (sideTempPointsFirstRunRef.current) { sideTempPointsFirstRunRef.current = false; return; }
    if (sideTempPointsFromMqttRef.current) { sideTempPointsFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/side-control/tempPoints", JSON.stringify(sideTempPoints), { qos: 1, retain: true });
  }, [sideTempPoints]);

  // 측창 AUTO 제어 로직 — 선택된 센서(온도/습도)→개도율 계산 후 자동 이동
  useEffect(() => {
    if (sideModeRef.current !== "AUTO") return;
    if (!sideAutoActive) return;
    const useHumi = sideAutoSensorRef.current === "humi";

    let targetRate = 0;
    let sensorLabel = "";

    if (useHumi) {
      const humis = [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[];
      if (humis.length === 0) return;
      const avgHumi = humis.reduce((a, b) => a + b, 0) / humis.length;
      sensorLabel = `습도${avgHumi.toFixed(0)}%`;
      const sorted = [...sideHumPoints].sort((a, b) => a.humi - b.humi);
      if (avgHumi < sorted[0].humi) targetRate = 0;
      else if (avgHumi >= sorted[sorted.length - 1].humi) targetRate = 100;
      else {
        for (let i = 0; i < sorted.length - 1; i++) {
          if (avgHumi >= sorted[i].humi && avgHumi < sorted[i + 1].humi) {
            const ratio = (avgHumi - sorted[i].humi) / (sorted[i + 1].humi - sorted[i].humi);
            targetRate = Math.round(sorted[i].rate + ratio * (sorted[i + 1].rate - sorted[i].rate));
            break;
          }
        }
      }
    } else {
      const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
      if (temps.length === 0) return;
      const avgTemp = temps.reduce((a, b) => a + b, 0) / temps.length;
      sensorLabel = `온도${avgTemp.toFixed(1)}°`;
      const sorted = [...sideTempPoints].sort((a, b) => a.temp - b.temp);
      if (avgTemp < sorted[0].temp) targetRate = 0;
      else if (avgTemp >= sorted[sorted.length - 1].temp) targetRate = 100;
      else {
        for (let i = 0; i < sorted.length - 1; i++) {
          if (avgTemp >= sorted[i].temp && avgTemp < sorted[i + 1].temp) {
            const ratio = (avgTemp - sorted[i].temp) / (sorted[i + 1].temp - sorted[i].temp);
            targetRate = Math.round(sorted[i].rate + ratio * (sorted[i + 1].rate - sorted[i].rate));
            break;
          }
        }
      }
    }

    sidescreens.forEach((sidescreen) => {
      const lastTarget = sideLastTargetRef.current[sidescreen.id] ?? null;
      if (lastTarget !== null && Math.abs(targetRate - lastTarget) < 2) return;

      const currentPos = currentPosition[sidescreen.id] ?? 0;
      const difference = targetRate - currentPos;
      if (Math.abs(difference) < 1) return;

      sideLastTargetRef.current[sidescreen.id] = targetRate;

      if (percentageTimers.current[sidescreen.id]) {
        clearTimeout(percentageTimers.current[sidescreen.id]);
        delete percentageTimers.current[sidescreen.id];
      }

      setOperationStatus(prev => ({ ...prev, [sidescreen.id]: 'running' }));

      const fullTimeSeconds = 120;
      const targetTimeSeconds = (Math.abs(difference) / 100) * fullTimeSeconds;
      const command = difference > 0 ? "OPEN" : "CLOSE";
      const mqttDeviceId = sidescreen.commandTopic.split('/')[2];

      console.log(`[SIDE AUTO] ${sidescreen.name} ${sensorLabel}→${targetRate}% (현재:${currentPos}%, ${targetTimeSeconds.toFixed(1)}초 ${command})`);

      sendDeviceCommand(sidescreen.esp32Id, mqttDeviceId, command).then(() => {
        percentageTimers.current[sidescreen.id] = setTimeout(async () => {
          await sendDeviceCommand(sidescreen.esp32Id, mqttDeviceId, "STOP");
          delete percentageTimers.current[sidescreen.id];
          setCurrentPosition(prev => ({ ...prev, [sidescreen.id]: targetRate }));
          setOperationStatus(prev => ({ ...prev, [sidescreen.id]: 'completed' }));
          console.log(`[SIDE AUTO] ${sidescreen.name} → ${targetRate}% 완료`);
        }, targetTimeSeconds * 1000);
      });
    });
  }, [farmSensors, sideTempPoints, sideHumPoints, sideAutoActive, sideAutoSensor]);

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

          // 다른 기기와 ON/OFF 상태 동기화 (retain)
          const updatedFanStates: Record<string, string> = {};
          fans.forEach(f => {
            updatedFanStates[f.id] = f.id === deviceId
              ? (isOn ? "on" : "off")
              : (deviceState[f.id]?.power ?? "off");
          });
          getMqttClient().publish(
            "tansaeng/fan-control/manualStates",
            JSON.stringify(updatedFanStates),
            { qos: 1, retain: true }
          );
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

        {/* 천창 스크린 제어 섹션 */}
        <section className="mb-2 sm:mb-3">
          <header className="bg-amber-400 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
              천창 스크린
            </h2>
            <span className="text-[10px] sm:text-xs text-gray-800">{skylights.length}개</span>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-2 sm:p-4">

            {/* 제어 모드 버튼 */}
            <div className="bg-gray-50 border border-gray-200 rounded-lg p-2 sm:p-3 mb-3">
              <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-2">제어 모드</p>
              <div className="flex gap-2 mb-2">
                <button
                  onClick={() => {
                    skyModeRef.current = "AUTO";
                    setSkyMode("AUTO");
                    setSkyAutoActive(false);
                    skyLastTargetRef.current = {};
                    getMqttClient().publish("tansaeng/sky-control/mode", "AUTO", { qos: 1, retain: true });
                    getMqttClient().publish("tansaeng/sky-control/autoActive", "false", { qos: 1, retain: true });
                  }}
                  className={`flex-1 py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors ${
                    skyMode === "AUTO"
                      ? "bg-gray-900 text-white shadow"
                      : "bg-white border border-amber-300 text-amber-600 hover:bg-amber-50"
                  }`}
                >
                  자동 (AUTO)
                </button>
                <button
                  onClick={() => {
                    skyModeRef.current = "MANUAL";
                    setSkyMode("MANUAL");
                    setSkyAutoActive(false);
                    getMqttClient().publish("tansaeng/sky-control/mode", "MANUAL", { qos: 1, retain: true });
                    getMqttClient().publish("tansaeng/sky-control/autoActive", "false", { qos: 1, retain: true });
                  }}
                  className={`flex-1 py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors ${
                    skyMode === "MANUAL"
                      ? "bg-gray-700 text-yellow-300 shadow"
                      : "bg-white border border-gray-300 text-gray-600 hover:bg-gray-50"
                  }`}
                >
                  수동 (MANUAL)
                </button>
              </div>
              {skyMode === "AUTO" && (
                <>
                  {/* 제어 기준 선택 */}
                  <div className="flex gap-2 mb-2">
                    <button
                      onClick={() => {
                        skyAutoTypeRef.current = "temp";
                        skyAutoTypeFromMqttRef.current = false;
                        setSkyAutoType("temp");
                        skyLastTargetRef.current = {};
                        getMqttClient().publish("tansaeng/sky-control/autoType", "temp", { qos: 1, retain: true });
                      }}
                      className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${
                        skyAutoType === "temp"
                          ? "bg-orange-500 text-white shadow"
                          : "bg-white border border-orange-300 text-orange-600 hover:bg-orange-50"
                      }`}
                    >
                      온도 기준
                    </button>
                    <button
                      onClick={() => {
                        skyAutoTypeRef.current = "time";
                        skyAutoTypeFromMqttRef.current = false;
                        setSkyAutoType("time");
                        skyLastTargetRef.current = {};
                        getMqttClient().publish("tansaeng/sky-control/autoType", "time", { qos: 1, retain: true });
                      }}
                      className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${
                        skyAutoType === "time"
                          ? "bg-blue-500 text-white shadow"
                          : "bg-white border border-blue-300 text-blue-600 hover:bg-blue-50"
                      }`}
                    >
                      시간 기준
                    </button>
                    <button
                      onClick={() => {
                        skyAutoTypeRef.current = "combined";
                        skyAutoTypeFromMqttRef.current = false;
                        setSkyAutoType("combined");
                        skyLastTargetRef.current = {};
                        getMqttClient().publish("tansaeng/sky-control/autoType", "combined", { qos: 1, retain: true });
                      }}
                      className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${
                        skyAutoType === "combined"
                          ? "bg-green-600 text-white shadow"
                          : "bg-white border border-green-500 text-green-700 hover:bg-green-50"
                      }`}
                    >
                      복합
                    </button>
                  </div>
                  {/* 작동 시작/멈춤 */}
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => {
                        setSkyAutoActive(true);
                        skyLastTargetRef.current = {};
                        // autoType을 먼저 재발행해서 데몬이 정확한 모드로 실행되도록 보장
                        getMqttClient().publish("tansaeng/sky-control/autoType", skyAutoTypeRef.current, { qos: 1, retain: true });
                        getMqttClient().publish("tansaeng/sky-control/autoActive", "true", { qos: 1, retain: true });
                      }}
                      disabled={skyAutoActive}
                      className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${
                        skyAutoActive
                          ? "bg-amber-500 text-white shadow"
                          : "bg-white border border-amber-400 text-amber-600 hover:bg-amber-50"
                      }`}
                    >
                      ▶ 작동시작
                    </button>
                    <button
                      onClick={() => {
                        setSkyAutoActive(false);
                        getMqttClient().publish("tansaeng/sky-control/autoActive", "false", { qos: 1, retain: true });
                        // 진행 중인 모터 타이머 취소 + STOP 명령 즉시 전송
                        skylights.forEach((skylight) => {
                          if (percentageTimers.current[skylight.id]) {
                            clearTimeout(percentageTimers.current[skylight.id]);
                            delete percentageTimers.current[skylight.id];
                          }
                          const mqttDeviceId = skylight.commandTopic.split('/')[2];
                          sendDeviceCommand(skylight.esp32Id, mqttDeviceId, "STOP");
                          setOperationStatus(prev => ({ ...prev, [skylight.id]: 'idle' }));
                        });
                      }}
                      disabled={!skyAutoActive}
                      className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${
                        skyAutoActive
                          ? "bg-red-500 text-white shadow"
                          : "bg-white border border-red-400 text-red-600 hover:bg-red-50"
                      }`}
                    >
                      ■ 작동멈춤
                    </button>
                    <span className="text-[10px] font-semibold text-amber-600">
                      {skyAutoActive ? "🌤 AUTO 작동 중" : "⏸ 대기"}
                    </span>
                  </div>
                </>
              )}
            </div>

            {/* 팜 상태 + 목표개도율 표시 */}
            {(() => {
              const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
              const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;

              // 온도 기준 목표개도율
              let tempTargetRate = 0;
              if (avgTemp !== null) {
                const sorted = [...skyTempPoints].sort((a, b) => a.temp - b.temp);
                if (avgTemp >= sorted[sorted.length - 1].temp) tempTargetRate = 100;
                else if (avgTemp >= sorted[0].temp) {
                  for (let i = 0; i < sorted.length - 1; i++) {
                    if (avgTemp >= sorted[i].temp && avgTemp < sorted[i + 1].temp) {
                      const ratio = (avgTemp - sorted[i].temp) / (sorted[i + 1].temp - sorted[i].temp);
                      tempTargetRate = Math.round(sorted[i].rate + ratio * (sorted[i + 1].rate - sorted[i].rate));
                      break;
                    }
                  }
                }
              }

              // 시간 기준 목표개도율
              const toMin = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
              const nowMin = currentMinute;
              let timeTargetRate = 0;
              const timeSorted = [...skyTimePoints].sort((a, b) => toMin(a.time) - toMin(b.time));
              if (timeSorted.length > 0) {
                if (nowMin < toMin(timeSorted[0].time)) timeTargetRate = timeSorted[0].rate;
                else if (nowMin >= toMin(timeSorted[timeSorted.length - 1].time)) timeTargetRate = timeSorted[timeSorted.length - 1].rate;
                else {
                  for (let i = 0; i < timeSorted.length - 1; i++) {
                    const s = toMin(timeSorted[i].time), e = toMin(timeSorted[i + 1].time);
                    if (nowMin >= s && nowMin < e) {
                      const ratio = (nowMin - s) / (e - s);
                      timeTargetRate = Math.round(timeSorted[i].rate + ratio * (timeSorted[i + 1].rate - timeSorted[i].rate));
                      break;
                    }
                  }
                }
              }
              const nowHH = String(Math.floor(nowMin / 60)).padStart(2, '0');
              const nowMM = String(nowMin % 60).padStart(2, '0');

              return (
                <div className="bg-green-50 border border-green-200 rounded-lg p-2 sm:p-3 mb-3">
                  <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-1.5">
                    팜 내부 평균온도
                    {skyMode === "AUTO" && <span className="ml-1 text-amber-600">(AUTO 기준)</span>}
                  </p>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                    <div className="bg-red-50 border border-red-100 rounded p-1.5 text-center">
                      <div className="text-lg sm:text-2xl font-bold text-red-500">
                        {avgTemp !== null ? `${avgTemp.toFixed(1)}°` : "—"}
                      </div>
                      <div className="text-[9px] text-gray-400">평균온도</div>
                    </div>
                    {skyMode === "AUTO" && skyAutoType === "temp" && (
                      <div className="bg-orange-50 border border-orange-100 rounded p-1.5 text-center">
                        <div className="text-lg sm:text-2xl font-bold text-orange-500">
                          {avgTemp !== null ? `${tempTargetRate}%` : "—"}
                        </div>
                        <div className="text-[9px] text-gray-400">목표개도율</div>
                      </div>
                    )}
                    {skyMode === "AUTO" && skyAutoType === "time" && (
                      <>
                        <div className="bg-blue-50 border border-blue-100 rounded p-1.5 text-center">
                          <div className="text-lg sm:text-2xl font-bold text-blue-500">
                            {nowHH}:{nowMM}
                          </div>
                          <div className="text-[9px] text-gray-400">현재 시각</div>
                        </div>
                        <div className="bg-blue-50 border border-blue-100 rounded p-1.5 text-center">
                          <div className="text-lg sm:text-2xl font-bold text-blue-600">
                            {timeTargetRate}%
                          </div>
                          <div className="text-[9px] text-gray-400">목표개도율</div>
                        </div>
                      </>
                    )}
                    {skyMode === "AUTO" && skyAutoType === "combined" && (
                      <>
                        <div className="bg-green-50 border border-green-100 rounded p-1.5 text-center">
                          <div className="text-lg sm:text-2xl font-bold text-blue-500">
                            {timeTargetRate}%
                          </div>
                          <div className="text-[9px] text-gray-400">시간 허용</div>
                        </div>
                        <div className="bg-green-50 border border-green-100 rounded p-1.5 text-center">
                          <div className="text-lg sm:text-2xl font-bold text-orange-500">
                            {avgTemp !== null ? `${tempTargetRate}%` : "—"}
                          </div>
                          <div className="text-[9px] text-gray-400">온도 기준</div>
                        </div>
                        <div className="bg-green-100 border border-green-300 rounded p-1.5 text-center">
                          <div className="text-lg sm:text-2xl font-bold text-green-700">
                            {avgTemp !== null ? `${Math.min(timeTargetRate, tempTargetRate)}%` : "—"}
                          </div>
                          <div className="text-[9px] text-gray-400">최종 개도율</div>
                        </div>
                      </>
                    )}
                    {[
                      { label: "앞", t: farmSensors.front },
                      { label: "뒤", t: farmSensors.back },
                      { label: "천장", t: farmSensors.top },
                    ].map(({ label, t }) => (
                      <div key={label} className="bg-white rounded p-1 text-center">
                        <div className="text-[10px] font-semibold text-red-500">{t !== null ? `${t.toFixed(1)}°` : "—"}</div>
                        <div className="text-[9px] text-gray-400">{label}</div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })()}

            {/* AUTO 모드: 온도 기준 설정 테이블 */}
            {skyMode === "AUTO" && skyAutoType === "temp" && (
              <div className="bg-orange-50 border border-orange-200 rounded-lg p-2 sm:p-3 mb-3">
                <div className="flex items-center justify-between mb-2">
                  <p className="text-[10px] sm:text-xs font-semibold text-gray-700">온도-개도율 설정</p>
                  <button
                    onClick={() => setSkyTempPoints(prev => {
                      const sorted = [...prev].sort((a, b) => a.temp - b.temp);
                      const lastTemp = sorted[sorted.length - 1]?.temp ?? 20;
                      return [...sorted, { temp: lastTemp + 3, rate: 100 }];
                    })}
                    className="text-[10px] px-2 py-1 bg-orange-500 text-white rounded hover:bg-orange-600"
                  >
                    + 포인트 추가
                  </button>
                </div>
                <div className="text-[9px] text-gray-500 mb-2">
                  최저 온도 미만 → 0% 닫힘 / 최고 온도 이상 → 100% 완전 개방 (선형 보간)
                </div>
                <div className="space-y-1.5">
                  {[...skyTempPoints]
                    .sort((a, b) => a.temp - b.temp)
                    .map((point, idx) => (
                    <div key={idx} className="flex items-center gap-2 bg-white rounded p-1.5">
                      <span className="text-[10px] text-gray-500 w-4">{idx + 1}</span>
                      <div className="flex items-center gap-1 flex-1">
                        <span className="text-[10px] text-gray-600">온도</span>
                        <input
                          type="number"
                          value={point.temp}
                          onChange={(e) => {
                            const newPoints = [...skyTempPoints];
                            const realIdx = skyTempPoints.indexOf(point);
                            newPoints[realIdx] = { ...point, temp: Number(e.target.value) };
                            setSkyTempPoints(newPoints);
                          }}
                          className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                        />
                        <span className="text-[10px] text-gray-600">°C →</span>
                        <input
                          type="number"
                          min="0"
                          max="100"
                          value={point.rate}
                          onChange={(e) => {
                            const newPoints = [...skyTempPoints];
                            const realIdx = skyTempPoints.indexOf(point);
                            newPoints[realIdx] = { ...point, rate: Math.min(100, Math.max(0, Number(e.target.value))) };
                            setSkyTempPoints(newPoints);
                          }}
                          className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                        />
                        <span className="text-[10px] text-gray-600">% 개방</span>
                      </div>
                      {skyTempPoints.length > 2 && (
                        <button
                          onClick={() => setSkyTempPoints(prev => prev.filter((_, i) => i !== skyTempPoints.indexOf(point)))}
                          className="text-[10px] text-red-500 hover:text-red-700 px-1"
                        >
                          ✕
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* AUTO 모드: 시간 기준 설정 테이블 */}
            {skyMode === "AUTO" && skyAutoType === "time" && (
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-2 sm:p-3 mb-3">
                <div className="flex items-center justify-between mb-2">
                  <p className="text-[10px] sm:text-xs font-semibold text-gray-700">시간-개도율 설정</p>
                  <button
                    onClick={() => setSkyTimePoints(prev => {
                      const sorted = [...prev].sort((a, b) => a.time.localeCompare(b.time));
                      const lastTime = sorted[sorted.length - 1]?.time ?? "08:00";
                      const [h, m] = lastTime.split(':').map(Number);
                      const newH = Math.min(23, h + 2);
                      return [...sorted, { time: `${String(newH).padStart(2, '0')}:${String(m).padStart(2, '0')}`, rate: 0 }];
                    })}
                    className="text-[10px] px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                  >
                    + 포인트 추가
                  </button>
                </div>
                <div className="text-[9px] text-gray-500 mb-2">
                  설정 시각 사이를 선형 보간하여 개도율을 결정합니다 (데몬 60초 주기 실행)
                </div>
                <div className="space-y-1.5">
                  {[...skyTimePoints]
                    .sort((a, b) => a.time.localeCompare(b.time))
                    .map((point, idx) => (
                    <div key={idx} className="flex items-center gap-2 bg-white rounded p-1.5">
                      <span className="text-[10px] text-gray-500 w-4">{idx + 1}</span>
                      <div className="flex items-center gap-1 flex-1">
                        <span className="text-[10px] text-gray-600">시각</span>
                        <input
                          type="time"
                          value={point.time}
                          onChange={(e) => {
                            const newPoints = [...skyTimePoints];
                            const realIdx = skyTimePoints.indexOf(point);
                            newPoints[realIdx] = { ...point, time: e.target.value };
                            setSkyTimePoints(newPoints);
                          }}
                          className="w-20 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                        />
                        <span className="text-[10px] text-gray-600">→</span>
                        <input
                          type="number"
                          min="0"
                          max="100"
                          value={point.rate}
                          onChange={(e) => {
                            const newPoints = [...skyTimePoints];
                            const realIdx = skyTimePoints.indexOf(point);
                            newPoints[realIdx] = { ...point, rate: Math.min(100, Math.max(0, Number(e.target.value))) };
                            setSkyTimePoints(newPoints);
                          }}
                          className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                        />
                        <span className="text-[10px] text-gray-600">% 개방</span>
                      </div>
                      {skyTimePoints.length > 2 && (
                        <button
                          onClick={() => setSkyTimePoints(prev => prev.filter((_, i) => i !== skyTimePoints.indexOf(point)))}
                          className="text-[10px] text-red-500 hover:text-red-700 px-1"
                        >
                          ✕
                        </button>
                      )}
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* AUTO 모드: 복합 기준 설정 (시간 허용치 + 온도 기준 둘 다 표시) */}
            {skyMode === "AUTO" && skyAutoType === "combined" && (
              <div className="space-y-3 mb-3">
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-2 sm:p-3">
                  <div className="flex items-center justify-between mb-2">
                    <p className="text-[10px] sm:text-xs font-semibold text-blue-700">⏱ 시간별 최대 허용 개폐율 (상한선)</p>
                    <button
                      onClick={() => setSkyTimePoints(prev => {
                        const sorted = [...prev].sort((a, b) => a.time.localeCompare(b.time));
                        const lastTime = sorted[sorted.length - 1]?.time ?? "08:00";
                        const [h, m] = lastTime.split(':').map(Number);
                        const newH = Math.min(23, h + 2);
                        return [...sorted, { time: `${String(newH).padStart(2, '0')}:${String(m).padStart(2, '0')}`, rate: 0 }];
                      })}
                      className="text-[10px] px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                    >
                      + 추가
                    </button>
                  </div>
                  <div className="text-[9px] text-gray-500 mb-2">이 시간대에 열 수 있는 최대치 — 햇빛 확보를 위해 아침엔 낮게 설정</div>
                  <div className="space-y-1.5">
                    {[...skyTimePoints].sort((a, b) => a.time.localeCompare(b.time)).map((point, idx) => (
                      <div key={idx} className="flex items-center gap-2 bg-white rounded p-1.5">
                        <span className="text-[10px] text-gray-500 w-4">{idx + 1}</span>
                        <div className="flex items-center gap-1 flex-1">
                          <span className="text-[10px] text-gray-600">시각</span>
                          <input type="time" value={point.time}
                            onChange={(e) => {
                              const newPoints = [...skyTimePoints];
                              const realIdx = skyTimePoints.indexOf(point);
                              newPoints[realIdx] = { ...point, time: e.target.value };
                              setSkyTimePoints(newPoints);
                            }}
                            className="w-20 px-1.5 py-1 text-xs border border-gray-300 rounded text-center" />
                          <span className="text-[10px] text-gray-600">→ 최대</span>
                          <input type="number" min="0" max="100" value={point.rate}
                            onChange={(e) => {
                              const newPoints = [...skyTimePoints];
                              const realIdx = skyTimePoints.indexOf(point);
                              newPoints[realIdx] = { ...point, rate: Math.min(100, Math.max(0, Number(e.target.value))) };
                              setSkyTimePoints(newPoints);
                            }}
                            className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center" />
                          <span className="text-[10px] text-gray-600">%</span>
                        </div>
                        {skyTimePoints.length > 2 && (
                          <button onClick={() => setSkyTimePoints(prev => prev.filter((_, i) => i !== skyTimePoints.indexOf(point)))}
                            className="text-[10px] text-red-500 hover:text-red-700 px-1">✕</button>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
                <div className="bg-orange-50 border border-orange-200 rounded-lg p-2 sm:p-3">
                  <div className="flex items-center justify-between mb-2">
                    <p className="text-[10px] sm:text-xs font-semibold text-orange-700">🌡 온도별 개폐율 (실제 기준)</p>
                    <button
                      onClick={() => setSkyTempPoints(prev => {
                        const sorted = [...prev].sort((a, b) => a.temp - b.temp);
                        const lastTemp = sorted[sorted.length - 1]?.temp ?? 20;
                        return [...sorted, { temp: lastTemp + 3, rate: 100 }];
                      })}
                      className="text-[10px] px-2 py-1 bg-orange-500 text-white rounded hover:bg-orange-600"
                    >
                      + 추가
                    </button>
                  </div>
                  <div className="text-[9px] text-gray-500 mb-2">온도가 높을수록 더 열어 환기 — 시간 허용치를 초과할 수 없음</div>
                  <div className="space-y-1.5">
                    {[...skyTempPoints].sort((a, b) => a.temp - b.temp).map((point, idx) => (
                      <div key={idx} className="flex items-center gap-2 bg-white rounded p-1.5">
                        <span className="text-[10px] text-gray-500 w-4">{idx + 1}</span>
                        <div className="flex items-center gap-1 flex-1">
                          <span className="text-[10px] text-gray-600">온도</span>
                          <input type="number" value={point.temp}
                            onChange={(e) => {
                              const newPoints = [...skyTempPoints];
                              const realIdx = skyTempPoints.indexOf(point);
                              newPoints[realIdx] = { ...point, temp: Number(e.target.value) };
                              setSkyTempPoints(newPoints);
                            }}
                            className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center" />
                          <span className="text-[10px] text-gray-600">°C →</span>
                          <input type="number" min="0" max="100" value={point.rate}
                            onChange={(e) => {
                              const newPoints = [...skyTempPoints];
                              const realIdx = skyTempPoints.indexOf(point);
                              newPoints[realIdx] = { ...point, rate: Math.min(100, Math.max(0, Number(e.target.value))) };
                              setSkyTempPoints(newPoints);
                            }}
                            className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center" />
                          <span className="text-[10px] text-gray-600">% 개방</span>
                        </div>
                        {skyTempPoints.length > 2 && (
                          <button onClick={() => setSkyTempPoints(prev => prev.filter((_, i) => i !== skyTempPoints.indexOf(point)))}
                            className="text-[10px] text-red-500 hover:text-red-700 px-1">✕</button>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}

            {/* 장치 카드 */}
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
                    <div className="flex items-center gap-1.5">
                      {operationStatus[skylight.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[skylight.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-100 text-green-700 text-[10px] font-semibold rounded-full">
                          완료
                        </span>
                      )}
                      <span className="text-[10px] sm:text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                        {skylight.esp32Id}
                      </span>
                    </div>
                  </div>

                  {/* 현재 위치 게이지 */}
                  <div className="mb-2">
                    <div className="flex items-center justify-between text-[10px] sm:text-xs text-gray-600 mb-1">
                      <span>현재 위치</span>
                      <span className="font-bold text-amber-600 text-sm">{currentPosition[skylight.id] ?? 0}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-amber-500 h-2 rounded-full transition-all duration-500"
                        style={{ width: `${currentPosition[skylight.id] ?? 0}%` }}
                      />
                    </div>
                  </div>

                  {/* MANUAL 모드에서만 수동 제어 표시 */}
                  {skyMode === "MANUAL" && (
                    <>
                      <div className="mb-2 sm:mb-3">
                        <p className="text-[10px] sm:text-xs text-gray-600 font-medium mb-1.5">버튼 제어</p>
                        <div className="flex gap-1.5 sm:gap-2">
                          <button
                            onClick={() => handleSkylightCommand(skylight.id, "OPEN")}
                            className="flex-1 bg-green-500 hover:bg-green-600 active:bg-green-700 text-white font-semibold py-2 px-2 rounded-md transition-colors text-xs sm:text-sm"
                          >
                            열기
                          </button>
                          <button
                            onClick={() => handleSkylightCommand(skylight.id, "STOP")}
                            className="flex-1 bg-yellow-500 hover:bg-yellow-600 active:bg-yellow-700 text-white font-semibold py-2 px-2 rounded-md transition-colors text-xs sm:text-sm"
                          >
                            정지
                          </button>
                          <button
                            onClick={() => handleSkylightCommand(skylight.id, "CLOSE")}
                            className="flex-1 bg-red-500 hover:bg-red-600 active:bg-red-700 text-white font-semibold py-2 px-2 rounded-md transition-colors text-xs sm:text-sm"
                          >
                            닫기
                          </button>
                        </div>
                      </div>
                      <div>
                        <p className="text-[10px] sm:text-xs text-gray-600 font-medium mb-1.5">개폐 퍼센트 설정</p>
                        <div className="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                          <input
                            type="number"
                            min="0"
                            max="100"
                            value={percentageInputs[skylight.id] ?? (deviceState[skylight.id]?.targetPercentage ?? 0)}
                            onChange={(e) => setPercentageInputs({
                              ...percentageInputs,
                              [skylight.id]: e.target.value
                            })}
                            className="flex-1 px-2 sm:px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                            placeholder="0-100"
                          />
                          <span className="text-xs font-semibold text-gray-900">%</span>
                          <button
                            onClick={() => handleSavePercentage(skylight.id)}
                            className="px-2 sm:px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded-md transition-colors"
                          >
                            저장
                          </button>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-[10px] text-gray-500">
                            저장: <span className="font-semibold text-amber-600">{deviceState[skylight.id]?.targetPercentage ?? 0}%</span>
                          </span>
                          <button
                            onClick={() => handleExecutePercentage(skylight.id)}
                            className="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold rounded-md transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                            disabled={operationStatus[skylight.id] === 'running'}
                          >
                            작동
                          </button>
                        </div>
                      </div>
                    </>
                  )}

                  {skyMode === "AUTO" && (
                    <div className="text-center text-[10px] text-amber-600 bg-amber-50 rounded p-1.5">
                      {skyAutoActive ? "🌤 온도 기반 자동 개폐 중" : "AUTO 모드 — 작동시작 버튼을 누르세요"}
                    </div>
                  )}
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
          <div className="bg-white shadow-sm rounded-b-lg p-2 sm:p-4">

            {/* 제어 모드 버튼 (항상 최상단에 표시) */}
            <div className="bg-gray-50 border border-gray-200 rounded-lg p-2 sm:p-3 mb-3">
              <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-2">제어 모드</p>
              <div className="flex gap-2 mb-2">
                <button
                  onClick={() => {
                    sideModeRef.current = "AUTO";
                    setSideMode("AUTO");
                    setSideAutoActive(false);
                    sideLastTargetRef.current = {};
                    getMqttClient().publish("tansaeng/side-control/mode", "AUTO", { qos: 1, retain: true });
                    getMqttClient().publish("tansaeng/side-control/autoActive", "false", { qos: 1, retain: true });
                  }}
                  className={`flex-1 py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors ${
                    sideMode === "AUTO"
                      ? "bg-gray-900 text-white shadow"
                      : "bg-white border border-blue-300 text-blue-600 hover:bg-blue-50"
                  }`}
                >
                  자동 (AUTO)
                </button>
                <button
                  onClick={() => {
                    sideModeRef.current = "MANUAL";
                    setSideMode("MANUAL");
                    setSideAutoActive(false);
                    getMqttClient().publish("tansaeng/side-control/mode", "MANUAL", { qos: 1, retain: true });
                    getMqttClient().publish("tansaeng/side-control/autoActive", "false", { qos: 1, retain: true });
                  }}
                  className={`flex-1 py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors ${
                    sideMode === "MANUAL"
                      ? "bg-gray-700 text-yellow-300 shadow"
                      : "bg-white border border-gray-300 text-gray-600 hover:bg-gray-50"
                  }`}
                >
                  수동 (MANUAL)
                </button>
              </div>
              {/* AUTO 모드: 작동시작/멈춤 + 상태 */}
              {sideMode === "AUTO" && (
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => {
                      setSideAutoActive(true);
                      sideLastTargetRef.current = {};
                      getMqttClient().publish("tansaeng/side-control/autoActive", "true", { qos: 1, retain: true });
                    }}
                    disabled={sideAutoActive}
                    className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${
                      sideAutoActive
                        ? "bg-blue-500 text-white shadow"
                        : "bg-white border border-blue-400 text-blue-600 hover:bg-blue-50"
                    }`}
                  >
                    ▶ 작동시작
                  </button>
                  <button
                    onClick={() => {
                      setSideAutoActive(false);
                      getMqttClient().publish("tansaeng/side-control/autoActive", "false", { qos: 1, retain: true });
                      // 진행 중인 모터 타이머 취소 + STOP 명령 즉시 전송
                      sidescreens.forEach((sidescreen) => {
                        if (percentageTimers.current[sidescreen.id]) {
                          clearTimeout(percentageTimers.current[sidescreen.id]);
                          delete percentageTimers.current[sidescreen.id];
                        }
                        const mqttDeviceId = sidescreen.commandTopic.split('/')[2];
                        sendDeviceCommand(sidescreen.esp32Id, mqttDeviceId, "STOP");
                        setOperationStatus(prev => ({ ...prev, [sidescreen.id]: 'idle' }));
                      });
                    }}
                    disabled={!sideAutoActive}
                    className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${
                      sideAutoActive
                        ? "bg-red-500 text-white shadow"
                        : "bg-white border border-red-400 text-red-600 hover:bg-red-50"
                    }`}
                  >
                    ■ 작동멈춤
                  </button>
                  <span className="text-[10px] font-semibold text-blue-600">
                    {sideAutoActive ? "🪟 AUTO 작동 중" : "⏸ 대기"}
                  </span>
                </div>
              )}
            </div>

            {/* 팜 평균온도 + 목표개도율 */}
            {(() => {
              const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
              const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
              let autoTargetRate = 0;
              if (avgTemp !== null) {
                const sorted = [...sideTempPoints].sort((a, b) => a.temp - b.temp);
                if (avgTemp >= sorted[sorted.length - 1].temp) {
                  autoTargetRate = 100;
                } else if (avgTemp >= sorted[0].temp) {
                  for (let i = 0; i < sorted.length - 1; i++) {
                    if (avgTemp >= sorted[i].temp && avgTemp < sorted[i + 1].temp) {
                      const ratio = (avgTemp - sorted[i].temp) / (sorted[i + 1].temp - sorted[i].temp);
                      autoTargetRate = Math.round(sorted[i].rate + ratio * (sorted[i + 1].rate - sorted[i].rate));
                      break;
                    }
                  }
                }
              }
              return (
                <div className="bg-green-50 border border-green-200 rounded-lg p-2 sm:p-3 mb-3">
                  <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-1.5">
                    팜 내부 평균온도
                    {sideMode === "AUTO" && <span className="ml-1 text-blue-600">(AUTO 기준)</span>}
                  </p>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                    <div className="bg-red-50 border border-red-100 rounded p-1.5 text-center">
                      <div className="text-lg sm:text-2xl font-bold text-red-500">
                        {avgTemp !== null ? `${avgTemp.toFixed(1)}°` : "—"}
                      </div>
                      <div className="text-[9px] text-gray-400">평균온도</div>
                    </div>
                    {sideMode === "AUTO" && (
                      <div className="bg-blue-50 border border-blue-100 rounded p-1.5 text-center">
                        <div className="text-lg sm:text-2xl font-bold text-blue-500">
                          {avgTemp !== null ? `${autoTargetRate}%` : "—"}
                        </div>
                        <div className="text-[9px] text-gray-400">목표개도율</div>
                      </div>
                    )}
                    {[
                      { label: "앞", t: farmSensors.front },
                      { label: "뒤", t: farmSensors.back },
                      { label: "천장", t: farmSensors.top },
                    ].map(({ label, t }) => (
                      <div key={label} className="bg-white rounded p-1 text-center">
                        <div className="text-[10px] font-semibold text-red-500">{t !== null ? `${t.toFixed(1)}°` : "—"}</div>
                        <div className="text-[9px] text-gray-400">{label}</div>
                      </div>
                    ))}
                  </div>
                </div>
              );
            })()}

            {/* AUTO 모드: 센서 선택 + 포인트 설정 */}
            {sideMode === "AUTO" && (
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-2 sm:p-3 mb-3 space-y-3">
                {/* 센서 선택 */}
                <div className="flex items-center gap-2">
                  <span className="text-[10px] sm:text-xs font-semibold text-gray-700">제어 기준:</span>
                  <div className="flex rounded overflow-hidden border border-gray-300">
                    {(["temp", "humi"] as const).map(s => (
                      <button
                        key={s}
                        onClick={() => {
                          sideAutoSensorRef.current = s;
                          setSideAutoSensor(s);
                          sideLastTargetRef.current = {};
                          getMqttClient().publish("tansaeng/side-control/autoSensor", s, { qos: 1, retain: true });
                        }}
                        className={`px-3 py-1 text-xs font-semibold transition-colors ${sideAutoSensor === s ? "bg-blue-500 text-white" : "bg-white text-gray-600 hover:bg-gray-50"}`}
                      >
                        {s === "temp" ? "🌡 온도" : "💧 습도"}
                      </button>
                    ))}
                  </div>
                  <span className="text-[10px] text-gray-500">{sideAutoSensor === "temp" ? "°C 기준" : "%RH 기준"}</span>
                </div>

                {/* 온도 포인트 설정 */}
                {sideAutoSensor === "temp" && (
                  <>
                    <div className="flex items-center justify-between">
                      <p className="text-[10px] sm:text-xs font-semibold text-gray-700">온도-개도율 설정</p>
                      <button
                        onClick={() => setSideTempPoints(prev => {
                          const sorted = [...prev].sort((a, b) => a.temp - b.temp);
                          const lastTemp = sorted[sorted.length - 1]?.temp ?? 20;
                          return [...sorted, { temp: lastTemp + 3, rate: 100 }];
                        })}
                        className="text-[10px] px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                      >
                        + 포인트 추가
                      </button>
                    </div>
                    <div className="text-[9px] text-gray-500">
                      설정 온도 미만 → 0% 닫힘 / 최고 온도 이상 → 100% 완전 개방
                    </div>
                    <div className="space-y-1.5">
                      {[...sideTempPoints].sort((a, b) => a.temp - b.temp).map((point, idx) => (
                        <div key={idx} className="flex items-center gap-2 bg-white rounded p-1.5">
                          <span className="text-[10px] text-gray-500 w-4">{idx + 1}</span>
                          <div className="flex items-center gap-1 flex-1">
                            <span className="text-[10px] text-gray-600">온도</span>
                            <input
                              type="number"
                              value={point.temp}
                              onChange={(e) => {
                                const newPoints = [...sideTempPoints];
                                const realIdx = sideTempPoints.indexOf(point);
                                newPoints[realIdx] = { ...point, temp: Number(e.target.value) };
                                setSideTempPoints(newPoints);
                              }}
                              className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                            />
                            <span className="text-[10px] text-gray-600">°C →</span>
                            <input
                              type="number" min="0" max="100"
                              value={point.rate}
                              onChange={(e) => {
                                const newPoints = [...sideTempPoints];
                                const realIdx = sideTempPoints.indexOf(point);
                                newPoints[realIdx] = { ...point, rate: Math.min(100, Math.max(0, Number(e.target.value))) };
                                setSideTempPoints(newPoints);
                              }}
                              className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                            />
                            <span className="text-[10px] text-gray-600">% 개방</span>
                          </div>
                          {sideTempPoints.length > 2 && (
                            <button
                              onClick={() => setSideTempPoints(prev => prev.filter((_, i) => i !== sideTempPoints.indexOf(point)))}
                              className="text-[10px] text-red-500 hover:text-red-700 px-1"
                            >✕</button>
                          )}
                        </div>
                      ))}
                    </div>
                  </>
                )}

                {/* 습도 포인트 설정 */}
                {sideAutoSensor === "humi" && (
                  <>
                    <div className="flex items-center justify-between">
                      <p className="text-[10px] sm:text-xs font-semibold text-gray-700">습도-개도율 설정</p>
                      <button
                        onClick={() => setSideHumPoints(prev => {
                          const sorted = [...prev].sort((a, b) => a.humi - b.humi);
                          const lastHumi = sorted[sorted.length - 1]?.humi ?? 60;
                          return [...sorted, { humi: Math.min(100, lastHumi + 5), rate: 100 }];
                        })}
                        className="text-[10px] px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                      >
                        + 포인트 추가
                      </button>
                    </div>
                    <div className="text-[9px] text-gray-500">
                      설정 습도 미만 → 0% 닫힘 / 최고 습도 이상 → 100% 완전 개방
                    </div>
                    <div className="space-y-1.5">
                      {[...sideHumPoints].sort((a, b) => a.humi - b.humi).map((point, idx) => (
                        <div key={idx} className="flex items-center gap-2 bg-white rounded p-1.5">
                          <span className="text-[10px] text-gray-500 w-4">{idx + 1}</span>
                          <div className="flex items-center gap-1 flex-1">
                            <span className="text-[10px] text-gray-600">습도</span>
                            <input
                              type="number" min="0" max="100"
                              value={point.humi}
                              onChange={(e) => {
                                const newPoints = [...sideHumPoints];
                                const realIdx = sideHumPoints.indexOf(point);
                                newPoints[realIdx] = { ...point, humi: Math.min(100, Math.max(0, Number(e.target.value))) };
                                setSideHumPoints(newPoints);
                              }}
                              className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                            />
                            <span className="text-[10px] text-gray-600">%RH →</span>
                            <input
                              type="number" min="0" max="100"
                              value={point.rate}
                              onChange={(e) => {
                                const newPoints = [...sideHumPoints];
                                const realIdx = sideHumPoints.indexOf(point);
                                newPoints[realIdx] = { ...point, rate: Math.min(100, Math.max(0, Number(e.target.value))) };
                                setSideHumPoints(newPoints);
                              }}
                              className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                            />
                            <span className="text-[10px] text-gray-600">% 개방</span>
                          </div>
                          {sideHumPoints.length > 2 && (
                            <button
                              onClick={() => setSideHumPoints(prev => prev.filter((_, i) => i !== sideHumPoints.indexOf(point)))}
                              className="text-[10px] text-red-500 hover:text-red-700 px-1"
                            >✕</button>
                          )}
                        </div>
                      ))}
                    </div>
                  </>
                )}
              </div>
            )}

            {/* 장치 카드: MANUAL 모드에서만 개별 제어 표시, AUTO에서는 현재 위치만 표시 */}
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
                    <div className="flex items-center gap-1.5">
                      {operationStatus[sidescreen.id] === 'running' && (
                        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-semibold rounded-full">
                          <span className="animate-pulse">●</span> 작동중
                        </span>
                      )}
                      {operationStatus[sidescreen.id] === 'completed' && (
                        <span className="inline-flex items-center gap-1 px-1.5 py-0.5 bg-green-100 text-green-700 text-[10px] font-semibold rounded-full">
                          완료
                        </span>
                      )}
                      <span className="text-[10px] sm:text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">
                        {sidescreen.esp32Id}
                      </span>
                    </div>
                  </div>

                  {/* 현재 위치 표시 (AUTO/MANUAL 공통) */}
                  <div className="mb-2">
                    <div className="flex items-center justify-between text-[10px] sm:text-xs text-gray-600 mb-1">
                      <span>현재 위치</span>
                      <span className="font-bold text-blue-600 text-sm">{currentPosition[sidescreen.id] ?? 0}%</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-blue-500 h-2 rounded-full transition-all duration-500"
                        style={{ width: `${currentPosition[sidescreen.id] ?? 0}%` }}
                      />
                    </div>
                  </div>

                  {/* MANUAL 모드에서만 수동 제어 표시 */}
                  {sideMode === "MANUAL" && (
                    <>
                      {/* 버튼 제어 */}
                      <div className="mb-2 sm:mb-3">
                        <p className="text-[10px] sm:text-xs text-gray-600 font-medium mb-1.5">버튼 제어</p>
                        <div className="flex gap-1.5 sm:gap-2">
                          <button
                            onClick={() => handleSkylightCommand(sidescreen.id, "OPEN")}
                            className="flex-1 bg-green-500 hover:bg-green-600 active:bg-green-700 text-white font-semibold py-2 px-2 rounded-md transition-colors text-xs sm:text-sm"
                          >
                            열기
                          </button>
                          <button
                            onClick={() => handleSkylightCommand(sidescreen.id, "STOP")}
                            className="flex-1 bg-yellow-500 hover:bg-yellow-600 active:bg-yellow-700 text-white font-semibold py-2 px-2 rounded-md transition-colors text-xs sm:text-sm"
                          >
                            정지
                          </button>
                          <button
                            onClick={() => handleSkylightCommand(sidescreen.id, "CLOSE")}
                            className="flex-1 bg-red-500 hover:bg-red-600 active:bg-red-700 text-white font-semibold py-2 px-2 rounded-md transition-colors text-xs sm:text-sm"
                          >
                            닫기
                          </button>
                        </div>
                      </div>

                      {/* 퍼센트 직접 이동 */}
                      <div>
                        <p className="text-[10px] sm:text-xs text-gray-600 font-medium mb-1.5">개폐 퍼센트 설정</p>
                        <div className="flex items-center gap-1.5 sm:gap-2 mb-1.5">
                          <input
                            type="number"
                            min="0"
                            max="100"
                            value={percentageInputs[sidescreen.id] ?? (deviceState[sidescreen.id]?.targetPercentage ?? 0)}
                            onChange={(e) => setPercentageInputs({
                              ...percentageInputs,
                              [sidescreen.id]: e.target.value
                            })}
                            className="flex-1 px-2 sm:px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="0-100"
                          />
                          <span className="text-xs font-semibold text-gray-900">%</span>
                          <button
                            onClick={() => handleSavePercentage(sidescreen.id)}
                            className="px-2 sm:px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded-md transition-colors"
                          >
                            저장
                          </button>
                        </div>
                        <div className="flex items-center justify-between">
                          <span className="text-[10px] text-gray-500">
                            저장: <span className="font-semibold text-blue-600">{deviceState[sidescreen.id]?.targetPercentage ?? 0}%</span>
                          </span>
                          <button
                            onClick={() => handleExecutePercentage(sidescreen.id)}
                            className="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-semibold rounded-md transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                            disabled={operationStatus[sidescreen.id] === 'running'}
                          >
                            작동
                          </button>
                        </div>
                      </div>
                    </>
                  )}

                  {/* AUTO 모드에서는 자동 제어 안내 표시 */}
                  {sideMode === "AUTO" && (
                    <div className="text-center text-[10px] text-blue-600 bg-blue-50 rounded p-1.5">
                      {sideAutoActive ? "🪟 온도 기반 자동 개폐 중" : "AUTO 모드 — 작동시작 버튼을 누르세요"}
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* 팬 제어 섹션 */}
        <section className="mb-2 sm:mb-3">
          <header className="bg-farm-500 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
              🌀 팬 제어
            </h2>
            <div className="flex items-center gap-2">
              <span className="text-[10px] sm:text-xs text-gray-800">{fans.length}개</span>
            </div>
          </header>
          <div className="bg-white shadow-sm rounded-b-lg p-2 sm:p-4">

            {/* 상단: 팜 평균온도 + 모드 (2열) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4 mb-3 sm:mb-4">

              {/* 팜 내부 평균온도/습도 */}
              {(() => {
                const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
                const hums  = [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[];
                const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
                const avgHum  = hums.length > 0  ? hums.reduce((a, b) => a + b, 0)  / hums.length  : null;
                const fanAutoStatus = avgTemp === null ? "센서 없음"
                  : !fanAutoActive ? "⏸ 작동 대기"
                  : "🌀 AUTO 작동 중";
                return (
                  <div className="bg-green-50 border border-green-200 rounded-lg p-2 sm:p-3">
                    <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-1.5">
                      팜 내부 평균
                      {fanMode === "AUTO" && <span className="ml-1 text-farm-600">(AUTO 기준)</span>}
                    </p>
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
                    {fanMode === "AUTO" && (
                      <div className="text-center text-[10px] font-semibold text-farm-600 mb-1.5">{fanAutoStatus}</div>
                    )}
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
                      fanModeRef.current = "AUTO";
                      setFanMode("AUTO");
                      setFanAutoActive(false);
                      fanDeviceLastCmd.current = {};
                      getMqttClient().publish("tansaeng/fan-control/mode", "AUTO", { qos: 1, retain: true });
                      getMqttClient().publish("tansaeng/fan-control/autoActive", "false", { qos: 1, retain: true });
                    }}
                    className={`w-full py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors ${
                      fanMode === "AUTO"
                        ? "bg-gray-900 text-white shadow"
                        : "bg-white border border-farm-300 text-farm-600 hover:bg-farm-50"
                    }`}
                  >
                    자동 (AUTO)
                  </button>
                  <button
                    onClick={() => {
                      fanModeRef.current = "MANUAL";
                      setFanMode("MANUAL");
                      getMqttClient().publish("tansaeng/fan-control/mode", "MANUAL", { qos: 1, retain: true });
                    }}
                    className={`w-full py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors ${
                      fanMode === "MANUAL"
                        ? "bg-gray-700 text-yellow-300 shadow"
                        : "bg-white border border-gray-300 text-gray-600 hover:bg-gray-50"
                    }`}
                  >
                    수동 (MANUAL)
                  </button>
                </div>
                <p className="text-[10px] text-gray-500 mt-1.5">
                  {fanMode === "AUTO" ? "온도 범위 설정에 따라 자동 제어" : "직접 장치 ON/OFF"}
                </p>
              </div>
            </div>

            {/* AUTO 모드: 장치별 개별 게이지 / MANUAL 모드: 장치 카드 */}
            {fanMode === "AUTO" ? (() => {
              const useHumi = fanAutoSensor === "humi";
              const GMIN = useHumi ? 0 : -10;
              const GMAX = useHumi ? 100 : 50;
              const gRange = GMAX - GMIN;
              const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
              const humis = [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[];
              const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
              const avgHumi = humis.length > 0 ? humis.reduce((a, b) => a + b, 0) / humis.length : null;
              const avgValue = useHumi ? avgHumi : avgTemp;
              const markerPct = avgValue !== null
                ? Math.max(0, Math.min(100, ((avgValue - GMIN) / gRange) * 100))
                : null;

              return (
                <div className="space-y-2 sm:space-y-3">
                  {/* 제어 센서 선택 */}
                  <div className="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                    <span className="text-xs text-gray-600 font-medium">제어 기준:</span>
                    <div className="flex rounded overflow-hidden border border-gray-300">
                      {(["temp", "humi"] as const).map(s => (
                        <button
                          key={s}
                          onClick={() => {
                            fanAutoSensorRef.current = s;
                            setFanAutoSensor(s);
                            fanDeviceLastCmd.current = {};
                            getMqttClient().publish("tansaeng/fan-control/autoSensor", s, { qos: 1, retain: true });
                          }}
                          className={`px-3 py-1 text-xs font-semibold transition-colors ${fanAutoSensor === s ? "bg-farm-500 text-white" : "bg-white text-gray-600 hover:bg-gray-50"}`}
                        >
                          {s === "temp" ? "🌡 온도" : "💧 습도"}
                        </button>
                      ))}
                    </div>
                    <span className="text-[10px] text-gray-500">{useHumi ? "%RH 기준" : "°C 기준"}</span>
                  </div>

                  {/* 현재값 + 작동시작/멈춤 버튼 */}
                  <div className="flex items-center justify-between bg-gray-100 rounded-lg px-3 py-2 gap-2">
                    <div className="text-xs text-gray-600">
                      현재 {useHumi ? "평균습도" : "평균온도"}:{" "}
                      <span className="font-bold text-gray-800">
                        {avgValue !== null ? (useHumi ? `${avgValue.toFixed(0)}%RH` : `${avgValue.toFixed(1)}°C`) : "—"}
                      </span>
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => {
                          fanDeviceLastCmd.current = {};
                          setFanAutoActive(true);
                          getMqttClient().publish("tansaeng/fan-control/autoActive", "true", { qos: 1, retain: true });
                        }}
                        className={`px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${
                          fanAutoActive
                            ? "bg-green-500 text-white shadow"
                            : "bg-white border border-green-400 text-green-600 hover:bg-green-50"
                        }`}
                      >
                        ▶ 작동시작
                      </button>
                      <button
                        onClick={() => {
                          setFanAutoActive(false);
                          getMqttClient().publish("tansaeng/fan-control/autoActive", "false", { qos: 1, retain: true });
                          fans.forEach((fan) => {
                            fanDeviceLastCmd.current[fan.id] = "OFF";
                            setDeviceState(prev => ({
                              ...prev,
                              [fan.id]: { ...prev[fan.id], power: "off", lastSavedAt: new Date().toISOString() }
                            }));
                            const mqttDeviceId = fan.commandTopic.split('/')[2];
                            sendDeviceCommand(fan.esp32Id, mqttDeviceId, "OFF");
                          });
                        }}
                        className={`px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${
                          !fanAutoActive
                            ? "bg-gray-500 text-white shadow"
                            : "bg-white border border-gray-400 text-gray-600 hover:bg-gray-50"
                        }`}
                      >
                        ■ 작동멈춤
                      </button>
                    </div>
                  </div>

                  {/* 장치별 게이지 */}
                  {fans.map((fan) => {
                    const range = useHumi
                      ? (fanHumRanges[fan.id] ?? { low: 60, high: 80 })
                      : (fanDeviceRanges[fan.id] ?? { low: 15, high: 22 });
                    const isOn = deviceState[fan.id]?.power === "on";
                    const inRange = avgValue !== null && avgValue >= range.low && avgValue <= range.high;
                    const unit = useHumi ? "%" : "°C";

                    return (
                      <div key={fan.id} className="bg-green-50 border border-green-200 rounded-lg p-2.5 sm:p-3">
                        {/* 헤더: 장치명 + 작동상태 LED */}
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-xs sm:text-sm font-semibold text-gray-700">🌀 {fan.name}</span>
                          <div className="flex items-center gap-1.5">
                            <div className={`w-2.5 h-2.5 rounded-full flex-shrink-0 ${isOn ? 'bg-green-500 animate-pulse' : 'bg-gray-400'}`} />
                            <span className={`text-xs font-bold ${isOn ? 'text-green-600' : 'text-gray-500'}`}>
                              {isOn ? '작동중' : '정지'}
                            </span>
                          </div>
                        </div>

                        {/* 범위 표시 */}
                        <div className="flex justify-between text-xs mb-1 px-3">
                          <span className="text-farm-600 font-bold">{range.low.toFixed(1)}{unit}</span>
                          <span className="text-[10px] text-gray-400">
                            {inRange ? `✅ 현재 범위 내` : `❌ 현재 범위 밖`}
                          </span>
                          <span className="text-red-600 font-bold">{range.high.toFixed(1)}{unit}</span>
                        </div>

                        {/* 듀얼 핸들 슬라이더 */}
                        <DualRangeSlider
                          min={GMIN} max={GMAX} step={useHumi ? 1 : 0.5}
                          low={range.low} high={range.high}
                          onLowChange={(v) => useHumi
                            ? setFanHumRanges(prev => ({ ...prev, [fan.id]: { ...prev[fan.id] ?? { low: 60, high: 80 }, low: v } }))
                            : setFanDeviceRanges(prev => ({ ...prev, [fan.id]: { ...prev[fan.id] ?? { low: 15, high: 22 }, low: v } }))
                          }
                          onHighChange={(v) => useHumi
                            ? setFanHumRanges(prev => ({ ...prev, [fan.id]: { ...prev[fan.id] ?? { low: 60, high: 80 }, high: v } }))
                            : setFanDeviceRanges(prev => ({ ...prev, [fan.id]: { ...prev[fan.id] ?? { low: 15, high: 22 }, high: v } }))
                          }
                          markerPct={markerPct}
                          isActive={inRange}
                        />
                        {/* 눈금 */}
                        <div className="flex justify-between text-[10px] text-gray-400 mx-3 mt-0.5">
                          {useHumi
                            ? <><span>0%</span><span>50%</span><span>100%</span></>
                            : <><span>-10°C</span><span>+20°C</span><span>+50°C</span></>
                          }
                        </div>
                        {/* 저장 버튼 */}
                        <div className="flex items-center justify-between mt-2 pt-2 border-t border-green-100">
                          <span className="text-[10px] text-gray-400">
                            {rangesSavedAt[fan.id] ? `저장: ${rangesSavedAt[fan.id]}` : "미저장"}
                          </span>
                          <button
                            onClick={() => {
                              if (!useHumi) saveDeviceRange(fan.id, fan.name, range.low, range.high);
                              else getMqttClient().publish("tansaeng/fan-control/humRanges", JSON.stringify(fanHumRanges), { qos: 1, retain: true });
                            }}
                            className="px-3 py-1 text-xs font-semibold bg-green-600 hover:bg-green-700 text-white rounded-md transition-colors"
                          >
                            💾 저장
                          </button>
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
              </>
            )}
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
                      setHpAutoActive(false);
                      hpDeviceLastCmd.current = { hp_pump: null, hp_heater: null, hp_fan: null };
                      getMqttClient().publish("tansaeng/ctlr-heat-001/mode/cmd", "MANUAL", { qos: 1, retain: true });
                      getMqttClient().publish("tansaeng/hp-control/mode", "AUTO", { qos: 1, retain: true });
                      getMqttClient().publish("tansaeng/hp-control/autoActive", "false", { qos: 1, retain: true });
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
                      setHpAutoActive(false);
                      hpAutoDemandRef.current = false;
                      hpDeviceLastCmd.current = { hp_pump: null, hp_heater: null, hp_fan: null };
                      getMqttClient().publish("tansaeng/ctlr-heat-001/mode/cmd", "MANUAL", { qos: 1, retain: true });
                      getMqttClient().publish("tansaeng/hp-control/mode", "MANUAL", { qos: 1, retain: true });
                      getMqttClient().publish("tansaeng/hp-control/autoActive", "false", { qos: 1, retain: true });
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
                { key: "hp_fan",    label: "장치실 팬",   icon: "🌀" },
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
                          getMqttClient().publish("tansaeng/hp-control/autoActive", "true", { qos: 1, retain: true });
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
                          getMqttClient().publish("tansaeng/hp-control/autoActive", "false", { qos: 1, retain: true });
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
                        {/* 저장 버튼 + 마지막 저장 시각 */}
                        <div className="flex items-center justify-between mt-2 pt-2 border-t border-orange-100">
                          <span className="text-[10px] text-gray-400">
                            {rangesSavedAt[key] ? `저장: ${rangesSavedAt[key]}` : "미저장"}
                          </span>
                          <button
                            onClick={() => saveDeviceRange(key, label, range.low, range.high)}
                            className="px-3 py-1 text-xs font-semibold bg-orange-500 hover:bg-orange-600 text-white rounded-md transition-colors"
                          >
                            💾 저장
                          </button>
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
