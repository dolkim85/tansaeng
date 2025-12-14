/**
 * Device Control API Helper
 * 모든 ESP32 장치 제어를 HTTP API로 전송
 */

export interface DeviceControlRequest {
  controllerId: string;
  deviceId: string;
  command: string;
}

export interface DeviceControlResponse {
  success: boolean;
  message: string;
  data?: {
    controllerId: string;
    deviceId: string;
    command: string;
    topic: string;
    timestamp: string;
  };
}

/**
 * ESP32 장치 제어 API 호출
 */
export async function sendDeviceCommand(
  controllerId: string,
  deviceId: string,
  command: string
): Promise<DeviceControlResponse> {
  try {
    const response = await fetch('https://www.tansaeng.com/api/smartfarm/device_control.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        controllerId,
        deviceId,
        command,
      }),
    });

    const data = await response.json();
    return data;
  } catch (error) {
    console.error('[API Error]', error);
    return {
      success: false,
      message: error instanceof Error ? error.message : 'Unknown error',
    };
  }
}
