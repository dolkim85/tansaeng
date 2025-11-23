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

const DEFAULT_MIST_ZONES: MistZoneConfig[] = [
  {
    id: "zone_a",
    name: "Zone A (상층)",
    mode: "OFF",
    intervalMinutes: null,
    spraySeconds: null,
    startTime: "",
    endTime: "",
    allowNightOperation: false,
  },
  {
    id: "zone_b",
    name: "Zone B (하층)",
    mode: "OFF",
    intervalMinutes: null,
    spraySeconds: null,
    startTime: "",
    endTime: "",
    allowNightOperation: false,
  },
  {
    id: "zone_c",
    name: "Zone C (테스트베드)",
    mode: "OFF",
    intervalMinutes: null,
    spraySeconds: null,
    startTime: "",
    endTime: "",
    allowNightOperation: false,
  },
];

export function usePersistedMistZones() {
  const [zones, setZones] = useState<MistZoneConfig[]>(DEFAULT_MIST_ZONES);

  useEffect(() => {
    const raw = localStorage.getItem(STORAGE_KEY_MIST);
    if (raw) {
      try {
        setZones(JSON.parse(raw));
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
