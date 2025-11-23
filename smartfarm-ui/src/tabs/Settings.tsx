import type { FarmSettings } from "../types";
import { DEVICES } from "../config/devices";

interface SettingsProps {
  farmSettings: FarmSettings;
  setFarmSettings: React.Dispatch<React.SetStateAction<FarmSettings>>;
}

export default function Settings({ farmSettings, setFarmSettings }: SettingsProps) {
  const mqttHost = import.meta.env.VITE_MQTT_HOST || "미설정";
  const mqttPort = import.meta.env.VITE_MQTT_WS_PORT || "미설정";
  const mqttUsername = import.meta.env.VITE_MQTT_USERNAME || "미설정";

  return (
    <div className="container mx-auto px-4 py-6 space-y-6">
      <div className="bg-gradient-to-r from-emerald-500 to-green-600 rounded-2xl px-6 py-4">
        <h1 className="text-white font-bold text-2xl">⚙️ 설정</h1>
        <p className="text-white/80 text-sm mt-1">
          MQTT 설정, 디바이스 레지스트리, 농장 기본 정보를 관리합니다
        </p>
      </div>

      {/* MQTT 설정 요약 */}
      <div className="bg-white rounded-2xl shadow-md p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">
          MQTT 연결 설정 (읽기 전용)
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
        <p className="text-sm text-gray-500 mt-4">
          MQTT 설정을 변경하려면 .env 파일을 수정하세요.
        </p>
      </div>

      {/* 디바이스 레지스트리 */}
      <div className="bg-white rounded-2xl shadow-md p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">
          디바이스 레지스트리
        </h2>
        <p className="text-sm text-gray-600 mb-4">
          총 {DEVICES.length}개 장치가 등록되어 있습니다.
        </p>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
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
              {DEVICES.map((device, index) => (
                <tr
                  key={device.id}
                  className={index % 2 === 0 ? "bg-white" : "bg-gray-50"}
                >
                  <td className="px-4 py-2 text-gray-700">{device.id}</td>
                  <td className="px-4 py-2 text-gray-700">{device.name}</td>
                  <td className="px-4 py-2">
                    <span
                      className={`
                      px-2 py-1 rounded text-xs font-medium
                      ${device.type === "fan" ? "bg-blue-100 text-blue-700" : ""}
                      ${device.type === "vent" ? "bg-green-100 text-green-700" : ""}
                      ${device.type === "pump" ? "bg-purple-100 text-purple-700" : ""}
                      ${device.type === "camera" ? "bg-orange-100 text-orange-700" : ""}
                    `}
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
              ))}
            </tbody>
          </table>
        </div>
        <p className="text-sm text-gray-500 mt-4">
          디바이스를 추가/수정하려면 src/config/devices.ts 파일을 편집하세요.
        </p>
      </div>

      {/* 농장 기본 정보 */}
      <div className="bg-white rounded-2xl shadow-md p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">
          농장 기본 정보
        </h2>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              농장 이름
            </label>
            <input
              type="text"
              value={farmSettings.farmName}
              onChange={(e) =>
                setFarmSettings({ ...farmSettings, farmName: e.target.value })
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              관리자 이름
            </label>
            <input
              type="text"
              value={farmSettings.adminName}
              onChange={(e) =>
                setFarmSettings({ ...farmSettings, adminName: e.target.value })
              }
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              비고
            </label>
            <textarea
              value={farmSettings.notes}
              onChange={(e) =>
                setFarmSettings({ ...farmSettings, notes: e.target.value })
              }
              rows={4}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
            />
          </div>
          <button className="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
            저장
          </button>
        </div>
      </div>
    </div>
  );
}
