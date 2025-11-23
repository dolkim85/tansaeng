import { useState, useEffect } from "react";
import type { SensorSnapshot } from "../types";
import { getMqttClient } from "../mqtt/mqttClient";

export default function Environment() {
  const [period, setPeriod] = useState("24h");
  const [selectedZone, setSelectedZone] = useState("all");

  // 초기 상태: 데이터 없음 (더미 값 사용하지 않음)
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

  const sensorCards = [
    { label: "공기 온도", value: currentValues.airTemp, unit: "°C" },
    { label: "공기 습도", value: currentValues.airHumidity, unit: "%" },
    { label: "근권 온도", value: currentValues.rootTemp, unit: "°C" },
    { label: "근권 습도", value: currentValues.rootHumidity, unit: "%" },
    { label: "EC", value: currentValues.ec, unit: "mS/cm" },
    { label: "pH", value: currentValues.ph, unit: "" },
    { label: "탱크 수위", value: currentValues.tankLevel, unit: "%" },
    { label: "CO₂", value: currentValues.co2, unit: "ppm" },
    { label: "PPFD", value: currentValues.ppfd, unit: "μmol/m²/s" },
  ];

  return (
    <div className="container mx-auto px-4 py-6 space-y-6">
      <div className="bg-gradient-to-r from-emerald-500 to-green-600 rounded-2xl px-6 py-4">
        <h1 className="text-white font-bold text-2xl">📊 환경 모니터링</h1>
        <p className="text-white/80 text-sm mt-1">
          온도, 습도, EC, pH 등 센서 데이터를 실시간으로 모니터링합니다
        </p>
      </div>

      {/* 필터 */}
      <div className="bg-white rounded-2xl shadow-md p-4">
        <div className="flex flex-col md:flex-row gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              기간
            </label>
            <select
              value={period}
              onChange={(e) => setPeriod(e.target.value)}
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
            >
              <option value="1h">최근 1시간</option>
              <option value="today">오늘</option>
              <option value="24h">24시간</option>
              <option value="7d">7일</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Zone
            </label>
            <select
              value={selectedZone}
              onChange={(e) => setSelectedZone(e.target.value)}
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
            >
              <option value="all">전체</option>
              <option value="zone_a">Zone A (상층)</option>
              <option value="zone_b">Zone B (하층)</option>
              <option value="zone_c">Zone C (테스트베드)</option>
            </select>
          </div>
        </div>
      </div>

      {/* 현재 값 카드 */}
      <div className="bg-white rounded-xl shadow-md p-6">
        <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
          <span className="text-xl">📡</span>
          실시간 센서 데이터
        </h2>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {sensorCards.map((sensor) => {
            const hasValue = sensor.value !== null && sensor.value !== undefined;

            // 막대 게이지용 최대값 설정
            let maxValue = 100;
            let barColor = "bg-green-600";
            if (sensor.label === "공기 온도" || sensor.label === "근권 온도") {
              maxValue = 50;
              barColor = hasValue && sensor.value! > 30 ? "bg-orange-500" : "bg-green-600";
            } else if (sensor.label === "EC") {
              maxValue = 5;
            } else if (sensor.label === "pH") {
              maxValue = 14;
            } else if (sensor.label === "CO₂") {
              maxValue = 2000;
            } else if (sensor.label === "PPFD") {
              maxValue = 1500;
            }

            const percentage = hasValue ? Math.min((sensor.value! / maxValue) * 100, 100) : 0;

            return (
              <div
                key={sensor.label}
                className={`
                  rounded-lg p-4 border transition-all duration-300
                  ${hasValue
                    ? "bg-white border-green-600 shadow-md"
                    : "bg-gray-50 border-gray-300"}
                `}
              >
                <div className="flex items-center justify-between mb-2">
                  <div className="text-sm font-semibold text-gray-700">
                    {sensor.label}
                  </div>
                  <div className={`
                    text-2xl font-bold
                    ${hasValue ? "text-green-700" : "text-gray-400"}
                  `}>
                    {hasValue ? sensor.value : "-"}
                    {hasValue && <span className="text-sm font-normal ml-1">{sensor.unit}</span>}
                  </div>
                </div>

                {/* 막대 게이지 */}
                <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                  <div
                    className={`h-full transition-all duration-500 ${barColor}`}
                    style={{ width: `${percentage}%` }}
                  />
                </div>

                {!hasValue && (
                  <div className="text-xs text-gray-500 mt-1 text-center">측정 대기중</div>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* 그래프 영역 */}
      <div className="bg-white rounded-2xl shadow-md p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">
          온도/습도 타임라인
        </h2>
        {chartData.length === 0 ? (
          <div className="flex items-center justify-center h-64 text-gray-500">
            데이터가 없습니다
          </div>
        ) : (
          <div className="h-64 flex items-center justify-center text-gray-400">
            {/* 차트 라이브러리 연동 시 여기에 구현 */}
            차트 데이터: {chartData.length}개 포인트
          </div>
        )}
      </div>

      <div className="bg-white rounded-2xl shadow-md p-6">
        <h2 className="text-lg font-semibold text-gray-800 mb-4">
          EC/pH/수위 타임라인
        </h2>
        {chartData.length === 0 ? (
          <div className="flex items-center justify-center h-64 text-gray-500">
            데이터가 없습니다
          </div>
        ) : (
          <div className="h-64 flex items-center justify-center text-gray-400">
            {/* 차트 라이브러리 연동 시 여기에 구현 */}
            차트 데이터: {chartData.length}개 포인트
          </div>
        )}
      </div>
    </div>
  );
}
