import { useOutdoorWeather } from "../hooks/useOutdoorWeather";

interface WeatherWidgetProps {
  compact?: boolean;
  farmAvgTemp?: number | null;
  farmAvgHumidity?: number | null;
}

function fmt(value: number | null | undefined, digits: number): string {
  return value !== null && value !== undefined && !isNaN(value) ? value.toFixed(digits) : "—";
}

function diffText(farm: number | null | undefined, outdoor: number | null | undefined, digits: number): string {
  if (farm === null || farm === undefined || outdoor === null || outdoor === undefined) return "—";
  const diff = farm - outdoor;
  const sign = diff > 0 ? "+" : "";
  return `${sign}${diff.toFixed(digits)}`;
}

function skyEmoji(sky: string | null | undefined, pty: string | null | undefined): string {
  if (pty && pty !== "없음") {
    if (pty.includes("눈")) return "🌨️";
    if (pty === "소나기") return "🌦️";
    return "🌧️";
  }
  if (sky === "맑음") return "☀️";
  if (sky === "구름많음") return "⛅";
  if (sky === "흐림") return "☁️";
  return "🌤️";
}

const DAY_LABELS = ["오늘", "내일", "모레"];

export default function WeatherWidget({ compact = false, farmAvgTemp, farmAvgHumidity }: WeatherWidgetProps) {
  const { weather, updatedAt, error } = useOutdoorWeather();
  const current = weather?.current;

  if (compact) {
    return (
      <div className="bg-white rounded-lg shadow-sm mb-2 px-3 py-2 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className="text-lg">{skyEmoji(current?.sky, current?.precipitationType)}</span>
          <div>
            <div className="text-[10px] text-gray-500">{weather?.location ?? "실외 날씨"}</div>
            <div className="text-sm font-bold text-gray-800">
              {fmt(current?.temperature, 1)}°C
              <span className="text-gray-400 font-normal mx-1">·</span>
              {fmt(current?.humidity, 0)}%
            </div>
          </div>
        </div>
        <div className="text-right">
          <div className="text-xs text-sky-600 font-medium">강수확률 {current?.precipitationProbability ?? "—"}%</div>
          {current?.precipitationType && current.precipitationType !== "없음" && (
            <div className="text-[10px] text-sky-500">{current.precipitationType} 중</div>
          )}
        </div>
      </div>
    );
  }

  return (
    <section className="mb-2 sm:mb-3">
      <header className="bg-sky-400 px-3 sm:px-4 py-2 sm:py-2.5 rounded-t-lg flex items-center justify-between">
        <h2 className="text-sm sm:text-base font-semibold flex items-center gap-1.5 text-gray-900">
          <span>{skyEmoji(current?.sky, current?.precipitationType)}</span>
          실외 날씨
          <span className="text-[10px] sm:text-xs font-normal text-gray-700">({weather?.location ?? "위치 확인 중"})</span>
        </h2>
        {updatedAt && <span className="text-[10px] sm:text-xs text-gray-700">{updatedAt.slice(11, 16)} 기준</span>}
      </header>
      <div className="bg-white shadow-sm rounded-b-lg p-2 sm:p-4">
        {error && <div className="text-xs text-red-500 mb-2">{error}</div>}

        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
          <div className="bg-red-50 border border-red-200 rounded-lg p-2 sm:p-3 text-center">
            <div className="text-[10px] text-gray-500 mb-1">외부 기온</div>
            <div className="text-xl sm:text-2xl font-bold text-red-500">{fmt(current?.temperature, 1)}</div>
            <div className="text-[10px] text-gray-400">°C</div>
          </div>
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-2 sm:p-3 text-center">
            <div className="text-[10px] text-gray-500 mb-1">외부 습도</div>
            <div className="text-xl sm:text-2xl font-bold text-blue-500">{fmt(current?.humidity, 0)}</div>
            <div className="text-[10px] text-gray-400">%RH</div>
          </div>
          <div className="bg-sky-50 border border-sky-200 rounded-lg p-2 sm:p-3 text-center">
            <div className="text-[10px] text-gray-500 mb-1">강수확률</div>
            <div className="text-xl sm:text-2xl font-bold text-sky-600">{current?.precipitationProbability ?? "—"}</div>
            <div className="text-[10px] text-gray-400">% ({current?.sky ?? "—"})</div>
          </div>
          <div className="bg-gray-50 border border-gray-200 rounded-lg p-2 sm:p-3 text-center">
            <div className="text-[10px] text-gray-500 mb-1">풍향/풍속</div>
            <div className="text-base sm:text-xl font-bold text-gray-700">{current?.windDirection ?? "—"}</div>
            <div className="text-[10px] text-gray-400">{fmt(current?.windSpeed, 1)} m/s</div>
          </div>
        </div>

        {(farmAvgTemp !== null && farmAvgTemp !== undefined) || (farmAvgHumidity !== null && farmAvgHumidity !== undefined) ? (
          <div className="text-[11px] sm:text-xs text-gray-600 mb-3 bg-gray-50 rounded-lg px-2 sm:px-3 py-1.5 sm:py-2">
            농장 내부 평균 대비 &nbsp;
            <span className="font-semibold">온도 {diffText(farmAvgTemp, current?.temperature, 1)}°C</span>
            &nbsp;·&nbsp;
            <span className="font-semibold">습도 {diffText(farmAvgHumidity, current?.humidity, 0)}%RH</span>
          </div>
        ) : null}

        <div className="grid grid-cols-3 gap-2">
          {(weather?.forecast ?? []).map((day, idx) => (
            <div key={day.date} className="bg-gray-50 border border-gray-200 rounded-lg p-2 text-center">
              <div className="text-[10px] text-gray-500 mb-1">
                {DAY_LABELS[idx] ?? day.date} ({day.date.slice(5)})
              </div>
              <div className="text-lg">{skyEmoji(day.sky, day.precipitationType)}</div>
              <div className="text-xs font-semibold text-gray-700">
                <span className="text-blue-500">{fmt(day.minTemp, 0)}°</span>
                {" / "}
                <span className="text-red-500">{fmt(day.maxTemp, 0)}°</span>
              </div>
              <div className="text-[10px] text-sky-600">강수 {day.precipitationProbability ?? "—"}%</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
