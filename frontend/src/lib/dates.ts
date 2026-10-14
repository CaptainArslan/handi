/** Date helpers. All "dates" are the user's local calendar dates as `YYYY-MM-DD`. */

const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

export const pad = (n: number) => String(n).padStart(2, '0');

/** Today in the device's timezone as YYYY-MM-DD. */
export function todayISO(now = new Date()): string {
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

export function parseISO(date: string): Date {
  const [y, m, d] = date.split('-').map(Number);
  return new Date(y, (m ?? 1) - 1, d ?? 1);
}

export function addDays(date: string, days: number): string {
  const d = parseISO(date);
  d.setDate(d.getDate() + days);
  return todayISO(d);
}

export function daysBetween(a: string, b: string): number {
  return Math.round((parseISO(b).getTime() - parseISO(a).getTime()) / 86400000);
}

/** "Tuesday, 13 October" */
export const longDate = (date: string) => {
  const d = parseISO(date);
  return `${WEEKDAYS[d.getDay()]}, ${d.getDate()} ${MONTHS[d.getMonth()]}`;
};

/** "Tue 13 Oct" */
export const shortDate = (date: string) => {
  const d = parseISO(date);
  return `${WEEKDAYS[d.getDay()].slice(0, 3)} ${d.getDate()} ${MONTHS[d.getMonth()].slice(0, 3)}`;
};

/** "12 Oct" */
export const dayMonth = (date: string) => {
  const d = parseISO(date);
  return `${d.getDate()} ${MONTHS[d.getMonth()].slice(0, 3)}`;
};

/** "October 2026" */
export const monthTitle = (month: string) => {
  const [y, m] = month.split('-').map(Number);
  return `${MONTHS[(m ?? 1) - 1]} ${y}`;
};

export const weekday = (date: string) => WEEKDAYS[parseISO(date).getDay()];
export const monthName = (m: number) => MONTHS[m];

/** "Today, 13 October" / "Yesterday, 12 October" / "Sunday, 11 October" */
export function dayTitle(date: string, today = todayISO()): string {
  const d = parseISO(date);
  const tail = `${d.getDate()} ${MONTHS[d.getMonth()]}`;
  const diff = daysBetween(date, today);
  if (diff === 0) return `Today, ${tail}`;
  if (diff === 1) return `Yesterday, ${tail}`;
  return `${WEEKDAYS[d.getDay()]}, ${tail}`;
}

/** "09:00" → "9:00 AM" */
export function time12(hhmm: string): string {
  const [h, m] = hhmm.split(':').map(Number);
  const suffix = h >= 12 ? 'PM' : 'AM';
  const hour = h % 12 === 0 ? 12 : h % 12;
  return `${hour}:${pad(m ?? 0)} ${suffix}`;
}

/** ISO timestamp → "9:12 AM" in the device timezone. */
export function clock12(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  return time12(`${pad(d.getHours())}:${pad(d.getMinutes())}`);
}

/** ISO timestamp → "07:10" */
export function clock24(iso: string | null | undefined): string {
  if (!iso) return '';
  const d = new Date(iso);
  return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function greeting(now = new Date()): string {
  const h = now.getHours();
  if (h < 12) return 'Good morning';
  if (h < 17) return 'Good afternoon';
  return 'Good evening';
}

/** "Not cooked in 9 days" / "Never cooked yet" */
export function lastCookedTag(days: number | null): string {
  if (days === null) return 'Never cooked yet';
  if (days === 0) return 'Cooked today';
  if (days === 1) return 'Cooked yesterday';
  return `Not cooked in ${days} days`;
}

/** "Last: 14 days ago" / "New for you" */
export function lastShort(days: number | null): string {
  if (days === null) return 'New for you';
  if (days === 0) return 'Last: today';
  if (days === 1) return 'Last: yesterday';
  return `Last: ${days} days ago`;
}

/** Tile meta: "Never cooked" / "Cooked yesterday" / "9 days ago" */
export function tileMeta(days: number | null): string {
  if (days === null) return 'Never cooked';
  if (days === 0) return 'Cooked today';
  if (days === 1) return 'Cooked yesterday';
  return `${days} days ago`;
}

/** "2 min ago", "Just now", "3 hours ago", "3 months ago" */
export function ago(iso: string | null | undefined, now = Date.now()): string {
  if (!iso) return 'never';
  const s = Math.max(0, Math.round((now - new Date(iso).getTime()) / 1000));
  if (s < 60) return 'Just now';
  const m = Math.round(s / 60);
  if (m < 60) return `${m} min ago`;
  const h = Math.round(m / 60);
  if (h < 24) return `${h} hour${h === 1 ? '' : 's'} ago`;
  const d = Math.round(h / 24);
  if (d < 30) return `${d} day${d === 1 ? '' : 's'} ago`;
  const mo = Math.round(d / 30);
  if (mo < 12) return `${mo} month${mo === 1 ? '' : 's'} ago`;
  const y = Math.round(mo / 12);
  return `${y} year${y === 1 ? '' : 's'} ago`;
}

export const capitalize = (s: string) => (s ? s[0].toUpperCase() + s.slice(1) : s);
