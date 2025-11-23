import { useState } from "react";
import type { CameraConfig } from "../types";

interface CamerasProps {
  cameras: CameraConfig[];
  setCameras: React.Dispatch<React.SetStateAction<CameraConfig[]>>;
}

export default function Cameras({ cameras, setCameras }: CamerasProps) {
  const [isAdding, setIsAdding] = useState(false);
  const [newCamera, setNewCamera] = useState<Partial<CameraConfig>>({
    name: "",
    streamUrl: "",
    relatedEsp32: "",
    enabled: true,
  });

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
    <div className="container mx-auto px-4 py-6 space-y-6">
      <div className="bg-gradient-to-r from-emerald-500 to-green-600 rounded-2xl px-6 py-4 flex items-center justify-between">
        <div>
          <h1 className="text-white font-bold text-2xl">📷 카메라</h1>
          <p className="text-white/80 text-sm mt-1">
            RTSP/HTTP 스트림 카메라를 추가하고 관리합니다
          </p>
        </div>
        <button
          onClick={() => setIsAdding(true)}
          className="bg-white text-emerald-600 hover:bg-emerald-50 font-medium py-2 px-4 rounded-lg transition-colors"
        >
          + 카메라 추가
        </button>
      </div>

      {/* 카메라 추가 폼 */}
      {isAdding && (
        <div className="bg-white rounded-2xl shadow-md p-6">
          <h2 className="text-lg font-semibold text-gray-800 mb-4">
            새 카메라 추가
          </h2>
          <div className="space-y-4">
            <div>
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
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                스트림 URL
              </label>
              <input
                type="text"
                value={newCamera.streamUrl}
                onChange={(e) =>
                  setNewCamera({ ...newCamera, streamUrl: e.target.value })
                }
                placeholder="rtsp://... 또는 http://..."
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                관련 장치 (선택사항)
              </label>
              <input
                type="text"
                value={newCamera.relatedEsp32}
                onChange={(e) =>
                  setNewCamera({ ...newCamera, relatedEsp32: e.target.value })
                }
                placeholder="예: esp32-node-4"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
              />
            </div>
            <div className="flex gap-3">
              <button
                onClick={handleAddCamera}
                className="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2 px-4 rounded-lg transition-colors"
              >
                추가
              </button>
              <button
                onClick={() => setIsAdding(false)}
                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors"
              >
                취소
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 카메라 리스트 */}
      {cameras.length === 0 ? (
        <div className="bg-white rounded-2xl shadow-md p-12 text-center">
          <div className="text-gray-400 text-4xl mb-4">📷</div>
          <p className="text-gray-500">등록된 카메라가 없습니다.</p>
          <p className="text-gray-400 text-sm mt-2">
            상단의 "카메라 추가" 버튼을 눌러 카메라를 추가하세요.
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {cameras.map((camera) => (
            <div
              key={camera.id}
              className="bg-white rounded-2xl shadow-md overflow-hidden"
            >
              {/* 미리보기 영역 */}
              <div className="bg-gray-900 aspect-video flex items-center justify-center">
                {camera.streamUrl ? (
                  <div className="text-gray-400 text-sm text-center p-4">
                    <div className="text-3xl mb-2">📹</div>
                    <div className="text-xs break-all">{camera.streamUrl}</div>
                    <div className="text-xs mt-2 text-gray-500">
                      스트림 미리보기는 별도 플레이어가 필요합니다
                    </div>
                  </div>
                ) : (
                  <div className="text-gray-500 text-sm">URL 미설정</div>
                )}
              </div>

              {/* 카메라 정보 */}
              <div className="p-4">
                <div className="flex items-center justify-between mb-2">
                  <h3 className="font-semibold text-gray-800">{camera.name}</h3>
                  <label className="flex items-center gap-2 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={camera.enabled}
                      onChange={() => handleToggleEnabled(camera.id)}
                      className="w-4 h-4 text-emerald-600 focus:ring-emerald-500 rounded"
                    />
                    <span className="text-sm text-gray-600">활성</span>
                  </label>
                </div>
                {camera.relatedEsp32 && (
                  <div className="text-xs text-gray-500 mb-3">
                    관련 장치: {camera.relatedEsp32}
                  </div>
                )}
                <button
                  onClick={() => handleDeleteCamera(camera.id)}
                  className="w-full bg-red-50 hover:bg-red-100 text-red-600 font-medium py-2 px-4 rounded-lg transition-colors text-sm"
                >
                  삭제
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
