import { useState, useEffect } from "react";
import type { SensorSnapshot } from "../types";
import { getMqttClient, onConnectionChange } from "../mqtt/mqttClient";
import SensorRow from "../components/SensorRow";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";

interface SensorData {
  temperature: number | null;
  humidity: number | null;
  lastUpdate: number | null; // timestamp로 변경
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

  // 날짜 선택 및 히스토리 데이터
  const [selectedStartDate, setSelectedStartDate] = useState<Date | null>(new Date());
  const [selectedEndDate, setSelectedEndDate] = useState<Date | null>(new Date());
  const [historicalData, setHistoricalData] = useState<any[]>([]);
  const [isLoadingHistory, setIsLoadingHistory] = useState(false);

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

  // 센서 타임아웃 체크 (5초 이상 데이터가 없으면 0으로 표시)
  useEffect(() => {
    const TIMEOUT_MS = 5000; // 5초
    const interval = setInterval(() => {
      const now = Date.now();

      setFrontSensor((prev) => {
        if (prev.lastUpdate && now - prev.lastUpdate > TIMEOUT_MS) {
          return { temperature: null, humidity: null, lastUpdate: null };
        }
        return prev;
      });

      setBackSensor((prev) => {
        if (prev.lastUpdate && now - prev.lastUpdate > TIMEOUT_MS) {
          return { temperature: null, humidity: null, lastUpdate: null };
        }
        return prev;
      });

      setTopSensor((prev) => {
        if (prev.lastUpdate && now - prev.lastUpdate > TIMEOUT_MS) {
          return { temperature: null, humidity: null, lastUpdate: null };
        }
        return prev;
      });
    }, 1000); // 1초마다 체크 (더 빠른 반응)

    return () => clearInterval(interval);
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
      const timestamp = Date.now(); // 타임스탬프로 변경

      sensors.forEach((sensor) => {
        let dataType: 'temperature' | 'humidity' | null = null;
        let sensorType = '';

        if (topic === sensor.tempTopic) {
          sensor.setter((prev) => ({
            ...prev,
            temperature: value,
            lastUpdate: timestamp,
          }));
          dataType = 'temperature';
          sensorType = sensor.tempTopic.includes('dht11') ? 'dht11' : 'dht22';
        } else if (topic === sensor.humTopic) {
          sensor.setter((prev) => ({
            ...prev,
            humidity: value,
            lastUpdate: timestamp,
          }));
          dataType = 'humidity';
          sensorType = sensor.humTopic.includes('dht11') ? 'dht11' : 'dht22';
        }

        // 데이터베이스에 저장
        if (dataType) {
          const controllerId = topic.split('/')[1]; // tansaeng/ctlr-0001/dht11/temperature에서 ctlr-0001 추출
          saveSensorData(controllerId, sensorType, sensor.name, dataType, value);
        }
      });
    };

    // 센서 데이터를 데이터베이스에 저장하는 함수
    const saveSensorData = async (
      controllerId: string,
      sensorType: string,
      sensorLocation: string,
      dataType: 'temperature' | 'humidity',
      value: number
    ) => {
      try {
        const payload: any = {
          controller_id: controllerId,
          sensor_type: sensorType,
          sensor_location: sensorLocation,
        };

        if (dataType === 'temperature') {
          payload.temperature = value;
        } else {
          payload.humidity = value;
        }

        await fetch('/api/smartfarm/save_sensor_data.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(payload),
        });
      } catch (error) {
        console.error('Failed to save sensor data:', error);
      }
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
        temperature: isNaN(avgTemp) ? null : parseFloat(avgTemp.toFixed(1)),
        humidity: isNaN(avgHum) ? null : parseFloat(avgHum.toFixed(1)),
      });
    }
  }, [chartData]);

  // 히스토리 데이터 조회 함수
  const loadHistoricalData = async () => {
    if (!selectedStartDate || !selectedEndDate) {
      return;
    }

    setIsLoadingHistory(true);
    try {
      const startStr = selectedStartDate.toISOString().split('T')[0];
      const endStr = selectedEndDate.toISOString().split('T')[0];

      const response = await fetch(
        `/api/smartfarm/get_sensor_data.php?start_date=${startStr}&end_date=${endStr}`
      );
      const result = await response.json();

      if (result.success) {
        setHistoricalData(result.data);
      } else {
        console.error('Failed to load historical data:', result.error);
      }
    } catch (error) {
      console.error('Error loading historical data:', error);
    } finally {
      setIsLoadingHistory(false);
    }
  };

  // 평균값 계산 (DevicesControl과 동일하게 null 제외하고 계산)
  const temps = [frontSensor.temperature, backSensor.temperature, topSensor.temperature].filter((t) => t !== null) as number[];
  const hums = [frontSensor.humidity, backSensor.humidity, topSensor.humidity].filter((h) => h !== null) as number[];

  const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
  const avgHum = hums.length > 0 ? hums.reduce((a, b) => a + b, 0) / hums.length : null;

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
                MQTT {mqttConnected ? "연결됨" : "연결 끊김"}
              </span>
            </div>
          </div>
        </header>

        {/* 온습도 센서 데이터 - 개선된 레이아웃 */}
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">
          {/* 평균 온습도 (좌측) */}
          <section className="lg:col-span-1">
            <header className="bg-farm-500 px-3 py-2 rounded-t-xl">
              <h3 className="text-sm font-semibold m-0">📊 평균</h3>
            </header>
            <div className="bg-white rounded-b-xl shadow-card p-3 space-y-3">
              <div className="text-center">
                <div className="text-xs text-gray-600 mb-1">평균 온도</div>
                <div className="text-2xl font-bold text-green-600">
                  {avgTemp !== null ? avgTemp.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-gray-600 mb-1">평균 습도</div>
                <div className="text-2xl font-bold text-blue-600">
                  {avgHum !== null ? avgHum.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>

          {/* 내부팬 앞 */}
          <section className="lg:col-span-1">
            <header className="bg-farm-500 px-3 py-2 rounded-t-xl">
              <h3 className="text-sm font-semibold m-0">📍 내부팬 앞</h3>
            </header>
            <div className="bg-white rounded-b-xl shadow-card p-3 space-y-2">
              <div className="text-center">
                <div className="text-xs text-gray-600">🌡️ 온도</div>
                <div className="text-xl font-semibold text-green-600">
                  {frontSensor.temperature !== null ? frontSensor.temperature.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-gray-600">💧 습도</div>
                <div className="text-xl font-semibold text-blue-600">
                  {frontSensor.humidity !== null ? frontSensor.humidity.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>

          {/* 내부팬 뒤 */}
          <section className="lg:col-span-1">
            <header className="bg-farm-500 px-3 py-2 rounded-t-xl">
              <h3 className="text-sm font-semibold m-0">📍 내부팬 뒤</h3>
            </header>
            <div className="bg-white rounded-b-xl shadow-card p-3 space-y-2">
              <div className="text-center">
                <div className="text-xs text-gray-600">🌡️ 온도</div>
                <div className="text-xl font-semibold text-green-600">
                  {backSensor.temperature !== null ? backSensor.temperature.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-gray-600">💧 습도</div>
                <div className="text-xl font-semibold text-blue-600">
                  {backSensor.humidity !== null ? backSensor.humidity.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>

          {/* 천장 */}
          <section className="lg:col-span-1">
            <header className="bg-farm-500 px-3 py-2 rounded-t-xl">
              <h3 className="text-sm font-semibold m-0">📍 천장</h3>
            </header>
            <div className="bg-white rounded-b-xl shadow-card p-3 space-y-2">
              <div className="text-center">
                <div className="text-xs text-gray-600">🌡️ 온도</div>
                <div className="text-xl font-semibold text-green-600">
                  {topSensor.temperature !== null ? topSensor.temperature.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-gray-600">💧 습도</div>
                <div className="text-xl font-semibold text-blue-600">
                  {topSensor.humidity !== null ? topSensor.humidity.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>

          {/* 10분 평균 온습도 (우측) */}
          <section className="lg:col-span-1">
            <header className="bg-farm-500 px-3 py-2 rounded-t-xl">
              <h3 className="text-sm font-semibold m-0">⏱️ 10분 평균</h3>
            </header>
            <div className="bg-white rounded-b-xl shadow-card p-3 space-y-3">
              <div className="text-center">
                <div className="text-xs text-gray-600 mb-1">평균 온도</div>
                <div className="text-2xl font-bold text-green-600">
                  {tenMinAvg.temperature !== null ? tenMinAvg.temperature.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-xs text-gray-600 mb-1">평균 습도</div>
                <div className="text-2xl font-bold text-blue-600">
                  {tenMinAvg.humidity !== null ? tenMinAvg.humidity.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>
        </div>

        {/* 히스토리 데이터 조회 섹션 */}
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">📅 히스토리 데이터 조회</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  시작 날짜
                </label>
                <DatePicker
                  selected={selectedStartDate}
                  onChange={(date) => setSelectedStartDate(date)}
                  dateFormat="yyyy-MM-dd"
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg text-base"
                  maxDate={new Date()}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  종료 날짜
                </label>
                <DatePicker
                  selected={selectedEndDate}
                  onChange={(date) => setSelectedEndDate(date)}
                  dateFormat="yyyy-MM-dd"
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg text-base"
                  maxDate={new Date()}
                />
              </div>
              <div>
                <button
                  onClick={loadHistoricalData}
                  disabled={isLoadingHistory}
                  className="w-full px-6 py-2 bg-farm-500 text-gray-900 rounded-lg font-medium hover:bg-farm-600 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isLoadingHistory ? '조회 중...' : '데이터 조회'}
                </button>
              </div>
            </div>

            {/* 히스토리 데이터 테이블 */}
            {historicalData.length > 0 && (
              <div className="mt-6 overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">위치</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">온도 (°C)</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">습도 (%)</th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">기록 시간</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {historicalData.slice(0, 100).map((record, index) => (
                      <tr key={index} className="hover:bg-gray-50">
                        <td className="px-4 py-3 text-sm text-gray-900">{record.sensor_location}</td>
                        <td className="px-4 py-3 text-sm text-gray-900">{record.temperature ?? '-'}</td>
                        <td className="px-4 py-3 text-sm text-gray-900">{record.humidity ?? '-'}</td>
                        <td className="px-4 py-3 text-sm text-gray-500">{new Date(record.recorded_at).toLocaleString('ko-KR')}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                {historicalData.length > 100 && (
                  <p className="text-sm text-gray-500 mt-2 text-center">
                    처음 100개 레코드만 표시됩니다 (전체: {historicalData.length}개)
                  </p>
                )}
              </div>
            )}
          </div>
        </section>

        {/* 필터 섹션 */}
        <section className="mb-6">
          <header className="bg-farm-500 px-6 py-4 rounded-t-xl">
            <h2 className="text-xl font-semibold m-0">🔍 실시간 차트 조회 조건</h2>
          </header>
          <div className="bg-white rounded-b-xl shadow-card p-6">
            <div className="grid grid-cols-[repeat(auto-fit,minmax(250px,1fr))] gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  기간
                </label>
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

        {/* 온도/습도 타임라인 차트 (좌우 분리) */}
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
                <ResponsiveContainer width="100%" height={400}>
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="timestamp" />
                    <YAxis />
                    <Tooltip />
                    <Legend />
                    <Line
                      type="monotone"
                      dataKey="frontTemp"
                      stroke="#22c55e"
                      name="앞 온도"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      type="monotone"
                      dataKey="backTemp"
                      stroke="#3b82f6"
                      name="뒤 온도"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      type="monotone"
                      dataKey="topTemp"
                      stroke="#f59e0b"
                      name="천장 온도"
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
                <ResponsiveContainer width="100%" height={400}>
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="timestamp" />
                    <YAxis />
                    <Tooltip />
                    <Legend />
                    <Line
                      type="monotone"
                      dataKey="frontHum"
                      stroke="#22c55e"
                      name="앞 습도"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      type="monotone"
                      dataKey="backHum"
                      stroke="#3b82f6"
                      name="뒤 습도"
                      strokeWidth={2}
                      dot={false}
                    />
                    <Line
                      type="monotone"
                      dataKey="topHum"
                      stroke="#f59e0b"
                      name="천장 습도"
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
