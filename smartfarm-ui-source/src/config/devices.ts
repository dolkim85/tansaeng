import type { DeviceConfig } from "../types";

/**
 * TP-Link 스타일 디바이스 레지스트리
 *
 * 모든 ESP32 장치를 구조화된 설정 파일에서 관리합니다.
 * 장치를 추가할 때 코드를 고치지 않고 이 파일만 수정하면 됩니다.
 *
 * MQTT 토픽 패턴:
 * - Command: tansaeng/<esp32Id>/<deviceId>/cmd
 * - State: tansaeng/<esp32Id>/<deviceId>/state
 */

export const DEVICES: DeviceConfig[] = [
  // ========== 팬 제어 (ON/OFF) ==========
  {
    id: "fan_front",
    name: "내부팬 앞",
    type: "fan",
    esp32Id: "ctlr-0001",
    commandTopic: "tansaeng/ctlr-0001/fan1/cmd",
    stateTopic: "tansaeng/ctlr-0001/fan1/state",
  },
  {
    id: "fan_back",
    name: "내부팬 뒤",
    type: "fan",
    esp32Id: "ctlr-0002",
    commandTopic: "tansaeng/ctlr-0002/fan2/cmd",
    stateTopic: "tansaeng/ctlr-0002/fan2/state",
  },
  {
    id: "fan_top",
    name: "천장팬",
    type: "fan",
    esp32Id: "esp32-node-1",
    commandTopic: "tansaeng/esp32-node-1/fan_top/cmd",
    stateTopic: "tansaeng/esp32-node-1/fan_top/state",
  },

  // ========== 개폐기 제어 (슬라이더 0~100%) ==========
  {
    id: "vent_side_left",
    name: "측창개폐기 Left",
    type: "vent",
    esp32Id: "esp32-node-2",
    commandTopic: "tansaeng/esp32-node-2/vent_side_left/cmd",
    stateTopic: "tansaeng/esp32-node-2/vent_side_left/state",
    extra: {
      supportsPercentage: true,
    },
  },
  {
    id: "vent_side_right",
    name: "측창개폐기 Right",
    type: "vent",
    esp32Id: "esp32-node-2",
    commandTopic: "tansaeng/esp32-node-2/vent_side_right/cmd",
    stateTopic: "tansaeng/esp32-node-2/vent_side_right/state",
    extra: {
      supportsPercentage: true,
    },
  },
  {
    id: "vent_top_left",
    name: "천창개폐기 Left",
    type: "vent",
    esp32Id: "esp32-node-2",
    commandTopic: "tansaeng/esp32-node-2/vent_top_left/cmd",
    stateTopic: "tansaeng/esp32-node-2/vent_top_left/state",
    extra: {
      supportsPercentage: true,
    },
  },
  {
    id: "vent_top_right",
    name: "천창개폐기 Right",
    type: "vent",
    esp32Id: "esp32-node-2",
    commandTopic: "tansaeng/esp32-node-2/vent_top_right/cmd",
    stateTopic: "tansaeng/esp32-node-2/vent_top_right/state",
    extra: {
      supportsPercentage: true,
    },
  },

  // ========== 펌프 제어 (ON/OFF) ==========
  {
    id: "pump_nutrient_fill",
    name: "양액탱크 급수펌프",
    type: "pump",
    esp32Id: "esp32-node-3",
    commandTopic: "tansaeng/esp32-node-3/pump_nutrient_fill/cmd",
    stateTopic: "tansaeng/esp32-node-3/pump_nutrient_fill/state",
  },
  {
    id: "pump_water_curtain",
    name: "수막펌프",
    type: "pump",
    esp32Id: "esp32-node-3",
    commandTopic: "tansaeng/esp32-node-3/pump_water_curtain/cmd",
    stateTopic: "tansaeng/esp32-node-3/pump_water_curtain/state",
  },
  {
    id: "pump_heating_fill",
    name: "히팅탱크 급수펌프",
    type: "pump",
    esp32Id: "esp32-node-3",
    commandTopic: "tansaeng/esp32-node-3/pump_heating_fill/cmd",
    stateTopic: "tansaeng/esp32-node-3/pump_heating_fill/state",
  },

  // ========== 카메라 (추가 가능) ==========
  // {
  //   id: "camera_1",
  //   name: "카메라 1",
  //   type: "camera",
  //   esp32Id: "esp32-node-4",
  //   commandTopic: "tansaeng/esp32-node-4/camera_1/cmd",
  //   stateTopic: "tansaeng/esp32-node-4/camera_1/state",
  //   extra: {
  //     streamUrl: "", // Settings에서 수정 가능
  //   },
  // },
];

/**
 * 장치 타입별로 필터링하는 헬퍼 함수
 */
export function getDevicesByType(type: DeviceConfig["type"]): DeviceConfig[] {
  return DEVICES.filter((device) => device.type === type);
}

/**
 * ID로 장치를 찾는 헬퍼 함수
 */
export function getDeviceById(id: string): DeviceConfig | undefined {
  return DEVICES.find((device) => device.id === id);
}
