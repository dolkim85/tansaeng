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

// 주간/야간 스케줄 설정
export interface MistScheduleSettings {
  sprayDurationSeconds: number | null;  // 작동분무주기 - 밸브 열림 시간 (초)
  stopDurationSeconds: number | null;   // 정지분무주기 - 밸브 닫힘 대기 시간 (초)
  startTime: string;                    // 시작 시간 "HH:MM"
  endTime: string;                      // 종료 시간 "HH:MM"
  enabled: boolean;                     // 활성화 여부
}

export interface MistZoneConfig {
  id: string;
  name: string;
  mode: MistMode;
  controllerId: string;            // 연결된 ESP32 컨트롤러 ID (예: "ctrl-0004")
  isRunning: boolean;              // 현재 작동 중인지 여부
  // 기존 단일 설정 (하위 호환성)
  intervalMinutes: number | null;
  spraySeconds: number | null;
  startTime: string; // "HH:MM" 또는 ""
  endTime: string;   // "HH:MM" 또는 ""
  allowNightOperation: boolean;
  // 주간/야간 분리 설정 (AUTO 모드용)
  daySchedule: MistScheduleSettings;
  nightSchedule: MistScheduleSettings;
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
