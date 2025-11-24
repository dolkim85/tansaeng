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
    <div style={{
      display: "flex",
      flexDirection: "column",
      minHeight: "100vh",
      background: "#f3f4f6"
    }}>
      {/* 상단 헤더 */}
      <Header connectionState={connectionState} />

      {/* 탭 네비게이션 */}
      <TabNavigation activeTab={activeTab} onTabChange={setActiveTab} />

      {/* 메인 콘텐츠 - 스크롤 가능 */}
      <main style={{
        flex: "1",
        overflowY: "auto",
        paddingBottom: "20px"
      }}>
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

      {/* 푸터 */}
      <footer style={{
        background: "white",
        borderTop: "1px solid #e5e7eb",
        padding: "12px 16px",
        flexShrink: 0
      }}>
        <div style={{
          maxWidth: "1400px",
          margin: "0 auto",
          textAlign: "center",
          fontSize: "0.75rem",
          color: "#6b7280"
        }}>
          <p style={{ margin: "0 0 4px 0" }}>탄생농원 스마트팜 환경제어 시스템 v2.5</p>
          <p style={{
            fontSize: "0.7rem",
            color: "#9ca3af",
            margin: "0"
          }}>
            Powered by React + TypeScript + HiveMQ Cloud
          </p>
        </div>
      </footer>
    </div>
  );
}

export default App;
