import { useState, useEffect } from "react";
import type { SensorSnapshot } from "../types";
import SensorRow from "../components/SensorRow";
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import DatePicker from "react-datepicker";
import "react-datepicker/dist/react-datepicker.css";
import * as XLSX from 'xlsx';

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

  // 차트 시간 단위 (주식 차트 스타일)
  const [chartInterval, setChartInterval] = useState<"1m" | "5m" | "10m" | "1h" | "1d" | "1w" | "1M">("1m");

  // 차트 라인 표시 토글
  const [visibleLines, setVisibleLines] = useState({
    front: true,
    back: true,
    top: true,
  });
  const [serverConnected, setServerConnected] = useState(true); // 서버는 항상 연결됨

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

  // 페이징 관련 상태
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 30;

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

  // 서버 연결 상태 체크 (API 응답 확인)
  useEffect(() => {
    const checkServerConnection = async () => {
      try {
        const response = await fetch('/api/smartfarm/get_realtime_sensor_data.php');
        setServerConnected(response.ok);
      } catch {
        setServerConnected(false);
      }
    };

    checkServerConnection();
    const interval = setInterval(checkServerConnection, 10000); // 10초마다 체크
    return () => clearInterval(interval);
  }, []);

  // 서버 API에서 실시간 센서 데이터 가져오기 (1초마다)
  useEffect(() => {
    const fetchSensorData = async () => {
      try {
        const response = await fetch('/api/smartfarm/get_realtime_sensor_data.php');
        const result = await response.json();

        if (result.success) {
          const data = result.data;

          // 각 위치별 데이터 업데이트
          setFrontSensor({
            temperature: data.front.temperature,
            humidity: data.front.humidity,
            lastUpdate: data.front.lastUpdate ? Date.now() : null,
          });

          setBackSensor({
            temperature: data.back.temperature,
            humidity: data.back.humidity,
            lastUpdate: data.back.lastUpdate ? Date.now() : null,
          });

          setTopSensor({
            temperature: data.top.temperature,
            humidity: data.top.humidity,
            lastUpdate: data.top.lastUpdate ? Date.now() : null,
          });
        }
      } catch (error) {
        console.error('Failed to fetch sensor data:', error);
      }
    };

    // 즉시 실행
    fetchSensorData();

    // 1초마다 갱신 (실시간)
    const interval = setInterval(fetchSensorData, 1000);
    return () => clearInterval(interval);
  }, []);

  // 센서 데이터는 백그라운드 MQTT 데몬이 수집하고 DB에 저장
  // Environment 페이지는 서버 API에서 데이터만 읽어옴 (위의 useEffect 참고)

  // 시간 단위에 따른 데이터 집계 함수
  const aggregateDataByInterval = (data: any[], interval: string): ChartDataPoint[] => {
    if (data.length === 0) return [];

    // 인터벌에 따른 그룹화 키 생성 함수
    const getGroupKey = (dateStr: string): string => {
      const date = new Date(dateStr);
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hour = String(date.getHours()).padStart(2, '0');
      const minute = date.getMinutes();

      switch (interval) {
        case "1m":
          return `${month}/${day} ${hour}:${String(minute).padStart(2, '0')}`;
        case "5m":
          return `${month}/${day} ${hour}:${String(Math.floor(minute / 5) * 5).padStart(2, '0')}`;
        case "10m":
          return `${month}/${day} ${hour}:${String(Math.floor(minute / 10) * 10).padStart(2, '0')}`;
        case "1h":
          return `${month}/${day} ${hour}:00`;
        case "1d":
          return `${month}/${day}`;
        case "1w":
          const weekStart = new Date(date);
          weekStart.setDate(date.getDate() - date.getDay());
          return `${weekStart.getMonth() + 1}/${weekStart.getDate()}주`;
        case "1M":
          return `${year}/${month}`;
        default:
          return `${month}/${day} ${hour}:${String(minute).padStart(2, '0')}`;
      }
    };

    // 그룹별로 데이터 집계
    const groups = new Map<string, {
      frontTemps: number[];
      backTemps: number[];
      topTemps: number[];
      frontHums: number[];
      backHums: number[];
      topHums: number[];
    }>();

    data.forEach((record: any) => {
      const key = getGroupKey(record.recorded_at);

      if (!groups.has(key)) {
        groups.set(key, {
          frontTemps: [],
          backTemps: [],
          topTemps: [],
          frontHums: [],
          backHums: [],
          topHums: [],
        });
      }

      const group = groups.get(key)!;
      const location = record.sensor_location;

      if (location === 'front') {
        if (record.temperature !== null) group.frontTemps.push(parseFloat(record.temperature));
        if (record.humidity !== null) group.frontHums.push(parseFloat(record.humidity));
      } else if (location === 'back') {
        if (record.temperature !== null) group.backTemps.push(parseFloat(record.temperature));
        if (record.humidity !== null) group.backHums.push(parseFloat(record.humidity));
      } else if (location === 'top') {
        if (record.temperature !== null) group.topTemps.push(parseFloat(record.temperature));
        if (record.humidity !== null) group.topHums.push(parseFloat(record.humidity));
      }
    });

    // 평균값 계산
    const avg = (arr: number[]) => arr.length > 0 ? arr.reduce((a, b) => a + b, 0) / arr.length : null;

    const result: ChartDataPoint[] = [];
    groups.forEach((group, key) => {
      result.push({
        timestamp: key,
        frontTemp: avg(group.frontTemps),
        backTemp: avg(group.backTemps),
        topTemp: avg(group.topTemps),
        frontHum: avg(group.frontHums),
        backHum: avg(group.backHums),
        topHum: avg(group.topHums),
      });
    });

    return result.reverse();
  };

  // 기간별 과거 데이터 로드
  useEffect(() => {
    const loadHistoricalChartData = async () => {
      if (period === "current") {
        // current 모드는 실시간 데이터만 사용
        return;
      }

      try {
        // 기간에 따른 시작일 계산
        const endDate = new Date();
        const startDate = new Date();

        if (period === "1h") {
          startDate.setHours(startDate.getHours() - 1);
        } else if (period === "1w") {
          startDate.setDate(startDate.getDate() - 7);
        } else if (period === "1m") {
          startDate.setMonth(startDate.getMonth() - 1);
        }

        const startStr = startDate.toISOString().split('T')[0];
        const endStr = endDate.toISOString().split('T')[0];

        const response = await fetch(
          `/api/smartfarm/get_sensor_data.php?start_date=${startStr}&end_date=${endStr}`
        );
        const result = await response.json();

        if (result.success && result.data) {
          // 시간 단위에 따라 데이터 집계
          const aggregatedData = aggregateDataByInterval(result.data, chartInterval);
          setChartData(aggregatedData);
        }
      } catch (error) {
        console.error('Failed to load historical chart data:', error);
      }
    };

    loadHistoricalChartData();
  }, [period, chartInterval]);

  // 차트 데이터 업데이트 (실시간 데이터를 차트에 추가 - current 모드일 때만)
  useEffect(() => {
    if (period !== "current") {
      // current 모드가 아니면 실시간 업데이트 하지 않음
      return;
    }

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
        // current 모드는 최대 20개 포인트
        const maxPoints = 20;
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
    setCurrentPage(1); // 조회 시 첫 페이지로 이동
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

  // 엑셀 내보내기 함수
  const exportToExcel = () => {
    if (historicalData.length === 0) {
      alert('내보낼 데이터가 없습니다.');
      return;
    }

    // 데이터 변환
    const excelData = historicalData.map((record) => ({
      '위치': record.sensor_location === 'front' ? '내부팬 앞' :
             record.sensor_location === 'back' ? '내부팬 뒤' :
             record.sensor_location === 'top' ? '천장' : record.sensor_location,
      '온도 (°C)': record.temperature ?? '-',
      '습도 (%)': record.humidity ?? '-',
      '기록 시간': new Date(record.recorded_at).toLocaleString('ko-KR'),
    }));

    // 워크시트 생성
    const worksheet = XLSX.utils.json_to_sheet(excelData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, '환경 데이터');

    // 파일명 생성
    const startStr = selectedStartDate?.toISOString().split('T')[0] || '';
    const endStr = selectedEndDate?.toISOString().split('T')[0] || '';
    const fileName = `환경데이터_${startStr}_${endStr}.xlsx`;

    // 다운로드
    XLSX.writeFile(workbook, fileName);
  };

  // 페이징 관련 계산
  const totalPages = Math.ceil(historicalData.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentData = historicalData.slice(startIndex, endIndex);

  // 페이지 변경 함수
  const goToPage = (page: number) => {
    if (page >= 1 && page <= totalPages) {
      setCurrentPage(page);
    }
  };

  // 평균값 계산 (DevicesControl과 동일하게 null 제외하고 계산)
  const temps = [frontSensor.temperature, backSensor.temperature, topSensor.temperature].filter((t) => t !== null) as number[];
  const hums = [frontSensor.humidity, backSensor.humidity, topSensor.humidity].filter((h) => h !== null) as number[];

  const avgTemp = temps.length > 0 ? temps.reduce((a, b) => a + b, 0) / temps.length : null;
  const avgHum = hums.length > 0 ? hums.reduce((a, b) => a + b, 0) / hums.length : null;

  return (
    <div className="bg-gray-50 min-h-screen">
      <div className="max-w-7xl mx-auto px-2 sm:px-4">
        {/* 페이지 헤더 */}
        <header className="bg-farm-500 p-3 sm:p-4 sm:px-6 rounded-lg sm:rounded-xl mb-3 sm:mb-6">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
              <h1 className="text-lg sm:text-2xl font-bold m-0">환경 모니터링</h1>
              <p className="text-xs sm:text-sm text-gray-800 mt-1 m-0 hidden sm:block">
                온도, 습도, EC, pH 등 센서 데이터를 실시간으로 모니터링합니다
              </p>
            </div>
            {/* 서버 연결 상태 */}
            <div className="flex items-center gap-1.5 sm:gap-2 bg-white/20 px-2 sm:px-4 py-1.5 sm:py-2 rounded-lg self-start sm:self-auto">
              <div
                className={`w-2 sm:w-3 h-2 sm:h-3 rounded-full flex-shrink-0 ${
                  serverConnected ? "bg-green-300 animate-pulse" : "bg-red-300"
                }`}
              ></div>
              <span className="text-xs sm:text-sm font-medium whitespace-nowrap">
                서버 {serverConnected ? "작동 중" : "끊김"}
              </span>
            </div>
          </div>
        </header>

        {/* 온습도 센서 데이터 - 개선된 레이아웃 */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-4 mb-3 sm:mb-6">
          {/* 평균 온습도 (좌측) */}
          <section className="col-span-1">
            <header className="bg-farm-500 px-2 sm:px-3 py-1.5 sm:py-2 rounded-t-lg sm:rounded-t-xl">
              <h3 className="text-xs sm:text-sm font-semibold m-0">평균</h3>
            </header>
            <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-2 sm:p-3 space-y-2 sm:space-y-3">
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600 mb-0.5 sm:mb-1">평균 온도</div>
                <div className="text-lg sm:text-2xl font-bold text-green-600">
                  {avgTemp !== null ? avgTemp.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600 mb-0.5 sm:mb-1">평균 습도</div>
                <div className="text-lg sm:text-2xl font-bold text-blue-600">
                  {avgHum !== null ? avgHum.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>

          {/* 내부팬 앞 */}
          <section className="col-span-1">
            <header className="bg-farm-500 px-2 sm:px-3 py-1.5 sm:py-2 rounded-t-lg sm:rounded-t-xl">
              <h3 className="text-xs sm:text-sm font-semibold m-0">팬 앞</h3>
            </header>
            <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-2 sm:p-3 space-y-1.5 sm:space-y-2">
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600">온도</div>
                <div className="text-base sm:text-xl font-semibold text-green-600">
                  {frontSensor.temperature !== null ? frontSensor.temperature.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600">습도</div>
                <div className="text-base sm:text-xl font-semibold text-blue-600">
                  {frontSensor.humidity !== null ? frontSensor.humidity.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>

          {/* 내부팬 뒤 */}
          <section className="col-span-1">
            <header className="bg-farm-500 px-2 sm:px-3 py-1.5 sm:py-2 rounded-t-lg sm:rounded-t-xl">
              <h3 className="text-xs sm:text-sm font-semibold m-0">팬 뒤</h3>
            </header>
            <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-2 sm:p-3 space-y-1.5 sm:space-y-2">
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600">온도</div>
                <div className="text-base sm:text-xl font-semibold text-green-600">
                  {backSensor.temperature !== null ? backSensor.temperature.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600">습도</div>
                <div className="text-base sm:text-xl font-semibold text-blue-600">
                  {backSensor.humidity !== null ? backSensor.humidity.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>

          {/* 천장 */}
          <section className="col-span-1">
            <header className="bg-farm-500 px-2 sm:px-3 py-1.5 sm:py-2 rounded-t-lg sm:rounded-t-xl">
              <h3 className="text-xs sm:text-sm font-semibold m-0">천장</h3>
            </header>
            <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-2 sm:p-3 space-y-1.5 sm:space-y-2">
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600">온도</div>
                <div className="text-base sm:text-xl font-semibold text-green-600">
                  {topSensor.temperature !== null ? topSensor.temperature.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600">습도</div>
                <div className="text-base sm:text-xl font-semibold text-blue-600">
                  {topSensor.humidity !== null ? topSensor.humidity.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>

          {/* 10분 평균 온습도 (우측) */}
          <section className="col-span-2 sm:col-span-1">
            <header className="bg-farm-500 px-2 sm:px-3 py-1.5 sm:py-2 rounded-t-lg sm:rounded-t-xl">
              <h3 className="text-xs sm:text-sm font-semibold m-0">10분 평균</h3>
            </header>
            <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-2 sm:p-3 space-y-2 sm:space-y-3">
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600 mb-0.5 sm:mb-1">평균 온도</div>
                <div className="text-lg sm:text-2xl font-bold text-green-600">
                  {tenMinAvg.temperature !== null ? tenMinAvg.temperature.toFixed(1) : '0.0'}°C
                </div>
              </div>
              <div className="text-center">
                <div className="text-[10px] sm:text-xs text-gray-600 mb-0.5 sm:mb-1">평균 습도</div>
                <div className="text-lg sm:text-2xl font-bold text-blue-600">
                  {tenMinAvg.humidity !== null ? tenMinAvg.humidity.toFixed(1) : '0.0'}%
                </div>
              </div>
            </div>
          </section>
        </div>

        {/* 히스토리 데이터 조회 섹션 */}
        <section className="mb-3 sm:mb-6">
          <header className="bg-farm-500 px-3 sm:px-6 py-2 sm:py-4 rounded-t-lg sm:rounded-t-xl">
            <h2 className="text-sm sm:text-xl font-semibold m-0">히스토리 조회</h2>
          </header>
          <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-3 sm:p-6">
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-4 items-end">
              <div>
                <label className="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">
                  시작 날짜
                </label>
                <DatePicker
                  selected={selectedStartDate}
                  onChange={(date) => setSelectedStartDate(date)}
                  dateFormat="yyyy-MM-dd"
                  className="w-full px-2 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs sm:text-base"
                  maxDate={new Date()}
                />
              </div>
              <div>
                <label className="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">
                  종료 날짜
                </label>
                <DatePicker
                  selected={selectedEndDate}
                  onChange={(date) => setSelectedEndDate(date)}
                  dateFormat="yyyy-MM-dd"
                  className="w-full px-2 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs sm:text-base"
                  maxDate={new Date()}
                />
              </div>
              <div className="col-span-2 sm:col-span-1">
                <button
                  onClick={loadHistoricalData}
                  disabled={isLoadingHistory}
                  className="w-full px-3 sm:px-6 py-1.5 sm:py-2 bg-farm-500 text-gray-900 rounded-lg text-xs sm:text-base font-medium hover:bg-farm-600 active:bg-farm-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isLoadingHistory ? '조회 중...' : '조회'}
                </button>
              </div>
            </div>

            {/* 히스토리 데이터 테이블 */}
            {historicalData.length > 0 && (
              <div className="mt-6">
                {/* 상단 정보 및 엑셀 내보내기 버튼 */}
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-4">
                  <p className="text-sm text-gray-600">
                    전체 {historicalData.length}개 중 {startIndex + 1} - {Math.min(endIndex, historicalData.length)}개 표시
                  </p>
                  <button
                    onClick={exportToExcel}
                    className="px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700 flex items-center gap-2 text-sm"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                    </svg>
                    엑셀 내보내기
                  </button>
                </div>

                {/* 테이블 */}
                <div className="overflow-x-auto">
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
                      {currentData.map((record, index) => (
                        <tr key={index} className="hover:bg-gray-50">
                          <td className="px-4 py-3 text-sm text-gray-900">
                            {record.sensor_location === 'front' ? '내부팬 앞' :
                             record.sensor_location === 'back' ? '내부팬 뒤' :
                             record.sensor_location === 'top' ? '천장' : record.sensor_location}
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-900">{record.temperature ?? '-'}</td>
                          <td className="px-4 py-3 text-sm text-gray-900">{record.humidity ?? '-'}</td>
                          <td className="px-4 py-3 text-sm text-gray-500">{new Date(record.recorded_at).toLocaleString('ko-KR')}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* 페이징 */}
                {totalPages > 1 && (
                  <div className="flex flex-wrap justify-center items-center gap-1 sm:gap-2 mt-4">
                    <button
                      onClick={() => goToPage(1)}
                      disabled={currentPage === 1}
                      className="px-2 sm:px-3 py-1 rounded border border-gray-300 text-xs sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                    >
                      처음
                    </button>
                    <button
                      onClick={() => goToPage(currentPage - 1)}
                      disabled={currentPage === 1}
                      className="px-2 sm:px-3 py-1 rounded border border-gray-300 text-xs sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                    >
                      이전
                    </button>

                    {/* 페이지 번호 */}
                    {Array.from({ length: Math.min(5, totalPages) }, (_, i) => {
                      let pageNum;
                      if (totalPages <= 5) {
                        pageNum = i + 1;
                      } else if (currentPage <= 3) {
                        pageNum = i + 1;
                      } else if (currentPage >= totalPages - 2) {
                        pageNum = totalPages - 4 + i;
                      } else {
                        pageNum = currentPage - 2 + i;
                      }
                      return (
                        <button
                          key={pageNum}
                          onClick={() => goToPage(pageNum)}
                          className={`px-2 sm:px-3 py-1 rounded text-xs sm:text-sm ${
                            currentPage === pageNum
                              ? 'bg-farm-500 text-gray-900 font-semibold'
                              : 'border border-gray-300 hover:bg-gray-100'
                          }`}
                        >
                          {pageNum}
                        </button>
                      );
                    })}

                    <button
                      onClick={() => goToPage(currentPage + 1)}
                      disabled={currentPage === totalPages}
                      className="px-2 sm:px-3 py-1 rounded border border-gray-300 text-xs sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                    >
                      다음
                    </button>
                    <button
                      onClick={() => goToPage(totalPages)}
                      disabled={currentPage === totalPages}
                      className="px-2 sm:px-3 py-1 rounded border border-gray-300 text-xs sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-100"
                    >
                      마지막
                    </button>
                  </div>
                )}
              </div>
            )}
          </div>
        </section>

        {/* 필터 섹션 */}
        <section className="mb-3 sm:mb-6">
          <header className="bg-farm-500 px-3 sm:px-6 py-2 sm:py-4 rounded-t-lg sm:rounded-t-xl">
            <h2 className="text-sm sm:text-xl font-semibold m-0">차트 조회 조건</h2>
          </header>
          <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-3 sm:p-6">
            <div className="grid grid-cols-2 gap-2 sm:gap-4">
              <div>
                <label className="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">
                  기간
                </label>
                <select
                  value={period}
                  onChange={(e) => setPeriod(e.target.value as "current" | "1h" | "1w" | "1m")}
                  className="w-full px-2 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs sm:text-base"
                >
                  <option value="current">현재</option>
                  <option value="1h">최근 1시간</option>
                  <option value="1w">최근 1주</option>
                  <option value="1m">최근 1개월</option>
                </select>
              </div>
              <div>
                <label className="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2">
                  Zone
                </label>
                <select
                  value={selectedZone}
                  onChange={(e) => setSelectedZone(e.target.value)}
                  className="w-full px-2 sm:px-4 py-1.5 sm:py-2 border border-gray-300 rounded-lg text-xs sm:text-base"
                >
                  <option value="all">전체</option>
                  <option value="zone_a">Zone A</option>
                  <option value="zone_b">Zone B</option>
                  <option value="zone_c">Zone C</option>
                </select>
              </div>
            </div>
          </div>
        </section>

        {/* 차트 컨트롤 (시간 단위 + 데이터 토글) */}
        <section className="mb-3 sm:mb-6">
          <header className="bg-farm-500 px-3 sm:px-6 py-2 sm:py-4 rounded-t-lg sm:rounded-t-xl">
            <h2 className="text-sm sm:text-xl font-semibold m-0">차트 설정</h2>
          </header>
          <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-3 sm:p-6">
            {/* 시간 단위 선택 (주식 차트 스타일) */}
            <div className="mb-4">
              <label className="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                시간 단위
              </label>
              <div className="flex flex-wrap gap-1 sm:gap-2">
                {[
                  { value: "1m", label: "1분" },
                  { value: "5m", label: "5분" },
                  { value: "10m", label: "10분" },
                  { value: "1h", label: "1시간" },
                  { value: "1d", label: "1일" },
                  { value: "1w", label: "1주" },
                  { value: "1M", label: "1달" },
                ].map((item) => (
                  <button
                    key={item.value}
                    onClick={() => setChartInterval(item.value as any)}
                    className={`px-2 sm:px-4 py-1 sm:py-2 rounded-lg text-xs sm:text-sm font-medium transition-all ${
                      chartInterval === item.value
                        ? "bg-farm-500 text-gray-900"
                        : "bg-gray-100 text-gray-600 hover:bg-gray-200"
                    }`}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
            </div>

            {/* 데이터 시리즈 토글 */}
            <div>
              <label className="block text-xs sm:text-sm font-medium text-gray-700 mb-2">
                표시할 데이터
              </label>
              <div className="flex flex-wrap gap-3 sm:gap-4">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={visibleLines.front}
                    onChange={(e) => setVisibleLines({ ...visibleLines, front: e.target.checked })}
                    className="w-4 h-4 accent-green-500"
                  />
                  <span className="flex items-center gap-1.5 text-xs sm:text-sm">
                    <span className="w-3 h-3 rounded-full bg-green-500"></span>
                    팬 앞
                  </span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={visibleLines.back}
                    onChange={(e) => setVisibleLines({ ...visibleLines, back: e.target.checked })}
                    className="w-4 h-4 accent-blue-500"
                  />
                  <span className="flex items-center gap-1.5 text-xs sm:text-sm">
                    <span className="w-3 h-3 rounded-full bg-blue-500"></span>
                    팬 뒤
                  </span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={visibleLines.top}
                    onChange={(e) => setVisibleLines({ ...visibleLines, top: e.target.checked })}
                    className="w-4 h-4 accent-amber-500"
                  />
                  <span className="flex items-center gap-1.5 text-xs sm:text-sm">
                    <span className="w-3 h-3 rounded-full bg-amber-500"></span>
                    천장
                  </span>
                </label>
              </div>
            </div>
          </div>
        </section>

        {/* 온도/습도 타임라인 차트 (좌우 분리) */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-6 mb-3 sm:mb-6">
          {/* 온도 차트 */}
          <section>
            <header className="bg-farm-500 px-3 sm:px-6 py-2 sm:py-4 rounded-t-lg sm:rounded-t-xl flex items-center justify-between">
              <h2 className="text-sm sm:text-xl font-semibold m-0">🌡️ 온도 타임라인</h2>
              <span className="text-xs sm:text-sm text-gray-800">
                {chartInterval === "1m" ? "1분" : chartInterval === "5m" ? "5분" : chartInterval === "10m" ? "10분" : chartInterval === "1h" ? "1시간" : chartInterval === "1d" ? "1일" : chartInterval === "1w" ? "1주" : "1달"} 단위
              </span>
            </header>
            <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-2 sm:p-6">
              {chartData.length === 0 ? (
                <div className="flex items-center justify-center h-32 sm:h-64 text-xs sm:text-base text-gray-500">
                  데이터를 수집하는 중입니다...
                </div>
              ) : (
                <ResponsiveContainer width="100%" height={280}>
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                    <XAxis
                      dataKey="timestamp"
                      tick={{ fontSize: 10 }}
                      angle={-45}
                      textAnchor="end"
                      height={60}
                      interval="preserveStartEnd"
                    />
                    <YAxis
                      tick={{ fontSize: 10 }}
                      domain={['dataMin - 2', 'dataMax + 2']}
                      tickFormatter={(value) => `${value}°`}
                    />
                    <Tooltip
                      formatter={(value: number) => [`${value?.toFixed(1)}°C`, '']}
                      labelStyle={{ fontSize: 12 }}
                      contentStyle={{ fontSize: 12 }}
                    />
                    <Legend wrapperStyle={{ fontSize: '11px', paddingTop: '10px' }} />
                    {visibleLines.front && (
                      <Line
                        type="monotone"
                        dataKey="frontTemp"
                        stroke="#22c55e"
                        name="팬 앞"
                        strokeWidth={2}
                        dot={chartData.length < 50}
                        connectNulls
                      />
                    )}
                    {visibleLines.back && (
                      <Line
                        type="monotone"
                        dataKey="backTemp"
                        stroke="#3b82f6"
                        name="팬 뒤"
                        strokeWidth={2}
                        dot={chartData.length < 50}
                        connectNulls
                      />
                    )}
                    {visibleLines.top && (
                      <Line
                        type="monotone"
                        dataKey="topTemp"
                        stroke="#f59e0b"
                        name="천장"
                        strokeWidth={2}
                        dot={chartData.length < 50}
                        connectNulls
                      />
                    )}
                  </LineChart>
                </ResponsiveContainer>
              )}
            </div>
          </section>

          {/* 습도 차트 */}
          <section>
            <header className="bg-farm-500 px-3 sm:px-6 py-2 sm:py-4 rounded-t-lg sm:rounded-t-xl flex items-center justify-between">
              <h2 className="text-sm sm:text-xl font-semibold m-0">💧 습도 타임라인</h2>
              <span className="text-xs sm:text-sm text-gray-800">
                {chartInterval === "1m" ? "1분" : chartInterval === "5m" ? "5분" : chartInterval === "10m" ? "10분" : chartInterval === "1h" ? "1시간" : chartInterval === "1d" ? "1일" : chartInterval === "1w" ? "1주" : "1달"} 단위
              </span>
            </header>
            <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-2 sm:p-6">
              {chartData.length === 0 ? (
                <div className="flex items-center justify-center h-32 sm:h-64 text-xs sm:text-base text-gray-500">
                  데이터를 수집하는 중입니다...
                </div>
              ) : (
                <ResponsiveContainer width="100%" height={280}>
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                    <XAxis
                      dataKey="timestamp"
                      tick={{ fontSize: 10 }}
                      angle={-45}
                      textAnchor="end"
                      height={60}
                      interval="preserveStartEnd"
                    />
                    <YAxis
                      tick={{ fontSize: 10 }}
                      domain={['dataMin - 5', 'dataMax + 5']}
                      tickFormatter={(value) => `${value}%`}
                    />
                    <Tooltip
                      formatter={(value: number) => [`${value?.toFixed(1)}%`, '']}
                      labelStyle={{ fontSize: 12 }}
                      contentStyle={{ fontSize: 12 }}
                    />
                    <Legend wrapperStyle={{ fontSize: '11px', paddingTop: '10px' }} />
                    {visibleLines.front && (
                      <Line
                        type="monotone"
                        dataKey="frontHum"
                        stroke="#22c55e"
                        name="팬 앞"
                        strokeWidth={2}
                        dot={chartData.length < 50}
                        connectNulls
                      />
                    )}
                    {visibleLines.back && (
                      <Line
                        type="monotone"
                        dataKey="backHum"
                        stroke="#3b82f6"
                        name="팬 뒤"
                        strokeWidth={2}
                        dot={chartData.length < 50}
                        connectNulls
                      />
                    )}
                    {visibleLines.top && (
                      <Line
                        type="monotone"
                        dataKey="topHum"
                        stroke="#f59e0b"
                        name="천장"
                        strokeWidth={2}
                        dot={chartData.length < 50}
                        connectNulls
                      />
                    )}
                  </LineChart>
                </ResponsiveContainer>
              )}
            </div>
          </section>
        </div>

        {/* 실시간 센서 데이터 */}
        <section className="mb-3 sm:mb-6">
          <header className="bg-farm-500 px-3 sm:px-6 py-2 sm:py-4 rounded-t-lg sm:rounded-t-xl">
            <h2 className="text-sm sm:text-xl font-semibold m-0">실시간 센서 데이터</h2>
          </header>
          <div className="bg-white rounded-b-lg sm:rounded-b-xl shadow-card p-3 sm:p-6">
            <dl className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3">
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
