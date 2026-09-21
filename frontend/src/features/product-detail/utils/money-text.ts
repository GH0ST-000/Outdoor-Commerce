/** Integer tetri → schema.org decimal string. No floating-point money math. */
export function minorToDecimalString(
  amountMinor: number,
  minorUnits = 2,
): string {
  const negative = amountMinor < 0;
  const abs = Math.abs(amountMinor);
  const factor = 10 ** minorUnits;
  const whole = Math.trunc(abs / factor);
  const frac = String(abs % factor).padStart(minorUnits, "0");
  return `${negative ? "-" : ""}${whole}.${frac}`;
}
