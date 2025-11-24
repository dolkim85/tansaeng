import { useEffect, useState } from "react";
import type { MqttConnectionState } from "../types";

interface HeaderProps {
  connectionState: MqttConnectionState;
}

export default function Header({ connectionState }: HeaderProps) {
  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentTime(new Date());
    }, 5000); // 5초마다 갱신

    return () => clearInterval(timer);
  }, []);

  const getConnectionBadge = () => {
    switch (connectionState) {
      case "connected":
        return (
          <span style={{
            display: "flex",
            alignItems: "center",
            gap: "6px",
            padding: "4px 10px",
            borderRadius: "9999px",
            background: "#d1fae5",
            color: "#047857",
            fontSize: "0.75rem",
            fontWeight: "500"
          }}>
            <span style={{
              width: "6px",
              height: "6px",
              borderRadius: "9999px",
              background: "#10b981"
            }}></span>
            Connected
          </span>
        );
      case "connecting":
        return (
          <span style={{
            display: "flex",
            alignItems: "center",
            gap: "6px",
            padding: "4px 10px",
            borderRadius: "9999px",
            background: "#fef3c7",
            color: "#b45309",
            fontSize: "0.75rem",
            fontWeight: "500"
          }}>
            <span style={{
              width: "6px",
              height: "6px",
              borderRadius: "9999px",
              background: "#f59e0b",
              animation: "pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite"
            }}></span>
            Connecting
          </span>
        );
      case "disconnected":
        return (
          <span style={{
            display: "flex",
            alignItems: "center",
            gap: "6px",
            padding: "4px 10px",
            borderRadius: "9999px",
            background: "#fee2e2",
            color: "#b91c1c",
            fontSize: "0.75rem",
            fontWeight: "500"
          }}>
            <span style={{
              width: "6px",
              height: "6px",
              borderRadius: "9999px",
              background: "#ef4444"
            }}></span>
            Disconnected
          </span>
        );
      case "error":
        return (
          <span style={{
            display: "flex",
            alignItems: "center",
            gap: "6px",
            padding: "4px 10px",
            borderRadius: "9999px",
            background: "#fee2e2",
            color: "#b91c1c",
            fontSize: "0.75rem",
            fontWeight: "500"
          }}>
            <span style={{
              width: "6px",
              height: "6px",
              borderRadius: "9999px",
              background: "#ef4444"
            }}></span>
            Error
          </span>
        );
    }
  };

  return (
    <header style={{
      background: "linear-gradient(to right, #10b981, #059669)",
      color: "white",
      boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
      flexShrink: 0
    }}>
      <div style={{
        maxWidth: "1400px",
        margin: "0 auto",
        padding: "12px 16px"
      }}>
        <div style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          flexWrap: "wrap",
          gap: "12px"
        }}>
          {/* 좌측: 로고 */}
          <div style={{
            fontSize: "1.125rem",
            fontWeight: "700"
          }}>
            탄생농원 | Tansaeng SmartFarm
          </div>

          {/* 중앙: 현재 시간 */}
          <div style={{
            fontSize: "0.875rem",
            fontWeight: "500"
          }}>
            {currentTime.toLocaleString("ko-KR", {
              year: "numeric",
              month: "2-digit",
              day: "2-digit",
              hour: "2-digit",
              minute: "2-digit",
              second: "2-digit",
            })}
          </div>

          {/* 우측: MQTT 연결 상태 */}
          <div style={{
            display: "flex",
            alignItems: "center",
            gap: "8px"
          }}>
            {getConnectionBadge()}
          </div>
        </div>
      </div>
    </header>
  );
}
