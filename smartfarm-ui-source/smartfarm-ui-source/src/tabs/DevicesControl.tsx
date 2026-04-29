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
  // 천창/측창 위치 초기화 상태
  const [resetStatus, setResetStatus] = useState<Record<string, 'idle' | 'resetting'>>({});

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

  // 스크린 개폐 기준시간 (초)
  const [skyLeftFullTime, setSkyLeftFullTime] = useState(300);
  const [skyRightFullTime, setSkyRightFullTime] = useState(300);
  const [sideLeftFullTime, setSideLeftFullTime] = useState(120);
  const [sideRightFullTime, setSideRightFullTime] = useState(120);
  const [editSkyLeftFullTime, setEditSkyLeftFullTime] = useState(300);
  const [editSkyRightFullTime, setEditSkyRightFullTime] = useState(300);
  const [editSideLeftFullTime, setEditSideLeftFullTime] = useState(120);
  const [editSideRightFullTime, setEditSideRightFullTime] = useState(120);
  const [screenTimeSaving, setScreenTimeSaving] = useState(false);
  const [screenTimeSavedMsg, setScreenTimeSavedMsg] = useState("");

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
  const [sideAutoType, setSideAutoType] = useState<"temp" | "time" | "daynight">("temp");
  const sideAutoTypeRef = useRef<"temp" | "time" | "daynight">("temp");
  const sideAutoTypeFromMqttRef = useRef(false);
  const sideAutoTypeFirstRunRef = useRef(true);
  const [sideTimePoints, setSideTimePoints] = useState<Array<{ time: string; rate: number }>>([
    { time: "08:00", rate: 0 },
    { time: "14:00", rate: 100 },
    { time: "20:00", rate: 0 },
  ]);
  const sideTimePointsFromMqttRef = useRef(false);
  const sideTimePointsFirstRunRef = useRef(true);
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

  // ── 주간/야간 공통 타입 ────────────────────────────────────────────────────
  type DayNightPeriodSide = {
    sensor: "temp" | "humi";
    tempPoints: { temp: number; rate: number }[];
    humPoints:  { humi: number; rate: number }[];
  };
  type DayNightConfigSide = {
    dayStart: string; nightStart: string;
    day: DayNightPeriodSide; night: DayNightPeriodSide;
  };
  type DayNightPeriodFan = {
    sensor: "temp" | "humi";
    ranges:    Record<string, { low: number; high: number }>;
    humRanges: Record<string, { low: number; high: number }>;
  };
  type DayNightConfigFan = {
    enabled: boolean; dayStart: string; nightStart: string;
    day: DayNightPeriodFan; night: DayNightPeriodFan;
  };
  type DayNightConfigHp = {
    enabled: boolean; dayStart: string; nightStart: string;
    day:   { ranges: Record<string, { low: number; high: number }> };
    night: { ranges: Record<string, { low: number; high: number }> };
  };

  // 측창 주야간 설정
  const defaultSideDNConfig: DayNightConfigSide = {
    dayStart: "06:00", nightStart: "20:00",
    day:   { sensor: "temp", tempPoints: [{temp:20,rate:0},{temp:28,rate:100}], humPoints: [{humi:60,rate:0},{humi:80,rate:100}] },
    night: { sensor: "temp", tempPoints: [{temp:15,rate:0},{temp:20,rate:50}],  humPoints: [{humi:70,rate:0},{humi:85,rate:100}] },
  };
  const [sideDayNightConfig, setSideDayNightConfig] = useState<DayNightConfigSide>(defaultSideDNConfig);
  const sideDayNightFromMqttRef = useRef(false);
  const sideDayNightFirstRunRef = useRef(true);

  // 팬 주야간 설정
  const defaultFanDNConfig: DayNightConfigFan = {
    enabled: false, dayStart: "06:00", nightStart: "20:00",
    day:   { sensor: "temp", ranges: {}, humRanges: {} },
    night: { sensor: "temp", ranges: {}, humRanges: {} },
  };
  const [fanDayNightConfig, setFanDayNightConfig] = useState<DayNightConfigFan>(defaultFanDNConfig);
  const fanDayNightFromMqttRef = useRef(false);
  const fanDayNightFirstRunRef = useRef(true);

  // HP 주야간 설정
  const defaultHpDNConfig: DayNightConfigHp = {
    enabled: false, dayStart: "06:00", nightStart: "20:00",
    day:   { ranges: { hp_pump:{low:15,high:22}, hp_heater:{low:15,high:22}, hp_fan:{low:15,high:22} } },
    night: { ranges: { hp_pump:{low:8,high:15},  hp_heater:{low:8,high:15},  hp_fan:{low:8,high:15}  } },
  };
  const [hpDayNightConfig, setHpDayNightConfig] = useState<DayNightConfigHp>(defaultHpDNConfig);
  const hpDayNightFromMqttRef = useRef(false);
  const hpDayNightFirstRunRef = useRef(true);

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

  // 스크린 개폐 기준시간 DB 로드
  useEffect(() => {
    fetch('/api/smartfarm/screen_settings.php')
      .then(r => r.json())
      .then(json => {
        if (json.success && json.data) {
          const skyLeftS = json.data.sky_left?.full_time_seconds ?? json.data.sky?.full_time_seconds ?? 300;
          const skyRightS = json.data.sky_right?.full_time_seconds ?? json.data.sky?.full_time_seconds ?? 300;
          const sideLeftS = json.data.side_left?.full_time_seconds ?? json.data.side?.full_time_seconds ?? 120;
          const sideRightS = json.data.side_right?.full_time_seconds ?? json.data.side?.full_time_seconds ?? 120;
          setSkyLeftFullTime(skyLeftS);
          setSkyRightFullTime(skyRightS);
          setSideLeftFullTime(sideLeftS);
          setSideRightFullTime(sideRightS);
          setEditSkyLeftFullTime(skyLeftS);
          setEditSkyRightFullTime(skyRightS);
          setEditSideLeftFullTime(sideLeftS);
          setEditSideRightFullTime(sideRightS);
        }
      })
      .catch(() => {});
  }, []);

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

  // HP AUTO 모드 제어 로직 — 주야간 모드 or 일반 온도 범위 기준 ON/OFF
  useEffect(() => {
    if (hpMode !== "AUTO") return;
    if (hpModeRef.current !== "AUTO") return;
    if (!hpAutoActive) return;

    const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
    const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
    const HP = "ctlr-heat-001";

    // 주야간 모드일 때 현재 구간 판단
    let activeRanges: Record<string, { low: number; high: number }> | null = null;
    if (hpDayNightConfig.enabled) {
      const toMin = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
      const now = new Date();
      const nowMin = now.getHours() * 60 + now.getMinutes();
      const dayMin = toMin(hpDayNightConfig.dayStart);
      const nightMin = toMin(hpDayNightConfig.nightStart);
      const isDay = dayMin < nightMin
        ? (nowMin >= dayMin && nowMin < nightMin)
        : (nowMin >= dayMin || nowMin < nightMin);
      activeRanges = isDay ? hpDayNightConfig.day.ranges : hpDayNightConfig.night.ranges;
    }

    const deviceMap: Array<{ key: string; mqttId: string; name: string }> = [
      { key: "hp_pump",   mqttId: "pump",   name: "냉각순환펌프" },
      { key: "hp_heater", mqttId: "heater", name: "냉각기"       },
      { key: "hp_fan",    mqttId: "fan",    name: "장치실 팬"     },
    ];

    deviceMap.forEach(({ key, mqttId, name }) => {
      const sensorValue = key === "hp_heater" ? hpSensors.waterTemp : avgTemp;
      if (sensorValue === null) return;
      const range = (activeRanges?.[key]) ?? hpDeviceRanges[key] ?? { low: 15, high: 22 };
      const newCmd: "ON" | "OFF" = (sensorValue >= range.low && sensorValue <= range.high) ? "ON" : "OFF";
      if (hpDeviceLastCmd.current[key] !== newCmd) {
        hpDeviceLastCmd.current[key] = newCmd;
        setHpDeviceStates(prev => ({ ...prev, [key]: newCmd }));
        sendDeviceCommand(HP, mqttId, newCmd).then(result => {
          if (result.success) console.log(`[API SUCCESS] ${name} - ${newCmd}`);
          else console.error(`[API ERROR] ${name} - ${newCmd}: ${result.message}`);
        });
      }
    });
  }, [farmSensors, hpSensors, hpDeviceRanges, hpAutoActive, hpMode, hpDayNightConfig, currentMinute]);

  // 팬 AUTO 모드 제어 로직 — 주야간 모드 or 일반 온도/습도 범위 기준 ON/OFF
  // 히스테리시스: 경계값 근처 진동 방지 (온도 0.5°C, 습도 2%RH)
  useEffect(() => {
    if (fanModeRef.current !== "AUTO") return;
    if (!fanAutoActive) return;

    let useHumi: boolean;
    let avgValue: number | null;
    let getRangeForFan: (fanId: string) => { low: number; high: number };

    if (fanDayNightConfig.enabled) {
      // 주야간 모드: 현재 시각으로 주간/야간 판단
      const toMin = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
      const now = new Date();
      const nowMin = now.getHours() * 60 + now.getMinutes();
      const dayMin = toMin(fanDayNightConfig.dayStart);
      const nightMin = toMin(fanDayNightConfig.nightStart);
      const isDay = dayMin < nightMin
        ? (nowMin >= dayMin && nowMin < nightMin)
        : (nowMin >= dayMin || nowMin < nightMin);
      const period = isDay ? "day" : "night";
      const cfg = fanDayNightConfig[period];
      useHumi = cfg.sensor === "humi";
      const vals = useHumi
        ? [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[]
        : [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
      if (vals.length === 0) return;
      avgValue = vals.reduce((a, b) => a + b, 0) / vals.length;
      const rangeKey = useHumi ? "humRanges" : "ranges";
      const defRange = useHumi ? { low: 60, high: 80 } : { low: 15, high: 22 };
      getRangeForFan = (fanId) => (cfg[rangeKey][fanId] ?? defRange);
    } else {
      // 일반 모드
      useHumi = fanAutoSensorRef.current === "humi";
      const vals = useHumi
        ? [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[]
        : [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
      if (vals.length === 0) return;
      avgValue = vals.reduce((a, b) => a + b, 0) / vals.length;
      getRangeForFan = (fanId) => useHumi
        ? (fanHumRanges[fanId] ?? { low: 60, high: 80 })
        : (fanDeviceRanges[fanId] ?? { low: 15, high: 22 });
    }

    const hyst = useHumi ? 2 : 0.5;
    fans.forEach((fan) => {
      const range = getRangeForFan(fan.id);
      const lastCmd = fanDeviceLastCmd.current[fan.id];
      let newCmd: "ON" | "OFF";
      if (lastCmd === "ON") {
        const shouldOff = avgValue! < range.low - hyst || avgValue! > range.high + hyst;
        newCmd = shouldOff ? "OFF" : "ON";
      } else {
        newCmd = (avgValue! >= range.low && avgValue! <= range.high) ? "ON" : "OFF";
      }
      if (lastCmd !== newCmd) {
        fanDeviceLastCmd.current[fan.id] = newCmd;
        setDeviceState(prev => ({
          ...prev,
          [fan.id]: { ...prev[fan.id], power: newCmd === "ON" ? "on" : "off", lastSavedAt: new Date().toISOString() }
        }));
        const mqttDeviceId = fan.commandTopic.split('/')[2];
        sendDeviceCommand(fan.esp32Id, mqttDeviceId, newCmd);
      }
    });
  }, [farmSensors, fanDeviceRanges, fanHumRanges, fanAutoActive, fanAutoSensor, fanDayNightConfig, currentMinute]);

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
      // 시간 기반 제어: 해당 시각이 되면 즉시 그 개도율로 이동 (step function)
      const toMin = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
      const nowMin = currentMinute;
      const sorted = [...skyTimePoints].sort((a, b) => toMin(a.time) - toMin(b.time));
      if (sorted.length === 0) return;
      // 현재 시각 이전의 가장 최근 포인트를 찾아 그 값 적용
      let activePoint = sorted[sorted.length - 1]; // 기본값: 마지막 포인트 (자정 이전 마지막 설정)
      for (let i = sorted.length - 1; i >= 0; i--) {
        if (nowMin >= toMin(sorted[i].time)) {
          activePoint = sorted[i];
          break;
        }
      }
      targetRate = activePoint.rate;
    } else if (skyAutoType === "combined" || skyAutoTypeRef.current === "combined") {
      // combined: min(시간 step값, 온도기준) — 햇빛 확보 + 환기 균형
      const toMin2 = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
      const nowMin2 = currentMinute;
      const timeSorted2 = [...skyTimePoints].sort((a, b) => toMin2(a.time) - toMin2(b.time));
      let timeRate = 0;
      if (timeSorted2.length > 0) {
        // 시간 기준도 step function으로 적용
        let activePoint2 = timeSorted2[timeSorted2.length - 1];
        for (let i = timeSorted2.length - 1; i >= 0; i--) {
          if (nowMin2 >= toMin2(timeSorted2[i].time)) {
            activePoint2 = timeSorted2[i];
            break;
          }
        }
        timeRate = activePoint2.rate;
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
      const currentPos = currentPosition[skylight.id] ?? 0;
      const difference = targetRate - currentPos;
      if (Math.abs(difference) < 1) return; // 이미 목표 위치에 있음

      // 타이머가 실행 중인 경우에만 중복 명령 방지 (위치가 달라졌으면 재명령)
      const lastTarget = skyLastTargetRef.current[skylight.id] ?? null;
      if (lastTarget !== null && Math.abs(targetRate - lastTarget) < 2 && percentageTimers.current[skylight.id]) return;

      skyLastTargetRef.current[skylight.id] = targetRate;

      if (percentageTimers.current[skylight.id]) {
        clearTimeout(percentageTimers.current[skylight.id]);
        delete percentageTimers.current[skylight.id];
      }

      setOperationStatus(prev => ({ ...prev, [skylight.id]: 'running' }));

      const fullTimeSeconds = skylight.id === "skylight_left" ? skyLeftFullTime : skyRightFullTime;
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
  }, [farmSensors, skyTempPoints, skyAutoActive, skyTimePoints, currentMinute, skyAutoType, skyLeftFullTime, skyRightFullTime]);

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
      subscribeToTopic("tansaeng/side-control/autoType", (v) => {
        if (v === "temp" || v === "time" || v === "daynight") {
          sideAutoTypeFromMqttRef.current = true;
          sideAutoTypeRef.current = v as "temp" | "time" | "daynight";
          setSideAutoType(v as "temp" | "time" | "daynight");
        }
      }),
      subscribeToTopic("tansaeng/side-control/timePoints", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (Array.isArray(parsed) && parsed.length > 0) {
            sideTimePointsFromMqttRef.current = true;
            setSideTimePoints(parsed);
          }
        } catch {}
      }),
      subscribeToTopic("tansaeng/side-control/dayNightConfig", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (parsed && typeof parsed === "object") { sideDayNightFromMqttRef.current = true; setSideDayNightConfig(parsed); }
        } catch {}
      }),
      subscribeToTopic("tansaeng/fan-control/dayNightConfig", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (parsed && typeof parsed === "object") { fanDayNightFromMqttRef.current = true; setFanDayNightConfig(parsed); }
        } catch {}
      }),
      subscribeToTopic("tansaeng/hp-control/dayNightConfig", (v) => {
        try {
          const parsed = JSON.parse(v);
          if (parsed && typeof parsed === "object") { hpDayNightFromMqttRef.current = true; setHpDayNightConfig(parsed); }
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

  // 측창 autoType 변경 시 MQTT retain 발행
  useEffect(() => {
    if (sideAutoTypeFirstRunRef.current) { sideAutoTypeFirstRunRef.current = false; return; }
    if (sideAutoTypeFromMqttRef.current) { sideAutoTypeFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/side-control/autoType", sideAutoType, { qos: 1, retain: true });
  }, [sideAutoType]);

  // 측창 시간 포인트 변경 시 MQTT retain 발행
  useEffect(() => {
    if (sideTimePointsFirstRunRef.current) { sideTimePointsFirstRunRef.current = false; return; }
    if (sideTimePointsFromMqttRef.current) { sideTimePointsFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/side-control/timePoints", JSON.stringify(sideTimePoints), { qos: 1, retain: true });
  }, [sideTimePoints]);

  // 측창 AUTO 제어 로직 — 시간/온도/습도 기준 개도율 계산 후 자동 이동
  useEffect(() => {
    if (sideModeRef.current !== "AUTO") return;
    if (!sideAutoActive) return;

    let targetRate = 0;
    let sensorLabel = "";

    if (sideAutoTypeRef.current === "time") {
      // 시간 기준 제어 (스텝 함수 — 데몬과 동일 알고리즘)
      const toMin = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
      const nowMin = currentMinute;
      const sorted = [...sideTimePoints].sort((a, b) => toMin(a.time) - toMin(b.time));
      if (sorted.length === 0) return;
      let activePoint = sorted[sorted.length - 1]; // 기본값: 마지막 포인트
      for (let i = sorted.length - 1; i >= 0; i--) {
        if (nowMin >= toMin(sorted[i].time)) { activePoint = sorted[i]; break; }
      }
      targetRate = activePoint.rate;
      sensorLabel = `시간기준`;
    } else if (sideAutoTypeRef.current === "daynight") {
      // 주간/야간 기준 제어
      const toMin = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
      const nowMin = currentMinute;
      const dayMin = toMin(sideDayNightConfig.dayStart);
      const nightMin = toMin(sideDayNightConfig.nightStart);
      const isDay = dayMin < nightMin
        ? (nowMin >= dayMin && nowMin < nightMin)
        : (nowMin >= dayMin || nowMin < nightMin);
      const periodCfg = isDay ? sideDayNightConfig.day : sideDayNightConfig.night;
      sensorLabel = `${isDay ? "주간" : "야간"} ${periodCfg.sensor === "temp" ? "온도" : "습도"}기준`;
      if (periodCfg.sensor === "humi") {
        const humis = [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[];
        if (humis.length === 0) return;
        const avg = humis.reduce((a, b) => a + b, 0) / humis.length;
        const pts = [...periodCfg.humPoints].sort((a, b) => a.humi - b.humi);
        if (avg < pts[0].humi) targetRate = 0;
        else if (avg >= pts[pts.length - 1].humi) targetRate = 100;
        else for (let i = 0; i < pts.length - 1; i++) {
          if (avg >= pts[i].humi && avg < pts[i + 1].humi) {
            targetRate = Math.round(pts[i].rate + (avg - pts[i].humi) / (pts[i + 1].humi - pts[i].humi) * (pts[i + 1].rate - pts[i].rate));
            break;
          }
        }
      } else {
        const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
        if (temps.length === 0) return;
        const avg = temps.reduce((a, b) => a + b, 0) / temps.length;
        const pts = [...periodCfg.tempPoints].sort((a, b) => a.temp - b.temp);
        if (avg < pts[0].temp) targetRate = 0;
        else if (avg >= pts[pts.length - 1].temp) targetRate = 100;
        else for (let i = 0; i < pts.length - 1; i++) {
          if (avg >= pts[i].temp && avg < pts[i + 1].temp) {
            targetRate = Math.round(pts[i].rate + (avg - pts[i].temp) / (pts[i + 1].temp - pts[i].temp) * (pts[i + 1].rate - pts[i].rate));
            break;
          }
        }
      }
    } else if (sideAutoSensorRef.current === "humi") {
      // 습도 기준 제어
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
      // 온도 기준 제어
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
      const currentPos = currentPosition[sidescreen.id] ?? 0;
      const difference = targetRate - currentPos;
      if (Math.abs(difference) < 1) return;

      const lastTarget = sideLastTargetRef.current[sidescreen.id] ?? null;
      if (lastTarget !== null && Math.abs(targetRate - lastTarget) < 2 && percentageTimers.current[sidescreen.id]) return;

      sideLastTargetRef.current[sidescreen.id] = targetRate;

      if (percentageTimers.current[sidescreen.id]) {
        clearTimeout(percentageTimers.current[sidescreen.id]);
        delete percentageTimers.current[sidescreen.id];
      }

      setOperationStatus(prev => ({ ...prev, [sidescreen.id]: 'running' }));

      const fullTimeSeconds = sidescreen.id === "sidescreen_left" ? sideLeftFullTime : sideRightFullTime;
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
  }, [farmSensors, sideTempPoints, sideHumPoints, sideTimePoints, sideDayNightConfig, sideAutoActive, sideAutoSensor, sideAutoType, currentMinute, sideLeftFullTime, sideRightFullTime]);

  // 측창 주야간 설정 변경 시 MQTT retain 발행
  useEffect(() => {
    if (sideDayNightFirstRunRef.current) { sideDayNightFirstRunRef.current = false; return; }
    if (sideDayNightFromMqttRef.current) { sideDayNightFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/side-control/dayNightConfig", JSON.stringify(sideDayNightConfig), { qos: 1, retain: true });
  }, [sideDayNightConfig]);

  // 팬 주야간 설정 변경 시 MQTT retain 발행
  useEffect(() => {
    if (fanDayNightFirstRunRef.current) { fanDayNightFirstRunRef.current = false; return; }
    if (fanDayNightFromMqttRef.current) { fanDayNightFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/fan-control/dayNightConfig", JSON.stringify(fanDayNightConfig), { qos: 1, retain: true });
  }, [fanDayNightConfig]);

  // HP 주야간 설정 변경 시 MQTT retain 발행
  useEffect(() => {
    if (hpDayNightFirstRunRef.current) { hpDayNightFirstRunRef.current = false; return; }
    if (hpDayNightFromMqttRef.current) { hpDayNightFromMqttRef.current = false; return; }
    getMqttClient().publish("tansaeng/hp-control/dayNightConfig", JSON.stringify(hpDayNightConfig), { qos: 1, retain: true });
  }, [hpDayNightConfig]);

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

    // 전체 시간 설정 (0% → 100%) — DB에서 로드한 값 사용
    const fullTimeSeconds = deviceId === "skylight_left" ? skyLeftFullTime : deviceId === "skylight_right" ? skyRightFullTime : deviceId === "sidescreen_left" ? sideLeftFullTime : sideRightFullTime;

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

  // 천창/측창 위치 초기화 핸들러 (완전 닫기 후 0% 리셋)
  const handleResetPosition = async (deviceId: string) => {
    if (!window.confirm("스크린을 완전히 닫고 위치를 0%로 초기화합니다.\n계속하시겠습니까?")) return;

    const device = [...skylights, ...sidescreens].find(d => d.id === deviceId);
    if (!device) return;

    // 진행 중인 타이머 취소
    if (percentageTimers.current[deviceId]) {
      clearTimeout(percentageTimers.current[deviceId]);
      delete percentageTimers.current[deviceId];
    }

    const mqttDeviceId = device.commandTopic.split('/')[2];
    const isSky = skylights.some(d => d.id === deviceId);
    const fullTimeSeconds = deviceId === "skylight_left" ? skyLeftFullTime : deviceId === "skylight_right" ? skyRightFullTime : deviceId === "sidescreen_left" ? sideLeftFullTime : sideRightFullTime;
    const groupPrefix = isSky ? "sky-control" : "side-control";

    setResetStatus(prev => ({ ...prev, [deviceId]: 'resetting' }));
    setOperationStatus(prev => ({ ...prev, [deviceId]: 'running' }));

    try {
      await sendDeviceCommand(device.esp32Id, mqttDeviceId, "CLOSE");
      percentageTimers.current[deviceId] = setTimeout(async () => {
        await sendDeviceCommand(device.esp32Id, mqttDeviceId, "STOP");
        delete percentageTimers.current[deviceId];
        setCurrentPosition(prev => ({ ...prev, [deviceId]: 0 }));
        getMqttClient().publish(
          `tansaeng/${groupPrefix}/${mqttDeviceId}/currentPos`,
          "0",
          { qos: 1, retain: true }
        );
        setResetStatus(prev => ({ ...prev, [deviceId]: 'idle' }));
        setOperationStatus(prev => ({ ...prev, [deviceId]: 'completed' }));
      }, fullTimeSeconds * 1000);
    } catch {
      setResetStatus(prev => ({ ...prev, [deviceId]: 'idle' }));
      setOperationStatus(prev => ({ ...prev, [deviceId]: 'idle' }));
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

        {/* 스크린 개폐 기준시간 설정 */}
        <section className="mb-2 sm:mb-3">
          <header className="bg-gray-600 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
            <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-white">
              스크린 개폐 기준시간 설정
            </h2>
          </header>
          <div className="bg-white border border-gray-200 rounded-b-lg p-3 sm:p-4">
            <p className="text-xs text-gray-500 mb-3">0% → 100% 완전 개폐에 걸리는 총 시간입니다. 모터 실제 속도에 맞게 조정하세요.</p>
            <table className="w-full text-sm border-collapse mb-3">
              <thead>
                <tr className="bg-gray-50">
                  <th className="border border-gray-200 px-3 py-2 text-left text-xs font-semibold text-gray-600">구분</th>
                  <th className="border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-600">현재 설정</th>
                  <th className="border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-600">변경 값 (초)</th>
                  <th className="border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-600">환산</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td className="border border-gray-200 px-3 py-2 font-medium text-amber-700">천창 좌측</td>
                  <td className="border border-gray-200 px-3 py-2 text-center text-gray-700">{skyLeftFullTime}초 ({Math.floor(skyLeftFullTime/60)}분 {skyLeftFullTime%60 > 0 ? `${skyLeftFullTime%60}초` : ""})</td>
                  <td className="border border-gray-200 px-3 py-2 text-center">
                    <input
                      type="number"
                      min={10} max={3600} step={10}
                      value={editSkyLeftFullTime}
                      onChange={e => setEditSkyLeftFullTime(Number(e.target.value))}
                      className="w-20 px-2 py-1 text-xs border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-amber-400"
                    />
                  </td>
                  <td className="border border-gray-200 px-3 py-2 text-center text-xs text-gray-500">
                    {Math.floor(editSkyLeftFullTime/60)}분 {editSkyLeftFullTime%60 > 0 ? `${editSkyLeftFullTime%60}초` : ""}
                  </td>
                </tr>
                <tr>
                  <td className="border border-gray-200 px-3 py-2 font-medium text-amber-600">천창 우측</td>
                  <td className="border border-gray-200 px-3 py-2 text-center text-gray-700">{skyRightFullTime}초 ({Math.floor(skyRightFullTime/60)}분 {skyRightFullTime%60 > 0 ? `${skyRightFullTime%60}초` : ""})</td>
                  <td className="border border-gray-200 px-3 py-2 text-center">
                    <input
                      type="number"
                      min={10} max={3600} step={10}
                      value={editSkyRightFullTime}
                      onChange={e => setEditSkyRightFullTime(Number(e.target.value))}
                      className="w-20 px-2 py-1 text-xs border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-amber-400"
                    />
                  </td>
                  <td className="border border-gray-200 px-3 py-2 text-center text-xs text-gray-500">
                    {Math.floor(editSkyRightFullTime/60)}분 {editSkyRightFullTime%60 > 0 ? `${editSkyRightFullTime%60}초` : ""}
                  </td>
                </tr>
                <tr>
                  <td className="border border-gray-200 px-3 py-2 font-medium text-blue-700">측창 좌측</td>
                  <td className="border border-gray-200 px-3 py-2 text-center text-gray-700">{sideLeftFullTime}초 ({Math.floor(sideLeftFullTime/60)}분 {sideLeftFullTime%60 > 0 ? `${sideLeftFullTime%60}초` : ""})</td>
                  <td className="border border-gray-200 px-3 py-2 text-center">
                    <input
                      type="number"
                      min={10} max={3600} step={10}
                      value={editSideLeftFullTime}
                      onChange={e => setEditSideLeftFullTime(Number(e.target.value))}
                      className="w-20 px-2 py-1 text-xs border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-blue-400"
                    />
                  </td>
                  <td className="border border-gray-200 px-3 py-2 text-center text-xs text-gray-500">
                    {Math.floor(editSideLeftFullTime/60)}분 {editSideLeftFullTime%60 > 0 ? `${editSideLeftFullTime%60}초` : ""}
                  </td>
                </tr>
                <tr>
                  <td className="border border-gray-200 px-3 py-2 font-medium text-blue-600">측창 우측</td>
                  <td className="border border-gray-200 px-3 py-2 text-center text-gray-700">{sideRightFullTime}초 ({Math.floor(sideRightFullTime/60)}분 {sideRightFullTime%60 > 0 ? `${sideRightFullTime%60}초` : ""})</td>
                  <td className="border border-gray-200 px-3 py-2 text-center">
                    <input
                      type="number"
                      min={10} max={3600} step={10}
                      value={editSideRightFullTime}
                      onChange={e => setEditSideRightFullTime(Number(e.target.value))}
                      className="w-20 px-2 py-1 text-xs border border-gray-300 rounded text-center focus:outline-none focus:ring-2 focus:ring-blue-400"
                    />
                  </td>
                  <td className="border border-gray-200 px-3 py-2 text-center text-xs text-gray-500">
                    {Math.floor(editSideRightFullTime/60)}분 {editSideRightFullTime%60 > 0 ? `${editSideRightFullTime%60}초` : ""}
                  </td>
                </tr>
              </tbody>
            </table>
            <div className="flex items-center gap-3">
              <button
                disabled={screenTimeSaving}
                onClick={async () => {
                  if (editSkyLeftFullTime < 10 || editSkyLeftFullTime > 3600 ||
                      editSkyRightFullTime < 10 || editSkyRightFullTime > 3600 ||
                      editSideLeftFullTime < 10 || editSideLeftFullTime > 3600 ||
                      editSideRightFullTime < 10 || editSideRightFullTime > 3600) {
                    setScreenTimeSavedMsg("10~3600초 범위로 입력하세요.");
                    return;
                  }
                  setScreenTimeSaving(true);
                  setScreenTimeSavedMsg("");
                  try {
                    const res = await fetch('/api/smartfarm/screen_settings.php', {
                      method: 'POST',
                      headers: { 'Content-Type': 'application/json' },
                      body: JSON.stringify({ sky_left: editSkyLeftFullTime, sky_right: editSkyRightFullTime, side_left: editSideLeftFullTime, side_right: editSideRightFullTime }),
                    });
                    const json = await res.json();
                    if (json.success) {
                      setSkyLeftFullTime(editSkyLeftFullTime);
                      setSkyRightFullTime(editSkyRightFullTime);
                      setSideLeftFullTime(editSideLeftFullTime);
                      setSideRightFullTime(editSideRightFullTime);
                      getMqttClient().publish("tansaeng/sky-control/fullTimeSeconds/left", String(editSkyLeftFullTime), { qos: 1, retain: true });
                      getMqttClient().publish("tansaeng/sky-control/fullTimeSeconds/right", String(editSkyRightFullTime), { qos: 1, retain: true });
                      getMqttClient().publish("tansaeng/side-control/fullTimeSeconds/left", String(editSideLeftFullTime), { qos: 1, retain: true });
                      getMqttClient().publish("tansaeng/side-control/fullTimeSeconds/right", String(editSideRightFullTime), { qos: 1, retain: true });
                      setScreenTimeSavedMsg("저장 완료!");
                    } else {
                      setScreenTimeSavedMsg(json.message ?? "저장 실패");
                    }
                  } catch {
                    setScreenTimeSavedMsg("서버 오류");
                  } finally {
                    setScreenTimeSaving(false);
                    setTimeout(() => setScreenTimeSavedMsg(""), 3000);
                  }
                }}
                className="px-4 py-1.5 text-sm bg-gray-700 text-white rounded hover:bg-gray-800 disabled:opacity-50"
              >
                {screenTimeSaving ? "저장 중..." : "저장"}
              </button>
              {screenTimeSavedMsg && (
                <span className={`text-xs font-medium ${screenTimeSavedMsg.includes("완료") ? "text-green-600" : "text-red-500"}`}>
                  {screenTimeSavedMsg}
                </span>
              )}
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
                        if (skyAutoActive) {
                          getMqttClient().publish("tansaeng/sky-control/autoActive", "true", { qos: 1, retain: true });
                        }
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
                        if (skyAutoActive) {
                          getMqttClient().publish("tansaeng/sky-control/autoActive", "true", { qos: 1, retain: true });
                        }
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
                        if (skyAutoActive) {
                          getMqttClient().publish("tansaeng/sky-control/autoActive", "true", { qos: 1, retain: true });
                        }
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
                  {skyAutoActive && (
                    <p className="text-[10px] text-blue-600 text-center mb-1">
                      ✔ 작동 중 기준 변경 즉시 적용됩니다
                    </p>
                  )}
                  {/* 작동 시작/멈춤 */}
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => {
                        setSkyAutoActive(true);
                        skyLastTargetRef.current = {};
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
                    <span className={`text-[10px] font-semibold ${
                      skyAutoActive
                        ? skyAutoType === "time" ? "text-blue-600" : skyAutoType === "combined" ? "text-green-700" : "text-amber-600"
                        : "text-gray-400"
                    }`}>
                      {skyAutoActive
                        ? skyAutoType === "time" ? "🕐 시간기준 작동 중"
                          : skyAutoType === "combined" ? "🌱 복합기준 작동 중"
                          : "🌡 온도기준 작동 중"
                        : "⏸ 대기"}
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

              // 시간 기준 목표개도율 (데몬과 동일한 스텝 함수 알고리즘)
              const toMin = (t: string) => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
              const nowMin = currentMinute;
              let timeTargetRate = 0;
              const timeSorted = [...skyTimePoints].sort((a, b) => toMin(a.time) - toMin(b.time));
              if (timeSorted.length > 0) {
                let activePoint = timeSorted[timeSorted.length - 1]; // 기본값: 마지막 포인트
                for (let i = timeSorted.length - 1; i >= 0; i--) {
                  if (nowMin >= toMin(timeSorted[i].time)) { activePoint = timeSorted[i]; break; }
                }
                timeTargetRate = activePoint.rate;
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
                      <div className="flex items-center gap-1.5">
                        <span className="font-bold text-amber-600 text-sm">{currentPosition[skylight.id] ?? 0}%</span>
                        <button
                          onClick={() => handleResetPosition(skylight.id)}
                          disabled={resetStatus[skylight.id] === 'resetting' || operationStatus[skylight.id] === 'running'}
                          className={`px-1.5 py-0.5 rounded text-[10px] font-semibold transition-colors ${
                            resetStatus[skylight.id] === 'resetting'
                              ? "bg-gray-300 text-gray-500 cursor-not-allowed"
                              : "bg-gray-200 hover:bg-red-100 text-gray-600 hover:text-red-600 border border-gray-300 hover:border-red-300"
                          }`}
                        >
                          {resetStatus[skylight.id] === 'resetting' ? "초기화중..." : "↺ 초기화"}
                        </button>
                      </div>
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
                    <div className={`text-center text-[10px] rounded p-1.5 ${
                      skyAutoActive
                        ? skyAutoType === "time" ? "text-blue-600 bg-blue-50"
                          : skyAutoType === "combined" ? "text-green-700 bg-green-50"
                          : "text-amber-600 bg-amber-50"
                        : "text-gray-500 bg-gray-50"
                    }`}>
                      {skyAutoActive
                        ? skyAutoType === "time" ? "🕐 시간 기반 자동 개폐 중"
                          : skyAutoType === "combined" ? "🌱 복합 기준 자동 개폐 중"
                          : "🌡 온도 기반 자동 개폐 중"
                        : "AUTO 모드 — 작동시작 버튼을 누르세요"}
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

            {/* AUTO 모드: 제어 방식 + 포인트 설정 */}
            {sideMode === "AUTO" && (
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-2 sm:p-3 mb-3 space-y-3">
                {/* 제어 방식 선택: 시간 / 온도·습도 */}
                <div>
                  <p className="text-[10px] sm:text-xs font-semibold text-gray-700 mb-1.5">제어 방식</p>
                  <div className="flex gap-2">
                    <button
                      onClick={() => {
                        sideAutoTypeRef.current = "temp";
                        sideAutoTypeFromMqttRef.current = false;
                        setSideAutoType("temp");
                        sideLastTargetRef.current = {};
                        getMqttClient().publish("tansaeng/side-control/autoType", "temp", { qos: 1, retain: true });
                      }}
                      className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${sideAutoType === "temp" ? "bg-blue-500 text-white shadow" : "bg-white border border-blue-300 text-blue-600 hover:bg-blue-50"}`}
                    >
                      🌡 온도·습도
                    </button>
                    <button
                      onClick={() => {
                        sideAutoTypeRef.current = "time";
                        sideAutoTypeFromMqttRef.current = false;
                        setSideAutoType("time");
                        sideLastTargetRef.current = {};
                        getMqttClient().publish("tansaeng/side-control/autoType", "time", { qos: 1, retain: true });
                      }}
                      className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${sideAutoType === "time" ? "bg-blue-500 text-white shadow" : "bg-white border border-blue-300 text-blue-600 hover:bg-blue-50"}`}
                    >
                      🕐 시간
                    </button>
                    <button
                      onClick={() => {
                        sideAutoTypeRef.current = "daynight";
                        sideAutoTypeFromMqttRef.current = false;
                        setSideAutoType("daynight");
                        sideLastTargetRef.current = {};
                        getMqttClient().publish("tansaeng/side-control/autoType", "daynight", { qos: 1, retain: true });
                      }}
                      className={`flex-1 py-1.5 rounded-md text-xs font-semibold transition-colors ${sideAutoType === "daynight" ? "bg-indigo-500 text-white shadow" : "bg-white border border-indigo-300 text-indigo-600 hover:bg-indigo-50"}`}
                    >
                      🌓 주간/야간
                    </button>
                  </div>
                </div>

                {/* 시간 기준: 포인트 설정 */}
                {sideAutoType === "time" && (
                  <>
                    <div className="flex items-center justify-between">
                      <p className="text-[10px] sm:text-xs font-semibold text-gray-700">시간-개도율 설정</p>
                      <button
                        onClick={() => setSideTimePoints(prev => {
                          const sorted = [...prev].sort((a, b) => a.time.localeCompare(b.time));
                          const lastTime = sorted[sorted.length - 1]?.time ?? "20:00";
                          const [h] = lastTime.split(':').map(Number);
                          const nextH = Math.min(h + 2, 23);
                          return [...prev, { time: `${String(nextH).padStart(2,'0')}:00`, rate: 0 }];
                        })}
                        className="text-[10px] px-2 py-1 bg-blue-500 text-white rounded hover:bg-blue-600"
                      >+ 포인트 추가</button>
                    </div>
                    <div className="text-[9px] text-gray-500">해당 시각이 되면 설정한 개도율로 즉시 이동 (스텝 방식)</div>
                    <div className="space-y-1.5">
                      {[...sideTimePoints].sort((a, b) => a.time.localeCompare(b.time)).map((point, idx) => (
                        <div key={idx} className="flex items-center gap-2 bg-white rounded p-1.5">
                          <span className="text-[10px] text-gray-500 w-4">{idx + 1}</span>
                          <div className="flex items-center gap-1 flex-1">
                            <span className="text-[10px] text-gray-600">시각</span>
                            <input
                              type="time"
                              value={point.time}
                              onChange={(e) => {
                                const newPoints = [...sideTimePoints];
                                const realIdx = sideTimePoints.indexOf(point);
                                newPoints[realIdx] = { ...point, time: e.target.value };
                                setSideTimePoints(newPoints);
                              }}
                              className="px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                            />
                            <span className="text-[10px] text-gray-600">→</span>
                            <input
                              type="number" min="0" max="100"
                              value={point.rate}
                              onChange={(e) => {
                                const newPoints = [...sideTimePoints];
                                const realIdx = sideTimePoints.indexOf(point);
                                newPoints[realIdx] = { ...point, rate: Math.min(100, Math.max(0, Number(e.target.value))) };
                                setSideTimePoints(newPoints);
                              }}
                              className="w-14 px-1.5 py-1 text-xs border border-gray-300 rounded text-center"
                            />
                            <span className="text-[10px] text-gray-600">% 개방</span>
                          </div>
                          {sideTimePoints.length > 2 && (
                            <button
                              onClick={() => setSideTimePoints(prev => prev.filter((_, i) => i !== sideTimePoints.indexOf(point)))}
                              className="text-[10px] text-red-500 hover:text-red-700 px-1"
                            >✕</button>
                          )}
                        </div>
                      ))}
                    </div>
                  </>
                )}

                {/* 주간/야간 기준 설정 */}
                {sideAutoType === "daynight" && (() => {
                  const mkPeriodEditor = (period: "day" | "night", label: string) => {
                    const cfg = sideDayNightConfig[period];
                    const updateCfg = (patch: Partial<typeof cfg>) =>
                      setSideDayNightConfig(prev => ({ ...prev, [period]: { ...prev[period], ...patch } }));
                    return (
                      <div key={period} className="bg-white border border-indigo-200 rounded-lg p-2 space-y-2">
                        <p className="text-xs font-bold text-indigo-700">{label}</p>
                        {/* 센서 선택 */}
                        <div className="flex items-center gap-2">
                          <span className="text-[10px] font-semibold text-gray-600">센서:</span>
                          <div className="flex rounded overflow-hidden border border-gray-300">
                            {(["temp", "humi"] as const).map(s => (
                              <button key={s} onClick={() => updateCfg({ sensor: s })}
                                className={`px-2 py-0.5 text-[10px] font-semibold transition-colors ${cfg.sensor === s ? "bg-indigo-500 text-white" : "bg-white text-gray-600 hover:bg-gray-50"}`}>
                                {s === "temp" ? "🌡온도" : "💧습도"}
                              </button>
                            ))}
                          </div>
                        </div>
                        {/* 온도 포인트 */}
                        {cfg.sensor === "temp" && (
                          <div className="space-y-1">
                            {[...cfg.tempPoints].sort((a,b)=>a.temp-b.temp).map((pt, i) => (
                              <div key={i} className="flex items-center gap-1 text-[10px]">
                                <span className="w-12 text-gray-500">온도:</span>
                                <input type="number" value={pt.temp} onChange={e => {
                                  const pts = cfg.tempPoints.map((p,j) => j===i ? {...p, temp: Number(e.target.value)} : p);
                                  updateCfg({ tempPoints: pts });
                                }} className="w-14 border rounded px-1 py-0.5 text-center text-[10px]" />
                                <span className="text-gray-400">°C →</span>
                                <input type="number" value={pt.rate} min={0} max={100} onChange={e => {
                                  const pts = cfg.tempPoints.map((p,j) => j===i ? {...p, rate: Number(e.target.value)} : p);
                                  updateCfg({ tempPoints: pts });
                                }} className="w-14 border rounded px-1 py-0.5 text-center text-[10px]" />
                                <span className="text-gray-400">%</span>
                                <button onClick={() => updateCfg({ tempPoints: cfg.tempPoints.filter((_,j)=>j!==i) })}
                                  className="text-red-400 hover:text-red-600 ml-1 text-xs">✕</button>
                              </div>
                            ))}
                            <button onClick={() => updateCfg({ tempPoints: [...cfg.tempPoints, {temp:25,rate:50}] })}
                              className="text-[10px] text-indigo-600 hover:text-indigo-800">+ 포인트 추가</button>
                          </div>
                        )}
                        {/* 습도 포인트 */}
                        {cfg.sensor === "humi" && (
                          <div className="space-y-1">
                            {[...cfg.humPoints].sort((a,b)=>a.humi-b.humi).map((pt, i) => (
                              <div key={i} className="flex items-center gap-1 text-[10px]">
                                <span className="w-12 text-gray-500">습도:</span>
                                <input type="number" value={pt.humi} onChange={e => {
                                  const pts = cfg.humPoints.map((p,j) => j===i ? {...p, humi: Number(e.target.value)} : p);
                                  updateCfg({ humPoints: pts });
                                }} className="w-14 border rounded px-1 py-0.5 text-center text-[10px]" />
                                <span className="text-gray-400">% →</span>
                                <input type="number" value={pt.rate} min={0} max={100} onChange={e => {
                                  const pts = cfg.humPoints.map((p,j) => j===i ? {...p, rate: Number(e.target.value)} : p);
                                  updateCfg({ humPoints: pts });
                                }} className="w-14 border rounded px-1 py-0.5 text-center text-[10px]" />
                                <span className="text-gray-400">%</span>
                                <button onClick={() => updateCfg({ humPoints: cfg.humPoints.filter((_,j)=>j!==i) })}
                                  className="text-red-400 hover:text-red-600 ml-1 text-xs">✕</button>
                              </div>
                            ))}
                            <button onClick={() => updateCfg({ humPoints: [...cfg.humPoints, {humi:75,rate:50}] })}
                              className="text-[10px] text-indigo-600 hover:text-indigo-800">+ 포인트 추가</button>
                          </div>
                        )}
                      </div>
                    );
                  };
                  return (
                    <div className="space-y-2">
                      {/* 주간/야간 시간 설정 */}
                      <div className="flex items-center gap-3 text-xs">
                        <span className="font-semibold text-gray-700">주간 시작:</span>
                        <input type="time" value={sideDayNightConfig.dayStart}
                          onChange={e => setSideDayNightConfig(prev => ({...prev, dayStart: e.target.value}))}
                          className="border rounded px-2 py-0.5 text-xs" />
                        <span className="font-semibold text-gray-700">야간 시작:</span>
                        <input type="time" value={sideDayNightConfig.nightStart}
                          onChange={e => setSideDayNightConfig(prev => ({...prev, nightStart: e.target.value}))}
                          className="border rounded px-2 py-0.5 text-xs" />
                      </div>
                      {mkPeriodEditor("day", "☀️ 주간 설정")}
                      {mkPeriodEditor("night", "🌙 야간 설정")}
                    </div>
                  );
                })()}

                {/* 온도·습도 기준: 센서 선택 */}
                {sideAutoType === "temp" && (
                <div className="flex items-center gap-2">
                  <span className="text-[10px] sm:text-xs font-semibold text-gray-700">센서:</span>
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
                )}

                {/* 온도 포인트 설정 */}
                {sideAutoType === "temp" && sideAutoSensor === "temp" && (
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
                {sideAutoType === "temp" && sideAutoSensor === "humi" && (
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
                      <div className="flex items-center gap-1.5">
                        <span className="font-bold text-blue-600 text-sm">{currentPosition[sidescreen.id] ?? 0}%</span>
                        <button
                          onClick={() => handleResetPosition(sidescreen.id)}
                          disabled={resetStatus[sidescreen.id] === 'resetting' || operationStatus[sidescreen.id] === 'running'}
                          className={`px-1.5 py-0.5 rounded text-[10px] font-semibold transition-colors ${
                            resetStatus[sidescreen.id] === 'resetting'
                              ? "bg-gray-300 text-gray-500 cursor-not-allowed"
                              : "bg-gray-200 hover:bg-red-100 text-gray-600 hover:text-red-600 border border-gray-300 hover:border-red-300"
                          }`}
                        >
                          {resetStatus[sidescreen.id] === 'resetting' ? "초기화중..." : "↺ 초기화"}
                        </button>
                      </div>
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
                      {sideAutoActive
                        ? sideAutoType === "time" ? "🕐 시간 기반 자동 개폐 중" : sideAutoType === "daynight" ? "🌓 주야간 기준 자동 개폐 중" : "🌡 온도 기반 자동 개폐 중"
                        : "AUTO 모드 — 작동시작 버튼을 누르세요"}
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
                  {/* 주간/야간 모드 토글 */}
                  <div className="flex items-center gap-2 bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2">
                    <span className="text-xs text-gray-700 font-medium flex-1">🌓 주간/야간 구분 제어</span>
                    <button
                      onClick={() => {
                        const next = { ...fanDayNightConfig, enabled: !fanDayNightConfig.enabled };
                        fanDayNightFromMqttRef.current = false;
                        setFanDayNightConfig(next);
                        getMqttClient().publish("tansaeng/fan-control/dayNightConfig", JSON.stringify(next), { qos: 1, retain: true });
                      }}
                      className={`px-3 py-1 rounded-md text-xs font-bold transition-colors ${fanDayNightConfig.enabled ? "bg-indigo-500 text-white" : "bg-white border border-indigo-300 text-indigo-600 hover:bg-indigo-50"}`}
                    >
                      {fanDayNightConfig.enabled ? "✅ 사용중" : "사용 안함"}
                    </button>
                  </div>

                  {fanDayNightConfig.enabled ? (
                    <>
                    {/* 주야간 모드 설정 UI */}
                    <div className="space-y-2">
                      {/* 시간 설정 */}
                      <div className="flex items-center gap-3 text-xs bg-white border border-indigo-100 rounded-lg px-3 py-2">
                        <span className="font-semibold text-gray-700">주간 시작:</span>
                        <input type="time" value={fanDayNightConfig.dayStart}
                          onChange={e => setFanDayNightConfig(prev => ({...prev, dayStart: e.target.value}))}
                          className="border rounded px-2 py-0.5 text-xs" />
                        <span className="font-semibold text-gray-700">야간 시작:</span>
                        <input type="time" value={fanDayNightConfig.nightStart}
                          onChange={e => setFanDayNightConfig(prev => ({...prev, nightStart: e.target.value}))}
                          className="border rounded px-2 py-0.5 text-xs" />
                      </div>
                      {/* 주간/야간 각각 설정 */}
                      {(["day", "night"] as const).map(period => {
                        const pCfg = fanDayNightConfig[period];
                        const pLabel = period === "day" ? "☀️ 주간" : "🌙 야간";
                        const useH = pCfg.sensor === "humi";
                        const GMIN = useH ? 0 : -10; const GMAX = useH ? 100 : 50; const gRange = GMAX - GMIN;
                        const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
                        const humis = [farmSensors.frontHum, farmSensors.backHum, farmSensors.topHum].filter(h => h !== null) as number[];
                        const avgV = useH
                          ? (humis.length > 0 ? humis.reduce((a,b)=>a+b,0)/humis.length : null)
                          : (temps.length > 0 ? temps.reduce((a,b)=>a+b,0)/temps.length : null);
                        const markerP = avgV !== null ? Math.max(0, Math.min(100, ((avgV - GMIN) / gRange) * 100)) : null;
                        return (
                          <div key={period} className="bg-white border border-indigo-200 rounded-lg p-2.5 space-y-2">
                            <div className="flex items-center gap-2">
                              <p className="text-xs font-bold text-indigo-700 flex-1">{pLabel}</p>
                              <div className="flex rounded overflow-hidden border border-gray-300">
                                {(["temp", "humi"] as const).map(s => (
                                  <button key={s}
                                    onClick={() => setFanDayNightConfig(prev => ({...prev, [period]: {...prev[period], sensor: s}}))}
                                    className={`px-2 py-0.5 text-[10px] font-semibold transition-colors ${pCfg.sensor === s ? "bg-indigo-500 text-white" : "bg-white text-gray-600 hover:bg-gray-50"}`}>
                                    {s === "temp" ? "🌡온도" : "💧습도"}
                                  </button>
                                ))}
                              </div>
                            </div>
                            {fans.map(f => {
                              const rangeKey = useH ? "humRanges" : "ranges";
                              const defRange = useH ? {low:60,high:80} : {low:15,high:22};
                              const range = pCfg[rangeKey][f.id] ?? defRange;
                              const inR = avgV !== null && avgV >= range.low && avgV <= range.high;
                              const unit = useH ? "%" : "°C";
                              return (
                                <div key={f.id} className="space-y-1">
                                  <div className="flex justify-between text-[10px] text-gray-600 px-1">
                                    <span className="font-semibold">🌀 {f.name}</span>
                                    <span className={inR ? "text-green-600" : "text-gray-400"}>{range.low}{unit} ~ {range.high}{unit}</span>
                                  </div>
                                  <DualRangeSlider
                                    min={GMIN} max={GMAX} step={useH ? 1 : 0.5}
                                    low={range.low} high={range.high}
                                    onLowChange={v => setFanDayNightConfig(prev => ({...prev, [period]: {...prev[period], [rangeKey]: {...prev[period][rangeKey], [f.id]: {...(prev[period][rangeKey][f.id] ?? defRange), low: v}}}}))}
                                    onHighChange={v => setFanDayNightConfig(prev => ({...prev, [period]: {...prev[period], [rangeKey]: {...prev[period][rangeKey], [f.id]: {...(prev[period][rangeKey][f.id] ?? defRange), high: v}}}}))}
                                    markerPct={markerP} isActive={inR}
                                  />
                                </div>
                              );
                            })}
                          </div>
                        );
                      })}
                    </div>
                    {/* 현재값 + 작동시작/멈춤 버튼 (주야간 모드) */}
                    <div className="flex items-center justify-between bg-gray-100 rounded-lg px-3 py-2 gap-2 mt-2">
                      <div className="text-xs text-gray-600">
                        평균온도: <span className="font-bold text-gray-800">{avgTemp !== null ? `${avgTemp.toFixed(1)}°C` : "—"}</span>
                        {" / "}습도: <span className="font-bold text-gray-800">{avgHumi !== null ? `${avgHumi.toFixed(0)}%` : "—"}</span>
                      </div>
                      <div className="flex gap-2">
                        <button
                          onClick={() => {
                            fanDeviceLastCmd.current = {};
                            setFanAutoActive(true);
                            getMqttClient().publish("tansaeng/fan-control/autoActive", "true", { qos: 1, retain: true });
                          }}
                          className={`px-3 py-1.5 rounded-md text-xs font-bold transition-colors ${
                            fanAutoActive ? "bg-green-500 text-white shadow" : "bg-white border border-green-400 text-green-600 hover:bg-green-50"
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
                            !fanAutoActive ? "bg-gray-500 text-white shadow" : "bg-white border border-gray-400 text-gray-600 hover:bg-gray-50"
                          }`}
                        >
                          ■ 작동멈춤
                        </button>
                      </div>
                    </div>
                    </>
                  ) : (
                  <>
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
                </>
              )}
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
              const GMIN = -30, GMAX = 50;
              const gRange = GMAX - GMIN;
              const temps = [farmSensors.front, farmSensors.back, farmSensors.top].filter(t => t !== null) as number[];
              const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
              const waterTemp = hpSensors.waterTemp;
              const toMarkerPct = (v: number | null) => v !== null
                ? Math.max(0, Math.min(100, ((v - GMIN) / gRange) * 100))
                : null;

              const gaugeItems = [
                { key: "hp_pump",   label: "냉각순환펌프", icon: "💧", sensorVal: avgTemp,   sensorLabel: "팜평균온도" },
                { key: "hp_heater", label: "냉각기",       icon: "❄️", sensorVal: waterTemp, sensorLabel: "물온도" },
                { key: "hp_fan",    label: "장치실 팬",     icon: "🌀", sensorVal: avgTemp,   sensorLabel: "팜평균온도" },
              ];

              return (
                <div className="space-y-2 sm:space-y-3">

                  {/* 주간/야간 모드 토글 */}
                  <div className="flex items-center gap-2 bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2">
                    <span className="text-xs text-gray-700 font-medium flex-1">🌓 주간/야간 구분 제어</span>
                    <button
                      onClick={() => {
                        const next = { ...hpDayNightConfig, enabled: !hpDayNightConfig.enabled };
                        hpDayNightFromMqttRef.current = false;
                        setHpDayNightConfig(next);
                        getMqttClient().publish("tansaeng/hp-control/dayNightConfig", JSON.stringify(next), { qos: 1, retain: true });
                      }}
                      className={`px-3 py-1 rounded-md text-xs font-bold transition-colors ${hpDayNightConfig.enabled ? "bg-indigo-500 text-white" : "bg-white border border-indigo-300 text-indigo-600 hover:bg-indigo-50"}`}
                    >
                      {hpDayNightConfig.enabled ? "✅ 사용중" : "사용 안함"}
                    </button>
                  </div>

                  {hpDayNightConfig.enabled && (
                    <div className="space-y-2">
                      {/* 시간 설정 */}
                      <div className="flex items-center gap-3 text-xs bg-white border border-indigo-100 rounded-lg px-3 py-2">
                        <span className="font-semibold text-gray-700">주간 시작:</span>
                        <input type="time" value={hpDayNightConfig.dayStart}
                          onChange={e => setHpDayNightConfig(prev => ({...prev, dayStart: e.target.value}))}
                          className="border rounded px-2 py-0.5 text-xs" />
                        <span className="font-semibold text-gray-700">야간 시작:</span>
                        <input type="time" value={hpDayNightConfig.nightStart}
                          onChange={e => setHpDayNightConfig(prev => ({...prev, nightStart: e.target.value}))}
                          className="border rounded px-2 py-0.5 text-xs" />
                      </div>
                      {/* 주간/야간 범위 설정 */}
                      {(["day", "night"] as const).map(period => {
                        const pRanges = hpDayNightConfig[period].ranges;
                        const pLabel = period === "day" ? "☀️ 주간" : "🌙 야간";
                        return (
                          <div key={period} className="bg-white border border-indigo-200 rounded-lg p-2.5 space-y-2">
                            <p className="text-xs font-bold text-indigo-700">{pLabel} — 작동 온도 범위</p>
                            {gaugeItems.map(({ key, label, icon }) => {
                              const range = pRanges[key] ?? { low: 8, high: 15 };
                              return (
                                <div key={key} className="space-y-1">
                                  <div className="flex justify-between text-[10px] text-gray-600 px-1">
                                    <span className="font-semibold">{icon} {label}</span>
                                    <span className="text-gray-400">{range.low}°C ~ {range.high}°C</span>
                                  </div>
                                  <DualRangeSlider
                                    min={-30} max={50} step={0.5}
                                    low={range.low} high={range.high}
                                    onLowChange={(v) => {
                                      const def = {low:8,high:15};
                                      setHpDayNightConfig(prev => ({ ...prev, [period]: { ranges: { ...prev[period].ranges, [key]: { ...(prev[period].ranges[key] ?? def), low: v } } } }));
                                    }}
                                    onHighChange={(v) => {
                                      const def = {low:8,high:15};
                                      setHpDayNightConfig(prev => ({ ...prev, [period]: { ranges: { ...prev[period].ranges, [key]: { ...(prev[period].ranges[key] ?? def), high: v } } } }));
                                    }}
                                    markerPct={toMarkerPct(avgTemp)} isActive={avgTemp !== null && avgTemp >= range.low && avgTemp <= range.high}
                                  />
                                </div>
                              );
                            })}
                          </div>
                        );
                      })}
                    </div>
                  )}

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
                  {gaugeItems.map(({ key, label, icon, sensorVal, sensorLabel }) => {
                    const range = hpDeviceRanges[key] ?? { low: 15, high: 22 };
                    const isOn = hpDeviceStates[key] === "ON";
                    const markerPct = toMarkerPct(sensorVal);
                    const inRange = sensorVal !== null && sensorVal >= range.low && sensorVal <= range.high;

                    return (
                      <div key={key} className="bg-orange-50 border border-orange-200 rounded-lg p-2.5 sm:p-3">
                        {/* 헤더: 장치명 + 작동상태 LED */}
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-xs sm:text-sm font-semibold text-gray-700">{icon} {label}</span>
                          <div className="flex items-center gap-1.5">
                            <span className="text-[10px] text-gray-400">{sensorLabel}: {sensorVal !== null ? `${sensorVal.toFixed(1)}°C` : "—"}</span>
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
                            {inRange ? `✅ ${sensorLabel} 범위 내` : `❌ ${sensorLabel} 범위 밖`}
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
                          <span>-30°C</span><span>+10°C</span><span>+50°C</span>
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
