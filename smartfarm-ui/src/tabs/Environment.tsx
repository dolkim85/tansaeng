import { useState, useEffect } from "react";
import type { SensorSnapshot } from "../types";
import { getMqttClient, onConnectionChange } from "../mqtt/mqttClient";
import GaugeCard from "../components/GaugeCard";
import SensorRow from "../components/SensorRow";

export default function Environment() {
  const [period, setPeriod] = useState("24h");
  const [selectedZone, setSelectedZone] = useState("all");
  const [mqttConnected, setMqttConnected] = useState(false);

  const [currentValues, setCurrentValues] = useState<Partial<SensorSnapshot>>({
    airTemp: null,
    airHumidity: null,
    rootTemp: null,
    rootHumidity: null,
    ec: null,
    ph: null,
    tankLevel: null,
    co2: null,
    ppfd: null,
  });

  // MQTT 연결 상태 감지
  useEffect(() => {
    const unsubscribe = onConnectionChange((connected) => {
      setMqttConnected(connected);
    });

    return unsubscribe;
  }, []);

  // MQTT 구독 - ESP32 DHT11 센서 데이터
  useEffect(() => {
    const client = getMqttClient();

    const tempTopic = "tansaeng/ctlr-0001/dht11/temperature";
    const humTopic = "tansaeng/ctlr-0001/dht11/humidity";

    const handleMessage = (topic: string, message: Buffer) => {
      const value = parseFloat(message.toString());

      if (topic === tempTopic) {
        setCurrentValues((prev) => ({ ...prev, airTemp: value }));
      } else if (topic === humTopic) {
        setCurrentValues((prev) => ({ ...prev, airHumidity: value }));
      }
    };

    client.on("message", handleMessage);
    client.subscribe(tempTopic);
    client.subscribe(humTopic);

    return () => {
      client.off("message", handleMessage);
      client.unsubscribe(tempTopic);
      client.unsubscribe(humTopic);
    };
  }, []);

  const [chartData] = useState<SensorSnapshot[]>([]);

  return (
    <div style={{ background: "#f9fafb" }}>
      <div style={{
        maxWidth: "1200px",
        margin: "0 auto",
        padding: "0 16px"
      }}>
        {/* 페이지 헤더 */}
        <header style={{
          background: "linear-gradient(to right, #10b981, #059669)",
          color: "white",
          padding: "16px 24px",
          borderRadius: "12px",
          marginBottom: "24px"
        }}>
          <div style={{
            display: "flex",
            alignItems: "center",
            justifyContent: "space-between"
          }}>
            <div>
              <h1 style={{
                fontSize: "1.5rem",
                fontWeight: "700",
                margin: 0
              }}>📊 환경 모니터링</h1>
              <p style={{
                fontSize: "0.875rem",
                opacity: 0.8,
                marginTop: "4px",
                margin: 0
              }}>
                온도, 습도, EC, pH 등 센서 데이터를 실시간으로 모니터링합니다
              </p>
            </div>
            {/* ESP32 연결 상태 */}
            <div style={{
              display: "flex",
              alignItems: "center",
              gap: "8px",
              background: "rgba(255, 255, 255, 0.2)",
              padding: "8px 16px",
              borderRadius: "8px"
            }}>
              <div style={{
                width: "12px",
                height: "12px",
                borderRadius: "50%",
                background: mqttConnected ? "#86efac" : "#fca5a5",
                animation: mqttConnected ? "pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite" : "none"
              }}></div>
              <span style={{
                fontSize: "0.875rem",
                fontWeight: "500"
              }}>
                {mqttConnected ? 'ESP32 연결됨' : 'ESP32 연결 끊김'}
              </span>
            </div>
          </div>
        </header>

        {/* 필터 섹션 */}
        <section style={{ marginBottom: "24px" }}>
          <header style={{
            background: "linear-gradient(to right, #10b981, #059669)",
            color: "white",
            padding: "16px 24px",
            borderRadius: "12px 12px 0 0"
          }}>
            <h2 style={{
              fontSize: "1.25rem",
              fontWeight: "600",
              margin: 0
            }}>🔍 조회 조건</h2>
          </header>
          <div style={{
            background: "white",
            borderRadius: "0 0 12px 12px",
            boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
            padding: "24px"
          }}>
            <div style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(250px, 1fr))",
              gap: "16px"
            }}>
              <div>
                <label style={{
                  display: "block",
                  fontSize: "0.875rem",
                  fontWeight: "500",
                  color: "#374151",
                  marginBottom: "8px"
                }}>
                  기간
                </label>
                <select
                  value={period}
                  onChange={(e) => setPeriod(e.target.value)}
                  style={{
                    width: "100%",
                    padding: "8px 16px",
                    border: "1px solid #d1d5db",
                    borderRadius: "8px",
                    fontSize: "1rem"
                  }}
                >
                  <option value="1h">최근 1시간</option>
                  <option value="today">오늘</option>
                  <option value="24h">24시간</option>
                  <option value="7d">7일</option>
                </select>
              </div>
              <div>
                <label style={{
                  display: "block",
                  fontSize: "0.875rem",
                  fontWeight: "500",
                  color: "#374151",
                  marginBottom: "8px"
                }}>
                  Zone
                </label>
                <select
                  value={selectedZone}
                  onChange={(e) => setSelectedZone(e.target.value)}
                  style={{
                    width: "100%",
                    padding: "8px 16px",
                    border: "1px solid #d1d5db",
                    borderRadius: "8px",
                    fontSize: "1rem"
                  }}
                >
                  <option value="all">전체</option>
                  <option value="zone_a">Zone A (상층)</option>
                  <option value="zone_b">Zone B (하층)</option>
                  <option value="zone_c">Zone C (테스트베드)</option>
                </select>
              </div>
            </div>
          </div>
        </section>

        {/* 온도/습도 게이지 카드 */}
        <div style={{
          display: "grid",
          gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))",
          gap: "24px",
          marginBottom: "24px"
        }}>
          <GaugeCard
            icon="🌡️"
            title="공기 온도"
            value={currentValues.airTemp}
            unit="°C"
            maxValue={50}
            color="green"
          />
          <GaugeCard
            icon="💧"
            title="공기 습도"
            value={currentValues.airHumidity}
            unit="%"
            maxValue={100}
            color="blue"
          />
        </div>

        {/* 실시간 센서 데이터 */}
        <section style={{ marginBottom: "24px" }}>
          <header style={{
            background: "linear-gradient(to right, #10b981, #059669)",
            color: "white",
            padding: "16px 24px",
            borderRadius: "12px 12px 0 0"
          }}>
            <h2 style={{
              fontSize: "1.25rem",
              fontWeight: "600",
              margin: 0
            }}>📈 실시간 센서 데이터</h2>
          </header>
          <div style={{
            background: "white",
            borderRadius: "0 0 12px 12px",
            boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
            padding: "24px"
          }}>
            <dl style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fit, minmax(250px, 1fr))",
              gap: "12px"
            }}>
              <SensorRow label="근권 온도" value={currentValues.rootTemp} unit="°C" />
              <SensorRow label="근권 습도" value={currentValues.rootHumidity} unit="%" />
              <SensorRow label="EC" value={currentValues.ec} unit="mS/cm" />
              <SensorRow label="pH" value={currentValues.ph} unit="" />
              <SensorRow label="탱크 수위" value={currentValues.tankLevel} unit="%" />
              <SensorRow label="CO₂" value={currentValues.co2} unit="ppm" />
              <SensorRow label="PPFD" value={currentValues.ppfd} unit="μmol/m²/s" />
            </dl>
          </div>
        </section>

        {/* 온도/습도 타임라인 */}
        <section style={{ marginBottom: "24px" }}>
          <header style={{
            background: "linear-gradient(to right, #10b981, #059669)",
            color: "white",
            padding: "16px 24px",
            borderRadius: "12px 12px 0 0"
          }}>
            <h2 style={{
              fontSize: "1.25rem",
              fontWeight: "600",
              margin: 0
            }}>📊 온도/습도 타임라인</h2>
          </header>
          <div style={{
            background: "white",
            borderRadius: "0 0 12px 12px",
            boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
            padding: "24px"
          }}>
            {chartData.length === 0 ? (
              <div style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                height: "256px",
                color: "#6b7280"
              }}>
                데이터가 없습니다
              </div>
            ) : (
              <div style={{
                height: "256px",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                color: "#9ca3af"
              }}>
                차트 데이터: {chartData.length}개 포인트
              </div>
            )}
          </div>
        </section>

        {/* EC/pH/수위 타임라인 */}
        <section style={{ marginBottom: "24px" }}>
          <header style={{
            background: "linear-gradient(to right, #10b981, #059669)",
            color: "white",
            padding: "16px 24px",
            borderRadius: "12px 12px 0 0"
          }}>
            <h2 style={{
              fontSize: "1.25rem",
              fontWeight: "600",
              margin: 0
            }}>💧 EC/pH/수위 타임라인</h2>
          </header>
          <div style={{
            background: "white",
            borderRadius: "0 0 12px 12px",
            boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
            padding: "24px"
          }}>
            {chartData.length === 0 ? (
              <div style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                height: "256px",
                color: "#6b7280"
              }}>
                데이터가 없습니다
              </div>
            ) : (
              <div style={{
                height: "256px",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                color: "#9ca3af"
              }}>
                차트 데이터: {chartData.length}개 포인트
              </div>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}
