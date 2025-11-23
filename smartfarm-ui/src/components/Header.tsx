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
          <span className="flex items-center gap-2 px-3 py-1 rounded-full bg-green-100 text-green-700 text-sm font-medium">
            <span className="w-2 h-2 rounded-full bg-green-500"></span>
            Connected
          </span>
        );
      case "connecting":
        return (
          <span className="flex items-center gap-2 px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-sm font-medium">
            <span className="w-2 h-2 rounded-full bg-yellow-500 animate-pulse"></span>
            Connecting
          </span>
        );
      case "disconnected":
        return (
          <span className="flex items-center gap-2 px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-medium">
            <span className="w-2 h-2 rounded-full bg-red-500"></span>
            Disconnected
          </span>
        );
      case "error":
        return (
          <span className="flex items-center gap-2 px-3 py-1 rounded-full bg-red-100 text-red-700 text-sm font-medium">
            <span className="w-2 h-2 rounded-full bg-red-500"></span>
            Error
          </span>
        );
    }
  };

  return (
    <header className="bg-gradient-to-r from-emerald-500 to-green-600 text-white shadow-lg">
      <div className="container mx-auto px-4 py-4">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          {/* 좌측: 로고 */}
          <div className="text-xl md:text-2xl font-bold">
            탄생농원 | Tansaeng SmartFarm
          </div>

          {/* 중앙: 현재 시간 */}
          <div className="text-sm md:text-base">
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
          <div className="flex items-center gap-3">
            {getConnectionBadge()}
            <span className="hidden md:inline text-sm">원격 제어 대기 중</span>
          </div>
        </div>
      </div>
    </header>
  );
}
