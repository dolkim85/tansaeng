/**
 * 카메라 탭
 *
 * HLS 라이브 스트리밍을 지원하는 카메라 모니터링 페이지
 * - 4개의 기본 카메라 (cam1, cam2, cam3, cam4)
 * - 각 카메라마다 이름/URL 직접 수정 가능
 * - 라즈베리파이 IP 설정 기능
 */

import { useState, useEffect } from "react";
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
  const [isEditingRpiUrl, setIsEditingRpiUrl] = useState(false);
  const [rpiBaseUrl, setRpiBaseUrl] = useState(
    localStorage.getItem("rpi_base_url") || DEFAULT_RPI_URL
  );
  const [tempRpiUrl, setTempRpiUrl] = useState(rpiBaseUrl);

  // 각 카메라별 편집 상태 (카메라 ID를 키로 사용)
  const [editingStates, setEditingStates] = useState<{
    [key: string]: { name: string; streamUrl: string };
  }>({});

  // 초기 카메라 설정 - useEffect로 안전하게 처리
  useEffect(() => {
    if (cameras.length === 0) {
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
      setCameras(defaultCameras);
    }
  }, [cameras.length, rpiBaseUrl, setCameras]);

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

  // 개별 카메라 수정 시작
  const handleStartEdit = (camera: CameraConfig) => {
    setEditingStates({
      ...editingStates,
      [camera.id]: {
        name: camera.name,
        streamUrl: camera.streamUrl,
      },
    });
  };

  // 개별 카메라 수정 저장
  const handleSaveCamera = (cameraId: string) => {
    const editState = editingStates[cameraId];
    if (!editState) return;

    if (!editState.name.trim() || !editState.streamUrl.trim()) {
      alert("카메라 이름과 스트림 URL을 입력해주세요.");
      return;
    }

    setCameras((prev) =>
      prev.map((cam) =>
        cam.id === cameraId
          ? { ...cam, name: editState.name, streamUrl: editState.streamUrl }
          : cam
      )
    );

    // 편집 상태 제거
    const newEditingStates = { ...editingStates };
    delete newEditingStates[cameraId];
    setEditingStates(newEditingStates);

    alert("카메라 정보가 저장되었습니다!");
  };

  // 개별 카메라 수정 취소
  const handleCancelEdit = (cameraId: string) => {
    const newEditingStates = { ...editingStates };
    delete newEditingStates[cameraId];
    setEditingStates(newEditingStates);
  };

  // 카메라 삭제
  const handleDeleteCamera = (id: string) => {
    if (confirm("이 카메라를 삭제하시겠습니까?")) {
      setCameras((prev) => prev.filter((cam) => cam.id !== id));
    }
  };

  // 카메라 활성/비활성 토글
  const handleToggleEnabled = (id: string) => {
    setCameras((prev) =>
      prev.map((cam) =>
        cam.id === id ? { ...cam, enabled: !cam.enabled } : cam
      )
    );
  };

  // 편집 중인지 확인
  const isEditing = (cameraId: string) => cameraId in editingStates;

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

        {/* 카메라 라이브 그리드 (2x2) */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {cameras.map((camera) => (
            <div key={camera.id} className="relative">
              {/* 카메라 라이브 영상 또는 비활성 메시지 */}
              {camera.enabled ? (
                <CameraLive
                  src={camera.streamUrl}
                  title={camera.name}
                />
              ) : (
                <div className="bg-gray-800 rounded-lg overflow-hidden">
                  <div className="bg-farm-500 px-4 py-2">
                    <h2 className="text-base font-semibold text-gray-900 m-0">{camera.name}</h2>
                  </div>
                  <div className="aspect-video flex items-center justify-center bg-gray-900">
                    <div className="text-center text-gray-400">
                      <div className="text-4xl mb-2">📵</div>
                      <div className="text-sm">비활성화된 카메라입니다</div>
                    </div>
                  </div>
                </div>
              )}

              {/* 카메라 설정 폼 */}
              <div className="bg-white rounded-lg shadow-sm p-4 mt-3 border border-gray-200">
                {!isEditing(camera.id) ? (
                  // 일반 모드 - 정보 표시
                  <>
                    <div className="mb-3">
                      <div className="text-xs text-gray-500 mb-1">카메라 이름</div>
                      <div className="text-sm font-medium text-gray-900">{camera.name}</div>
                    </div>
                    <div className="mb-3">
                      <div className="text-xs text-gray-500 mb-1">스트림 URL</div>
                      <div className="text-xs text-gray-700 break-all font-mono bg-gray-50 p-2 rounded">
                        {camera.streamUrl}
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleStartEdit(camera)}
                        className="flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 font-medium px-3 py-2 rounded border-none cursor-pointer text-sm transition-all duration-200"
                      >
                        ✏️ 수정
                      </button>
                      <button
                        onClick={() => handleToggleEnabled(camera.id)}
                        className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-3 py-2 rounded border-none cursor-pointer text-sm transition-all duration-200"
                      >
                        {camera.enabled ? "🔇 비활성" : "🔊 활성"}
                      </button>
                      <button
                        onClick={() => handleDeleteCamera(camera.id)}
                        className="flex-1 bg-red-50 hover:bg-red-100 text-red-600 font-medium px-3 py-2 rounded border-none cursor-pointer text-sm transition-all duration-200"
                      >
                        🗑️ 삭제
                      </button>
                    </div>
                  </>
                ) : (
                  // 편집 모드 - 입력 폼
                  <>
                    <div className="mb-3">
                      <label className="block text-xs font-medium text-gray-700 mb-1">
                        카메라 이름
                      </label>
                      <input
                        type="text"
                        value={editingStates[camera.id].name}
                        onChange={(e) =>
                          setEditingStates({
                            ...editingStates,
                            [camera.id]: {
                              ...editingStates[camera.id],
                              name: e.target.value,
                            },
                          })
                        }
                        placeholder="예: 하우스 카메라 1"
                        className="w-full px-3 py-2 border border-gray-300 rounded text-sm"
                      />
                    </div>
                    <div className="mb-3">
                      <label className="block text-xs font-medium text-gray-700 mb-1">
                        스트림 URL
                      </label>
                      <input
                        type="text"
                        value={editingStates[camera.id].streamUrl}
                        onChange={(e) =>
                          setEditingStates({
                            ...editingStates,
                            [camera.id]: {
                              ...editingStates[camera.id],
                              streamUrl: e.target.value,
                            },
                          })
                        }
                        placeholder="http://192.168.0.100/tapo/cam1/stream.m3u8"
                        className="w-full px-3 py-2 border border-gray-300 rounded text-sm font-mono"
                      />
                    </div>
                    <div className="flex gap-2">
                      <button
                        onClick={() => handleSaveCamera(camera.id)}
                        className="flex-1 bg-green-500 hover:bg-green-600 text-white font-medium px-3 py-2 rounded border-none cursor-pointer text-sm transition-all duration-200"
                      >
                        💾 저장
                      </button>
                      <button
                        onClick={() => handleCancelEdit(camera.id)}
                        className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium px-3 py-2 rounded border-none cursor-pointer text-sm transition-all duration-200"
                      >
                        ✖️ 취소
                      </button>
                    </div>
                  </>
                )}
              </div>
            </div>
          ))}
        </div>

        {/* 카메라가 없을 때 메시지 */}
        {cameras.length === 0 && (
          <div className="bg-white rounded-lg shadow-card p-12 text-center">
            <div className="text-6xl mb-4">📷</div>
            <p className="text-gray-600 text-lg">
              카메라를 불러오는 중입니다...
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
