import { useEffect, useState, useRef } from "react";
import type { MistZoneConfig, MistMode, MistScheduleSettings } from "../types";
import { getMqttClient, isMqttConnected, onConnectionChange, subscribeToTopic } from "../mqtt/mqttClient";
import { saveDeviceSettings } from "../api/deviceControl";

interface MistControlProps {
  zones: MistZoneConfig[];
  setZones: React.Dispatch<React.SetStateAction<MistZoneConfig[]>>;
}

// Zone ID와 Controller ID 매핑
const ZONE_CONTROLLER_MAP: Record<string, string> = {
  zone_a: "ctlr-0004",
  zone_b: "ctlr-0005",
  zone_c: "ctlr-0006",
  zone_d: "ctlr-0007",
  zone_e: "ctlr-0008",
};

export default function MistControl({ zones, setZones }: MistControlProps) {
  // MQTT 연결 상태
  const [mqttConnected, setMqttConnected] = useState(isMqttConnected());

  // ESP32 밸브 상태 (valve1/state 토픽에서 수신)
  const [valveState, setValveState] = useState<Record<string, "OPEN" | "CLOSE" | "UNKNOWN">>({});

  // ESP32 온라인 상태
  const [esp32Online, setEsp32Online] = useState<Record<string, boolean>>({});

  // 밸브 상태가 마지막으로 변경된 시각 (타이머용)
  const valveChangedAt = useRef<Record<string, number>>({});

  // 타이머 표시 강제 갱신
  const [, forceUpdate] = useState(0);

  // isRunning echo 방지 (자신이 publish한 retain 무시)
  const isRunningSelfPublishRef = useRef<Record<string, boolean>>({});

  // ── MQTT 연결 상태 감시 ────────────────────────────────────────────────────
  useEffect(() => {
    getMqttClient();
    const unsub = onConnectionChange((connected) => {
      setMqttConnected(connected);
    });
    return () => unsub();
  }, []);

  // ── ESP32 valve1/state / status 구독 ─────────────────────────────────────
  useEffect(() => {
    const client = getMqttClient();

    const handleMessage = (topic: string, message: Buffer) => {
      const msg = message.toString();

      const ZONES_TOPICS: Record<string, string> = {
        "tansaeng/ctlr-0004/valve1/state": "zone_a",
        "tansaeng/ctlr-0005/valve1/state": "zone_b",
        "tansaeng/ctlr-0006/valve1/state": "zone_c",
        "tansaeng/ctlr-0007/valve1/state": "zone_d",
        "tansaeng/ctlr-0008/valve1/state": "zone_e",
      };
      const STATUS_TOPICS: Record<string, string> = {
        "tansaeng/ctlr-0004/status": "ctlr-0004",
        "tansaeng/ctlr-0005/status": "ctlr-0005",
        "tansaeng/ctlr-0006/status": "ctlr-0006",
        "tansaeng/ctlr-0007/status": "ctlr-0007",
        "tansaeng/ctlr-0008/status": "ctlr-0008",
      };

      if (topic in ZONES_TOPICS) {
        const zoneId = ZONES_TOPICS[topic];
        const newState = msg === "OPEN" ? "OPEN" : "CLOSE";
        setValveState(prev => {
          // 상태가 변경됐을 때만 타이머 리셋
          if (prev[zoneId] !== newState) {
            valveChangedAt.current[zoneId] = Date.now();
          }
          return { ...prev, [zoneId]: newState };
        });
      }

      if (topic in STATUS_TOPICS) {
        const controllerId = STATUS_TOPICS[topic];
        setEsp32Online(prev => ({ ...prev, [controllerId]: msg === "online" }));
      }
    };

    client.on("message", handleMessage);

    const topics = [
      "tansaeng/ctlr-0004/valve1/state", "tansaeng/ctlr-0004/status",
      "tansaeng/ctlr-0005/valve1/state", "tansaeng/ctlr-0005/status",
      "tansaeng/ctlr-0006/valve1/state", "tansaeng/ctlr-0006/status",
      "tansaeng/ctlr-0007/valve1/state", "tansaeng/ctlr-0007/status",
      "tansaeng/ctlr-0008/valve1/state", "tansaeng/ctlr-0008/status",
    ];
    topics.forEach(t => client.subscribe(t, () => {}));

    return () => { client.off("message", handleMessage); };
  }, []);

  // ── isRunning / schedule MQTT retain 구독 ─────────────────────────────────
  useEffect(() => {
    const zoneIds = Object.keys(ZONE_CONTROLLER_MAP);
    const unsubs = [
      ...zoneIds.map(zoneId =>
        subscribeToTopic(`tansaeng/mist-control/${zoneId}/isRunning`, (v) => {
          if (isRunningSelfPublishRef.current[zoneId]) {
            isRunningSelfPublishRef.current[zoneId] = false;
            return;
          }
          const running = v === "true";
          setZones(prev => prev.map(z => z.id === zoneId ? { ...z, isRunning: running } : z));
        })
      ),
      ...zoneIds.map(zoneId =>
        subscribeToTopic(`tansaeng/mist-control/${zoneId}/schedule`, (v) => {
          try {
            const parsed = JSON.parse(v);
            if (!parsed || typeof parsed !== "object") return;
            setZones(prev => prev.map(z =>
              z.id === zoneId
                ? {
                    ...z,
                    mode: parsed.mode ?? z.mode,
                    daySchedule: parsed.daySchedule ?? z.daySchedule,
                    nightSchedule: parsed.nightSchedule ?? z.nightSchedule,
                  }
                : z
            ));
          } catch {}
        })
      ),
    ];
    return () => unsubs.forEach(u => u());
  }, [setZones]);

  // ── ESP32 상태 API 폴링 ────────────────────────────────────────────────────
  useEffect(() => {
    const fetchStatus = async () => {
      try {
        const res = await fetch("/api/device_status.php");
        const data = await res.json();
        if (data.success) {
          const status: Record<string, boolean> = {};
          Object.entries(data.devices).forEach(([id, info]: [string, any]) => {
            status[id] = info.is_online;
          });
          setEsp32Online(status);
        }
      } catch {}
    };
    fetchStatus();
    const interval = setInterval(fetchStatus, 5000);
    return () => clearInterval(interval);
  }, []);

  // ── 1초마다 타이머 갱신 ───────────────────────────────────────────────────
  useEffect(() => {
    const interval = setInterval(() => forceUpdate(n => n + 1), 1000);
    return () => clearInterval(interval);
  }, []);

  // ── 유틸 ──────────────────────────────────────────────────────────────────
  const formatMMSS = (seconds: number): string => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
  };

  const getModeColor = (mode: MistMode) => {
    if (mode === "OFF")    return { bg: "#f3f4f6", text: "#4b5563" };
    if (mode === "MANUAL") return { bg: "#dbeafe", text: "#1e40af" };
    return { bg: "#d1fae5", text: "#065f46" };
  };

  const getCurrentSchedule = (zone: MistZoneConfig): MistScheduleSettings | null => {
    const now = new Date();
    const cur = now.getHours() * 60 + now.getMinutes();
    const parse = (t: string) => { if (!t) return 0; const [h, m] = t.split(":").map(Number); return h * 60 + m; };

    if (zone.daySchedule.enabled) {
      const s = parse(zone.daySchedule.startTime), e = parse(zone.daySchedule.endTime);
      if (s <= cur && cur < e) return zone.daySchedule;
    }
    if (zone.nightSchedule.enabled) {
      const s = parse(zone.nightSchedule.startTime), e = parse(zone.nightSchedule.endTime);
      if (s > e) { if (cur >= s || cur < e) return zone.nightSchedule; }
      else if (s <= cur && cur < e) return zone.nightSchedule;
    }
    return null;
  };

  // ── 컴포넌트 ──────────────────────────────────────────────────────────────

  // LED + 온라인 상태 표시
  const LedIndicator = ({ zoneId, controllerId }: { zoneId: string; controllerId?: string }) => {
    const state = valveState[zoneId] ?? "UNKNOWN";
    const online = controllerId ? esp32Online[controllerId] === true : false;

    if (state === "OPEN") {
      return (
        <div className="flex items-center gap-2 p-3 bg-green-100 rounded-lg border border-green-300">
          <div className="relative">
            <div className="w-4 h-4 bg-green-500 rounded-full animate-pulse"></div>
            <div className="absolute inset-0 w-4 h-4 bg-green-400 rounded-full animate-ping opacity-75"></div>
          </div>
          <span className="text-green-700 font-semibold">작동중</span>
          {online && <span className="text-xs text-green-600 ml-2">(온라인)</span>}
        </div>
      );
    }
    if (state === "CLOSE") {
      return (
        <div className="flex items-center gap-2 p-3 bg-red-100 rounded-lg border border-red-300">
          <div className="w-4 h-4 bg-red-500 rounded-full"></div>
          <span className="text-red-700 font-semibold">멈춤</span>
          {online && <span className="text-xs text-red-600 ml-2">(온라인)</span>}
        </div>
      );
    }
    return (
      <div className="flex items-center gap-2 p-3 bg-gray-100 rounded-lg border border-gray-300">
        <div className="w-4 h-4 bg-gray-400 rounded-full"></div>
        <span className="text-gray-600 font-medium">대기</span>
        {!online && <span className="text-xs text-gray-500 ml-2">(오프라인)</span>}
      </div>
    );
  };

  // 경과 시간 타이머 (ESP32 valve/state 피드백 기반)
  const ZoneTimer = ({ zoneId, stopDuration }: { zoneId: string; stopDuration?: number | null }) => {
    const state = valveState[zoneId];
    if (!state || state === "UNKNOWN") return null;
    const changedAt = valveChangedAt.current[zoneId];
    if (!changedAt) return null;

    const elapsed = Math.floor((Date.now() - changedAt) / 1000);

    if (state === "OPEN") {
      return (
        <div className="rounded-lg border px-3 py-2 mt-2 bg-green-50 border-green-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-green-700">💧 작동중</span>
            <span className="text-lg font-bold tabular-nums text-green-600">{formatMMSS(elapsed)}</span>
          </div>
        </div>
      );
    } else {
      const display = stopDuration ? Math.min(elapsed, stopDuration) : elapsed;
      return (
        <div className="rounded-lg border px-3 py-2 mt-2 bg-red-50 border-red-200">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold text-red-700">⏸ 멈춤</span>
            <span className="text-lg font-bold tabular-nums text-red-600">
              {formatMMSS(display)}
              {stopDuration && <span className="text-xs font-normal text-red-400"> / {formatMMSS(stopDuration)}</span>}
            </span>
          </div>
        </div>
      );
    }
  };

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

  // ── 핸들러 ────────────────────────────────────────────────────────────────

  const updateZone = async (zoneId: string, updates: Partial<MistZoneConfig>) => {
    setZones(prev => prev.map(z => z.id === zoneId ? { ...z, ...updates } : z));

    if ("mode" in updates) {
      const zone = zones.find(z => z.id === zoneId);
      if (zone) {
        const newMode = updates.mode ?? zone.mode;

        // 모드 변경 시 isRunning 리셋 → 데몬이 자동으로 사이클 시작하지 않도록
        if (newMode !== zone.mode && zone.isRunning) {
          setZones(prev => prev.map(z => z.id === zoneId ? { ...z, isRunning: false } : z));
          isRunningSelfPublishRef.current[zoneId] = true;
          getMqttClient().publish(`tansaeng/mist-control/${zoneId}/isRunning`, "false", { qos: 1, retain: true });
        }

        await saveDeviceSettings({
          mist_zones: {
            [zoneId]: { mode: newMode, controllerId: zone.controllerId, deviceId: "valve1", isRunning: false, daySchedule: zone.daySchedule, nightSchedule: zone.nightSchedule },
          },
        });
        getMqttClient().publish(
          `tansaeng/mist-control/${zoneId}/schedule`,
          JSON.stringify({ mode: newMode, daySchedule: zone.daySchedule, nightSchedule: zone.nightSchedule }),
          { qos: 1, retain: true }
        );
      }
    }
  };

  const updateDaySchedule = (zoneId: string, updates: Partial<MistScheduleSettings>) => {
    setZones(prev => prev.map(z => z.id === zoneId ? { ...z, daySchedule: { ...z.daySchedule, ...updates } } : z));
  };

  const updateNightSchedule = (zoneId: string, updates: Partial<MistScheduleSettings>) => {
    setZones(prev => prev.map(z => z.id === zoneId ? { ...z, nightSchedule: { ...z.nightSchedule, ...updates } } : z));
  };

  const handleSaveZone = async (zone: MistZoneConfig) => {
    if (zone.mode === "AUTO") {
      if (zone.daySchedule.enabled && !zone.daySchedule.sprayDurationSeconds) {
        alert("주간 모드: 작동분무주기(초)를 입력해야 합니다."); return;
      }
      if (zone.nightSchedule.enabled && !zone.nightSchedule.sprayDurationSeconds) {
        alert("야간 모드: 작동분무주기(초)를 입력해야 합니다."); return;
      }
      if (!zone.daySchedule.enabled && !zone.nightSchedule.enabled) {
        alert("AUTO 모드에서는 주간 또는 야간을 하나 이상 활성화해야 합니다."); return;
      }
    }

    const result = await saveDeviceSettings({
      mist_zones: {
        [zone.id]: { mode: zone.mode, controllerId: zone.controllerId, deviceId: "valve1", isRunning: zone.isRunning, daySchedule: zone.daySchedule, nightSchedule: zone.nightSchedule },
      },
    });

    if (result.success) {
      getMqttClient().publish(
        `tansaeng/mist-control/${zone.id}/schedule`,
        JSON.stringify({ mode: zone.mode, daySchedule: zone.daySchedule, nightSchedule: zone.nightSchedule }),
        { qos: 1, retain: true }
      );
      alert(`${zone.name} 설정이 저장되었습니다.`);
    } else {
      alert(`설정 저장 실패: ${result.message}`);
    }
  };

  // AUTO 작동 시작 → 서버 데몬에 위임
  const handleStartOperation = async (zone: MistZoneConfig) => {
    if (!zone.controllerId) { alert("컨트롤러가 연결되어 있지 않습니다."); return; }

    setZones(prev => prev.map(z => z.id === zone.id ? { ...z, isRunning: true } : z));
    isRunningSelfPublishRef.current[zone.id] = true;
    getMqttClient().publish(`tansaeng/mist-control/${zone.id}/isRunning`, "true", { qos: 1, retain: true });
  };

  // AUTO 작동 중지
  const handleStopOperation = async (zone: MistZoneConfig) => {
    if (!zone.controllerId) { alert("컨트롤러가 연결되어 있지 않습니다."); return; }

    // 밸브 즉시 닫기
    getMqttClient().publish(`tansaeng/${zone.controllerId}/valve1/cmd`, "CLOSE", { qos: 1 });

    setZones(prev => prev.map(z => z.id === zone.id ? { ...z, isRunning: false } : z));
    isRunningSelfPublishRef.current[zone.id] = true;
    getMqttClient().publish(`tansaeng/mist-control/${zone.id}/isRunning`, "false", { qos: 1, retain: true });

    await saveDeviceSettings({
      mist_zones: {
        [zone.id]: { mode: zone.mode, controllerId: zone.controllerId, deviceId: "valve1", isRunning: false, daySchedule: zone.daySchedule, nightSchedule: zone.nightSchedule },
      },
    });
  };

  // MANUAL 분무 시작
  const handleManualSpray = (zone: MistZoneConfig) => {
    if (!zone.controllerId) { alert("컨트롤러가 연결되어 있지 않습니다."); return; }
    getMqttClient().publish(`tansaeng/${zone.controllerId}/valve1/cmd`, "OPEN", { qos: 1 });
    console.log(`[MANUAL] OPEN → tansaeng/${zone.controllerId}/valve1/cmd`);
  };

  // MANUAL 분무 중지
  const handleManualStop = (zone: MistZoneConfig) => {
    if (!zone.controllerId) { alert("컨트롤러가 연결되어 있지 않습니다."); return; }
    getMqttClient().publish(`tansaeng/${zone.controllerId}/valve1/cmd`, "CLOSE", { qos: 1 });
    console.log(`[MANUAL] CLOSE → tansaeng/${zone.controllerId}/valve1/cmd`);
  };

  // ── 렌더링 ────────────────────────────────────────────────────────────────
  return (
    <div className="bg-gray-50 min-h-full">
      <div className="p-2">
        {/* 헤더 */}
        <div className="flex items-center justify-between bg-white rounded-lg px-3 py-2 mb-2 shadow-sm">
          <span className="text-sm font-bold text-gray-800">💧 분무수경</span>
          <div className="flex items-center gap-2">
            <div className={`w-2.5 h-2.5 rounded-full ${mqttConnected ? "bg-green-500 animate-pulse" : "bg-red-500"}`}></div>
            <span className="text-xs text-gray-600">MQTT</span>
          </div>
        </div>

        {zones.map((zone) => {
          const modeColor = getModeColor(zone.mode);
          const isOnline = zone.controllerId ? esp32Online[zone.controllerId] === true : false;
          const currentSchedule = getCurrentSchedule(zone);

          return (
            <div key={zone.id} className="bg-white rounded-lg shadow-sm mb-2 overflow-hidden">
              {/* Zone 헤더 */}
              <div className="flex items-center justify-between px-3 py-2 bg-farm-500">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-bold text-gray-900">{zone.name}</span>
                  {zone.controllerId && (
                    <span className={`w-2 h-2 rounded-full ${isOnline ? "bg-green-400 animate-pulse" : "bg-red-400"}`}></span>
                  )}
                </div>
                <div className="flex items-center gap-1.5">
                  {zone.isRunning && <span className="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">작동중</span>}
                  <span className="text-xs px-1.5 py-0.5 rounded" style={{ background: modeColor.bg, color: modeColor.text }}>{zone.mode}</span>
                </div>
              </div>

              <div className="p-3">
                {/* 모드 선택 */}
                <div className="flex gap-1 mb-3">
                  {(["OFF", "MANUAL", "AUTO"] as MistMode[]).map((mode) => (
                    <button
                      key={mode}
                      onClick={() => updateZone(zone.id, { mode })}
                      className={`flex-1 py-2 text-xs font-bold rounded transition-all ${
                        zone.mode === mode ? "bg-farm-500 text-white" : "bg-gray-100 text-gray-600 active:bg-gray-200"
                      }`}
                    >
                      {mode}
                    </button>
                  ))}
                </div>

                {/* MANUAL 모드 */}
                {zone.mode === "MANUAL" && (
                  <div className="space-y-2">
                    <LedIndicator zoneId={zone.id} controllerId={zone.controllerId} />
                    <ZoneTimer zoneId={zone.id} />
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

                {/* AUTO 모드 */}
                {zone.mode === "AUTO" && (
                  <div>
                    {/* 주간 설정 */}
                    <div className="mb-6 p-4 bg-yellow-50 rounded-xl border border-yellow-200">
                      <div className="flex items-center justify-between mb-3">
                        <h3 className="text-lg font-semibold text-yellow-800 m-0">☀️ 주간 설정</h3>
                        <label className="flex items-center gap-2 cursor-pointer">
                          <input type="checkbox" checked={zone.daySchedule.enabled}
                            onChange={(e) => updateDaySchedule(zone.id, { enabled: e.target.checked })}
                            className="w-4 h-4 accent-yellow-500" />
                          <span className="text-sm text-yellow-700">활성화</span>
                        </label>
                      </div>
                      {zone.daySchedule.enabled && (
                        <div className="space-y-3">
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-1">시작 시간</label>
                              <input type="time" value={zone.daySchedule.startTime}
                                onChange={(e) => updateDaySchedule(zone.id, { startTime: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base" />
                            </div>
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-1">종료 시간</label>
                              <input type="time" value={zone.daySchedule.endTime}
                                onChange={(e) => updateDaySchedule(zone.id, { endTime: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base" />
                            </div>
                          </div>
                          <div className="grid grid-cols-2 gap-3">
                            <div className="bg-green-50 p-3 rounded-lg border border-green-200">
                              <label className="block text-sm font-medium text-green-700 mb-1">🟢 작동분무주기 (초)</label>
                              <input type="number" min="1" value={zone.daySchedule.sprayDurationSeconds ?? ""}
                                onChange={(e) => updateDaySchedule(zone.id, { sprayDurationSeconds: Number(e.target.value) || null })}
                                placeholder="밸브 열림 시간"
                                className="w-full px-3 py-2 border border-green-300 rounded-lg text-base" />
                              <p className="text-xs text-green-600 mt-1">밸브가 열려있는 시간</p>
                            </div>
                            <div className="bg-red-50 p-3 rounded-lg border border-red-200">
                              <label className="block text-sm font-medium text-red-700 mb-1">🔴 정지분무주기 (초)</label>
                              <input type="number" min="0" value={zone.daySchedule.stopDurationSeconds ?? ""}
                                onChange={(e) => updateDaySchedule(zone.id, { stopDurationSeconds: Number(e.target.value) || null })}
                                placeholder="밸브 닫힘 대기 시간"
                                className="w-full px-3 py-2 border border-red-300 rounded-lg text-base" />
                              <p className="text-xs text-red-600 mt-1">밸브가 닫혀있는 대기 시간</p>
                            </div>
                          </div>
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
                          <input type="checkbox" checked={zone.nightSchedule.enabled}
                            onChange={(e) => updateNightSchedule(zone.id, { enabled: e.target.checked })}
                            className="w-4 h-4 accent-indigo-500" />
                          <span className="text-sm text-indigo-700">활성화</span>
                        </label>
                      </div>
                      {zone.nightSchedule.enabled && (
                        <div className="space-y-3">
                          <div className="grid grid-cols-2 gap-3">
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-1">시작 시간</label>
                              <input type="time" value={zone.nightSchedule.startTime}
                                onChange={(e) => updateNightSchedule(zone.id, { startTime: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base" />
                            </div>
                            <div>
                              <label className="block text-sm font-medium text-gray-700 mb-1">종료 시간</label>
                              <input type="time" value={zone.nightSchedule.endTime}
                                onChange={(e) => updateNightSchedule(zone.id, { endTime: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base" />
                            </div>
                          </div>
                          <div className="grid grid-cols-2 gap-3">
                            <div className="bg-green-50 p-3 rounded-lg border border-green-200">
                              <label className="block text-sm font-medium text-green-700 mb-1">🟢 작동분무주기 (초)</label>
                              <input type="number" min="1" value={zone.nightSchedule.sprayDurationSeconds ?? ""}
                                onChange={(e) => updateNightSchedule(zone.id, { sprayDurationSeconds: Number(e.target.value) || null })}
                                placeholder="밸브 열림 시간"
                                className="w-full px-3 py-2 border border-green-300 rounded-lg text-base" />
                              <p className="text-xs text-green-600 mt-1">밸브가 열려있는 시간</p>
                            </div>
                            <div className="bg-red-50 p-3 rounded-lg border border-red-200">
                              <label className="block text-sm font-medium text-red-700 mb-1">🔴 정지분무주기 (초)</label>
                              <input type="number" min="0" value={zone.nightSchedule.stopDurationSeconds ?? ""}
                                onChange={(e) => updateNightSchedule(zone.id, { stopDurationSeconds: Number(e.target.value) || null })}
                                placeholder="밸브 닫힘 대기 시간"
                                className="w-full px-3 py-2 border border-red-300 rounded-lg text-base" />
                              <p className="text-xs text-red-600 mt-1">밸브가 닫혀있는 대기 시간</p>
                            </div>
                          </div>
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

                    {/* LED 상태 + 타이머 */}
                    <div className="mb-2">
                      <LedIndicator zoneId={zone.id} controllerId={zone.controllerId} />
                      <ZoneTimer zoneId={zone.id} stopDuration={currentSchedule?.stopDurationSeconds} />
                    </div>

                    {/* 제어 버튼 */}
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

                {/* OFF 모드 */}
                {zone.mode === "OFF" && (
                  <p className="text-gray-500 text-xs text-center py-2">모드를 선택하세요</p>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
