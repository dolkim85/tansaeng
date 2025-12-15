#!/usr/bin/env node
/**
 * MQTT Publish Script
 * PHP API에서 호출하여 MQTT 메시지를 발행합니다.
 *
 * Usage: node mqtt_publish.js <topic> <message>
 */

const mqtt = require('mqtt');

// HiveMQ Cloud 설정
const MQTT_BROKER = 'mqtts://22ada06fd6cf4059bd700ddbf6004d68.s1.eu.hivemq.cloud:8883';
const MQTT_USERNAME = 'esp32-client-01';
const MQTT_PASSWORD = 'Qjawns3445';

// 명령행 인자 확인
if (process.argv.length < 4) {
  console.error('Usage: node mqtt_publish.js <topic> <message>');
  process.exit(1);
}

const topic = process.argv[2];
const message = process.argv[3];

// MQTT 클라이언트 연결
const client = mqtt.connect(MQTT_BROKER, {
  username: MQTT_USERNAME,
  password: MQTT_PASSWORD,
  rejectUnauthorized: false,
  protocol: 'mqtts',
  port: 8883
});

client.on('connect', () => {
  console.log(`[MQTT] Connected to broker`);

  // 메시지 발행
  client.publish(topic, message, { qos: 1, retain: true }, (err) => {
    if (err) {
      console.error(`[ERROR] Failed to publish: ${err.message}`);
      process.exit(1);
    }

    console.log(`[SUCCESS] Published to ${topic}: ${message}`);
    client.end();
    process.exit(0);
  });
});

client.on('error', (err) => {
  console.error(`[ERROR] MQTT connection error: ${err.message}`);
  process.exit(1);
});

// 타임아웃 (5초)
setTimeout(() => {
  console.error('[ERROR] Timeout - Failed to publish within 5 seconds');
  client.end();
  process.exit(1);
}, 5000);
