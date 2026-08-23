#include "ethernet_manager.h"
#include "board_pins.h"
#include "config.h"
#include <SPI.h>
#include <Ethernet.h>

// ⚠️ 알려진 제약: 고전 Arduino Ethernet 라이브러리의 Ethernet.begin(mac)은 내부적으로
// DHCP 협상을 동기(블로킹)로 수행한다(기본 타임아웃 약 60초). 부팅 시 1회 호출은
// "재연결을 기다리는 루프"가 아니라 초기 기동 비용으로 간주해 허용한다(docs/open-decisions.md
// 참고). 운영 중 재시도는 반드시 시간 간격을 두어(RETRY_INTERVAL_MS) 짧은 루프가 되지
// 않도록 한다 — 이 재시도 호출 자체도 실패 시 최대 수 초간 블로킹될 수 있다는 점은
// 라이브러리의 한계로 문서화해두고, 필요하면 2차 버전에서 비동기 DHCP 클라이언트로 교체 검토.

static const unsigned long RETRY_INTERVAL_MS = 30000; // 30초마다만 재시도(운영 중)
static const unsigned long LINK_CHECK_INTERVAL_MS = 1000;

void EthernetManager::begin() {
  pinMode(PIN_W5500_CS, OUTPUT);
  digitalWrite(PIN_W5500_CS, HIGH);

  SPI.begin(PIN_SPI_SCLK, PIN_SPI_MISO, PIN_SPI_MOSI, PIN_W5500_CS);
  Ethernet.init(PIN_W5500_CS);

  byte mac[6];
  esp_read_mac(mac, ESP_MAC_ETH); // ESP32 내장 MAC 사용(eFuse) — 근거: hardware-verification.md 참고 예제와 동일 패턴

  Serial.println("[ETH] W5500 초기화 및 DHCP 시도 중...");
#if ETH_USE_DHCP
  if (Ethernet.begin(mac) == 0) {
    Serial.println("[ETH] 부팅 시 DHCP 실패 — 재부팅하지 않고 계속 진행, update()에서 주기적으로 재시도");
    state_ = State::LINK_DOWN;
  } else {
    Serial.print("[ETH] IP 획득: ");
    Serial.println(Ethernet.localIP());
    state_ = State::CONNECTED;
  }
#else
  IPAddress ip(ETH_STATIC_IP);
  IPAddress dns(ETH_STATIC_DNS);
  IPAddress gw(ETH_STATIC_GW);
  IPAddress sn(ETH_STATIC_SUBNET);
  Ethernet.begin(mac, ip, dns, gw, sn);
  Serial.print("[ETH] 고정IP 설정: ");
  Serial.println(ip);
  state_ = State::CONNECTED;
#endif

  lastRetryMs_ = millis();
  lastLinkCheckMs_ = millis();
  wasLinked_ = (Ethernet.linkStatus() == LinkON);
}

void EthernetManager::update() {
  unsigned long now = millis();

  if (now - lastLinkCheckMs_ >= LINK_CHECK_INTERVAL_MS) {
    lastLinkCheckMs_ = now;
    bool linked = (Ethernet.linkStatus() == LinkON);
    if (linked != wasLinked_) {
      Serial.printf("[ETH] 링크 상태 변경: %s\n", linked ? "UP" : "DOWN");
      wasLinked_ = linked;
    }
    state_ = linked ? (Ethernet.localIP() != IPAddress(0, 0, 0, 0) ? State::CONNECTED : State::WAIT_DHCP)
                     : State::LINK_DOWN;
  }

  // DHCP 임대 유지(짧고 논블로킹) — 정상 연결 상태에서만
  if (state_ == State::CONNECTED) {
    Ethernet.maintain();
  }

  // 링크는 있는데 IP가 없거나(WAIT_DHCP), 링크 자체가 없다가 복구된 경우 —
  // 너무 자주 재시도하지 않도록 간격을 둔다(이 호출은 내부적으로 잠깐 블로킹될 수 있음, 위 주석 참고)
  if ((state_ == State::WAIT_DHCP || state_ == State::LINK_DOWN) && wasLinked_) {
    if (now - lastRetryMs_ >= RETRY_INTERVAL_MS) {
      lastRetryMs_ = now;
      Serial.println("[ETH] DHCP 재시도...");
      byte mac[6];
      esp_read_mac(mac, ESP_MAC_ETH);
      if (Ethernet.begin(mac) != 0) {
        Serial.print("[ETH] 재연결 성공, IP: ");
        Serial.println(Ethernet.localIP());
        state_ = State::CONNECTED;
      }
    }
  }
}

bool EthernetManager::isLinked() const {
  return wasLinked_;
}

bool EthernetManager::hasIp() const {
  return state_ == State::CONNECTED;
}
