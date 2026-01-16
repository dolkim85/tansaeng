import { useEffect, useState } from "react";
import type { MistZoneConfig, MistMode, MistScheduleSettings } from "../types";
import { publishCommand, getMqttClient } from "../mqtt/mqttClient";

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

export default function MistControl({ zones, setZones }: MistControlProps) {
  // ESP32 밸브 상태
  const [valveStatus, setValveStatus] = useState<ValveStatus>({});

  // 수동 분무 상태 (UI 표시용)
  const [manualSprayState, setManualSprayState] = useState<{[zoneId: string]: "spraying" | "stopped" | "idle"}>({});

  // MQTT 구독 - ESP32 상태 수신
  useEffect(() => {
    const client = getMqttClient();

    const handleMessage = (topic: string, message: Buffer) => {
      const msg = message.toString();

      // Zone A (ctrl-0004) 상태 처리
      if (topic === "tansaeng/ctlr-0004/valve1/state") {
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

  const updateZone = (zoneId: string, updates: Partial<MistZoneConfig>) => {
    setZones((prev) =>
      prev.map((zone) =>
        zone.id === zoneId ? { ...zone, ...updates } : zone
      )
    );
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
  const handleSaveZone = (zone: MistZoneConfig) => {
    if (zone.mode === "AUTO") {
      if (zone.daySchedule.enabled) {
        if (!zone.daySchedule.intervalMinutes || !zone.daySchedule.spraySeconds) {
          alert("주간 모드가 활성화되어 있습니다. 분무 주기와 분무 시간을 입력해야 합니다.");
          return;
        }
      }
      if (zone.nightSchedule.enabled) {
        if (!zone.nightSchedule.intervalMinutes || !zone.nightSchedule.spraySeconds) {
          alert("야간 모드가 활성화되어 있습니다. 분무 주기와 분무 시간을 입력해야 합니다.");
          return;
        }
      }
      if (!zone.daySchedule.enabled && !zone.nightSchedule.enabled) {
        alert("AUTO 모드에서는 주간 또는 야간 중 하나 이상을 활성화해야 합니다.");
        return;
      }
    }

    // 컨트롤러가 연결되어 있으면 MQTT 명령 발행
    if (zone.controllerId) {
      publishCommand(`tansaeng/mist/${zone.id}/config`, {
        mode: zone.mode,
        controllerId: zone.controllerId,
        daySchedule: zone.daySchedule,
        nightSchedule: zone.nightSchedule,
      });
    }

    alert(`${zone.name} 설정이 저장되었습니다.`);
  };

  // 시스템 작동 시작
  const handleStartOperation = (zone: MistZoneConfig) => {
    if (!zone.controllerId) {
      alert("컨트롤러가 연결되어 있지 않습니다.");
      return;
    }

    if (zone.mode === "OFF") {
      alert("먼저 운전 모드를 MANUAL 또는 AUTO로 설정해주세요.");
      return;
    }

    publishCommand(`tansaeng/mist/${zone.id}/control`, {
      action: "start",
      controllerId: zone.controllerId,
    });

    updateZone(zone.id, { isRunning: true });
    alert(`${zone.name} 작동을 시작했습니다.`);
  };

  // 시스템 작동 중지
  const handleStopOperation = (zone: MistZoneConfig) => {
    if (!zone.controllerId) {
      alert("컨트롤러가 연결되어 있지 않습니다.");
      return;
    }

    // ESP32에 CLOSE 명령 전송
    const cmdTopic = getValveCmdTopic(zone.controllerId);
    publishCommand(cmdTopic, { power: "off" });

    publishCommand(`tansaeng/mist/${zone.id}/control`, {
      action: "stop",
      controllerId: zone.controllerId,
    });

    updateZone(zone.id, { isRunning: false });
    alert(`${zone.name} 작동을 중지했습니다.`);
  };

  // 수동 분무 실행 - ESP32에 직접 명령
  const handleManualSpray = (zone: MistZoneConfig) => {
    if (!zone.controllerId) {
      alert("컨트롤러가 연결되어 있지 않습니다.");
      return;
    }

    // ESP32 밸브 열기 명령
    const cmdTopic = getValveCmdTopic(zone.controllerId);
    publishCommand(cmdTopic, { power: "on" });

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

    // UI 상태 업데이트
    setManualSprayState(prev => ({ ...prev, [zone.id]: "stopped" }));

    console.log(`[MQTT] Published to ${cmdTopic}: OFF`);
  };

  const getModeColor = (mode: MistMode) => {
    if (mode === "OFF") return { bg: "#f3f4f6", text: "#4b5563" };
    if (mode === "MANUAL") return { bg: "#dbeafe", text: "#1e40af" };
    return { bg: "#d1fae5", text: "#065f46" };
  };

  const getRunningStatusColor = (isRunning: boolean) => {
    return isRunning
      ? { bg: "#dcfce7", text: "#16a34a", border: "#22c55e" }
      : { bg: "#f3f4f6", text: "#6b7280", border: "#d1d5db" };
  };

  // LED 상태 컴포넌트
  const LedIndicator = ({ state, zoneId }: { state: "spraying" | "stopped" | "idle"; zoneId: string }) => {
    const status = valveStatus[zoneId];
    const isOnline = status?.online ?? false;
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
        주기 {schedule.intervalMinutes ?? "-"}분, 분무 {schedule.spraySeconds ?? "-"}초
      </div>
    );
  };

  return (
    <div className="bg-gray-50">
      <div className="max-w-7xl mx-auto px-4">
        <div className="bg-gradient-to-r from-farm-500 to-farm-600 rounded-2xl px-6 py-4 mb-6">
          <h1 className="text-gray-900 font-bold text-2xl m-0">💧 분무수경 설정</h1>
          <p className="text-white/80 text-sm mt-1 m-0">각 Zone별 분무 인터벌 및 운전 시간대를 설정합니다</p>
        </div>

        {zones.map((zone) => {
          const modeColor = getModeColor(zone.mode);
          const runningStatus = getRunningStatusColor(zone.isRunning);
          const sprayState = manualSprayState[zone.id] || "idle";
          const status = valveStatus[zone.id];

          return (
            <div key={zone.id} className="bg-white rounded-2xl shadow-card hover:shadow-card-hover transition-all duration-200 p-6 mb-6">
              {/* 상단: Zone 이름 + 상태 배지들 */}
              <div className="flex items-center justify-between mb-4 flex-wrap gap-2">
                <div className="flex items-center gap-3">
                  <h2 className="text-xl font-semibold text-gray-800 m-0">{zone.name}</h2>
                  {zone.controllerId ? (
                    <span className={`text-xs px-2 py-1 rounded-full ${status?.online ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700'}`}>
                      {zone.controllerId} {status?.online ? '(온라인)' : ''}
                    </span>
                  ) : (
                    <span className="text-xs px-2 py-1 bg-gray-100 text-gray-500 rounded-full">
                      미연결
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-2">
                  {/* 작동 상태 */}
                  <span
                    className="px-3 py-1 rounded-full text-sm font-medium border"
                    style={{
                      background: runningStatus.bg,
                      color: runningStatus.text,
                      borderColor: runningStatus.border
                    }}
                  >
                    {zone.isRunning ? "🟢 작동중" : "⚪ 정지"}
                  </span>
                  {/* 모드 */}
                  <span
                    className="px-3 py-1 rounded-full text-sm font-medium"
                    style={{
                      background: modeColor.bg,
                      color: modeColor.text
                    }}
                  >
                    {zone.mode}
                  </span>
                </div>
              </div>

              {/* 모드 선택 */}
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  운전 모드
                </label>
                <div className="flex gap-4">
                  {(["OFF", "MANUAL", "AUTO"] as MistMode[]).map((mode) => (
                    <label key={mode} className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="radio"
                        name={`mode-${zone.id}`}
                        checked={zone.mode === mode}
                        onChange={() => updateZone(zone.id, { mode })}
                        className="w-4 h-4 accent-farm-500"
                      />
                      <span className="text-gray-700">{mode}</span>
                    </label>
                  ))}
                </div>
              </div>

              {/* MANUAL 모드: 즉시 분무 / 분무 중지 버튼 + LED 상태 */}
              {zone.mode === "MANUAL" && (
                <div className="mb-4 space-y-4">
                  {/* LED 상태 표시 */}
                  <LedIndicator state={sprayState} zoneId={zone.id} />

                  <div className="grid grid-cols-2 gap-3">
                    <button
                      onClick={() => handleManualSpray(zone)}
                      disabled={!zone.controllerId}
                      className="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium px-4 py-3 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5 flex items-center justify-center gap-2"
                    >
                      <span className="text-lg">💧</span> 즉시 분무 실행
                    </button>
                    <button
                      onClick={() => handleManualStop(zone)}
                      disabled={!zone.controllerId}
                      className="bg-red-500 hover:bg-red-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-medium px-4 py-3 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5 flex items-center justify-center gap-2"
                    >
                      <span className="text-lg">🛑</span> 즉시 분무 중지
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
                      <div className="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3 mb-3">
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
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            분무 주기 (분)
                          </label>
                          <input
                            type="number"
                            min="1"
                            value={zone.daySchedule.intervalMinutes ?? ""}
                            onChange={(e) =>
                              updateDaySchedule(zone.id, {
                                intervalMinutes: Number(e.target.value) || null,
                              })
                            }
                            placeholder="예: 30"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            분무 시간 (초)
                          </label>
                          <input
                            type="number"
                            min="1"
                            value={zone.daySchedule.spraySeconds ?? ""}
                            onChange={(e) =>
                              updateDaySchedule(zone.id, {
                                spraySeconds: Number(e.target.value) || null,
                              })
                            }
                            placeholder="예: 10"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                          />
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
                      <div className="grid grid-cols-[repeat(auto-fit,minmax(150px,1fr))] gap-3 mb-3">
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
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            분무 주기 (분)
                          </label>
                          <input
                            type="number"
                            min="1"
                            value={zone.nightSchedule.intervalMinutes ?? ""}
                            onChange={(e) =>
                              updateNightSchedule(zone.id, {
                                intervalMinutes: Number(e.target.value) || null,
                              })
                            }
                            placeholder="예: 60"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-1">
                            분무 시간 (초)
                          </label>
                          <input
                            type="number"
                            min="1"
                            value={zone.nightSchedule.spraySeconds ?? ""}
                            onChange={(e) =>
                              updateNightSchedule(zone.id, {
                                spraySeconds: Number(e.target.value) || null,
                              })
                            }
                            placeholder="예: 5"
                            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                          />
                        </div>
                      </div>
                    )}
                  </div>

                  {/* 저장된 설정값 표시 */}
                  <div className="mb-4 flex flex-wrap gap-2">
                    <SavedSettingsDisplay schedule={zone.daySchedule} label="☀️ 주간" />
                    <SavedSettingsDisplay schedule={zone.nightSchedule} label="🌙 야간" />
                  </div>

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
                <div className="mt-4">
                  <p className="text-gray-500 text-sm mb-3">
                    운전 모드가 OFF입니다. MANUAL 또는 AUTO 모드를 선택하세요.
                  </p>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
