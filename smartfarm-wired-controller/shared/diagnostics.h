#pragma once
// 두 펌웨어가 공유하는 진단/장애 코드 정의.
// 로그에 비밀번호·토큰을 남기지 않는다는 원칙(작업지시서 11번)을 지키기 위해,
// 로그 출력은 항상 이 코드/이름을 통해서만 하고 원시 인증정보 변수를 직접 찍지 않는다.

enum class FaultCode : uint8_t {
  NONE                     = 0,
  RS485_TIMEOUT            = 1,
  RS485_CRC                = 2,
  VALVE_SAFETY_TIMEOUT     = 3,
  CONFIG_CRC_MISMATCH      = 4,
  CONFIG_VERSION_MISMATCH  = 5,
  ETHERNET_LINK_DOWN       = 6,
  MQTT_DISCONNECTED        = 7,
};

inline const char* faultCodeName(FaultCode c) {
  switch (c) {
    case FaultCode::NONE:                    return "none";
    case FaultCode::RS485_TIMEOUT:           return "rs485_timeout";
    case FaultCode::RS485_CRC:               return "rs485_crc";
    case FaultCode::VALVE_SAFETY_TIMEOUT:    return "valve_safety_timeout";
    case FaultCode::CONFIG_CRC_MISMATCH:     return "config_crc_mismatch";
    case FaultCode::CONFIG_VERSION_MISMATCH: return "config_version_mismatch";
    case FaultCode::ETHERNET_LINK_DOWN:      return "ethernet_link_down";
    case FaultCode::MQTT_DISCONNECTED:       return "mqtt_disconnected";
    default:                                 return "unknown";
  }
}
