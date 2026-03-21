/// <reference types="vite/client" />

interface ImportMetaEnv {
  // MQTT Configuration
  readonly VITE_MQTT_HOST: string;
  readonly VITE_MQTT_WS_PORT: string;
  readonly VITE_MQTT_USERNAME: string;
  readonly VITE_MQTT_PASSWORD: string;

  // Raspberry Pi Camera Server
  readonly VITE_RPI_BASE_URL: string;

  // Tapo Camera HLS URLs
  readonly VITE_TAPO_CAM1_HLS_URL: string;
  readonly VITE_TAPO_CAM2_HLS_URL: string;
  readonly VITE_TAPO_CAM3_HLS_URL: string;
  readonly VITE_TAPO_CAM4_HLS_URL: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
