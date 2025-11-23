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
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="container mx-auto px-4 max-w-6xl space-y-6">
        {/* 페이지 헤더 */}
        <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-xl">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold">📊 환경 모니터링</h1>
              <p className="text-sm opacity-80 mt-1">
                온도, 습도, EC, pH 등 센서 데이터를 실시간으로 모니터링합니다
              </p>
            </div>
            {/* ESP32 연결 상태 */}
            <div className="flex items-center gap-2 bg-white bg-opacity-20 px-4 py-2 rounded-lg">
              <div className={`w-3 h-3 rounded-full ${mqttConnected ? 'bg-green-300 animate-pulse' : 'bg-red-300'}`}></div>
              <span className="text-sm font-medium">
                {mqttConnected ? 'ESP32 연결됨' : 'ESP32 연결 끊김'}
              </span>
            </div>
          </div>
        </header>

        {/* 필터 섹션 */}
        <section>
          <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold">🔍 조회 조건</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-md p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="flex-1">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  기간
                </label>
                <select
                  value={period}
                  onChange={(e) => setPeriod(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                >
                  <option value="1h">최근 1시간</option>
                  <option value="today">오늘</option>
                  <option value="24h">24시간</option>
                  <option value="7d">7일</option>
                </select>
              </div>
              <div className="flex-1">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Zone
                </label>
                <select
                  value={selectedZone}
                  onChange={(e) => setSelectedZone(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
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
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
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
        <section>
          <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold">📈 실시간 센서 데이터</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-md p-6">
            <dl className="grid grid-cols-1 md:grid-cols-2 gap-y-3 gap-x-8">
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
        <section>
          <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold">📊 온도/습도 타임라인</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-md p-6">
            {chartData.length === 0 ? (
              <div className="flex items-center justify-center h-64 text-gray-500">
                데이터가 없습니다
              </div>
            ) : (
              <div className="h-64 flex items-center justify-center text-gray-400">
                차트 데이터: {chartData.length}개 포인트
              </div>
            )}
          </div>
        </section>

        {/* EC/pH/수위 타임라인 */}
        <section>
          <header className="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold">💧 EC/pH/수위 타임라인</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-md p-6">
            {chartData.length === 0 ? (
              <div className="flex items-center justify-center h-64 text-gray-500">
                데이터가 없습니다
              </div>
            ) : (
              <div className="h-64 flex items-center justify-center text-gray-400">
                차트 데이터: {chartData.length}개 포인트
              </div>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}
