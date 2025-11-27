/**
 * 카메라 탭 - 정적 4개 카메라 HLS 라이브 스트리밍
 *
 * Tapo 카메라 4대의 실시간 HLS 스트림을 2x2 그리드로 표시
 */

import TapoCameraView from "../components/camera/TapoCameraView";
import RpiIpSettings from "../components/camera/RpiIpSettings";

// App.tsx와의 호환성을 위해 props 타입 유지 (사용하지 않음)
interface CamerasProps {
  cameras?: any;
  setCameras?: any;
}

export default function Cameras(_props: CamerasProps) {
  return (
    <div className="p-4">
      <h1 className="mb-4 text-2xl font-bold text-slate-100">
        📷 카메라 라이브 모니터링
      </h1>

      {/* 라즈베리파이 IP 설정 */}
      <RpiIpSettings />

      {/* 카메라 그리드 */}
      <div className="grid gap-4 md:grid-cols-2">
        <TapoCameraView cameraId={1} title="하우스 카메라 1" />
        <TapoCameraView cameraId={2} title="하우스 카메라 2" />
        <TapoCameraView cameraId={3} title="하우스 카메라 3" />
        <TapoCameraView cameraId={4} title="집 카메라" />
      </div>
    </div>
  );
}
