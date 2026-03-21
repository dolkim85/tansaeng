/**
 * CameraPage - Tapo 카메라 모니터링 페이지
 *
 * TP-Link Tapo 카메라 4대의 HLS 스트림을 2x2 그리드로 표시
 * - 반응형 레이아웃 (모바일: 1열, 데스크톱: 2열)
 * - 실시간 HLS 스트리밍
 * - 기존 스마트팜 UI와 독립적인 페이지
 */

import React from "react";
import TapoCameraView from "../components/camera/TapoCameraView";

const CameraPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gray-50 py-6">
      <div className="max-w-screen-2xl mx-auto px-4">
        {/* 헤더 */}
        <header className="bg-farm-500 rounded-lg px-6 py-4 mb-6 shadow-md">
          <h1 className="text-gray-900 font-bold text-2xl m-0">
            📹 Tapo 카메라 모니터링
          </h1>
          <p className="text-gray-800 text-sm mt-1 m-0">
            TP-Link Tapo 카메라 4대 실시간 HLS 스트리밍
          </p>
        </header>

        {/* 카메라 그리드 (2x2) */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <TapoCameraView cameraId={1} title="하우스 카메라 1" />
          <TapoCameraView cameraId={2} title="하우스 카메라 2" />
          <TapoCameraView cameraId={3} title="하우스 카메라 3" />
          <TapoCameraView cameraId={4} title="집 카메라" />
        </div>

        {/* 안내 메시지 */}
        <div className="mt-6 bg-white rounded-lg shadow-sm p-4">
          <h3 className="text-sm font-semibold text-gray-700 mb-2">
            📖 사용 안내
          </h3>
          <ul className="text-xs text-gray-600 space-y-1">
            <li>• 각 카메라는 실시간 HLS 스트리밍으로 재생됩니다</li>
            <li>• 자동 재생이 차단된 경우 재생 버튼을 클릭해주세요</li>
            <li>• 네트워크 오류 시 자동으로 재연결을 시도합니다</li>
            <li>
              • 스트림 URL은 .env 파일의 VITE_TAPO_CAM*_HLS_URL 변수로 설정합니다
            </li>
          </ul>
        </div>
      </div>
    </div>
  );
};

export default CameraPage;
