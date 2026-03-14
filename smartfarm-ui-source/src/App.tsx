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
  const [activeTab, setActiveTab] = useState("dashboard");
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

  // MQTT 브로드캐스트 수신 - 다른 기기에서 설정 변경 시 즉시 동기화
  useEffect(() => {
    const client = getMqttClient();
    const MIST_TOPIC = "tansaeng/settings/mist_sync";
    const DEVICE_TOPIC = "tansaeng/settings/device_sync";

    const handleMessage = (topic: string, message: Buffer) => {
      try {
        if (topic === MIST_TOPIC) {
          // 서버에서 오는 형식: { zone_a: {...}, zone_b: {...}, ... }
          const serverZones: Record<string, Record<string, unknown>> = JSON.parse(message.toString());
          setMistZones((prev) =>
            prev.map((zone) => {
              const s = serverZones[zone.id];
              if (!s) return zone;
              return {
                ...zone,
                name: (s.name as string) ?? zone.name,
                mode: (s.mode as typeof zone.mode) ?? zone.mode,
                isRunning: typeof s.isRunning === "boolean" ? s.isRunning : zone.isRunning,
                daySchedule: s.daySchedule ? { ...zone.daySchedule, ...(s.daySchedule as object) } : zone.daySchedule,
                nightSchedule: s.nightSchedule ? { ...zone.nightSchedule, ...(s.nightSchedule as object) } : zone.nightSchedule,
              };
            })
          );
        } else if (topic === DEVICE_TOPIC) {
          // 서버에서 오는 형식: { fan1: { power: "on" }, fan2: { power: "off" }, ... }
          const serverDevices: Record<string, { power?: string }> = JSON.parse(message.toString());
          setDeviceState((prev) => {
            const next = { ...prev };
            Object.entries(serverDevices).forEach(([deviceId, info]) => {
              if (info.power) {
                next[deviceId] = {
                  ...prev[deviceId],
                  power: info.power as "on" | "off",
                };
              }
            });
            return next;
          });
        }
      } catch (e) {
        console.error("[MQTT Sync] 파싱 실패:", e);
      }
    };

    client.on("message", handleMessage);
    client.subscribe(MIST_TOPIC, (err) => {
      if (err) console.error("[MQTT Sync] mist 구독 실패:", err);
    });
    client.subscribe(DEVICE_TOPIC, (err) => {
      if (err) console.error("[MQTT Sync] device 구독 실패:", err);
    });

    return () => {
      client.off("message", handleMessage);
    };
  }, [setMistZones, setDeviceState]);

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
          {activeTab === "dashboard" && <Dashboard mistZones={mistZones} />}
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
