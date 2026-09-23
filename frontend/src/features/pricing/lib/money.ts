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

function isGeorgianLocale(locale: string): boolean {
  return locale.toLowerCase().startsWith("ka");
}

function groupThousands(whole: number, separator: string): string {
  const digits = String(Math.trunc(Math.abs(whole)));
  const parts: string[] = [];
  for (let i = digits.length; i > 0; i -= 3) {
    parts.unshift(digits.slice(Math.max(0, i - 3), i));
  }
  return parts.join(separator);
}

/**
 * Format minor units with a stable ka/en pattern.
 * Intl is not used here: Node and Chromium ship different ICU data, which
 * hydrates `GEL 129.99` on the server and `129,99 ₾` in the browser.
 */
export function formatMoneyMinor(
  amountMinor: number,
  currencyCode: string,
  locale: string = "ka-GE",
): string {
  if (!Number.isFinite(amountMinor) || !Number.isInteger(amountMinor)) {
    return "";
  }

  const currency = CURRENCIES[currencyCode] ?? {
    code: currencyCode,
    minor_units: 2,
    symbol: currencyCode,
  };
  const georgian = isGeorgianLocale(locale);
  const scale = 10 ** currency.minor_units;
  const negative = amountMinor < 0;
  const absolute = Math.abs(amountMinor);
  const whole = Math.trunc(absolute / scale);
  const fraction = String(absolute % scale).padStart(currency.minor_units, "0");
  const grouped = groupThousands(whole, georgian ? "\u00a0" : ",");
  const number = `${negative ? "-" : ""}${grouped}${georgian ? "," : "."}${fraction}`;

  if (currency.code === "GEL") {
    return georgian ? `${number} ₾` : `GEL ${number}`;
  }

  return georgian
    ? `${number} ${currency.symbol}`
    : `${currency.symbol}${number}`;
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
