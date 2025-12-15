import type { ESP32Controller } from "../types";

/**
 * ESP32 컨트롤러 레지스트리
 *
 * 모든 ESP32 장치의 연결 상태를 모니터링하기 위한 목록입니다.
 * 각 컨트롤러는 status 토픽으로 주기적으로 heartbeat를 전송해야 합니다.
 */
export const ESP32_CONTROLLERS: ESP32Controller[] = [
  {
    id: "esp32-in-fan-front",
    name: "내부팬 앞",
    controllerId: "ctlr-0001",
    statusTopic: "tansaeng/ctlr-0001/status",
  },
  {
    id: "esp32-in-fan-back",
    name: "내부팬 뒤",
    controllerId: "ctlr-0002",
    statusTopic: "tansaeng/ctlr-0002/status",
  },
  {
    id: "esp32-in-top",
    name: "천장 환기",
    controllerId: "ctlr-0003",
    statusTopic: "tansaeng/ctlr-0003/status",
  },
  {
    id: "esp32-in-main-val",
    name: "메인 밸브",
    controllerId: "ctlr-0004",
    statusTopic: "tansaeng/ctlr-0004/status",
  },
  {
    id: "esp32-in-line-val",
    name: "라인 밸브",
    controllerId: "ctlr-0005",
    statusTopic: "tansaeng/ctlr-0005/status",
  },
  {
    id: "esp32-out-pressur-pump",
    name: "가압 펌프",
    controllerId: "ctlr-0006",
    statusTopic: "tansaeng/ctlr-0006/status",
  },
  {
    id: "esp32-out-insert-pump",
    name: "주입 펌프",
    controllerId: "ctlr-0007",
    statusTopic: "tansaeng/ctlr-0007/status",
  },
  {
    id: "esp32-out-hit-pump-val",
    name: "히트펌프 밸브",
    controllerId: "ctlr-0008",
    statusTopic: "tansaeng/ctlr-0008/status",
  },
  {
    id: "esp32-out-chiller-pump",
    name: "칠러 펌프",
    controllerId: "ctlr-0009",
    statusTopic: "tansaeng/ctlr-0009/status",
  },
  {
    id: "esp32-in-chil-hit-pump",
    name: "칠러/히트 통합 펌프",
    controllerId: "ctlr-0010",
    statusTopic: "tansaeng/ctlr-0010/status",
  },
  {
    id: "esp32-out-skylight-screen",
    name: "천창 스크린",
    controllerId: "ctlr-0012",
    statusTopic: "tansaeng/ctlr-0012/status",
  },
  {
    id: "esp32-out-side-screen",
    name: "측창 스크린",
    controllerId: "ctlr-0021",
    statusTopic: "tansaeng/ctlr-0021/status",
  },
  {
    id: "esp32-out-top-chillerline-pump-valve",
    name: "천창 칠러라인 펌프 밸브",
    controllerId: "ctlr-0013",
    statusTopic: "tansaeng/ctlr-0013/status",
  },
];

/**
 * Controller ID로 ESP32 장치 찾기
 */
export function getControllerById(controllerId: string): ESP32Controller | undefined {
  return ESP32_CONTROLLERS.find((ctrl) => ctrl.controllerId === controllerId);
}

/**
 * ESP32 ID로 장치 찾기
 */
export function getControllerByEsp32Id(esp32Id: string): ESP32Controller | undefined {
  return ESP32_CONTROLLERS.find((ctrl) => ctrl.id === esp32Id);
}
