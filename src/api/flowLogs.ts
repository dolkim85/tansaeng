/**
 * 분무수경 유량 로그 API 헬퍼
 * - 최근 데이터(DB)와 1주일 지나 압축 보관된 아카이브(.json.gz)를 함께 다룬다.
 */

const BASE = "https://www.tansaeng.com/api/smartfarm";

export interface FlowLogRow {
  id?: number;
  zone_id: string;
  log_type: "session" | "noflow";
  log_at: string;
  started_at?: string | null;
  ended_at?: string | null;
  duration_sec?: number | null;
  liters?: number | null;
  bypass_triggered?: 0 | 1 | null;
  created_at?: string;
  source: "db" | "archive";
  archive_file?: string;
}

export interface FlowArchiveInfo {
  file: string;
  year: number;
  week: number;
  week_start: string;
  week_end: string;
  size_bytes: number;
  count: number | null;
  modified_at: string;
}

async function getJson<T>(url: string): Promise<T> {
  const res = await fetch(url);
  return res.json();
}

async function postJson<T>(url: string, body: unknown): Promise<T> {
  const res = await fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body),
  });
  return res.json();
}

export function getFlowLogs(params: {
  zoneId: string;
  from: string; // YYYY-MM-DD
  to: string;   // YYYY-MM-DD
  logType?: "session" | "noflow";
  includeArchived?: boolean;
}): Promise<{ success: boolean; data?: FlowLogRow[]; count?: number; message?: string }> {
  const qs = new URLSearchParams({
    zone_id: params.zoneId,
    from: params.from,
    to: params.to,
  });
  if (params.logType) qs.set("log_type", params.logType);
  if (params.includeArchived === false) qs.set("include_archived", "0");
  return getJson(`${BASE}/get_flow_logs.php?${qs.toString()}`);
}

export function deleteFlowLogs(ids: number[]): Promise<{ success: boolean; deleted?: number; message?: string }> {
  return postJson(`${BASE}/delete_flow_log.php`, { ids });
}

export function listFlowArchives(): Promise<{ success: boolean; data?: FlowArchiveInfo[]; message?: string }> {
  return getJson(`${BASE}/list_flow_archives.php`);
}

export function getFlowArchive(file: string, zoneId?: string): Promise<{ success: boolean; data?: FlowLogRow[]; count?: number; message?: string }> {
  const qs = new URLSearchParams({ file });
  if (zoneId) qs.set("zone_id", zoneId);
  return getJson(`${BASE}/get_flow_archive.php?${qs.toString()}`);
}

export function deleteFlowArchive(file: string): Promise<{ success: boolean; message?: string }> {
  return postJson(`${BASE}/delete_flow_archive.php`, { file });
}
