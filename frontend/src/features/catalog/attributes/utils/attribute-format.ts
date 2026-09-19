/**
 * Mirrors backend AttributeCode::normalize / isValid for client UX.
 */
export function normalizeAttributeCode(value: string): string {
  const next = value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9_]+/g, "_");
  return next.replace(/^_+|_+$/g, "");
}

export function isValidAttributeCode(code: string): boolean {
  return /^[a-z][a-z0-9_]{0,62}$/.test(code);
}

/**
 * Mirrors backend ColorHex::normalize — accepts 3- or 6-digit hex, stores #RRGGBB.
 */
export function normalizeColorHex(value: string): string | null {
  const raw = value.trim().replace(/^#/, "");

  if (/^[0-9a-fA-F]{3}$/.test(raw)) {
    return `#${raw
      .split("")
      .map((char) => `${char}${char}`)
      .join("")}`.toUpperCase();
  }

  if (/^[0-9a-fA-F]{6}$/.test(raw)) {
    return `#${raw.toUpperCase()}`;
  }

  return null;
}

export function isValidColorHex(value: string): boolean {
  return normalizeColorHex(value) !== null;
}
