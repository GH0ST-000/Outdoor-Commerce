/**
 * GEL major-unit <-> tetri (minor-unit) conversion without float multiplication.
 */

export function gelMajorToMinor(value: string): number | undefined {
  const trimmed = value.trim().replace(",", ".");
  if (trimmed === "") {
    return undefined;
  }
  const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(trimmed);
  if (!match) {
    return undefined;
  }
  const major = Number(match[1]);
  const fraction = (match[2] ?? "00").padEnd(2, "0");
  const minor = major * 100 + Number(fraction);
  if (!Number.isSafeInteger(minor) || minor < 0) {
    return undefined;
  }
  return minor;
}

export function minorToGelMajor(minor: number | undefined): string {
  if (minor === undefined) {
    return "";
  }
  const major = Math.trunc(minor / 100);
  const fraction = Math.abs(minor % 100);
  if (fraction === 0) {
    return String(major);
  }
  return `${major}.${String(fraction).padStart(2, "0")}`;
}
