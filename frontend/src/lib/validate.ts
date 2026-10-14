/** Validation rules and the exact error copy from the design. */

export const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
export const NAME_RE = /^[\p{L} .'-]+$/u;
export const DISH_NAME_RE = /^[\p{L}\p{M} '’()-]+$/u;

export function nameError(raw: string): string | null {
  const v = raw.trim();
  if (!v) return 'Enter your name.';
  if (v.length < 2) return 'Name is too short.';
  if (v.length > 50) return 'Keep it under 50 characters.';
  if (!NAME_RE.test(v)) return 'Use letters and spaces only.';
  return null;
}

export function emailError(raw: string, empty = 'Enter your email.', invalid = 'Enter a valid email, like name@example.com.'): string | null {
  const v = raw.trim().toLowerCase();
  if (!v) return empty;
  if (!EMAIL_RE.test(v)) return invalid;
  return null;
}

export function newPasswordError(pw: string): string | null {
  if (!pw) return 'Create a password.';
  if (pw.length < 8) return 'Use at least 8 characters.';
  if (!/[A-Za-z]/.test(pw) || !/\d/.test(pw)) return 'Mix letters and at least one number.';
  return null;
}

export function confirmError(pw: string, pw2: string, empty = 'Confirm your password.'): string | null {
  if (!pw2) return empty;
  if (pw !== pw2) return 'Passwords don’t match.';
  return null;
}

/** Password strength 0–4: len≥8, len≥12, lower+upper, digit+symbol. */
export function strength(pw: string): number {
  let s = 0;
  if (pw.length >= 8) s++;
  if (pw.length >= 12) s++;
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++;
  if (/\d/.test(pw) && /[^A-Za-z0-9]/.test(pw)) s++;
  return s;
}
export const STRENGTH_LABEL = ['Weak', 'Weak', 'Okay', 'Good', 'Strong'];

export const pwRules = (pw: string) => ({
  length: pw.length >= 8,
  letter: /[A-Za-z]/.test(pw),
  number: /\d/.test(pw),
  trimmed: pw.length > 0 && pw === pw.trim(),
});

/** Family size field (onboarding + settings). */
export function familyError(raw: string, max = 'For more than 20 people, enter 20.'): string | null {
  if (!raw.trim()) return 'Enter how many people eat at home.';
  if (!/^\d+$/.test(raw.trim())) return 'Use a whole number.';
  const n = Number(raw);
  if (n < 1) return 'At least 1 person.';
  if (n > 20) return max;
  return null;
}

export const cleanDishName = (raw: string) => raw.trim().replace(/\s+/g, ' ');

export function dishNameError(raw: string, existing: string[] = []): string | null {
  const v = cleanDishName(raw);
  if (!v) return 'Give your dish a name.';
  if (v.length < 2) return 'Name is too short.';
  if (!DISH_NAME_RE.test(v)) return 'Use letters, spaces and ( ) - only.';
  if (existing.some((e) => e.toLowerCase() === v.toLowerCase())) return `“${v}” is already in your dishes.`;
  return null;
}

/** Reminder time "HH:mm" between 05:00 and 13:00. */
export const timeOk = (t: string) => /^\d{2}:\d{2}$/.test(t) && t >= '05:00' && t <= '13:00';
export const TIME_ERROR = 'Pick a time between 5:00 AM and 1:00 PM.';

/** "Muhammad Arslan" → "MA", "Hina" → "H" (first + last word). */
export function initials(name: string | null | undefined): string {
  const words = (name ?? '').trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) return '?';
  const first = words[0][0] ?? '';
  const last = words.length > 1 ? (words[words.length - 1][0] ?? '') : '';
  return (first + last).toUpperCase();
}
