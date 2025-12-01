import { useState, useEffect } from "react";
import type { SensorSnapshot } from "../types";
import { getMqttClient, onConnectionChange } from "../mqtt/mqttClient";
import GaugeCard from "../components/GaugeCard";
import SensorRow from "../components/SensorRow";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';

interface SensorData {
  temperature: number | null;
  humidity: number | null;
  lastUpdate: string | null;
}

interface ChartDataPoint {
  timestamp: string;
  frontTemp: number | null;
  backTemp: number | null;
  topTemp: number | null;
  frontHum: number | null;
  backHum: number | null;
  topHum: number | null;
}

export default function Environment() {
  const [period, setPeriod] = useState<"current" | "1h" | "1w" | "1m">("current");
  const [selectedZone, setSelectedZone] = useState("all");
  const [mqttConnected, setMqttConnected] = useState(false);

  // 3개 센서 데이터 (앞, 뒤, 천장)
  const [frontSensor, setFrontSensor] = useState<SensorData>({
    temperature: null,
    humidity: null,
    lastUpdate: null,
  });
  const [backSensor, setBackSensor] = useState<SensorData>({
    temperature: null,
    humidity: null,
    lastUpdate: null,
  });
  const [topSensor, setTopSensor] = useState<SensorData>({
    temperature: null,
    humidity: null,
    lastUpdate: null,
  });

  // 차트 데이터 (최근 기록)
  const [chartData, setChartData] = useState<ChartDataPoint[]>([]);

  // 10분 평균값
  const [tenMinAvg, setTenMinAvg] = useState<{
    temperature: number | null;
    humidity: number | null;
  }>({
    temperature: null,
    humidity: null,
  });

  // 기타 센서 데이터
  const [currentValues] = useState<Partial<SensorSnapshot>>({
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

  // 3개 센서 데이터 구독 (앞, 뒤, 천장)
  useEffect(() => {
    const client = getMqttClient();

    const sensors = [
      {
        name: "front",
        tempTopic: "tansaeng/ctlr-0001/dht11/temperature",
        humTopic: "tansaeng/ctlr-0001/dht11/humidity",
        setter: setFrontSensor,
      },
      {
        name: "back",
        tempTopic: "tansaeng/ctlr-0002/dht22/temperature",
        humTopic: "tansaeng/ctlr-0002/dht22/humidity",
        setter: setBackSensor,
      },
      {
        name: "top",
        tempTopic: "tansaeng/ctlr-0003/dht22/temperature",
        humTopic: "tansaeng/ctlr-0003/dht22/humidity",
        setter: setTopSensor,
      },
    ];

    const handleMessage = (topic: string, message: Buffer) => {
      const value = parseFloat(message.toString());
      const timestamp = new Date().toISOString();

      sensors.forEach((sensor) => {
        if (topic === sensor.tempTopic) {
          sensor.setter((prev) => ({
            ...prev,
            temperature: value,
            lastUpdate: timestamp,
          }));
        } else if (topic === sensor.humTopic) {
          sensor.setter((prev) => ({
            ...prev,
            humidity: value,
            lastUpdate: timestamp,
          }));
        }
      });
    };

    client.on("message", handleMessage);

    // 모든 센서 구독
    sensors.forEach((sensor) => {
      client.subscribe(sensor.tempTopic);
      client.subscribe(sensor.humTopic);
    });

    return () => {
      client.off("message", handleMessage);
      sensors.forEach((sensor) => {
        client.unsubscribe(sensor.tempTopic);
        client.unsubscribe(sensor.humTopic);
      });
    };
  }, []);

  // 차트 데이터 업데이트 (실시간 데이터를 차트에 추가)
  useEffect(() => {
    if (
      frontSensor.temperature !== null ||
      backSensor.temperature !== null ||
      topSensor.temperature !== null
    ) {
      const newDataPoint: ChartDataPoint = {
        timestamp: new Date().toLocaleTimeString("ko-KR", {
          hour: "2-digit",
          minute: "2-digit",
        }),
        frontTemp: frontSensor.temperature,
        backTemp: backSensor.temperature,
        topTemp: topSensor.temperature,
        frontHum: frontSensor.humidity,
        backHum: backSensor.humidity,
        topHum: topSensor.humidity,
      };

      setChartData((prev) => {
        const updated = [...prev, newDataPoint];
        // 기간에 따라 데이터 포인트 제한
        const maxPoints = period === "current" ? 20 : period === "1h" ? 60 : period === "1w" ? 168 : 720;
        return updated.slice(-maxPoints);
      });
    }
  }, [frontSensor, backSensor, topSensor, period]);

  // 10분 평균값 계산 (최근 10분 데이터 사용)
  useEffect(() => {
    if (chartData.length > 0) {
      const recentData = chartData.slice(-10); // 최근 10개 포인트
      const avgTemp =
        recentData.reduce((sum, d) => {
          const temps = [d.frontTemp, d.backTemp, d.topTemp].filter((t) => t !== null) as number[];
          return sum + (temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : 0);
        }, 0) / recentData.length;

      const avgHum =
        recentData.reduce((sum, d) => {
          const hums = [d.frontHum, d.backHum, d.topHum].filter((h) => h !== null) as number[];
          return sum + (hums.length > 0 ? hums.reduce((a, b) => a + b, 0) / hums.length : 0);
        }, 0) / recentData.length;

      setTenMinAvg({
        temperature: isNaN(avgTemp) ? null : parseFloat(avgTemp.toFixed(2)),
        humidity: isNaN(avgHum) ? null : parseFloat(avgHum.toFixed(2)),
      });
    }
  }, [chartData]);

  // 평균값 계산 (소수점 2자리)
  const avgTemp = (() => {
    const temps = [frontSensor.temperature, backSensor.temperature, topSensor.temperature].filter(
      (t) => t !== null
    ) as number[];
    if (temps.length === 0) return null;
    const avg = temps.reduce((a, b) => a + b, 0) / temps.length;
    return parseFloat(avg.toFixed(2));
  })();

  const avgHum = (() => {
    const hums = [frontSensor.humidity, backSensor.humidity, topSensor.humidity].filter(
      (h) => h !== null
    ) as number[];
    if (hums.length === 0) return null;
    const avg = hums.reduce((a, b) => a + b, 0) / hums.length;
    return parseFloat(avg.toFixed(2));
  })();

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
            {/* MQTT 연결 상태 */}
            <div className="flex items-center gap-2 bg-white/20 px-4 py-2 rounded-lg">
              <div
                className={`w-3 h-3 rounded-full ${
                  mqttConnected ? "bg-green-300 animate-pulse" : "bg-red-300"
                }`}
              ></div>
              <span className="text-sm font-medium">
                {mqttConnected ? "MQTT 연결됨" : "MQTT 연결 끊김"}
              </span>
            </div>
          </div>
        </header>

        {/* 10분 평균 온습도 */}
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">⏱️ 10분 평균 온습도</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-6">
              <GaugeCard
                icon="🌡️"
                title="10분 평균 온도"
                value={tenMinAvg.temperature}
                unit="°C"
                maxValue={50}
                color="green"
              />
              <GaugeCard
                icon="💧"
                title="10분 평균 습도"
                value={tenMinAvg.humidity}
                unit="%"
                maxValue={100}
                color="blue"
              />
            </div>
          </div>
        </section>

        {/* 온습도 센서 데이터 (3개 센서 + 평균) */}
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">🌡️ 온도 센서</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
              <GaugeCard
                icon="🌡️"
                title="내부팬 앞"
                value={frontSensor.temperature}
                unit="°C"
                maxValue={50}
                color="green"
              />
              <GaugeCard
                icon="🌡️"
                title="내부팬 뒤"
                value={backSensor.temperature}
                unit="°C"
                maxValue={50}
                color="green"
              />
              <GaugeCard
                icon="🌡️"
                title="천장"
                value={topSensor.temperature}
                unit="°C"
                maxValue={50}
                color="green"
              />
              <GaugeCard
                icon="🌡️"
                title="평균 온도"
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
            <h2 className="text-xl font-semibold m-0">💧 습도 센서</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] gap-4">
              <GaugeCard
                icon="💧"
                title="내부팬 앞"
                value={frontSensor.humidity}
                unit="%"
                maxValue={100}
                color="blue"
              />
              <GaugeCard
                icon="💧"
                title="내부팬 뒤"
                value={backSensor.humidity}
                unit="%"
                maxValue={100}
                color="blue"
              />
              <GaugeCard
                icon="💧"
                title="천장"
                value={topSensor.humidity}
                unit="%"
                maxValue={100}
                color="blue"
              />
              <GaugeCard
                icon="💧"
                title="평균 습도"
                value={avgHum}
                unit="%"
                maxValue={100}
                color="green"
              />
            </div>
          </div>
        </section>

        {/* 필터 섹션 */}
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">🔍 차트 조회 조건</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(250px,1fr))] gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">기간</label>
                <select
                  value={period}
                  onChange={(e) => setPeriod(e.target.value as "current" | "1h" | "1w" | "1m")}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg text-base"
                >
                  <option value="current">현재</option>
                  <option value="1h">최근 1시간</option>
                  <option value="1w">최근 1주</option>
                  <option value="1m">최근 1개월</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Zone</label>
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

        {/* 온도/습도 타임라인 차트 (좌우 배치) */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          {/* 온도 차트 */}
          <section>
            <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
              <h2 className="text-xl font-semibold m-0">📊 온도 타임라인</h2>
            </header>
            <div className="bg-white rounded-b-xl shadow-card p-6">
              {chartData.length === 0 ? (
                <div className="flex items-center justify-center h-64 text-gray-500">
                  데이터를 수집하는 중입니다...
                </div>
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="timestamp" tick={{ fontSize: 11 }} />
                    <YAxis />
                    <Tooltip />
                    <Legend />
                    <Line
                      type="monotone"
                      dataKey="frontTemp"
                      stroke="#22c55e"
                      name="내부팬 앞"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      type="monotone"
                      dataKey="backTemp"
                      stroke="#3b82f6"
                      name="내부팬 뒤"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      type="monotone"
                      dataKey="topTemp"
                      stroke="#f59e0b"
                      name="천장"
                      strokeWidth={2}
                      dot={false}
                    />
                  </LineChart>
                </ResponsiveContainer>
              )}
            </div>
          </section>

          {/* 습도 차트 */}
          <section>
            <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
              <h2 className="text-xl font-semibold m-0">📊 습도 타임라인</h2>
            </header>
            <div className="bg-white rounded-b-xl shadow-card p-6">
              {chartData.length === 0 ? (
                <div className="flex items-center justify-center h-64 text-gray-500">
                  데이터를 수집하는 중입니다...
                </div>
              ) : (
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="timestamp" tick={{ fontSize: 11 }} />
                    <YAxis />
                    <Tooltip />
                    <Legend />
                    <Line
                      type="monotone"
                      dataKey="frontHum"
                      stroke="#22c55e"
                      name="내부팬 앞"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      type="monotone"
                      dataKey="backHum"
                      stroke="#3b82f6"
                      name="내부팬 뒤"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      type="monotone"
                      dataKey="topHum"
                      stroke="#f59e0b"
                      name="천장"
                      strokeWidth={2}
                      dot={false}
                    />
                  </LineChart>
                </ResponsiveContainer>
              )}
            </div>
          </section>
        </div>

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
      </div>
    </div>
  );
}
