/**
 * 카메라 탭
 *
 * HLS 라이브 스트리밍을 지원하는 카메라 모니터링 페이지
 * - 4개의 기본 카메라 (cam1, cam2, cam3, cam4)
 * - 카메라 추가/삭제/수정 기능
 * - 라즈베리파이 IP 설정 기능
 */

import { useState } from "react";
import type { CameraConfig } from "../types";
import CameraLive from "../components/CameraLive";

interface CamerasProps {
  cameras: CameraConfig[];
  setCameras: React.Dispatch<React.SetStateAction<CameraConfig[]>>;
}

// .env 파일에서 라즈베리파이 기본 URL 가져오기
// 개발자는 .env 파일의 VITE_RPI_BASE_URL을 수정하세요
const DEFAULT_RPI_URL = import.meta.env.VITE_RPI_BASE_URL || "http://[라즈베리파이IP]";

export default function Cameras({ cameras, setCameras }: CamerasProps) {
  const [isAdding, setIsAdding] = useState(false);
  const [isEditingRpiUrl, setIsEditingRpiUrl] = useState(false);
  const [rpiBaseUrl, setRpiBaseUrl] = useState(
    localStorage.getItem("rpi_base_url") || DEFAULT_RPI_URL
  );
  const [tempRpiUrl, setTempRpiUrl] = useState(rpiBaseUrl);

  const [editingCamera, setEditingCamera] = useState<CameraConfig | null>(null);
  const [newCamera, setNewCamera] = useState<Partial<CameraConfig>>({
    name: "",
    streamUrl: "",
    relatedEsp32: "",
    enabled: true,
  });

  // 초기 카메라 설정 (cameras가 비어있을 때만)
  const defaultCameras: CameraConfig[] = [
    {
      id: "cam1",
      name: "하우스 카메라 1",
      streamUrl: `${rpiBaseUrl}/tapo/cam1/stream.m3u8`,
      enabled: true,
    },
    {
      id: "cam2",
      name: "하우스 카메라 2",
      streamUrl: `${rpiBaseUrl}/tapo/cam2/stream.m3u8`,
      enabled: true,
    },
    {
      id: "cam3",
      name: "하우스 카메라 3",
      streamUrl: `${rpiBaseUrl}/tapo/cam3/stream.m3u8`,
      enabled: true,
    },
    {
      id: "cam4",
      name: "집 카메라",
      streamUrl: "http://192.168.219.170/tapo/cam4/stream.m3u8",
      enabled: true,
    },
  ];

  // 첫 로드 시 기본 카메라가 없으면 추가
  if (cameras.length === 0) {
    setCameras(defaultCameras);
  }

  // 라즈베리파이 URL 저장
  const handleSaveRpiUrl = () => {
    const sanitizedUrl = tempRpiUrl.trim().replace(/\/$/, ""); // 끝의 / 제거
    setRpiBaseUrl(sanitizedUrl);
    localStorage.setItem("rpi_base_url", sanitizedUrl);

    // cam1, cam2, cam3의 URL 업데이트
    setCameras((prev) =>
      prev.map((cam) => {
        if (cam.id === "cam1" || cam.id === "cam2" || cam.id === "cam3") {
          const camNum = cam.id.replace("cam", "");
          return {
            ...cam,
            streamUrl: `${sanitizedUrl}/tapo/cam${camNum}/stream.m3u8`,
          };
        }
        return cam;
      })
    );

    setIsEditingRpiUrl(false);
    alert("라즈베리파이 URL이 저장되었습니다!");
  };

  const handleAddCamera = () => {
    if (!newCamera.name || !newCamera.streamUrl) {
      alert("카메라 이름과 스트림 URL을 입력해주세요.");
      return;
    }

    const camera: CameraConfig = {
      id: `camera_${Date.now()}`,
      name: newCamera.name,
      streamUrl: newCamera.streamUrl,
      relatedEsp32: newCamera.relatedEsp32,
      enabled: newCamera.enabled ?? true,
    };

    setCameras((prev) => [...prev, camera]);
    setNewCamera({ name: "", streamUrl: "", relatedEsp32: "", enabled: true });
    setIsAdding(false);
  };

  const handleUpdateCamera = () => {
    if (!editingCamera) return;

    setCameras((prev) =>
      prev.map((cam) =>
        cam.id === editingCamera.id ? editingCamera : cam
      )
    );
    setEditingCamera(null);
    alert("카메라 정보가 수정되었습니다!");
  };

  const handleDeleteCamera = (id: string) => {
    if (confirm("이 카메라를 삭제하시겠습니까?")) {
      setCameras((prev) => prev.filter((cam) => cam.id !== id));
    }
  };

  const handleToggleEnabled = (id: string) => {
    setCameras((prev) =>
      prev.map((cam) =>
        cam.id === id ? { ...cam, enabled: !cam.enabled } : cam
      )
    );
  };

  return (
    <div className="bg-gray-50 pb-6">
      <div className="max-w-screen-2xl mx-auto px-4">
        {/* 헤더 */}
        <header className="bg-farm-500 rounded-lg px-6 py-4 mb-6 shadow-md">
          <div className="flex items-center justify-between flex-wrap gap-3">
            <div>
              <h1 className="text-gray-900 font-bold text-2xl m-0">📷 카메라 라이브 모니터링</h1>
              <p className="text-gray-800 text-sm mt-1 m-0">
                HLS 스트리밍으로 실시간 카메라 영상을 확인합니다
              </p>
            </div>
            <div className="flex gap-2">
              <button
                onClick={() => setIsEditingRpiUrl(true)}
                className="bg-white hover:bg-farm-50 text-farm-700 font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5 text-sm"
              >
                🔧 라즈베리파이 IP 설정
              </button>
              <button
                onClick={() => setIsAdding(true)}
                className="bg-white hover:bg-farm-50 text-farm-700 font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200 hover:-translate-y-0.5"
              >
                + 카메라 추가
              </button>
            </div>
          </div>
        </header>

        {/* 라즈베리파이 IP 설정 모달 */}
        {isEditingRpiUrl && (
          <div className="bg-white rounded-lg shadow-card p-6 mb-6 border-2 border-farm-500">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">
              🔧 라즈베리파이 기본 URL 설정
            </h2>
            <p className="text-sm text-gray-600 mb-4">
              cam1, cam2, cam3 카메라의 기본 URL입니다. 라즈베리파이 IP 주소를 입력하세요.
            </p>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-2">
                기본 URL (예: http://192.168.0.100)
              </label>
              <input
                type="text"
                value={tempRpiUrl}
                onChange={(e) => setTempRpiUrl(e.target.value)}
                placeholder="http://192.168.0.100"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg text-base"
              />
              <div className="mt-2 text-xs text-gray-500">
                현재 설정: <code className="bg-gray-100 px-2 py-1 rounded">{rpiBaseUrl}</code>
              </div>
            </div>
            <div className="flex gap-3">
              <button
                onClick={handleSaveRpiUrl}
                className="flex-1 bg-farm-500 hover:bg-farm-600 text-gray-900 font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200"
              >
                저장
              </button>
              <button
                onClick={() => {
                  setTempRpiUrl(rpiBaseUrl);
                  setIsEditingRpiUrl(false);
                }}
                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200"
              >
                취소
              </button>
            </div>
          </div>
        )}

        {/* 카메라 추가 폼 */}
        {isAdding && (
          <div className="bg-white rounded-lg shadow-card p-6 mb-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">
              새 카메라 추가
            </h2>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                카메라 이름
              </label>
              <input
                type="text"
                value={newCamera.name}
                onChange={(e) =>
                  setNewCamera({ ...newCamera, name: e.target.value })
                }
                placeholder="예: 온실 입구 카메라"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
              />
            </div>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                HLS 스트림 URL
              </label>
              <input
                type="text"
                value={newCamera.streamUrl}
                onChange={(e) =>
                  setNewCamera({ ...newCamera, streamUrl: e.target.value })
                }
                placeholder="http://192.168.0.100/tapo/cam5/stream.m3u8"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
              />
            </div>
            <div className="flex gap-3">
              <button
                onClick={handleAddCamera}
                className="flex-1 bg-farm-500 hover:bg-farm-600 text-gray-900 font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200"
              >
                추가
              </button>
              <button
                onClick={() => setIsAdding(false)}
                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200"
              >
                취소
              </button>
            </div>
          </div>
        )}

        {/* 카메라 수정 폼 */}
        {editingCamera && (
          <div className="bg-white rounded-lg shadow-card p-6 mb-6 border-2 border-blue-500">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">
              카메라 수정
            </h2>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                카메라 이름
              </label>
              <input
                type="text"
                value={editingCamera.name}
                onChange={(e) =>
                  setEditingCamera({ ...editingCamera, name: e.target.value })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
              />
            </div>
            <div className="mb-4">
              <label className="block text-sm font-medium text-gray-700 mb-1">
                HLS 스트림 URL
              </label>
              <input
                type="text"
                value={editingCamera.streamUrl}
                onChange={(e) =>
                  setEditingCamera({ ...editingCamera, streamUrl: e.target.value })
                }
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-base"
              />
            </div>
            <div className="flex gap-3">
              <button
                onClick={handleUpdateCamera}
                className="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200"
              >
                수정 완료
              </button>
              <button
                onClick={() => setEditingCamera(null)}
                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium px-4 py-2 rounded-lg border-none cursor-pointer transition-all duration-200"
              >
                취소
              </button>
            </div>
          </div>
        )}

        {/* 카메라 라이브 그리드 (2x2) */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {cameras
            .filter((cam) => cam.enabled)
            .map((camera) => (
              <div key={camera.id} className="relative">
                <CameraLive
                  src={camera.streamUrl}
                  title={camera.name}
                />

                {/* 카메라 컨트롤 버튼 */}
                <div className="flex gap-2 mt-2">
                  <button
                    onClick={() => setEditingCamera(camera)}
                    className="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 font-medium px-3 py-1.5 rounded border-none cursor-pointer text-sm transition-all duration-200"
                  >
                    ✏️ 수정
                  </button>
                  <button
                    onClick={() => handleToggleEnabled(camera.id)}
                    className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-3 py-1.5 rounded border-none cursor-pointer text-sm transition-all duration-200"
                  >
                    {camera.enabled ? "🔇 숨기기" : "🔊 보이기"}
                  </button>
                  <button
                    onClick={() => handleDeleteCamera(camera.id)}
                    className="flex-1 bg-red-50 hover:bg-red-100 text-red-600 font-medium px-3 py-1.5 rounded border-none cursor-pointer text-sm transition-all duration-200"
                  >
                    🗑️ 삭제
                  </button>
                </div>
              </div>
            ))}
        </div>

        {/* 비활성화된 카메라 목록 */}
        {cameras.filter((cam) => !cam.enabled).length > 0 && (
          <div className="mt-6">
            <h3 className="text-sm font-semibold text-gray-600 mb-3">
              비활성화된 카메라
            </h3>
            <div className="flex flex-wrap gap-2">
              {cameras
                .filter((cam) => !cam.enabled)
                .map((camera) => (
                  <button
                    key={camera.id}
                    onClick={() => handleToggleEnabled(camera.id)}
                    className="bg-gray-200 hover:bg-farm-100 text-gray-700 px-3 py-1.5 rounded text-sm border-none cursor-pointer transition-all duration-200"
                  >
                    {camera.name} (클릭하여 활성화)
                  </button>
                ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
