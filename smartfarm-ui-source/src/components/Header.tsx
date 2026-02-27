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
    }, 1000); // 1초마다 갱신

    return () => clearInterval(timer);
  }, []);

  const getConnectionBadge = () => {
    switch (connectionState) {
      case "connected":
        return (
          <span className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-farm-100 text-farm-700 text-xs font-medium">
            <span className="w-1.5 h-1.5 rounded-full bg-farm-500"></span>
            Connected
          </span>
        );
      case "connecting":
        return (
          <span className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-medium">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
            Connecting
          </span>
        );
      case "disconnected":
        return (
          <span className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">
            <span className="w-1.5 h-1.5 rounded-full bg-red-500"></span>
            Disconnected
          </span>
        );
      case "error":
        return (
          <span className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium">
            <span className="w-1.5 h-1.5 rounded-full bg-red-500"></span>
            Error
          </span>
        );
    }
  };

  return (
    <header className="bg-farm-500 shadow-lg flex-shrink-0">
      <div className="max-w-screen-2xl mx-auto px-4 py-3">
        <div className="flex items-center justify-between flex-wrap gap-3">
          {/* 좌측: 로고 + 링크 */}
          <div className="flex items-center gap-3">
            <div className="text-lg font-bold text-gray-900">
              탄생농원 | Tansaeng SmartFarm
            </div>
            <div className="flex items-center gap-2">
              <a
                href="/"
                target="_blank"
                className="text-xs px-2 py-1 bg-white text-farm-700 rounded hover:bg-farm-50 transition-colors font-medium"
              >
                메인페이지
              </a>
              <a
                href="/admin/"
                target="_blank"
                className="text-xs px-2 py-1 bg-white text-farm-700 rounded hover:bg-farm-50 transition-colors font-medium"
              >
                관리자
              </a>
            </div>
          </div>

          {/* 중앙: 현재 시간 */}
          <div className="text-sm font-medium text-gray-800">
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
          <div className="flex items-center gap-2">
            {getConnectionBadge()}
          </div>
        </div>
      </div>
    </header>
  );
}
