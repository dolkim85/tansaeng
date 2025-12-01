import { useState, useEffect } from "react";
import type { SensorSnapshot } from "../types";
import { getMqttClient, onConnectionChange } from "../mqtt/mqttClient";
import GaugeCard from "../components/GaugeCard";
import SensorRow from "../components/SensorRow";

export default function Environment() {
  const [period, setPeriod] = useState("24h");
  const [selectedZone, setSelectedZone] = useState("all");
  const [mqttConnected, setMqttConnected] = useState(false);

  const [currentValues] = useState<Partial<SensorSnapshot>>({
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

  // 앞/뒤 개별 센서 값
  const [frontTemp, setFrontTemp] = useState<number | null>(null);
  const [frontHumidity, setFrontHumidity] = useState<number | null>(null);
  const [backTemp, setBackTemp] = useState<number | null>(null);
  const [backHumidity, setBackHumidity] = useState<number | null>(null);

  // 평균값 계산
  const avgTemp = frontTemp !== null && backTemp !== null
    ? (frontTemp + backTemp) / 2
    : null;
  const avgHumidity = frontHumidity !== null && backHumidity !== null
    ? (frontHumidity + backHumidity) / 2
    : null;

  // MQTT 연결 상태 감지
  useEffect(() => {
    const unsubscribe = onConnectionChange((connected) => {
      setMqttConnected(connected);
    });

    return unsubscribe;
  }, []);

  // MQTT 구독 - ESP32 앞/뒤 온습도 센서 데이터
  useEffect(() => {
    const client = getMqttClient();

    // ESP32-앞 (ctlr-0001) - DHT11
    const frontTempTopic = "tansaeng/ctlr-0001/dht11/temperature";
    const frontHumTopic = "tansaeng/ctlr-0001/dht11/humidity";

    // ESP32-뒤 (ctlr-0002) - DHT22
    const backTempTopic = "tansaeng/ctlr-0002/dht22/temperature";
    const backHumTopic = "tansaeng/ctlr-0002/dht22/humidity";

    const handleMessage = (topic: string, message: Buffer) => {
      const value = parseFloat(message.toString());

      // 앞 센서
      if (topic === frontTempTopic) {
        setFrontTemp(value);
      } else if (topic === frontHumTopic) {
        setFrontHumidity(value);
      }
      // 뒤 센서
      else if (topic === backTempTopic) {
        setBackTemp(value);
      } else if (topic === backHumTopic) {
        setBackHumidity(value);
      }
    };

    client.on("message", handleMessage);
    client.subscribe(frontTempTopic);
    client.subscribe(frontHumTopic);
    client.subscribe(backTempTopic);
    client.subscribe(backHumTopic);

    return () => {
      client.off("message", handleMessage);
      client.unsubscribe(frontTempTopic);
      client.unsubscribe(frontHumTopic);
      client.unsubscribe(backTempTopic);
      client.unsubscribe(backHumTopic);
    };
  }, []);

  const [chartData] = useState<SensorSnapshot[]>([]);

  return (
    <div className="bg-gray-50">
      <div className="max-w-7xl mx-auto px-4">
        {/* 페이지 헤더 */}
        <header className="bg-farm-500 p-4 sm:px-6 rounded-xl mb-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold m-0">📊 환경 모니터링</h1>
              <p className="text-sm text-gray-800 mt-1 m-0">
                온도, 습도, EC, pH 등 센서 데이터를 실시간으로 모니터링합니다
              </p>
            </div>
            {/* ESP32 연결 상태 */}
            <div className="flex items-center gap-2 bg-white/20 px-4 py-2 rounded-lg">
              <div className={`w-3 h-3 rounded-full ${mqttConnected ? 'bg-green-300 animate-pulse' : 'bg-red-300'}`}></div>
              <span className="text-sm font-medium">
                {mqttConnected ? 'ESP32 연결됨' : 'ESP32 연결 끊김'}
              </span>
            </div>
          </div>
        </header>

        {/* 필터 섹션 */}
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">🔍 조회 조건</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(250px,1fr))] gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  기간
                </label>
                <select
                  value={period}
                  onChange={(e) => setPeriod(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg text-base"
                >
                  <option value="1h">최근 1시간</option>
                  <option value="today">오늘</option>
                  <option value="24h">24시간</option>
                  <option value="7d">7일</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Zone
                </label>
                <select
                  value={selectedZone}
                  onChange={(e) => setSelectedZone(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg text-base"
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

        {/* 온도/습도 게이지 카드 - 앞/뒤/평균 */}
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">🌡️ 공기 온도</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-4">
              <GaugeCard
                icon="🌡️"
                title="온도 (앞)"
                value={frontTemp}
                unit="°C"
                maxValue={50}
                color="green"
              />
              <GaugeCard
                icon="🌡️"
                title="온도 (뒤)"
                value={backTemp}
                unit="°C"
                maxValue={50}
                color="green"
              />
              <GaugeCard
                icon="🌡️"
                title="온도 (평균)"
                value={avgTemp}
                unit="°C"
                maxValue={50}
                color="blue"
              />
            </div>
          </div>
        </section>

        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">💧 공기 습도</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-4">
              <GaugeCard
                icon="💧"
                title="습도 (앞)"
                value={frontHumidity}
                unit="%"
                maxValue={100}
                color="blue"
              />
              <GaugeCard
                icon="💧"
                title="습도 (뒤)"
                value={backHumidity}
                unit="%"
                maxValue={100}
                color="blue"
              />
              <GaugeCard
                icon="💧"
                title="습도 (평균)"
                value={avgHumidity}
                unit="%"
                maxValue={100}
                color="green"
              />
            </div>
          </div>
        </section>

        {/* 실시간 센서 데이터 */}
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">📈 실시간 센서 데이터</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <dl className="grid grid-cols-[repeat(auto-fit,minmax(250px,1fr))] gap-3">
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
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">📊 온도/습도 타임라인</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
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
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">💧 EC/pH/수위 타임라인</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
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
