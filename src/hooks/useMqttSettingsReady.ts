import { useEffect, useRef, useState } from "react";
import { isMqttConnected, onConnectionChange } from "../mqtt/mqttClient";

// MQTT 연결 후 retain 메시지가 도착할 시간을 벌어주는 유예시간.
// 이 시간 전에는 로컬 state가 하드코딩된 기본값(마운트 직후)일 수 있으므로
// 저장/모드변경/작동시작 등 "현재 state를 그대로 발행"하는 액션을 막아야 한다.
// (2026-08-13: 이 가드가 없어 재로드 직후 blank 기본값이 실제 AUTO 설정을 덮어쓴 사고 발생)
const SETTINGS_SYNC_GRACE_MS = 3000;

export function useMqttSettingsReady(graceMs: number = SETTINGS_SYNC_GRACE_MS): boolean {
  const [settingsReady, setSettingsReady] = useState(false);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const clearTimer = () => {
      if (timerRef.current) {
        clearTimeout(timerRef.current);
        timerRef.current = null;
      }
    };

    const unsub = onConnectionChange((connected) => {
      if (connected) {
        clearTimer();
        timerRef.current = setTimeout(() => setSettingsReady(true), graceMs);
      } else {
        // 재연결 시에도 동일한 레이스가 발생하므로 다시 대기
        clearTimer();
        setSettingsReady(false);
      }
    });

    if (isMqttConnected()) {
      timerRef.current = setTimeout(() => setSettingsReady(true), graceMs);
    }

    return () => {
      clearTimer();
      unsub();
    };
  }, [graceMs]);

  return settingsReady;
}
