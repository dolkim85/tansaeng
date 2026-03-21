/**
 * TapoCameraView 컴포넌트
 *
 * TP-Link Tapo 카메라의 HLS 스트림을 재생하는 컴포넌트
 * - hls.js를 사용하여 HLS 스트림 재생
 * - 브라우저 네이티브 HLS 지원도 처리 (Safari 등)
 * - 로딩 및 에러 상태 표시
 *
 * 사용법:
 * <TapoCameraView cameraId={1} title="하우스 카메라 1" />
 */

import { useEffect, useRef, useState } from "react";
import Hls from "hls.js";

export interface TapoCameraViewProps {
  cameraId: 1 | 2 | 3 | 4;
  title?: string;
}

export default function TapoCameraView({ cameraId, title }: TapoCameraViewProps) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const hlsRef = useRef<Hls | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  // 카메라 ID에 따라 HLS URL 선택
  const urlMap = {
    1: import.meta.env.VITE_TAPO_CAM1_HLS_URL,
    2: import.meta.env.VITE_TAPO_CAM2_HLS_URL,
    3: import.meta.env.VITE_TAPO_CAM3_HLS_URL,
    4: import.meta.env.VITE_TAPO_CAM4_HLS_URL,
  };

  const hlsUrl = urlMap[cameraId];

  useEffect(() => {
    const video = videoRef.current;
    if (!video || !hlsUrl) {
      setError("스트림 URL이 설정되지 않았습니다.");
      setLoading(false);
      return;
    }

    setError(null);
    setLoading(true);

    // HLS.js를 사용할 수 있는 경우 (대부분의 브라우저)
    if (Hls.isSupported()) {
      const hls = new Hls({
        // 라이브 스트림 최적화: 백버퍼를 0으로 설정하여 지연 최소화
        liveBackBufferLength: 0,
        liveSyncDurationCount: 3,
        liveMaxLatencyDurationCount: 5,
        enableWorker: true,
        lowLatencyMode: true,
      });

      hlsRef.current = hls;

      hls.loadSource(hlsUrl);
      hls.attachMedia(video);

      hls.on(Hls.Events.MANIFEST_PARSED, () => {
        setLoading(false);
        // 자동 재생 시도
        video.play().catch((err) => {
          console.warn("Auto-play failed:", err);
          setError("재생 버튼을 클릭해주세요");
        });
      });

      hls.on(Hls.Events.ERROR, (_event, data) => {
        console.error("HLS error:", data);
        if (data.fatal) {
          switch (data.type) {
            case Hls.ErrorTypes.NETWORK_ERROR:
              setError("네트워크 오류: 스트림에 연결할 수 없습니다");
              // 네트워크 오류 시 재시도
              setTimeout(() => {
                hls.startLoad();
              }, 3000);
              break;
            case Hls.ErrorTypes.MEDIA_ERROR:
              setError("미디어 오류: 스트림 재생 중 문제 발생");
              hls.recoverMediaError();
              break;
            default:
              setError("치명적 오류: 스트림을 로드할 수 없습니다");
              hls.destroy();
              break;
          }
        }
      });

      return () => {
        hls.destroy();
        hlsRef.current = null;
      };
    }
    // Safari 등 네이티브 HLS 지원 브라우저
    else if (video.canPlayType("application/vnd.apple.mpegurl")) {
      video.src = hlsUrl;
      video.addEventListener("loadedmetadata", () => {
        setLoading(false);
        video.play().catch((err) => {
          console.warn("Auto-play failed:", err);
          setError("재생 버튼을 클릭해주세요");
        });
      });

      video.addEventListener("error", () => {
        setError("비디오 로드 실패");
      });

      return () => {
        video.src = "";
      };
    } else {
      setError("이 브라우저는 HLS 스트리밍을 지원하지 않습니다");
      setLoading(false);
    }
  }, [hlsUrl]);

  return (
    <div className="bg-white rounded-lg shadow-card overflow-hidden">
      {/* 제목 */}
      {title && (
        <div className="bg-farm-500 px-4 py-2">
          <h3 className="text-base font-semibold text-gray-900 m-0">{title}</h3>
        </div>
      )}

      {/* 비디오 영역 */}
      <div className="relative bg-black aspect-video">
        <video
          ref={videoRef}
          className="w-full h-full"
          controls
          autoPlay
          muted
          playsInline
        />

        {/* 로딩 오버레이 */}
        {loading && (
          <div className="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50">
            <div className="text-white text-center">
              <div className="text-2xl mb-2">⏳</div>
              <div className="text-sm">스트림 로딩 중...</div>
            </div>
          </div>
        )}

        {/* 에러 오버레이 */}
        {error && (
          <div className="absolute inset-0 flex items-center justify-center bg-black bg-opacity-70">
            <div className="text-white text-center p-4">
              <div className="text-3xl mb-2">⚠️</div>
              <div className="text-sm">{error}</div>
            </div>
          </div>
        )}
      </div>

      {/* 스트림 URL 표시 */}
      <div className="px-4 py-2 bg-gray-50 border-t border-gray-200">
        <div className="text-xs text-gray-500 truncate" title={hlsUrl}>
          📡 {hlsUrl}
        </div>
      </div>
    </div>
  );
}
