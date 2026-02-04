/**
 * 라즈베리파이 IP 설정 컴포넌트
 * localStorage에 IP 저장 및 관리
 */

import { useState, useEffect } from "react";

const RPI_IP_STORAGE_KEY = "tansaeng_rpi_ip";

export default function RpiIpSettings() {
  const [ipInput, setIpInput] = useState("");
  const [savedIp, setSavedIp] = useState<string | null>(null);
  const [message, setMessage] = useState("");

  // 컴포넌트 마운트 시 저장된 IP 불러오기
  useEffect(() => {
    const stored = localStorage.getItem(RPI_IP_STORAGE_KEY);
    if (stored) {
      setSavedIp(stored);
    }
  }, []);

  // IP 주소 유효성 검증
  const isValidIp = (ip: string): boolean => {
    const ipPattern = /^(\d{1,3}\.){3}\d{1,3}$/;
    if (!ipPattern.test(ip)) return false;

    const parts = ip.split(".");
    return parts.every((part) => {
      const num = parseInt(part, 10);
      return num >= 0 && num <= 255;
    });
  };

  // IP 저장 핸들러
  const handleSave = () => {
    const trimmedIp = ipInput.trim();
    console.log("[RpiIpSettings] 저장 버튼 클릭, 입력값:", trimmedIp);

    if (!trimmedIp) {
      console.log("[RpiIpSettings] 입력값 없음");
      setMessage("❌ IP 주소를 입력하세요");
      return;
    }

    if (!isValidIp(trimmedIp)) {
      console.log("[RpiIpSettings] IP 형식 오류:", trimmedIp);
      setMessage("❌ 올바른 IP 주소 형식이 아닙니다 (예: 192.168.1.100)");
      return;
    }

    // localStorage에 저장
    localStorage.setItem(RPI_IP_STORAGE_KEY, trimmedIp);
    console.log("[RpiIpSettings] localStorage 저장 완료:", trimmedIp);
    console.log("[RpiIpSettings] 저장 확인:", localStorage.getItem(RPI_IP_STORAGE_KEY));

    setSavedIp(trimmedIp);
    setIpInput("");
    setMessage("✅ 라즈베리파이 IP가 저장되었습니다");

    // 3초 후 메시지 제거
    setTimeout(() => setMessage(""), 3000);
  };

  // IP 삭제 핸들러
  const handleDelete = () => {
    if (!savedIp) return;

    if (window.confirm("저장된 라즈베리파이 IP를 삭제하시겠습니까?")) {
      localStorage.removeItem(RPI_IP_STORAGE_KEY);
      setSavedIp(null);
      setMessage("🗑️ 라즈베리파이 IP가 삭제되었습니다");

      setTimeout(() => setMessage(""), 3000);
    }
  };

  // Enter 키 입력 시 저장
  const handleKeyPress = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "Enter") {
      handleSave();
    }
  };

  return (
    <div className="mb-6 rounded-lg border border-slate-600 bg-slate-800 p-4">
      <h2 className="mb-3 text-lg font-semibold text-slate-100">
        ⚙️ 라즈베리파이 IP 설정
      </h2>

      {/* IP 입력 폼 */}
      <div className="mb-3 flex gap-2">
        <input
          type="text"
          value={ipInput}
          onChange={(e) => setIpInput(e.target.value)}
          onKeyPress={handleKeyPress}
          placeholder="예: 192.168.219.170"
          className="flex-1 rounded border border-slate-600 bg-slate-700 px-3 py-2 text-slate-100 placeholder-slate-400 focus:border-blue-500 focus:outline-none"
        />
        <button
          onClick={handleSave}
          className="rounded bg-blue-600 px-4 py-2 font-medium text-white transition hover:bg-blue-700"
        >
          저장
        </button>
      </div>

      {/* 메시지 표시 */}
      {message && (
        <div
          className={`mb-3 rounded p-2 text-sm ${
            message.includes("❌")
              ? "bg-red-900/30 text-red-300"
              : message.includes("🗑️")
                ? "bg-yellow-900/30 text-yellow-300"
                : "bg-green-900/30 text-green-300"
          }`}
        >
          {message}
        </div>
      )}

      {/* 저장된 IP 표시 */}
      {savedIp && (
        <div className="rounded border border-slate-600 bg-slate-700 p-3">
          <div className="mb-2 flex items-center justify-between">
            <span className="text-sm text-slate-400">현재 설정된 IP:</span>
            <button
              onClick={handleDelete}
              className="rounded bg-red-600 px-3 py-1 text-sm font-medium text-white transition hover:bg-red-700"
            >
              삭제
            </button>
          </div>
          <div className="rounded bg-slate-800 p-2 font-mono text-green-400">
            {savedIp}
          </div>
          <div className="mt-2 text-xs text-slate-400">
            💡 이 IP는 HLS 스트림 동기화에 사용됩니다
          </div>
        </div>
      )}

      {!savedIp && (
        <div className="rounded border border-yellow-600/30 bg-yellow-900/20 p-3 text-sm text-yellow-300">
          ⚠️ 라즈베리파이 IP가 설정되지 않았습니다. 카메라 스트리밍을 위해 IP를
          설정해주세요.
        </div>
      )}
    </div>
  );
}

// 저장된 IP를 가져오는 유틸리티 함수 (다른 컴포넌트에서 사용)
export function getSavedRpiIp(): string | null {
  return localStorage.getItem(RPI_IP_STORAGE_KEY);
}
