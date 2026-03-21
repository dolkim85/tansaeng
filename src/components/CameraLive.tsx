/**
 * CameraLive 컴포넌트
 *
 * HLS (HTTP Live Streaming) 프로토콜을 사용하여 라이브 카메라 영상을 재생합니다.
 * hls.js 라이브러리를 사용하며, Safari 등 네이티브 HLS 지원 브라우저도 처리합니다.
 *
 * 사용법:
 * <CameraLive
 *   src="http://192.168.0.100/tapo/cam1/stream.m3u8"
 *   title="하우스 카메라 1"
 * />
 */

import { useEffect, useRef, useState } from "react";
import Hls from "hls.js";

interface CameraLiveProps {
  src: string;   // HLS m3u8 URL
  title?: string;
}

export default function CameraLive({ src, title }: CameraLiveProps) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const hlsRef = useRef<Hls | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const video = videoRef.current;
    if (!video || !src) return;

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

      hls.loadSource(src);
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
      video.src = src;
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
  }, [src]);

  return (
    <div className="bg-white rounded-lg shadow-card overflow-hidden">
      {/* 제목 */}
      {title && (
        <div className="bg-farm-500 px-4 py-2">
          <h2 className="text-base font-semibold text-gray-900 m-0">{title}</h2>
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
        <div className="text-xs text-gray-500 truncate" title={src}>
          📡 {src}
        </div>
      </div>
    </div>
  );
}
