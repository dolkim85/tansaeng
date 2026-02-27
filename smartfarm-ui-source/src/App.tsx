import { useEffect, useState } from "react";
import Header from "./components/Header";
import TabNavigation from "./components/TabNavigation";
import DevicesControl from "./tabs/DevicesControl";
import MistControl from "./tabs/MistControl";
import Environment from "./tabs/Environment";
import Cameras from "./tabs/Cameras";
import Dashboard from "./tabs/Dashboard";
import MistLogs from "./tabs/MistLogs";
import Settings from "./tabs/Settings";
import ErrorBoundary from "./components/ErrorBoundary";
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
    <div className="flex flex-col h-screen bg-gray-100">
      {/* 상단 헤더 */}
      <Header connectionState={connectionState} />

      {/* 탭 네비게이션 */}
      <TabNavigation activeTab={activeTab} onTabChange={setActiveTab} />

      {/* 메인 콘텐츠 - 스크롤 가능 */}
      <main className="flex-1 overflow-y-auto overflow-x-hidden pb-5 min-h-0">
        {/* key={activeTab}: 탭 전환 시 에러 상태 자동 리셋 */}
        <ErrorBoundary key={activeTab}>
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
          {activeTab === "dashboard" && <Dashboard />}
          {activeTab === "mistlogs" && <MistLogs />}
          {activeTab === "settings" && (
            <Settings
              farmSettings={farmSettings}
              setFarmSettings={setFarmSettings}
            />
          )}
        </ErrorBoundary>
      </main>

      {/* 푸터 */}
      <footer className="bg-white border-t border-gray-200 px-4 py-3 flex-shrink-0">
        <div className="max-w-screen-2xl mx-auto text-center text-xs text-gray-500">
          <p className="mb-1">탄생농원 스마트팜 환경제어 시스템 v2.5</p>
          <p className="text-2xs text-gray-400">
            Powered by React + TypeScript + HiveMQ Cloud
          </p>
        </div>
      </footer>
    </div>
  );
}

export default App;
