import { useEffect, useState, useRef } from "react";
import type { MistZoneConfig, MistMode, MistScheduleSettings } from "../types";
import { publishCommand, getMqttClient, isMqttConnected, onConnectionChange } from "../mqtt/mqttClient";
import { saveDeviceSettings } from "../api/deviceControl";

interface MistControlProps {
  zones: MistZoneConfig[];
  setZones: React.Dispatch<React.SetStateAction<MistZoneConfig[]>>;
}

// 각 Zone의 밸브 상태 (ESP32에서 받아온 상태)
interface ValveStatus {
  [zoneId: string]: {
    valveState: "OPEN" | "CLOSE" | "UNKNOWN";
    online: boolean;
    lastUpdated: string;
  };
}

// Zone ID와 Controller ID 매핑
const ZONE_CONTROLLER_MAP: Record<string, string> = {
  zone_a: "ctlr-0004",
  zone_b: "ctlr-0005",
  zone_c: "ctlr-0006",
  zone_d: "ctlr-0007",
  zone_e: "ctlr-0008",
};

// AUTO 사이클 타이머 타입
interface CycleTimer {
  stopTimer: NodeJS.Timeout | null;
  sprayTimer: NodeJS.Timeout | null;
  isRunning: boolean;
}

export default function MistControl({ zones, setZones }: MistControlProps) {
  // ESP32 밸브 상태
  const [valveStatus, setValveStatus] = useState<ValveStatus>({});

  // ESP32 장치별 연결 상태 (API 폴링)
  const [esp32Status, setEsp32Status] = useState<Record<string, boolean>>({});

  // 수동 분무 상태 (UI 표시용)
  const [manualSprayState, setManualSprayState] = useState<{[zoneId: string]: "spraying" | "stopped" | "idle"}>({});

  // AUTO 사이클 상태 (UI 표시용)
  const [autoCycleState, setAutoCycleState] = useState<{[zoneId: string]: "waiting" | "spraying" | "idle"}>({});

  // AUTO 사이클 타이머 참조
  const cycleTimers = useRef<Record<string, CycleTimer>>({});

  // 경과 시간 추적
  const lastStateChangeTime = useRef<Record<string, number>>({});
  const lastKnownValveState = useRef<Record<string, string>>({});
  const [, forceTimerUpdate] = useState(0);

  // MQTT 연결 상태
  const [mqttConnected, setMqttConnected] = useState(false);

  // MQTT 연결 상태 모니터링
  useEffect(() => {
    getMqttClient();
    const unsubscribe = onConnectionChange((connected) => {
      setMqttConnected(connected);
      console.log(`[MQTT] Connection status: ${connected ? "Connected" : "Disconnected"}`);
    });
    return () => unsubscribe();
  }, []);

  // 1초마다 경과 시간 갱신
  useEffect(() => {
    const interval = setInterval(() => forceTimerUpdate(n => n + 1), 1000);
    return () => clearInterval(interval);
  }, []);

  // ESP32 상태 API 폴링 (DevicesControl과 동일한 방식)
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

          // valveStatus의 online 상태도 업데이트
          setValveStatus(prev => {
            const updated = { ...prev };
            Object.entries(ZONE_CONTROLLER_MAP).forEach(([zoneId, controllerId]) => {
              if (updated[zoneId]) {
                updated[zoneId] = { ...updated[zoneId], online: newStatus[controllerId] ?? false };
              } else {
                updated[zoneId] = { valveState: "UNKNOWN", online: newStatus[controllerId] ?? false, lastUpdated: "" };
              }
            });
            return updated;
          });
        }
      } catch (error) {
        console.error("[API] Failed to fetch ESP32 status:", error);
      }
    };

    fetchESP32Status();
    const interval = setInterval(fetchESP32Status, 5000);
    return () => clearInterval(interval);
  }, []);

  // MQTT 구독 - ESP32 상태 수신
  useEffect(() => {
    const client = getMqttClient();

    const handleMessage = (topic: string, message: Buffer) => {
      const msg = message.toString();

      // 밸브 상태 변경 시 경과 타이머 리셋 헬퍼
      const recordStateChange = (zoneId: string, newState: string) => {
        if (lastKnownValveState.current[zoneId] !== newState) {
          lastKnownValveState.current[zoneId] = newState;
          lastStateChangeTime.current[zoneId] = Date.now();
        }
      };

      // Zone A (ctrl-0004) 상태 처리
      if (topic === "tansaeng/ctlr-0004/valve1/state") {
        recordStateChange("zone_a", msg);
        setValveStatus(prev => ({
          ...prev,
          zone_a: {
            ...prev.zone_a,
            valveState: msg === "OPEN" ? "OPEN" : "CLOSE",
            lastUpdated: new Date().toLocaleTimeString()
          }
        }));
        // 수동 분무 상태 업데이트
        setManualSprayState(prev => ({
          ...prev,
          zone_a: msg === "OPEN" ? "spraying" : "stopped"
        }));
      }

      if (topic === "tansaeng/ctlr-0004/status") {
        setValveStatus(prev => ({
          ...prev,
          zone_a: {
            ...prev.zone_a,
            online: msg === "online",
            lastUpdated: new Date().toLocaleTimeString()
          }
        }));
      }

      // 다른 Zone들도 같은 패턴으로 처리 (ctrl-0005, ctrl-0006 등)
      // Zone B
      if (topic === "tansaeng/ctlr-0005/valve1/state") {
        recordStateChange("zone_b", msg);
        setValveStatus(prev => ({
          ...prev,
          zone_b: { ...prev.zone_b, valveState: msg === "OPEN" ? "OPEN" : "CLOSE", lastUpdated: new Date().toLocaleTimeString() }
        }));
        setManualSprayState(prev => ({ ...prev, zone_b: msg === "OPEN" ? "spraying" : "stopped" }));
      }
      if (topic === "tansaeng/ctlr-0005/status") {
        setValveStatus(prev => ({ ...prev, zone_b: { ...prev.zone_b, online: msg === "online", lastUpdated: new Date().toLocaleTimeString() } }));
      }

      // Zone C
      if (topic === "tansaeng/ctlr-0006/valve1/state") {
        recordStateChange("zone_c", msg);
        setValveStatus(prev => ({
          ...prev,
          zone_c: { ...prev.zone_c, valveState: msg === "OPEN" ? "OPEN" : "CLOSE", lastUpdated: new Date().toLocaleTimeString() }
        }));
        setManualSprayState(prev => ({ ...prev, zone_c: msg === "OPEN" ? "spraying" : "stopped" }));
      }
      if (topic === "tansaeng/ctlr-0006/status") {
        setValveStatus(prev => ({ ...prev, zone_c: { ...prev.zone_c, online: msg === "online", lastUpdated: new Date().toLocaleTimeString() } }));
      }

      // Zone D
      if (topic === "tansaeng/ctlr-0007/valve1/state") {
        recordStateChange("zone_d", msg);
        setValveStatus(prev => ({
          ...prev,
          zone_d: { ...prev.zone_d, valveState: msg === "OPEN" ? "OPEN" : "CLOSE", lastUpdated: new Date().toLocaleTimeString() }
        }));
        setManualSprayState(prev => ({ ...prev, zone_d: msg === "OPEN" ? "spraying" : "stopped" }));
      }
      if (topic === "tansaeng/ctlr-0007/status") {
        setValveStatus(prev => ({ ...prev, zone_d: { ...prev.zone_d, online: msg === "online", lastUpdated: new Date().toLocaleTimeString() } }));
      }

      // Zone E
      if (topic === "tansaeng/ctlr-0008/valve1/state") {
        recordStateChange("zone_e", msg);
        setValveStatus(prev => ({
          ...prev,
          zone_e: { ...prev.zone_e, valveState: msg === "OPEN" ? "OPEN" : "CLOSE", lastUpdated: new Date().toLocaleTimeString() }
        }));
        setManualSprayState(prev => ({ ...prev, zone_e: msg === "OPEN" ? "spraying" : "stopped" }));
      }
      if (topic === "tansaeng/ctlr-0008/status") {
        setValveStatus(prev => ({ ...prev, zone_e: { ...prev.zone_e, online: msg === "online", lastUpdated: new Date().toLocaleTimeString() } }));
      }
    };

    client.on("message", handleMessage);

    // 토픽 구독
    const topics = [
      "tansaeng/ctlr-0004/valve1/state", "tansaeng/ctlr-0004/status",
      "tansaeng/ctlr-0005/valve1/state", "tansaeng/ctlr-0005/status",
      "tansaeng/ctlr-0006/valve1/state", "tansaeng/ctlr-0006/status",
      "tansaeng/ctlr-0007/valve1/state", "tansaeng/ctlr-0007/status",
      "tansaeng/ctlr-0008/valve1/state", "tansaeng/ctlr-0008/status",
    ];

    topics.forEach(topic => {
      client.subscribe(topic, (err) => {
        if (!err) {
          console.log(`[MQTT] Subscribed: ${topic}`);
        }
      });
    });

    return () => {
      client.off("message", handleMessage);
    };
  }, []);

  const updateZone = async (zoneId: string, updates: Partial<MistZoneConfig>) => {
    setZones((prev) =>
      prev.map((zone) =>
        zone.id === zoneId ? { ...zone, ...updates } : zone
      )
    );

    // 모드 변경 시 서버에 저장
    if ('mode' in updates) {
      const zone = zones.find(z => z.id === zoneId);
      if (zone) {
        await saveDeviceSettings({
          mist_zones: {
            [zoneId]: {
              mode: updates.mode,
              controllerId: zone.controllerId,
              deviceId: 'valve1',
              isRunning: zone.isRunning,
              daySchedule: zone.daySchedule,
              nightSchedule: zone.nightSchedule,
            }
          }
        });
        console.log(`[SETTINGS] Zone ${zoneId} mode saved: ${updates.mode}`);
      }
    }
  };

  const updateDaySchedule = (zoneId: string, updates: Partial<MistScheduleSettings>) => {
    setZones((prev) =>
      prev.map((zone) =>
        zone.id === zoneId
          ? { ...zone, daySchedule: { ...zone.daySchedule, ...updates } }
          : zone
      )
    );
  };

  const updateNightSchedule = (zoneId: string, updates: Partial<MistScheduleSettings>) => {
    setZones((prev) =>
      prev.map((zone) =>
        zone.id === zoneId
          ? { ...zone, nightSchedule: { ...zone.nightSchedule, ...updates } }
          : zone
      )
    );
  };

  // ESP32 MQTT 토픽 가져오기
  const getValveCmdTopic = (controllerId: string) => {
    return `tansaeng/${controllerId}/valve1/cmd`;
  };

  // 설정 저장
  const handleSaveZone = async (zone: MistZoneConfig) => {
    if (zone.mode === "AUTO") {
      if (zone.daySchedule.enabled) {
        // 작동분무주기만 필수 (정지분무주기는 선택)
        if (!zone.daySchedule.sprayDurationSeconds) {
          alert("주간 모드가 활성화되어 있습니다. 작동분무주기(초)를 입력해야 합니다.");
          return;
        }
      }
      if (zone.nightSchedule.enabled) {
        // 작동분무주기만 필수 (정지분무주기는 선택)
        if (!zone.nightSchedule.sprayDurationSeconds) {
          alert("야간 모드가 활성화되어 있습니다. 작동분무주기(초)를 입력해야 합니다.");
          return;
        }
      }
      if (!zone.daySchedule.enabled && !zone.nightSchedule.enabled) {
        alert("AUTO 모드에서는 주간 또는 야간 중 하나 이상을 활성화해야 합니다.");
        return;
      }
    }

    // 서버에 설정 저장 (데몬이 읽어서 자동 제어)
    const result = await saveDeviceSettings({
      mist_zones: {
        [zone.id]: {
          mode: zone.mode,
          controllerId: zone.controllerId,
          deviceId: 'valve1',
          isRunning: zone.isRunning,
          daySchedule: zone.daySchedule,
          nightSchedule: zone.nightSchedule,
        }
      }
    });

    if (result.success) {
      console.log(`[SETTINGS] Zone ${zone.id} saved to server`);
      alert(`${zone.name} 설정이 저장되었습니다.`);
    } else {
      alert(`설정 저장 실패: ${result.message}`);
    }
  };

  // AUTO 사이클 중지 함수
  const stopAutoCycle = (zoneId: string) => {
    const timer = cycleTimers.current[zoneId];
    if (timer) {
      if (timer.stopTimer) clearTimeout(timer.stopTimer);
      if (timer.sprayTimer) clearTimeout(timer.sprayTimer);
      timer.isRunning = false;
    }
    setAutoCycleState(prev => ({ ...prev, [zoneId]: "idle" }));
  };

  // AUTO 사이클 시작 함수 (정지대기 → 분무 → 반복)
  // 서버 데몬에서 처리하므로 더 이상 브라우저에서는 사용하지 않음
  const _startAutoCycle = (zone: MistZoneConfig, schedule: MistScheduleSettings) => {
    const zoneId = zone.id;
    const controllerId = zone.controllerId;
    if (!controllerId) return;

    const cmdTopic = getValveCmdTopic(controllerId);
    const sprayDuration = (schedule.sprayDurationSeconds ?? 0) * 1000; // ms
    const stopDuration = (schedule.stopDurationSeconds ?? 0) * 1000;   // ms

    console.log(`[AUTO] Starting cycle for ${zone.name}`);
    console.log(`[AUTO] Topic: ${cmdTopic}`);
    console.log(`[AUTO] Spray: ${sprayDuration/1000}s, Stop: ${stopDuration/1000}s`);
    console.log(`[AUTO] MQTT Connected: ${isMqttConnected()}`);

    // 기존 타이머 정리
    stopAutoCycle(zoneId);

    // 타이머 초기화
    cycleTimers.current[zoneId] = {
      stopTimer: null,
      sprayTimer: null,
      isRunning: true,
    };

    const runCycle = () => {
      if (!cycleTimers.current[zoneId]?.isRunning) return;

      // MQTT 연결 확인
      if (!isMqttConnected()) {
        console.error(`[AUTO] MQTT not connected! Retrying in 3 seconds...`);
        cycleTimers.current[zoneId].stopTimer = setTimeout(runCycle, 3000);
        return;
      }

      // 1. 정지 대기 (밸브 닫힘)
      console.log(`[AUTO] ${zone.name}: Sending OFF to ${cmdTopic}`);
      publishCommand(cmdTopic, { power: "off" });
      setAutoCycleState(prev => ({ ...prev, [zoneId]: "waiting" }));
      // manualSprayState도 업데이트 (LED 표시용)
      setManualSprayState(prev => ({ ...prev, [zoneId]: "stopped" }));

      cycleTimers.current[zoneId].stopTimer = setTimeout(() => {
        if (!cycleTimers.current[zoneId]?.isRunning) return;

        // 2. 분무 (밸브 열림)
        console.log(`[AUTO] ${zone.name}: Sending ON to ${cmdTopic}`);
        publishCommand(cmdTopic, { power: "on" });
        setAutoCycleState(prev => ({ ...prev, [zoneId]: "spraying" }));
        // manualSprayState도 업데이트 (LED 표시용)
        setManualSprayState(prev => ({ ...prev, [zoneId]: "spraying" }));

        cycleTimers.current[zoneId].sprayTimer = setTimeout(() => {
          if (!cycleTimers.current[zoneId]?.isRunning) return;
          // 3. 다음 사이클 시작
          runCycle();
        }, sprayDuration);
      }, stopDuration);
    };

    // 사이클 시작
    runCycle();
  };

  // 현재 시간대에 맞는 스케줄 가져오기
  const getCurrentSchedule = (zone: MistZoneConfig): MistScheduleSettings | null => {
    const now = new Date();
    const currentTime = now.getHours() * 60 + now.getMinutes();

    const parseTime = (timeStr: string): number => {
      if (!timeStr) return 0;
      const [h, m] = timeStr.split(":").map(Number);
      return h * 60 + m;
    };

    // 주간 스케줄 확인
    if (zone.daySchedule.enabled) {
      const start = parseTime(zone.daySchedule.startTime);
      const end = parseTime(zone.daySchedule.endTime);
      if (start <= currentTime && currentTime < end) {
        return zone.daySchedule;
      }
    }

    // 야간 스케줄 확인
    if (zone.nightSchedule.enabled) {
      const start = parseTime(zone.nightSchedule.startTime);
      const end = parseTime(zone.nightSchedule.endTime);
      // 야간은 시작 > 종료 (예: 18:00 ~ 06:00)
      if (start > end) {
        if (currentTime >= start || currentTime < end) {
          return zone.nightSchedule;
        }
      } else if (start <= currentTime && currentTime < end) {
        return zone.nightSchedule;
      }
    }

    return null;
  };

  // 시스템 작동 시작
  const handleStartOperation = async (zone: MistZoneConfig) => {
    if (!zone.controllerId) {
      alert("컨트롤러가 연결되어 있지 않습니다.");
      return;
    }

    if (zone.mode === "OFF") {
      alert("먼저 운전 모드를 MANUAL 또는 AUTO로 설정해주세요.");
      return;
    }

    if (zone.mode === "AUTO") {
      // 현재 시간대에 맞는 스케줄 확인
      const schedule = getCurrentSchedule(zone);
      if (!schedule) {
        alert("현재 시간대에 활성화된 스케줄이 없습니다. 주간/야간 설정을 확인해주세요.");
        return;
      }

      // 서버에 isRunning 상태 저장 (데몬이 AUTO 제어 시작)
      await saveDeviceSettings({
        mist_zones: {
          [zone.id]: {
            mode: zone.mode,
            controllerId: zone.controllerId,
            deviceId: 'valve1',
            isRunning: true,
            daySchedule: zone.daySchedule,
            nightSchedule: zone.nightSchedule,
          }
        }
      });

      setZones((prev) =>
        prev.map((z) => z.id === zone.id ? { ...z, isRunning: true } : z)
      );

      console.log(`[SETTINGS] Zone ${zone.id} isRunning=true saved to server`);
      alert(`${zone.name} AUTO 사이클을 시작합니다.\n서버 데몬이 자동 제어합니다.`);
    } else {
      // MANUAL 모드 (기존 로직)
      publishCommand(`tansaeng/mist/${zone.id}/control`, {
        action: "start",
        controllerId: zone.controllerId,
      });
      setZones((prev) =>
        prev.map((z) => z.id === zone.id ? { ...z, isRunning: true } : z)
      );
      alert(`${zone.name} 작동을 시작했습니다.`);
    }
  };

  // 시스템 작동 중지
  const handleStopOperation = async (zone: MistZoneConfig) => {
    if (!zone.controllerId) {
      alert("컨트롤러가 연결되어 있지 않습니다.");
      return;
    }

    // 서버에 isRunning=false 저장 (데몬이 AUTO 제어 중지)
    await saveDeviceSettings({
      mist_zones: {
        [zone.id]: {
          mode: zone.mode,
          controllerId: zone.controllerId,
          deviceId: 'valve1',
          isRunning: false,
          daySchedule: zone.daySchedule,
          nightSchedule: zone.nightSchedule,
        }
      }
    });

    // 로컬 사이클도 중지 (브라우저에서 실행 중인 경우)
    stopAutoCycle(zone.id);

    setZones((prev) =>
      prev.map((z) => z.id === zone.id ? { ...z, isRunning: false } : z)
    );

    console.log(`[SETTINGS] Zone ${zone.id} isRunning=false saved to server`);
    alert(`${zone.name} 작동을 중지했습니다.`);
  };

  // 컴포넌트 언마운트 시 모든 타이머 정리
  useEffect(() => {
    return () => {
      Object.keys(cycleTimers.current).forEach(zoneId => {
        stopAutoCycle(zoneId);
      });
    };
  }, []);

  // 수동 분무 실행 - ESP32에 직접 명령
  const handleManualSpray = (zone: MistZoneConfig) => {
    if (!zone.controllerId) {
      alert("컨트롤러가 연결되어 있지 않습니다.");
      return;
    }

    // ESP32 밸브 열기 명령
    const cmdTopic = getValveCmdTopic(zone.controllerId);
    publishCommand(cmdTopic, { power: "on" });

    // 타이머 리셋
    if (lastKnownValveState.current[zone.id] !== "OPEN") {
      lastKnownValveState.current[zone.id] = "OPEN";
      lastStateChangeTime.current[zone.id] = Date.now();
    }
    // UI 상태 업데이트
    setManualSprayState(prev => ({ ...prev, [zone.id]: "spraying" }));

    console.log(`[MQTT] Published to ${cmdTopic}: ON`);
  };

  // 수동 분무 중지 - ESP32에 직접 명령
  const handleManualStop = (zone: MistZoneConfig) => {
    if (!zone.controllerId) {
      alert("컨트롤러가 연결되어 있지 않습니다.");
      return;
    }

    // ESP32 밸브 닫기 명령
    const cmdTopic = getValveCmdTopic(zone.controllerId);
    publishCommand(cmdTopic, { power: "off" });

    // 타이머 리셋
    if (lastKnownValveState.current[zone.id] !== "CLOSE") {
      lastKnownValveState.current[zone.id] = "CLOSE";
      lastStateChangeTime.current[zone.id] = Date.now();
    }
    // UI 상태 업데이트
    setManualSprayState(prev => ({ ...prev, [zone.id]: "stopped" }));

    console.log(`[MQTT] Published to ${cmdTopic}: OFF`);
  };

  // 경과 시간 계산
  const getElapsedSeconds = (zoneId: string): number => {
    const t = lastStateChangeTime.current[zoneId];
    if (!t) return 0;
    return Math.floor((Date.now() - t) / 1000);
  };

  // MM:SS 포맷
  const formatMMSS = (seconds: number): string => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
  };

  // 경과/남은 시간 표시 컴포넌트
  const ZoneTimer = ({ zoneId, sprayState, sprayDuration, stopDuration }: {
    zoneId: string;
    sprayState: "spraying" | "stopped" | "idle";
    sprayDuration?: number | null;
    stopDuration?: number | null;
  }) => {
    if (sprayState === "idle") return null;
    const elapsed = getElapsedSeconds(zoneId);
    const isSpraying = sprayState === "spraying";
    const totalSec = isSpraying ? (sprayDuration ?? null) : (stopDuration ?? null);
    const remaining = totalSec !== null ? Math.max(0, totalSec - elapsed) : null;
    const progress = totalSec ? Math.min(100, (elapsed / totalSec) * 100) : null;

    return (
      <div className={`rounded-lg border px-3 py-2 mt-2 ${isSpraying ? "bg-green-50 border-green-200" : "bg-red-50 border-red-200"}`}>
        <div className="flex items-center justify-between mb-1">
          <span className={`text-xs font-semibold ${isSpraying ? "text-green-700" : "text-red-700"}`}>
            {isSpraying ? "💧 분무 지속" : "⏸ 정지 지속"}
          </span>
          <span className={`text-lg font-bold tabular-nums ${isSpraying ? "text-green-600" : "text-red-600"}`}>
            {formatMMSS(elapsed)}
          </span>
        </div>
        {totalSec !== null && remaining !== null && (
          <>
            <div className="flex items-center justify-between text-[10px] text-gray-500 mb-1">
              <span>설정: {formatMMSS(totalSec)}</span>
              <span className="font-medium">남은 시간: {formatMMSS(remaining)}</span>
            </div>
            {progress !== null && (
              <div className="w-full bg-gray-200 rounded-full h-1.5">
                <div
                  className={`h-1.5 rounded-full transition-all ${isSpraying ? "bg-green-500" : "bg-red-500"}`}
                  style={{ width: `${progress}%` }}
                />
              </div>
            )}
          </>
        )}
      </div>
    );
  };

  const getModeColor = (mode: MistMode) => {
    if (mode === "OFF") return { bg: "#f3f4f6", text: "#4b5563" };
    if (mode === "MANUAL") return { bg: "#dbeafe", text: "#1e40af" };
    return { bg: "#d1fae5", text: "#065f46" };
  };

  // LED 상태 컴포넌트
  const LedIndicator = ({ state, zoneId, controllerId }: { state: "spraying" | "stopped" | "idle"; zoneId: string; controllerId?: string }) => {
    const status = valveStatus[zoneId];
    // API 폴링에서 가져온 ESP32 연결 상태 사용 (즉시 반영)
    const isOnline = controllerId ? esp32Status[controllerId] === true : (status?.online ?? false);
    const valveState = status?.valveState ?? "UNKNOWN";

    // ESP32 상태가 있으면 그것을 우선 사용
    const actualState = valveState === "OPEN" ? "spraying" : valveState === "CLOSE" ? "stopped" : state;

    if (actualState === "spraying") {
      return (
        <div className="flex items-center gap-2 p-3 bg-green-100 rounded-lg border border-green-300">
          <div className="relative">
            <div className="w-4 h-4 bg-green-500 rounded-full animate-pulse"></div>
            <div className="absolute inset-0 w-4 h-4 bg-green-400 rounded-full animate-ping opacity-75"></div>
          </div>
          <span className="text-green-700 font-semibold">작동중</span>
          {isOnline && <span className="text-xs text-green-600 ml-2">(온라인)</span>}
        </div>
      );
    } else if (actualState === "stopped") {
      return (
        <div className="flex items-center gap-2 p-3 bg-red-100 rounded-lg border border-red-300">
          <div className="w-4 h-4 bg-red-500 rounded-full"></div>
          <span className="text-red-700 font-semibold">멈춤</span>
          {isOnline && <span className="text-xs text-red-600 ml-2">(온라인)</span>}
        </div>
      );
    }
    return (
      <div className="flex items-center gap-2 p-3 bg-gray-100 rounded-lg border border-gray-300">
        <div className="w-4 h-4 bg-gray-400 rounded-full"></div>
        <span className="text-gray-600 font-medium">대기</span>
        {!isOnline && <span className="text-xs text-gray-500 ml-2">(오프라인)</span>}
      </div>
    );
  };

  // 저장된 설정값 표시 컴포넌트
  const SavedSettingsDisplay = ({ schedule, label }: { schedule: MistScheduleSettings; label: string }) => {
    if (!schedule.enabled) return null;

    return (
      <div className="text-xs bg-white/80 rounded px-2 py-1 border">
        <span className="font-medium">{label}:</span>{" "}
        {schedule.startTime || "--:--"} ~ {schedule.endTime || "--:--"},{" "}
        정지 {schedule.stopDurationSeconds ?? 0}초 → 작동 {schedule.sprayDurationSeconds ?? 0}초
      </div>
    );
  };

  return (
    <div className="bg-gray-50 min-h-full">
      <div className="p-2">
        {/* 컴팩트 헤더 */}
        <div className="flex items-center justify-between bg-white rounded-lg px-3 py-2 mb-2 shadow-sm">
          <span className="text-sm font-bold text-gray-800">💧 분무수경</span>
          <div className="flex items-center gap-2">
            <div className={`w-2.5 h-2.5 rounded-full ${mqttConnected ? "bg-green-500 animate-pulse" : "bg-red-500"}`}></div>
            <span className="text-xs text-gray-600">MQTT</span>
          </div>
        </div>

        {zones.map((zone) => {
          const modeColor = getModeColor(zone.mode);
          const sprayState = manualSprayState[zone.id] || "idle";
          const isOnline = zone.controllerId ? esp32Status[zone.controllerId] === true : false;

          return (
            <div key={zone.id} className="bg-white rounded-lg shadow-sm mb-2 overflow-hidden">
              {/* 컴팩트 Zone 헤더 */}
              <div className="flex items-center justify-between px-3 py-2 bg-farm-500">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-bold text-gray-900">{zone.name}</span>
                  {zone.controllerId && (
                    <span className={`w-2 h-2 rounded-full ${isOnline ? 'bg-green-400 animate-pulse' : 'bg-red-400'}`}></span>
                  )}
                </div>
                <div className="flex items-center gap-1.5">
                  {zone.isRunning && <span className="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">작동중</span>}
                  <span className="text-xs px-1.5 py-0.5 rounded" style={{ background: modeColor.bg, color: modeColor.text }}>{zone.mode}</span>
                </div>
              </div>

              <div className="p-3">

              {/* 모드 선택 - 컴팩트 버튼 그룹 */}
              <div className="flex gap-1 mb-3">
                {(["OFF", "MANUAL", "AUTO"] as MistMode[]).map((mode) => (
                  <button
                    key={mode}
                    onClick={() => updateZone(zone.id, { mode })}
                    className={`flex-1 py-2 text-xs font-bold rounded transition-all ${
                      zone.mode === mode
                        ? "bg-farm-500 text-white"
                        : "bg-gray-100 text-gray-600 active:bg-gray-200"
                    }`}
                  >
                    {mode}
                  </button>
                ))}
              </div>

              {/* MANUAL 모드: 컴팩트 버튼 */}
              {zone.mode === "MANUAL" && (
                <div className="space-y-2">
                  <LedIndicator state={sprayState} zoneId={zone.id} controllerId={zone.controllerId} />
                  <ZoneTimer zoneId={zone.id} sprayState={sprayState} />
                  <div className="flex gap-2">
                    <button
                      onClick={() => handleManualSpray(zone)}
                      disabled={!zone.controllerId}
                      className="flex-1 bg-green-500 active:bg-green-600 disabled:bg-gray-300 text-white font-bold py-3 rounded text-sm"
                    >
                      💧 분무
                    </button>
                    <button
                      onClick={() => handleManualStop(zone)}
                      disabled={!zone.controllerId}
                      className="flex-1 bg-red-500 active:bg-red-600 disabled:bg-gray-300 text-white font-bold py-3 rounded text-sm"
                    >
                      🛑 중지
                    </button>
                  </div>
                </div>
              )}

              {/* AUTO 모드: 주간/야간 분리 설정 */}
              {zone.mode === "AUTO" && (
                <div>
                  {/* 주간 설정 */}
                  <div className="mb-6 p-4 bg-yellow-50 rounded-xl border border-yellow-200">
                    <div className="flex items-center justify-between mb-3">
                      <h3 className="text-lg font-semibold text-yellow-800 m-0">☀️ 주간 설정</h3>
                      <label className="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={zone.daySchedule.enabled}
                          onChange={(e) =>
                            updateDaySchedule(zone.id, { enabled: e.target.checked })
                          }
                          className="w-4 h-4 accent-yellow-500"
                        />
                        <span className="text-sm text-yellow-700">활성화</span>
                      </label>
                    </div>

                    {zone.daySchedule.enabled && (
                      <div className="space-y-3">
                        {/* 운영 시간대 */}
                        <div className="grid grid-cols-2 gap-3">
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                              시작 시간
                            </label>
                            <input
                              type="time"
                              value={zone.daySchedule.startTime}
                              onChange={(e) =>
                                updateDaySchedule(zone.id, { startTime: e.target.value })
                              }
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                              종료 시간
                            </label>
                            <input
                              type="time"
                              value={zone.daySchedule.endTime}
                              onChange={(e) =>
                                updateDaySchedule(zone.id, { endTime: e.target.value })
                              }
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                            />
                          </div>
                        </div>
                        {/* 분무 주기 설정 */}
                        <div className="grid grid-cols-2 gap-3">
                          <div className="bg-green-50 p-3 rounded-lg border border-green-200">
                            <label className="block text-sm font-medium text-green-700 mb-1">
                              🟢 작동분무주기 (초)
                            </label>
                            <input
                              type="number"
                              min="1"
                              value={zone.daySchedule.sprayDurationSeconds ?? ""}
                              onChange={(e) =>
                                updateDaySchedule(zone.id, {
                                  sprayDurationSeconds: Number(e.target.value) || null,
                                })
                              }
                              placeholder="밸브 열림 시간"
                              className="w-full px-3 py-2 border border-green-300 rounded-lg text-base"
                            />
                            <p className="text-xs text-green-600 mt-1">밸브가 열려있는 시간</p>
                          </div>
                          <div className="bg-red-50 p-3 rounded-lg border border-red-200">
                            <label className="block text-sm font-medium text-red-700 mb-1">
                              🔴 정지분무주기 (초)
                            </label>
                            <input
                              type="number"
                              min="0"
                              value={zone.daySchedule.stopDurationSeconds ?? ""}
                              onChange={(e) =>
                                updateDaySchedule(zone.id, {
                                  stopDurationSeconds: Number(e.target.value) || null,
                                })
                              }
                              placeholder="밸브 닫힘 대기 시간"
                              className="w-full px-3 py-2 border border-red-300 rounded-lg text-base"
                            />
                            <p className="text-xs text-red-600 mt-1">밸브가 닫혀있는 대기 시간</p>
                          </div>
                        </div>
                        {/* 사이클 설명 */}
                        <div className="text-xs text-gray-500 bg-gray-100 p-2 rounded">
                          💡 사이클: 정지대기({zone.daySchedule.stopDurationSeconds ?? 0}초) → 분무({zone.daySchedule.sprayDurationSeconds ?? 0}초) → 반복
                        </div>
                      </div>
                    )}
                  </div>

                  {/* 야간 설정 */}
                  <div className="mb-6 p-4 bg-indigo-50 rounded-xl border border-indigo-200">
                    <div className="flex items-center justify-between mb-3">
                      <h3 className="text-lg font-semibold text-indigo-800 m-0">🌙 야간 설정</h3>
                      <label className="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={zone.nightSchedule.enabled}
                          onChange={(e) =>
                            updateNightSchedule(zone.id, { enabled: e.target.checked })
                          }
                          className="w-4 h-4 accent-indigo-500"
                        />
                        <span className="text-sm text-indigo-700">활성화</span>
                      </label>
                    </div>

                    {zone.nightSchedule.enabled && (
                      <div className="space-y-3">
                        {/* 운영 시간대 */}
                        <div className="grid grid-cols-2 gap-3">
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                              시작 시간
                            </label>
                            <input
                              type="time"
                              value={zone.nightSchedule.startTime}
                              onChange={(e) =>
                                updateNightSchedule(zone.id, { startTime: e.target.value })
                              }
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                              종료 시간
                            </label>
                            <input
                              type="time"
                              value={zone.nightSchedule.endTime}
                              onChange={(e) =>
                                updateNightSchedule(zone.id, { endTime: e.target.value })
                              }
                              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                            />
                          </div>
                        </div>
                        {/* 분무 주기 설정 */}
                        <div className="grid grid-cols-2 gap-3">
                          <div className="bg-green-50 p-3 rounded-lg border border-green-200">
                            <label className="block text-sm font-medium text-green-700 mb-1">
                              🟢 작동분무주기 (초)
                            </label>
                            <input
                              type="number"
                              min="1"
                              value={zone.nightSchedule.sprayDurationSeconds ?? ""}
                              onChange={(e) =>
                                updateNightSchedule(zone.id, {
                                  sprayDurationSeconds: Number(e.target.value) || null,
                                })
                              }
                              placeholder="밸브 열림 시간"
                              className="w-full px-3 py-2 border border-green-300 rounded-lg text-base"
                            />
                            <p className="text-xs text-green-600 mt-1">밸브가 열려있는 시간</p>
                          </div>
                          <div className="bg-red-50 p-3 rounded-lg border border-red-200">
                            <label className="block text-sm font-medium text-red-700 mb-1">
                              🔴 정지분무주기 (초)
                            </label>
                            <input
                              type="number"
                              min="0"
                              value={zone.nightSchedule.stopDurationSeconds ?? ""}
                              onChange={(e) =>
                                updateNightSchedule(zone.id, {
                                  stopDurationSeconds: Number(e.target.value) || null,
                                })
                              }
                              placeholder="밸브 닫힘 대기 시간"
                              className="w-full px-3 py-2 border border-red-300 rounded-lg text-base"
                            />
                            <p className="text-xs text-red-600 mt-1">밸브가 닫혀있는 대기 시간</p>
                          </div>
                        </div>
                        {/* 사이클 설명 */}
                        <div className="text-xs text-gray-500 bg-gray-100 p-2 rounded">
                          💡 사이클: 정지대기({zone.nightSchedule.stopDurationSeconds ?? 0}초) → 분무({zone.nightSchedule.sprayDurationSeconds ?? 0}초) → 반복
                        </div>
                      </div>
                    )}
                  </div>

                  {/* 저장된 설정값 표시 */}
                  <div className="mb-4 flex flex-wrap gap-2">
                    <SavedSettingsDisplay schedule={zone.daySchedule} label="☀️ 주간" />
                    <SavedSettingsDisplay schedule={zone.nightSchedule} label="🌙 야간" />
                  </div>

                  {/* LED 상태 표시 (AUTO 모드) */}
                  <div className="mb-2">
                    <LedIndicator
                      state={manualSprayState[zone.id] || "idle"}
                      zoneId={zone.id}
                      controllerId={zone.controllerId}
                    />
                    {(() => {
                      const currentSchedule = getCurrentSchedule(zone);
                      return (
                        <ZoneTimer
                          zoneId={zone.id}
                          sprayState={manualSprayState[zone.id] || "idle"}
                          sprayDuration={currentSchedule?.sprayDurationSeconds}
                          stopDuration={currentSchedule?.stopDurationSeconds}
                        />
                      );
                    })()}
                  </div>

                  {/* AUTO 사이클 상태 표시 */}
                  {zone.isRunning && (
                    <div className={`mb-4 p-3 rounded-lg border flex items-center gap-3 ${
                      autoCycleState[zone.id] === "spraying"
                        ? "bg-green-100 border-green-300"
                        : autoCycleState[zone.id] === "waiting"
                        ? "bg-yellow-100 border-yellow-300"
                        : "bg-gray-100 border-gray-300"
                    }`}>
                      <div className="relative">
                        <div className={`w-4 h-4 rounded-full ${
                          autoCycleState[zone.id] === "spraying"
                            ? "bg-green-500 animate-pulse"
                            : autoCycleState[zone.id] === "waiting"
                            ? "bg-yellow-500"
                            : "bg-gray-400"
                        }`}></div>
                        {autoCycleState[zone.id] === "spraying" && (
                          <div className="absolute inset-0 w-4 h-4 bg-green-400 rounded-full animate-ping opacity-75"></div>
                        )}
                      </div>
                      <span className={`font-semibold ${
                        autoCycleState[zone.id] === "spraying"
                          ? "text-green-700"
                          : autoCycleState[zone.id] === "waiting"
                          ? "text-yellow-700"
                          : "text-gray-600"
                      }`}>
                        {autoCycleState[zone.id] === "spraying"
                          ? "💧 분무 중..."
                          : autoCycleState[zone.id] === "waiting"
                          ? "⏳ 정지 대기 중..."
                          : "대기"}
                      </span>
                    </div>
                  )}

                  {/* 제어 버튼들 */}
                  <div className="grid grid-cols-3 gap-3">
                    <button
                      onClick={() => handleSaveZone(zone)}
                      className="bg-farm-500 hover:bg-farm-600 text-white font-medium px-4 py-3 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5"
                    >
                      💾 설정 저장
                    </button>
                    <button
                      onClick={() => handleStartOperation(zone)}
                      disabled={!zone.controllerId || zone.isRunning}
                      className="bg-green-500 hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium px-4 py-3 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5"
                    >
                      ▶️ 작동
                    </button>
                    <button
                      onClick={() => handleStopOperation(zone)}
                      disabled={!zone.controllerId || !zone.isRunning}
                      className="bg-red-500 hover:bg-red-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium px-4 py-3 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5"
                    >
                      ⏹️ 중지
                    </button>
                  </div>
                </div>
              )}

              {/* OFF 모드일 때 */}
              {zone.mode === "OFF" && (
                <p className="text-gray-500 text-xs text-center py-2">
                  모드를 선택하세요
                </p>
              )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
