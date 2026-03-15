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

/**
 * 장치 설정 저장 API 호출
 * 서버의 device_settings.json에 설정을 저장합니다.
 * 데몬이 이 설정을 읽어서 자동 제어합니다.
 */
export async function saveDeviceSettings(settings: Record<string, unknown>): Promise<{ success: boolean; message: string }> {
  try {
    const response = await fetch('https://www.tansaeng.com/api/smartfarm/save_device_settings.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(settings),
    });

    const data = await response.json();
    return data;
  } catch (error) {
    console.error('[API Error] saveDeviceSettings:', error);
    return {
      success: false,
      message: error instanceof Error ? error.message : 'Unknown error',
    };
  }
}

/**
 * 농장 기본 정보 저장 API 호출
 * 서버의 farm_settings.json에 농장명/관리자명/메모를 저장합니다.
 */
export async function saveFarmSettings(settings: {
  farmName: string;
  adminName: string;
  notes: string;
}): Promise<{ success: boolean; message: string }> {
  try {
    const response = await fetch('https://www.tansaeng.com/api/smartfarm/save_farm_settings.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(settings),
    });

    const data = await response.json();
    return data;
  } catch (error) {
    console.error('[API Error] saveFarmSettings:', error);
    return {
      success: false,
      message: error instanceof Error ? error.message : 'Unknown error',
    };
  }
}

/**
 * 장치 설정 조회 API 호출
 */
export async function getDeviceSettings(): Promise<{ success: boolean; data?: Record<string, unknown> }> {
  try {
    const response = await fetch('https://www.tansaeng.com/api/smartfarm/get_device_settings.php');
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('[API Error] getDeviceSettings:', error);
    return { success: false };
  }
}
