import { LEGAL_TIME_ZONE } from "@/features/map/lib/map-config";

export function tbilisiToday(now = new Date()): string {
  return new Intl.DateTimeFormat("en-CA", {
    timeZone: LEGAL_TIME_ZONE,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(now);
}

export function addCalendarDays(isoDate: string, days: number): string {
  const [year, month, day] = isoDate.split("-").map(Number);
  const utc = new Date(Date.UTC(year, month - 1, day + days));
  return utc.toISOString().slice(0, 10);
}

export function weekRange(isoDate: string): { from: string; to: string } {
  const [year, month, day] = isoDate.split("-").map(Number);
  const utc = new Date(Date.UTC(year, month - 1, day));
  const weekday = utc.getUTCDay();
  const mondayOffset = weekday === 0 ? -6 : 1 - weekday;
  const from = addCalendarDays(isoDate, mondayOffset);
  return { from, to: addCalendarDays(from, 6) };
}

export function monthRange(isoDate: string): { from: string; to: string } {
  const [year, month] = isoDate.split("-").map(Number);
  const from = `${isoDate.slice(0, 7)}-01`;
  const last = new Date(Date.UTC(year, month, 0)).getUTCDate();
  return {
    from,
    to: `${isoDate.slice(0, 7)}-${String(last).padStart(2, "0")}`,
  };
}

export function tbilisiNoon(isoDate: string): string {
  return `${isoDate}T12:00:00+04:00`;
}
