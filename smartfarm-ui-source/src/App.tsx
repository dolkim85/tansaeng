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
    <div className="flex flex-col h-screen bg-gray-100">
      {/* 상단 헤더 */}
      <Header connectionState={connectionState} />

      {/* 메인 콘텐츠 - 스크롤 가능, 하단 탭바 공간 확보 */}
      <main className="flex-1 overflow-y-auto overflow-x-hidden pb-20 min-h-0">
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

      {/* 하단 탭 네비게이션 (고정) */}
      <TabNavigation activeTab={activeTab} onTabChange={setActiveTab} />
    </div>
  );
}

export default App;
