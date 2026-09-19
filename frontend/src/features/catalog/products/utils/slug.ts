/**
 * Normalize a product slug while preserving Unicode letters (incl. Georgian).
 * Mirrors backend CatalogSlug::normalize / isValid behavior for client UX.
 */
export function normalizeSlug(value: string): string {
  let next = value.trim().toLocaleLowerCase();
  next = next.replace(/\s+/gu, "-");
  next = next.replace(/[^\p{L}\p{N}\-]+/gu, "");
  next = next.replace(/-+/g, "-");
  return next.replace(/^-+|-+$/g, "");
}

export function slugFromName(name: string): string {
  return normalizeSlug(name);
}

export function isValidSlug(slug: string): boolean {
  if (slug === "" || slug.length > 255) {
    return false;
  }
  return /^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u.test(slug);
}
