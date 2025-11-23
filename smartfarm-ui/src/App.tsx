import { useEffect, useState } from "react";
import Header from "./components/Header";
import TabNavigation from "./components/TabNavigation";
import DevicesControl from "./tabs/DevicesControl";
import MistControl from "./tabs/MistControl";
import Environment from "./tabs/Environment";
import Cameras from "./tabs/Cameras";
import Settings from "./tabs/Settings";
import { getMqttClient } from "./mqtt/mqttClient";
import {
  usePersistedDeviceState,
  usePersistedMistZones,
  usePersistedCameras,
  usePersistedFarmSettings,
} from "./store/usePersistedStore";
import type { MqttConnectionState } from "./types";

function App() {
  const [activeTab, setActiveTab] = useState("devices");
  const [connectionState, setConnectionState] = useState<MqttConnectionState>("connecting");

  // localStorage 기반 상태 관리
  const [deviceState, setDeviceState] = usePersistedDeviceState();
  const [mistZones, setMistZones] = usePersistedMistZones();
  const [cameras, setCameras] = usePersistedCameras();
  const [farmSettings, setFarmSettings] = usePersistedFarmSettings();

  // MQTT 연결
  useEffect(() => {
    const client = getMqttClient();

    client.on("connect", () => {
      setConnectionState("connected");
    });

    client.on("error", () => {
      setConnectionState("error");
    });

    client.on("reconnect", () => {
      setConnectionState("connecting");
    });

    client.on("offline", () => {
      setConnectionState("disconnected");
    });

    // 정리
    return () => {
      // MQTT 클라이언트는 싱글톤이므로 여기서 종료하지 않음
    };
  }, []);

  return (
    <div className="min-h-screen bg-gray-100">
      {/* 상단 헤더 */}
      <Header connectionState={connectionState} />

      {/* 탭 네비게이션 */}
      <TabNavigation activeTab={activeTab} onTabChange={setActiveTab} />

      {/* 메인 콘텐츠 */}
      <main className="py-6">
        {activeTab === "devices" && (
          <DevicesControl
            deviceState={deviceState}
            setDeviceState={setDeviceState}
          />
        )}
        {activeTab === "mist" && (
          <MistControl zones={mistZones} setZones={setMistZones} />
        )}
        {activeTab === "environment" && <Environment />}
        {activeTab === "cameras" && (
          <Cameras cameras={cameras} setCameras={setCameras} />
        )}
        {activeTab === "settings" && (
          <Settings
            farmSettings={farmSettings}
            setFarmSettings={setFarmSettings}
          />
        )}
      </main>

      {/* 푸터 (선택사항) */}
      <footer className="bg-white border-t border-gray-200 py-4 mt-12">
        <div className="container mx-auto px-4 text-center text-sm text-gray-600">
          <p>탄생농원 스마트팜 환경제어 시스템 v1.0</p>
          <p className="text-xs text-gray-500 mt-1">
            Powered by React + TypeScript + HiveMQ Cloud
          </p>
        </div>
      </footer>
    </div>
  );
}

export default App;
