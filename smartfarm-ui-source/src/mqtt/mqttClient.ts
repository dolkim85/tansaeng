import mqtt from "mqtt";
import type { MqttClient } from "mqtt";

/**
 * HiveMQ Cloud WebSocket MQTT 클라이언트
 *
 * 브라우저에서 HiveMQ Cloud에 WebSocket(TLS)으로 연결합니다.
 * - ESP32는 8883 포트 (MQTT/TLS) 사용
 * - 웹 브라우저는 8884 포트 (WebSocket/TLS) 사용
 */

const MQTT_HOST = import.meta.env.VITE_MQTT_HOST;
const MQTT_WS_PORT = import.meta.env.VITE_MQTT_WS_PORT || "8884";
const MQTT_USERNAME = import.meta.env.VITE_MQTT_USERNAME;
const MQTT_PASSWORD = import.meta.env.VITE_MQTT_PASSWORD;

let client: MqttClient | null = null;

// 연결 상태 콜백 리스트
const connectionCallbacks: Array<(connected: boolean) => void> = [];

export function getMqttClient(): MqttClient {
  if (client) return client;

  const brokerUrl = `wss://${MQTT_HOST}:${MQTT_WS_PORT}/mqtt`;

  console.log(`🔌 Connecting to HiveMQ Cloud: ${brokerUrl}`);

  client = mqtt.connect(brokerUrl, {
    username: MQTT_USERNAME,
    password: MQTT_PASSWORD,
    clean: true,
    reconnectPeriod: 3000,
    clientId: `tansaeng-web-${Math.random().toString(16).slice(2, 10)}`,
  });

  // 연결 이벤트 로깅
  client.on("connect", () => {
    console.log("✅ MQTT Connected to HiveMQ Cloud");
    connectionCallbacks.forEach(cb => cb(true));
  });

  client.on("error", (err) => {
    console.error("❌ MQTT Connection Error:", err);
    connectionCallbacks.forEach(cb => cb(false));
  });

  client.on("reconnect", () => {
    console.log("🔄 MQTT Reconnecting...");
  });

  client.on("offline", () => {
    console.log("⚠️ MQTT Offline");
    connectionCallbacks.forEach(cb => cb(false));
  });

  return client;
}

/**
 * MQTT 연결 상태 확인
 */
export function isMqttConnected(): boolean {
  return client?.connected ?? false;
}

/**
 * MQTT 연결 상태 변경 감지
 */
export function onConnectionChange(callback: (connected: boolean) => void): () => void {
  connectionCallbacks.push(callback);

  // 현재 상태 즉시 콜백
  if (client) {
    callback(client.connected);
  }

  // unsubscribe 함수 반환
  return () => {
    const index = connectionCallbacks.indexOf(callback);
    if (index > -1) {
      connectionCallbacks.splice(index, 1);
    }
  };
}

/**
 * MQTT 메시지 발행 헬퍼 함수
 */
export function publishCommand(topic: string, payload: object): void {
  const client = getMqttClient();

  // ESP32 호환: { power: "on" } → "ON", { power: "off" } → "OFF"
  let message: string;
  if ('power' in payload) {
    message = (payload as { power: string }).power.toUpperCase();
  } else {
    message = JSON.stringify(payload);
  }

  client.publish(topic, message, { qos: 1 }, (err) => {
    if (err) {
      console.error(`❌ Failed to publish to ${topic}:`, err);
    } else {
      console.log(`📤 Published to ${topic}:`, message);
    }
  });
}

/**
 * MQTT 토픽 구독 헬퍼 함수
 */
export function subscribeToTopic(
  topic: string,
  callback: (payload: string) => void
): void {
  const client = getMqttClient();

  client.subscribe(topic, { qos: 1 }, (err) => {
    if (err) {
      console.error(`❌ Failed to subscribe to ${topic}:`, err);
    } else {
      console.log(`📥 Subscribed to ${topic}`);
    }
  });

  client.on("message", (receivedTopic, message) => {
    if (receivedTopic === topic) {
      callback(message.toString());
    }
  });
}
