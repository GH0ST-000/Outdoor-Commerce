export function logStorefrontWarning(
  message: string,
  context?: Record<string, unknown>,
): void {
  console.warn(`[storefront] ${message}`, context ?? {});
}

export function logStorefrontError(
  message: string,
  context?: Record<string, unknown>,
): void {
  console.error(`[storefront] ${message}`, context ?? {});
}
