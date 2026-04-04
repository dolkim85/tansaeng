import { useState } from "react";
import type { FarmSettings } from "../types";
import { DEVICES } from "../config/devices";
import { saveFarmSettings } from "../api/deviceControl";

interface SettingsProps {
  farmSettings: FarmSettings;
  setFarmSettings: React.Dispatch<React.SetStateAction<FarmSettings>>;
}

export default function Settings({ farmSettings, setFarmSettings }: SettingsProps) {
  const [saveStatus, setSaveStatus] = useState<"idle" | "saving" | "saved" | "error">("idle");

  const handleSave = async () => {
    setSaveStatus("saving");
    const result = await saveFarmSettings({
      farmName: farmSettings.farmName,
      adminName: farmSettings.adminName,
      notes: farmSettings.notes,
    });
    if (result.success) {
      setSaveStatus("saved");
      setTimeout(() => setSaveStatus("idle"), 2500);
    } else {
      setSaveStatus("error");
      setTimeout(() => setSaveStatus("idle"), 3000);
    }
  };

  const mqttHost = import.meta.env.VITE_MQTT_HOST || "미설정";
  const mqttPort = import.meta.env.VITE_MQTT_WS_PORT || "미설정";
  const mqttUsername = import.meta.env.VITE_MQTT_USERNAME || "미설정";

  const getDeviceTypeColor = (type: string) => {
    if (type === "fan") return { bg: "#dbeafe", text: "#1e40af" };
    if (type === "vent") return { bg: "#d1fae5", text: "#065f46" };
    if (type === "pump") return { bg: "#f3e8ff", text: "#6b21a8" };
    if (type === "camera") return { bg: "#fed7aa", text: "#c2410c" };
    return { bg: "#f3f4f6", text: "#4b5563" };
  };

  return (
    <div className="bg-gray-50">
      <div className="max-w-7xl mx-auto px-4">
        <div className="bg-gradient-to-r from-farm-500 to-farm-600 rounded-2xl px-6 py-4 mb-6">
          <h1 className="text-gray-900 font-bold text-2xl m-0">⚙️ 설정</h1>
          <p className="text-white/80 text-sm mt-1 m-0">
            MQTT 설정, 디바이스 레지스트리, 농장 기본 정보를 관리합니다
          </p>
        </div>

        {/* MQTT 설정 요약 */}
        <div className="bg-white rounded-2xl shadow-card p-6 mb-6">
          <h2 className="text-lg font-semibold text-gray-800 mb-4">
            MQTT 연결 설정 (읽기 전용)
          </h2>
          <div className="grid grid-cols-[repeat(auto-fit,minmax(250px,1fr))] gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-600 mb-1">
                HiveMQ Cloud Host
              </label>
              <div className="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                {mqttHost}
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-600 mb-1">
                WebSocket Port
              </label>
              <div className="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                {mqttPort}
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-600 mb-1">
                Username
              </label>
              <div className="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                {mqttUsername}
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-600 mb-1">
                Password
              </label>
              <div className="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-700">
                ●●●●●●●●
              </div>
            </div>
          </div>
          <p className="text-sm text-gray-500 mt-4 mb-0">
            MQTT 설정을 변경하려면 .env 파일을 수정하세요.
          </p>
        </div>

        {/* 디바이스 레지스트리 */}
        <div className="bg-white rounded-2xl shadow-card p-6 mb-6">
          <h2 className="text-lg font-semibold text-gray-800 mb-4">
            디바이스 레지스트리
          </h2>
          <p className="text-sm text-gray-600 mb-4">
            총 {DEVICES.length}개 장치가 등록되어 있습니다.
          </p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm border-collapse">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-2 text-left text-gray-600 font-medium">
                    ID
                  </th>
                  <th className="px-4 py-2 text-left text-gray-600 font-medium">
                    이름
                  </th>
                  <th className="px-4 py-2 text-left text-gray-600 font-medium">
                    타입
                  </th>
                  <th className="px-4 py-2 text-left text-gray-600 font-medium">
                    ESP32 노드
                  </th>
                  <th className="px-4 py-2 text-left text-gray-600 font-medium">
                    Command Topic
                  </th>
                </tr>
              </thead>
              <tbody>
                {DEVICES.map((device, index) => {
                  const typeColor = getDeviceTypeColor(device.type);
                  return (
                    <tr
                      key={device.id}
                      className={index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}
                    >
                      <td className="px-4 py-2 text-gray-700">{device.id}</td>
                      <td className="px-4 py-2 text-gray-700">{device.name}</td>
                      <td className="px-4 py-2">
                        <span
                          className="px-2 py-0.5 rounded text-xs font-medium"
                          style={{
                            background: typeColor.bg,
                            color: typeColor.text
                          }}
                        >
                          {device.type}
                        </span>
                      </td>
                      <td className="px-4 py-2 text-gray-600 text-xs">
                        {device.esp32Id}
                      </td>
                      <td className="px-4 py-2 text-gray-500 text-xs font-mono">
                        {device.commandTopic}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p className="text-sm text-gray-500 mt-4 mb-0">
            디바이스를 추가/수정하려면 src/config/devices.ts 파일을 편집하세요.
          </p>
        </div>

        {/* 농장 기본 정보 */}
        <div className="bg-white rounded-2xl shadow-card p-6 mb-6">
          <h2 className="text-lg font-semibold text-gray-800 mb-4">
            농장 기본 정보
          </h2>
          <div>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                농장 이름
              </label>
              <input
                type="text"
                value={farmSettings.farmName}
                onChange={(e) =>
                  setFarmSettings({ ...farmSettings, farmName: e.target.value })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
              />
            </div>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                관리자 이름
              </label>
              <input
                type="text"
                value={farmSettings.adminName}
                onChange={(e) =>
                  setFarmSettings({ ...farmSettings, adminName: e.target.value })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
              />
            </div>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                비고
              </label>
              <textarea
                value={farmSettings.notes}
                onChange={(e) =>
                  setFarmSettings({ ...farmSettings, notes: e.target.value })
                }
                rows={4}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base resize-vertical"
              />
            </div>
            <button
              onClick={handleSave}
              disabled={saveStatus === "saving"}
              className={`w-full font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-70 ${
                saveStatus === "saved"
                  ? "bg-green-500 text-white"
                  : saveStatus === "error"
                  ? "bg-red-500 text-white"
                  : "bg-farm-500 hover:bg-farm-600 text-gray-900"
              }`}
            >
              {saveStatus === "saving"
                ? "저장 중..."
                : saveStatus === "saved"
                ? "저장됨 ✓"
                : saveStatus === "error"
                ? "저장 실패 — 다시 시도"
                : "저장"}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
