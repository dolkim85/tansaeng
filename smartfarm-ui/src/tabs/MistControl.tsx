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

  return (
    <div className="container mx-auto px-4 py-6 space-y-6">
      <div className="bg-gradient-to-r from-emerald-500 to-green-600 rounded-2xl px-6 py-4">
        <h1 className="text-white font-bold text-2xl">💧 분무수경 설정</h1>
        <p className="text-white/80 text-sm mt-1">각 Zone별 분무 인터벌 및 운전 시간대를 설정합니다</p>
      </div>

      {zones.map((zone) => (
        <div key={zone.id} className="bg-white rounded-2xl shadow-md p-6">
          {/* 상단: Zone 이름 + 현재 모드 */}
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-xl font-semibold text-gray-800">{zone.name}</h2>
            <span
              className={`
                px-3 py-1 rounded-full text-sm font-medium
                ${zone.mode === "OFF" ? "bg-gray-100 text-gray-600" : ""}
                ${zone.mode === "MANUAL" ? "bg-blue-100 text-blue-700" : ""}
                ${zone.mode === "AUTO" ? "bg-green-100 text-green-700" : ""}
              `}
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
                    className="w-4 h-4 text-emerald-600 focus:ring-emerald-500"
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
                className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors"
              >
                즉시 분무 실행
              </button>
            </div>
          )}

          {/* AUTO 모드: 설정 폼 */}
          {zone.mode === "AUTO" && (
            <div className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
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
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
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
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                  />
                </div>
              </div>

              <div>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={zone.allowNightOperation}
                    onChange={(e) =>
                      updateZone(zone.id, {
                        allowNightOperation: e.target.checked,
                      })
                    }
                    className="w-4 h-4 text-emerald-600 focus:ring-emerald-500 rounded"
                  />
                  <span className="text-gray-700">야간 운전 허용</span>
                </label>
              </div>

              <button
                onClick={() => handleSaveZone(zone)}
                className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3 px-4 rounded-lg transition-colors"
              >
                설정 저장
              </button>
            </div>
          )}
        </div>
      ))}
    </div>
  );
}
