// ========== Device Types ==========

export type DeviceType = "fan" | "vent" | "pump" | "camera" | "skylight" | "sidescreen";

export interface DeviceConfig {
  id: string;            // 내부 ID (예: "fan_front")
  name: string;          // 화면 표시 이름 (예: "내부팬 앞")
  type: DeviceType;
  esp32Id: string;       // 해당 장치를 제어하는 ESP32 노드 ID (예: "esp32-node-1")
  commandTopic: string;  // 제어용 MQTT 토픽
  stateTopic: string;    // 장치 상태 리포트용 MQTT 토픽
  extra?: {
    supportsPercentage?: boolean;    // vent 처럼 0~100% 제어 여부
    streamUrl?: string;              // camera의 RTSP/HTTP URL (Settings에서 수정 가능)
    supportsWindowControl?: boolean; // skylight의 OPEN/CLOSE/STOP 제어 여부
  };
}

// ========== Device States ==========

export interface DeviceDesiredState {
  [deviceId: string]: {
    power?: "on" | "off";
    targetPercentage?: number; // vent 목표 개방도
    lastSavedAt?: string;      // ISO string
  };
}

export interface DeviceReportedState {
  [deviceId: string]: {
    power?: "on" | "off";
    currentPercentage?: number;
    lastReportedAt?: string;
  };
}

// ========== Mist Control Types ==========

export type MistMode = "OFF" | "MANUAL" | "AUTO";

export interface MistZoneConfig {
  id: string;
  name: string;
  mode: MistMode;
  intervalMinutes: number | null;
  spraySeconds: number | null;
  startTime: string; // "HH:MM" 또는 ""
  endTime: string;   // "HH:MM" 또는 ""
  allowNightOperation: boolean;
}

// ========== Sensor Data Types ==========

export interface SensorSnapshot {
  timestamp: string;
  airTemp: number | null;
  airHumidity: number | null;
  rootTemp: number | null;
  rootHumidity: number | null;
  ec: number | null;
  ph: number | null;
  tankLevel: number | null;
  co2: number | null;
  ppfd: number | null;
}

// ========== MQTT Connection States ==========

export type MqttConnectionState = "connecting" | "connected" | "disconnected" | "error";

// ========== Camera Types ==========

export interface CameraConfig {
  id: string;
  name: string;
  streamUrl: string;
  relatedEsp32?: string;
  enabled: boolean;
}

// ========== Farm Settings ==========

export interface FarmSettings {
  farmName: string;
  adminName: string;
  notes: string;
}

// ========== ESP32 Controller Registry ==========

export interface ESP32Controller {
  id: string;           // 예: "esp32-in-fan-front"
  name: string;         // 화면 표시 이름
  controllerId: string; // 예: "ctlr-0001"
  statusTopic: string;  // 연결 상태 확인용 토픽
  category?: "skylight" | "vent"; // 장치 카테고리 (천창/측창 구분)
}
