import type { Locale } from "@/i18n/dictionaries";

/** Every storefront fixture carries both locales so nothing falls back to English. */
export type Localized = Record<Locale, string>;

export type CategorySlug =
  "hunting" | "fishing" | "camping" | "clothing" | "optics" | "knives-tools";

/**
 * Commercial state of a catalogue entry. There is no `in-stock` member on
 * purpose: the storefront has no inventory source yet, so claiming stock would
 * be dishonest.
 */
export type Availability = "coming-soon" | "in-development" | "archived";

/** `src` is nullable so the UI is forced to handle art that has not shipped. */
export type StorefrontImage = {
  src: string | null;
  alt: Localized;
  width: number;
  height: number;
};

export type StorefrontCategory = {
  slug: CategorySlug;
  name: Localized;
  /** Short nav-level descriptor, one line. */
  kicker: Localized;
  lede: Localized;
  image: StorefrontImage;
  /** Drives the mixed-scale gateway on the homepage. */
  emphasis: "primary" | "secondary" | "tertiary";
  /**
   * Editorial prose shown on the category hero. Deliberately not links —
   * sub-category routes do not exist yet and dead nav is worse than none.
   */
  highlights: Localized[];
};

export type VariantAxisKind = "color" | "text";

export type VariantOption = {
  id: string;
  label: Localized;
  /** Present only on `color` axes; never the sole carrier of meaning. */
  swatch?: string;
  available: boolean;
};

export type VariantAxis = {
  id: string;
  kind: VariantAxisKind;
  label: Localized;
  options: VariantOption[];
};

export type ProductSpec = {
  label: Localized;
  value: Localized;
};

export type StorefrontProduct = {
  id: string;
  slug: string;
  name: Localized;
  /** Placeholder house brands — see docs/storefront-design.md. */
  brand: string;
  categorySlug: CategorySlug;
  summary: Localized;
  description: Localized;
  images: StorefrontImage[];
  availability: Availability;
  /**
   * Pricing is intentionally not modelled as a number. Until the pricing
   * service exists, the PDP and cards show "price on request".
   */
  priceMode: "on-request";
  weightClass: WeightClass;
  specs: ProductSpec[];
  axes: VariantAxis[];
  /** Used by the client-side filter prototype. */
  facets: {
    season: SeasonTag[];
    terrain: TerrainTag[];
  };
};

export type WeightClass = "light" | "standard" | "heavy";

export type SeasonTag = "spring" | "summer" | "autumn" | "winter";

export type TerrainTag = "alpine" | "forest" | "river" | "steppe";

export type SeasonState = "open" | "closed" | "restricted" | "review";

export type SpeciesEntry = {
  id: string;
  name: Localized;
  latin: string;
  region: Localized;
  state: SeasonState;
  window: Localized;
  note: Localized;
};

export type MapRegion = {
  id: string;
  name: Localized;
  terrain: TerrainTag;
  note: Localized;
  /** Percentage coordinates on the decorative map surface. */
  x: number;
  y: number;
};

export type FieldGuideArticle = {
  slug: string;
  title: Localized;
  standfirst: Localized;
  section: Localized;
  readingMinutes: number;
  image: StorefrontImage;
  body: Localized[];
};

export type HouseBrand = {
  id: string;
  name: string;
  discipline: Localized;
  origin: Localized;
};

export type SearchHit = {
  id: string;
  label: Localized;
  context: Localized;
  href: string;
  kind: "product" | "category" | "guide" | "tool";
};
