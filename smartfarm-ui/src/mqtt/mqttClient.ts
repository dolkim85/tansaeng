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
  });

  client.on("error", (err) => {
    console.error("❌ MQTT Connection Error:", err);
  });

  client.on("reconnect", () => {
    console.log("🔄 MQTT Reconnecting...");
  });

  client.on("offline", () => {
    console.log("⚠️ MQTT Offline");
  });

  return client;
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
