import type { MistZoneConfig, MistMode } from "../types";
import { publishCommand } from "../mqtt/mqttClient";

interface MistControlProps {
  zones: MistZoneConfig[];
  setZones: React.Dispatch<React.SetStateAction<MistZoneConfig[]>>;
}

export default function MistControl({ zones, setZones }: MistControlProps) {
  const updateZone = (zoneId: string, updates: Partial<MistZoneConfig>) => {
    setZones((prev) =>
      prev.map((zone) =>
        zone.id === zoneId ? { ...zone, ...updates } : zone
      )
    );
  };

  const handleSaveZone = (zone: MistZoneConfig) => {
    // 검증
    if (zone.mode === "AUTO") {
      if (!zone.intervalMinutes || !zone.spraySeconds) {
        alert("AUTO 모드에서는 분무 주기와 분무 시간을 입력해야 합니다.");
        return;
      }
      if (zone.startTime && zone.endTime && zone.startTime >= zone.endTime) {
        alert("종료 시간은 시작 시간보다 늦어야 합니다.");
        return;
      }
    }

    // MQTT 명령 발행
    publishCommand(`tansaeng/mist/${zone.id}/config`, {
      mode: zone.mode,
      intervalMinutes: zone.intervalMinutes,
      spraySeconds: zone.spraySeconds,
      startTime: zone.startTime,
      endTime: zone.endTime,
      allowNightOperation: zone.allowNightOperation,
    });

    alert(`${zone.name} 설정이 저장되었습니다.`);
  };

  const handleManualSpray = (zone: MistZoneConfig) => {
    publishCommand(`tansaeng/mist/${zone.id}/manual`, { action: "spray" });
    alert(`${zone.name} 즉시 분무 명령을 전송했습니다.`);
  };

  const getModeColor = (mode: MistMode) => {
    if (mode === "OFF") return { bg: "#f3f4f6", text: "#4b5563" };
    if (mode === "MANUAL") return { bg: "#dbeafe", text: "#1e40af" };
    return { bg: "#d1fae5", text: "#065f46" };
  };

  return (
    <div className="bg-gray-50">
      <div className="max-w-7xl mx-auto px-4">
        <div className="bg-gradient-to-r from-farm-500 to-farm-600 rounded-2xl px-6 py-4 mb-6">
          <h1 className="text-white font-bold text-2xl m-0">💧 분무수경 설정</h1>
          <p className="text-white/80 text-sm mt-1 m-0">각 Zone별 분무 인터벌 및 운전 시간대를 설정합니다</p>
        </div>

        {zones.map((zone) => {
          const modeColor = getModeColor(zone.mode);
          return (
            <div key={zone.id} className="bg-white rounded-2xl shadow-card hover:shadow-card-hover transition-all duration-200 p-6 mb-6">
              {/* 상단: Zone 이름 + 현재 모드 */}
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-xl font-semibold text-gray-800 m-0">{zone.name}</h2>
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

              {/* MANUAL 모드: 즉시 분무 버튼 */}
              {zone.mode === "MANUAL" && (
                <div className="mb-4">
                  <button
                    onClick={() => handleManualSpray(zone)}
                    className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-3 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5"
                  >
                    즉시 분무 실행
                  </button>
                </div>
              )}

              {/* AUTO 모드: 설정 폼 */}
              {zone.mode === "AUTO" && (
                <div>
                  <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4 mb-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        분무 주기 (분)
                      </label>
                      <input
                        type="number"
                        min="1"
                        value={zone.intervalMinutes ?? ""}
                        onChange={(e) =>
                          updateZone(zone.id, {
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
                        value={zone.spraySeconds ?? ""}
                        onChange={(e) =>
                          updateZone(zone.id, {
                            spraySeconds: Number(e.target.value) || null,
                          })
                        }
                        placeholder="예: 10"
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4 mb-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        시작 시간
                      </label>
                      <input
                        type="time"
                        value={zone.startTime}
                        onChange={(e) =>
                          updateZone(zone.id, { startTime: e.target.value })
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
                        value={zone.endTime}
                        onChange={(e) =>
                          updateZone(zone.id, { endTime: e.target.value })
                        }
                        className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
                      />
                    </div>
                  </div>

                  <div className="mb-4">
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={zone.allowNightOperation}
                        onChange={(e) =>
                          updateZone(zone.id, {
                            allowNightOperation: e.target.checked,
                          })
                        }
                        className="w-4 h-4 accent-farm-500"
                      />
                      <span className="text-gray-700">야간 운전 허용</span>
                    </label>
                  </div>

                  <button
                    onClick={() => handleSaveZone(zone)}
                    className="w-full bg-farm-500 hover:bg-farm-600 text-white font-medium px-4 py-3 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5"
                  >
                    설정 저장
                  </button>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
