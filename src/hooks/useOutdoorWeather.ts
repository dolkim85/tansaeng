import { useEffect, useState } from "react";

export interface OutdoorWeatherCurrent {
  temperature: number | null;
  humidity: number | null;
  sky: string | null;
  precipitationType: string | null;
  precipitation1h: string | null;
  precipitationProbability: number | null;
  windDirection: string | null;
  windSpeed: number | null;
}

export interface OutdoorWeatherForecastDay {
  date: string;
  minTemp: number | null;
  maxTemp: number | null;
  sky: string | null;
  precipitationType: string | null;
  precipitationProbability: number | null;
}

export interface OutdoorWeather {
  location: string;
  current: OutdoorWeatherCurrent;
  forecast: OutdoorWeatherForecastDay[];
}

const REFRESH_INTERVAL_MS = 10 * 60 * 1000; // 서버 캐시 주기(10분)와 동일

export function useOutdoorWeather() {
  const [weather, setWeather] = useState<OutdoorWeather | null>(null);
  const [updatedAt, setUpdatedAt] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    const fetchWeather = async () => {
      try {
        const res = await fetch("/api/smartfarm/get_weather.php");
        const result = await res.json();
        if (cancelled) return;
        if (result.success) {
          setWeather(result.data);
          setUpdatedAt(result.updatedAt ?? null);
          setError(null);
        } else {
          setError(result.error ?? "날씨 조회 실패");
        }
      } catch {
        if (!cancelled) setError("날씨 서버 연결 실패");
      }
    };

    fetchWeather();
    const interval = setInterval(fetchWeather, REFRESH_INTERVAL_MS);
    return () => {
      cancelled = true;
      clearInterval(interval);
    };
  }, []);

  return { weather, updatedAt, error };
}
