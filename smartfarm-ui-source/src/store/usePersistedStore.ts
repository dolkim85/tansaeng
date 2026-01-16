import { useEffect, useState } from "react";
import type { DeviceDesiredState, MistZoneConfig, CameraConfig, FarmSettings } from "../types";

/**
 * localStorage 기반 상태 유지 시스템
 *
 * 페이지를 새로고침해도 사용자가 설정한 상태가 유지됩니다.
 */

const STORAGE_KEY_DEVICE = "tansaeng-smartfarm-device-state-v1";
const STORAGE_KEY_MIST = "tansaeng-smartfarm-mist-zones-v1";
const STORAGE_KEY_CAMERA = "tansaeng-smartfarm-cameras-v1";
const STORAGE_KEY_FARM = "tansaeng-smartfarm-farm-settings-v1";

// ========== 장치 상태 유지 ==========

export function usePersistedDeviceState() {
  const [state, setState] = useState<DeviceDesiredState>({});

  // 초기 로드 (localStorage에서 읽기)
  useEffect(() => {
    const raw = localStorage.getItem(STORAGE_KEY_DEVICE);
    if (raw) {
      try {
        setState(JSON.parse(raw));
      } catch (error) {
        console.error("Failed to parse device state from localStorage:", error);
      }
    }
  }, []);

  // 상태 변경 시 localStorage에 저장
  useEffect(() => {
    localStorage.setItem(STORAGE_KEY_DEVICE, JSON.stringify(state));
  }, [state]);

  return [state, setState] as const;
}

// ========== 분무수경 Zone 설정 유지 ==========

// 기본 스케줄 설정
const DEFAULT_SCHEDULE_SETTINGS = {
  intervalMinutes: null,
  spraySeconds: null,
  startTime: "",
  endTime: "",
  enabled: false,
};

const DEFAULT_MIST_ZONES: MistZoneConfig[] = [
  {
    id: "zone_a",
    name: "Zone A",
    mode: "OFF",
    controllerId: "ctlr-0004",  // Zone A는 ctlr-0004에 연결
    isRunning: false,
    intervalMinutes: null,
    spraySeconds: null,
    startTime: "",
    endTime: "",
    allowNightOperation: false,
    daySchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "06:00", endTime: "18:00" },
    nightSchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "18:00", endTime: "06:00" },
  },
  {
    id: "zone_b",
    name: "Zone B",
    mode: "OFF",
    controllerId: "",  // 추후 연결
    isRunning: false,
    intervalMinutes: null,
    spraySeconds: null,
    startTime: "",
    endTime: "",
    allowNightOperation: false,
    daySchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "06:00", endTime: "18:00" },
    nightSchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "18:00", endTime: "06:00" },
  },
  {
    id: "zone_c",
    name: "Zone C",
    mode: "OFF",
    controllerId: "",  // 추후 연결
    isRunning: false,
    intervalMinutes: null,
    spraySeconds: null,
    startTime: "",
    endTime: "",
    allowNightOperation: false,
    daySchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "06:00", endTime: "18:00" },
    nightSchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "18:00", endTime: "06:00" },
  },
  {
    id: "zone_d",
    name: "Zone D",
    mode: "OFF",
    controllerId: "",  // 추후 연결
    isRunning: false,
    intervalMinutes: null,
    spraySeconds: null,
    startTime: "",
    endTime: "",
    allowNightOperation: false,
    daySchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "06:00", endTime: "18:00" },
    nightSchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "18:00", endTime: "06:00" },
  },
  {
    id: "zone_e",
    name: "Zone E",
    mode: "OFF",
    controllerId: "",  // 추후 연결
    isRunning: false,
    intervalMinutes: null,
    spraySeconds: null,
    startTime: "",
    endTime: "",
    allowNightOperation: false,
    daySchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "06:00", endTime: "18:00" },
    nightSchedule: { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "18:00", endTime: "06:00" },
  },
];

// controllerId 오타 수정 함수 (ctrl- → ctlr-)
function fixControllerId(controllerId: string | undefined): string {
  if (!controllerId) return "";
  // "ctrl-0004" → "ctlr-0004" 변환
  if (controllerId.startsWith("ctrl-")) {
    return controllerId.replace("ctrl-", "ctlr-");
  }
  return controllerId;
}

// 기존 데이터를 새 형식으로 마이그레이션
function migrateZoneData(oldZone: Partial<MistZoneConfig>, defaultZone: MistZoneConfig): MistZoneConfig {
  // controllerId 오타 수정 적용
  const fixedControllerId = fixControllerId(oldZone.controllerId) || defaultZone.controllerId;

  return {
    ...defaultZone,
    ...oldZone,
    // 새 필드가 없으면 기본값 사용 + controllerId 오타 수정
    controllerId: fixedControllerId,
    isRunning: oldZone.isRunning ?? false,
    daySchedule: oldZone.daySchedule ?? { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "06:00", endTime: "18:00" },
    nightSchedule: oldZone.nightSchedule ?? { ...DEFAULT_SCHEDULE_SETTINGS, startTime: "18:00", endTime: "06:00" },
  };
}

export function usePersistedMistZones() {
  const [zones, setZones] = useState<MistZoneConfig[]>(DEFAULT_MIST_ZONES);

  useEffect(() => {
    const raw = localStorage.getItem(STORAGE_KEY_MIST);
    if (raw) {
      try {
        const parsed = JSON.parse(raw) as Partial<MistZoneConfig>[];
        // 기존 데이터 마이그레이션 + 새 Zone 추가
        const migratedZones = DEFAULT_MIST_ZONES.map((defaultZone) => {
          const existingZone = parsed.find((z) => z.id === defaultZone.id);
          if (existingZone) {
            return migrateZoneData(existingZone, defaultZone);
          }
          return defaultZone;
        });
        setZones(migratedZones);
      } catch (error) {
        console.error("Failed to parse mist zones from localStorage:", error);
      }
    }
  }, []);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY_MIST, JSON.stringify(zones));
  }, [zones]);

  return [zones, setZones] as const;
}

// ========== 카메라 설정 유지 ==========

export function usePersistedCameras() {
  const [cameras, setCameras] = useState<CameraConfig[]>([]);

  useEffect(() => {
    const raw = localStorage.getItem(STORAGE_KEY_CAMERA);
    if (raw) {
      try {
        setCameras(JSON.parse(raw));
      } catch (error) {
        console.error("Failed to parse cameras from localStorage:", error);
      }
    }
  }, []);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY_CAMERA, JSON.stringify(cameras));
  }, [cameras]);

  return [cameras, setCameras] as const;
}

// ========== 농장 기본 정보 유지 ==========

const DEFAULT_FARM_SETTINGS: FarmSettings = {
  farmName: "탄생농원",
  adminName: "",
  notes: "",
};

export function usePersistedFarmSettings() {
  const [settings, setSettings] = useState<FarmSettings>(DEFAULT_FARM_SETTINGS);

  useEffect(() => {
    const raw = localStorage.getItem(STORAGE_KEY_FARM);
    if (raw) {
      try {
        setSettings(JSON.parse(raw));
      } catch (error) {
        console.error("Failed to parse farm settings from localStorage:", error);
      }
    }
  }, []);

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY_FARM, JSON.stringify(settings));
  }, [settings]);

  return [settings, setSettings] as const;
}
