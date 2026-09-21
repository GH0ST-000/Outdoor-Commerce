export const STOREFRONT_EVENTS = {
  homepage_cta_clicked: "homepage_cta_clicked",
  category_opened: "category_opened",
  filter_applied: "filter_applied",
  filter_cleared: "filter_cleared",
  sort_changed: "sort_changed",
  product_card_clicked: "product_card_clicked",
  pagination_changed: "pagination_changed",
} as const;

export type StorefrontEventName =
  (typeof STOREFRONT_EVENTS)[keyof typeof STOREFRONT_EVENTS];

export type StorefrontEventPayload = {
  href?: string;
  category?: string;
  sort?: string;
  page?: number;
  filter?: string;
};

/**
 * Day 33 will attach a provider. Until then this is a no-op and never
 * sends personal data or blocks navigation.
 */
export function trackStorefrontEvent(
  name: StorefrontEventName,
  payload: StorefrontEventPayload = {},
): void {
  void name;
  void payload;
}
