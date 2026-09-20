/**
 * Integer minor-unit money helpers for display and form parsing.
 * Never use parseFloat * 100 for business amounts.
 */

export type CurrencyCode = "GEL" | "USD" | "EUR" | (string & {});

export type MoneyDto = {
  amount_minor: number;
  currency: CurrencyCode;
  formatted?: string;
};

export type CurrencyConfig = {
  code: CurrencyCode;
  minor_units: number;
  symbol: string;
};

export const CURRENCIES: Record<string, CurrencyConfig> = {
  GEL: { code: "GEL", minor_units: 2, symbol: "₾" },
  USD: { code: "USD", minor_units: 2, symbol: "$" },
  EUR: { code: "EUR", minor_units: 2, symbol: "€" },
};

const MAX_AMOUNT_MINOR = 9_999_999_999_999;

export type ParseMoneyError =
  | "empty"
  | "invalid"
  | "negative"
  | "too_many_decimals"
  | "scientific"
  | "currency_symbol"
  | "overflow";

export type ParseMoneyResult =
  { ok: true; amount_minor: number } | { ok: false; error: ParseMoneyError };

/**
 * Strict major-unit decimal → minor units. Accepts "." as decimal separator only.
 * Rejects scientific notation, currency symbols, negatives, and excess decimals.
 */
export function parseMajorToMinor(
  input: string,
  currencyCode: string = "GEL",
): ParseMoneyResult {
  const currency = CURRENCIES[currencyCode] ?? CURRENCIES.GEL;
  const minorUnits = currency!.minor_units;
  const raw = input.trim();

  if (raw === "") {
    return { ok: false, error: "empty" };
  }

  if (/[€$₾£¥]/.test(raw) || /[A-Za-z]/.test(raw)) {
    if (/[eE]/.test(raw) && /[0-9]/.test(raw)) {
      return { ok: false, error: "scientific" };
    }
    return { ok: false, error: "currency_symbol" };
  }

  if (/[eE]/.test(raw)) {
    return { ok: false, error: "scientific" };
  }

  if (raw.startsWith("-")) {
    return { ok: false, error: "negative" };
  }

  if (!/^\d+(\.\d+)?$/.test(raw)) {
    return { ok: false, error: "invalid" };
  }

  const [wholePart, fracPart = ""] = raw.split(".");
  if (fracPart.length > minorUnits) {
    return { ok: false, error: "too_many_decimals" };
  }

  const paddedFrac = fracPart.padEnd(minorUnits, "0");
  const digits = `${wholePart}${paddedFrac}`.replace(/^0+(?=\d)/, "") || "0";

  if (digits.length > 15) {
    return { ok: false, error: "overflow" };
  }

  const amountMinor = Number(digits);
  if (!Number.isSafeInteger(amountMinor) || amountMinor > MAX_AMOUNT_MINOR) {
    return { ok: false, error: "overflow" };
  }

  return { ok: true, amount_minor: amountMinor };
}

/**
 * Format minor units for a locale using Intl — never concatenate symbols manually.
 */
export function formatMoneyMinor(
  amountMinor: number,
  currencyCode: string,
  locale: string = "ka-GE",
): string {
  const currency = CURRENCIES[currencyCode] ?? {
    code: currencyCode,
    minor_units: 2,
    symbol: currencyCode,
  };
  const major = amountMinor / 10 ** currency.minor_units;

  return new Intl.NumberFormat(locale, {
    style: "currency",
    currency: currency.code,
    minimumFractionDigits: currency.minor_units,
    maximumFractionDigits: currency.minor_units,
  }).format(major);
}

export function formatMoneyRange(
  minMinor: number,
  maxMinor: number,
  currencyCode: string,
  locale: string = "ka-GE",
): string {
  if (minMinor === maxMinor) {
    return formatMoneyMinor(minMinor, currencyCode, locale);
  }
  return `${formatMoneyMinor(minMinor, currencyCode, locale)} – ${formatMoneyMinor(maxMinor, currencyCode, locale)}`;
}

/** Display-only: basis points → percent label (e.g. 1500 → "15%"). */
export function basisPointsToPercentLabel(basisPoints: number): string {
  if (!Number.isInteger(basisPoints) || basisPoints < 0) {
    return "";
  }
  const whole = Math.floor(basisPoints / 100);
  const frac = basisPoints % 100;
  if (frac === 0) {
    return `${whole}%`;
  }
  const fracStr = String(frac).padStart(2, "0").replace(/0+$/, "");
  return `${whole}.${fracStr}%`;
}

/** Form helper: percent string "15.5" → basis points 1550. */
export function parsePercentToBasisPoints(input: string): ParseMoneyResult {
  const parsed = parseMajorToMinor(input, "GEL");
  if (!parsed.ok) {
    return parsed;
  }
  // parseMajorToMinor with 2 decimals: "15.5" → 1550 — same as basis points scale.
  if (parsed.amount_minor > 10_000) {
    return { ok: false, error: "overflow" };
  }
  if (parsed.amount_minor < 1) {
    return { ok: false, error: "invalid" };
  }
  return parsed;
}
