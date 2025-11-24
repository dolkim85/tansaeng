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
    <div style={{ background: "#f9fafb" }}>
      <div style={{
        maxWidth: "1200px",
        margin: "0 auto",
        padding: "0 16px"
      }}>
        <div style={{
          background: "linear-gradient(to right, #10b981, #059669)",
          borderRadius: "16px",
          padding: "16px 24px",
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          marginBottom: "24px"
        }}>
          <div>
            <h1 style={{
              color: "white",
              fontWeight: "700",
              fontSize: "1.5rem",
              margin: 0
            }}>📷 카메라</h1>
            <p style={{
              color: "rgba(255, 255, 255, 0.8)",
              fontSize: "0.875rem",
              marginTop: "4px",
              margin: 0
            }}>
              RTSP/HTTP 스트림 카메라를 추가하고 관리합니다
            </p>
          </div>
          <button
            onClick={() => setIsAdding(true)}
            style={{
              background: "white",
              color: "#10b981",
              fontWeight: "500",
              padding: "8px 16px",
              borderRadius: "8px",
              border: "none",
              cursor: "pointer",
              transition: "background 0.2s"
            }}
            onMouseEnter={(e) => e.currentTarget.style.background = "#d1fae5"}
            onMouseLeave={(e) => e.currentTarget.style.background = "white"}
          >
            + 카메라 추가
          </button>
        </div>

        {/* 카메라 추가 폼 */}
        {isAdding && (
          <div style={{
            background: "white",
            borderRadius: "16px",
            boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
            padding: "24px",
            marginBottom: "24px"
          }}>
            <h2 style={{
              fontSize: "1.125rem",
              fontWeight: "600",
              color: "#1f2937",
              marginBottom: "16px"
            }}>
              새 카메라 추가
            </h2>
            <div>
              <div style={{ marginBottom: "16px" }}>
                <label style={{
                  display: "block",
                  fontSize: "0.875rem",
                  fontWeight: "500",
                  color: "#374151",
                  marginBottom: "4px"
                }}>
                  카메라 이름
                </label>
                <input
                  type="text"
                  value={newCamera.name}
                  onChange={(e) =>
                    setNewCamera({ ...newCamera, name: e.target.value })
                  }
                  placeholder="예: 온실 입구 카메라"
                  style={{
                    width: "100%",
                    padding: "8px 12px",
                    border: "1px solid #d1d5db",
                    borderRadius: "8px",
                    fontSize: "1rem"
                  }}
                />
              </div>
              <div style={{ marginBottom: "16px" }}>
                <label style={{
                  display: "block",
                  fontSize: "0.875rem",
                  fontWeight: "500",
                  color: "#374151",
                  marginBottom: "4px"
                }}>
                  스트림 URL
                </label>
                <input
                  type="text"
                  value={newCamera.streamUrl}
                  onChange={(e) =>
                    setNewCamera({ ...newCamera, streamUrl: e.target.value })
                  }
                  placeholder="rtsp://... 또는 http://..."
                  style={{
                    width: "100%",
                    padding: "8px 12px",
                    border: "1px solid #d1d5db",
                    borderRadius: "8px",
                    fontSize: "1rem"
                  }}
                />
              </div>
              <div style={{ marginBottom: "16px" }}>
                <label style={{
                  display: "block",
                  fontSize: "0.875rem",
                  fontWeight: "500",
                  color: "#374151",
                  marginBottom: "4px"
                }}>
                  관련 장치 (선택사항)
                </label>
                <input
                  type="text"
                  value={newCamera.relatedEsp32}
                  onChange={(e) =>
                    setNewCamera({ ...newCamera, relatedEsp32: e.target.value })
                  }
                  placeholder="예: esp32-node-4"
                  style={{
                    width: "100%",
                    padding: "8px 12px",
                    border: "1px solid #d1d5db",
                    borderRadius: "8px",
                    fontSize: "1rem"
                  }}
                />
              </div>
              <div style={{ display: "flex", gap: "12px" }}>
                <button
                  onClick={handleAddCamera}
                  style={{
                    flex: 1,
                    background: "#10b981",
                    color: "white",
                    fontWeight: "500",
                    padding: "8px 16px",
                    borderRadius: "8px",
                    border: "none",
                    cursor: "pointer",
                    transition: "background 0.2s"
                  }}
                  onMouseEnter={(e) => e.currentTarget.style.background = "#059669"}
                  onMouseLeave={(e) => e.currentTarget.style.background = "#10b981"}
                >
                  추가
                </button>
                <button
                  onClick={() => setIsAdding(false)}
                  style={{
                    flex: 1,
                    background: "#e5e7eb",
                    color: "#374151",
                    fontWeight: "500",
                    padding: "8px 16px",
                    borderRadius: "8px",
                    border: "none",
                    cursor: "pointer",
                    transition: "background 0.2s"
                  }}
                  onMouseEnter={(e) => e.currentTarget.style.background = "#d1d5db"}
                  onMouseLeave={(e) => e.currentTarget.style.background = "#e5e7eb"}
                >
                  취소
                </button>
              </div>
            </div>
          </div>
        )}

        {/* 카메라 리스트 */}
        {cameras.length === 0 ? (
          <div style={{
            background: "white",
            borderRadius: "16px",
            boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
            padding: "48px",
            textAlign: "center"
          }}>
            <div style={{
              color: "#9ca3af",
              fontSize: "2.25rem",
              marginBottom: "16px"
            }}>📷</div>
            <p style={{
              color: "#6b7280",
              margin: "0 0 8px 0"
            }}>등록된 카메라가 없습니다.</p>
            <p style={{
              color: "#9ca3af",
              fontSize: "0.875rem",
              margin: 0
            }}>
              상단의 "카메라 추가" 버튼을 눌러 카메라를 추가하세요.
            </p>
          </div>
        ) : (
          <div style={{
            display: "grid",
            gridTemplateColumns: "repeat(auto-fill, minmax(300px, 1fr))",
            gap: "24px"
          }}>
            {cameras.map((camera) => (
              <div
                key={camera.id}
                style={{
                  background: "white",
                  borderRadius: "16px",
                  boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
                  overflow: "hidden"
                }}
              >
                {/* 미리보기 영역 */}
                <div style={{
                  background: "#111827",
                  aspectRatio: "16 / 9",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center"
                }}>
                  {camera.streamUrl ? (
                    <div style={{
                      color: "#9ca3af",
                      fontSize: "0.875rem",
                      textAlign: "center",
                      padding: "16px"
                    }}>
                      <div style={{
                        fontSize: "1.875rem",
                        marginBottom: "8px"
                      }}>📹</div>
                      <div style={{
                        fontSize: "0.75rem",
                        wordBreak: "break-all"
                      }}>{camera.streamUrl}</div>
                      <div style={{
                        fontSize: "0.75rem",
                        marginTop: "8px",
                        color: "#6b7280"
                      }}>
                        스트림 미리보기는 별도 플레이어가 필요합니다
                      </div>
                    </div>
                  ) : (
                    <div style={{
                      color: "#6b7280",
                      fontSize: "0.875rem"
                    }}>URL 미설정</div>
                  )}
                </div>

                {/* 카메라 정보 */}
                <div style={{ padding: "16px" }}>
                  <div style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    marginBottom: "8px"
                  }}>
                    <h3 style={{
                      fontWeight: "600",
                      color: "#1f2937",
                      margin: 0
                    }}>{camera.name}</h3>
                    <label style={{
                      display: "flex",
                      alignItems: "center",
                      gap: "8px",
                      cursor: "pointer"
                    }}>
                      <input
                        type="checkbox"
                        checked={camera.enabled}
                        onChange={() => handleToggleEnabled(camera.id)}
                        style={{
                          width: "16px",
                          height: "16px",
                          accentColor: "#10b981"
                        }}
                      />
                      <span style={{
                        fontSize: "0.875rem",
                        color: "#4b5563"
                      }}>활성</span>
                    </label>
                  </div>
                  {camera.relatedEsp32 && (
                    <div style={{
                      fontSize: "0.75rem",
                      color: "#6b7280",
                      marginBottom: "12px"
                    }}>
                      관련 장치: {camera.relatedEsp32}
                    </div>
                  )}
                  <button
                    onClick={() => handleDeleteCamera(camera.id)}
                    style={{
                      width: "100%",
                      background: "#fef2f2",
                      color: "#dc2626",
                      fontWeight: "500",
                      padding: "8px 16px",
                      borderRadius: "8px",
                      border: "none",
                      cursor: "pointer",
                      fontSize: "0.875rem",
                      transition: "background 0.2s"
                    }}
                    onMouseEnter={(e) => e.currentTarget.style.background = "#fee2e2"}
                    onMouseLeave={(e) => e.currentTarget.style.background = "#fef2f2"}
                  >
                    삭제
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
