export type StorefrontLocale = "en" | "ka";

export type StorefrontNavItem = {
  id: string;
  href: string;
  labelKey: keyof StorefrontCopy["nav"];
};

export type ProductCardData = {
  id: string;
  slug: string;
  brand: string;
  name: Record<StorefrontLocale, string>;
  href: string;
  imageSrc: string;
  imageAlt: Record<StorefrontLocale, string>;
  badges?: Array<"featured" | "new">;
  attributePreview?: Record<StorefrontLocale, string>;
  /** Presentation only — not authoritative pricing. */
  priceLabel?: Record<StorefrontLocale, string>;
  availabilityLabel?: Record<StorefrontLocale, string>;
};

export type CategoryGatewayItem = {
  id: string;
  slug: string;
  href: string;
  name: Record<StorefrontLocale, string>;
  label: Record<StorefrontLocale, string>;
  imageSrc: string;
  span?: "wide" | "tall" | "standard";
};

export type SeasonStatus = "open" | "closed" | "conditional" | "unknown";

export type SeasonDemoRow = {
  id: string;
  species: Record<StorefrontLocale, string>;
  region: Record<StorefrontLocale, string>;
  month: Record<StorefrontLocale, string>;
  status: SeasonStatus;
  limit: Record<StorefrontLocale, string>;
};

export type FieldGuideCardData = {
  id: string;
  slug: string;
  href: string;
  category: Record<StorefrontLocale, string>;
  title: Record<StorefrontLocale, string>;
  excerpt: Record<StorefrontLocale, string>;
  imageSrc: string;
};

export type BrandShowcaseItem = {
  id: string;
  name: string;
  focus: Record<StorefrontLocale, string>;
  href: string;
};

export type ProductDetailFixture = ProductCardData & {
  modelNumber?: string;
  sku?: string;
  shortDescription: Record<StorefrontLocale, string>;
  description: Record<StorefrontLocale, string>;
  gallery: string[];
  variants: {
    axes: Array<{
      id: string;
      code: string;
      name: Record<StorefrontLocale, string>;
      type: "select" | "color";
      values: Array<{
        id: string;
        code: string;
        name: Record<StorefrontLocale, string>;
        colorHex?: string;
        disabled?: boolean;
      }>;
    }>;
  };
  specs: Array<{
    label: Record<StorefrontLocale, string>;
    value: Record<StorefrontLocale, string>;
  }>;
  contexts: Array<Record<StorefrontLocale, string>>;
};

export type StorefrontCopy = {
  nav: {
    hunting: string;
    fishing: string;
    camping: string;
    clothing: string;
    optics: string;
    knives: string;
    brands: string;
    calendar: string;
    map: string;
    fieldGuide: string;
    catalog: string;
    search: string;
    account: string;
    cartSoon: string;
    wishlistSoon: string;
    openMenu: string;
    closeMenu: string;
    skipToContent: string;
  };
  home: {
    shopCta: string;
    guideCta: string;
    categoriesTitle: string;
    categoriesLead: string;
    featuredTitle: string;
    featuredLead: string;
    seasonTitle: string;
    seasonLead: string;
    mapTitle: string;
    mapLead: string;
    mapCta: string;
    brandsTitle: string;
    brandsLead: string;
    journalTitle: string;
    journalLead: string;
    trustTitle: string;
    newsletterTitle: string;
    newsletterLead: string;
    newsletterCta: string;
    newsletterHint: string;
  };
  catalog: {
    title: string;
    lead: string;
    filters: string;
    sort: string;
    results: string;
    empty: string;
    clearFilters: string;
    applyFilters: string;
  };
  product: {
    model: string;
    sku: string;
    selectVariant: string;
    specs: string;
    context: string;
    related: string;
    notify: string;
    notifyHint: string;
    gallery: string;
  };
  calendar: {
    title: string;
    lead: string;
    search: string;
    open: string;
    closed: string;
    conditional: string;
    unknown: string;
  };
  map: {
    title: string;
    lead: string;
    legend: string;
    panel: string;
    unavailable: string;
  };
  fieldGuide: {
    title: string;
    lead: string;
    read: string;
    updated: string;
    sources: string;
  };
  common: {
    loading: string;
    error: string;
    retry: string;
    back: string;
    priceOnRequest: string;
    comingSoon: string;
    demoLegal: string;
  };
};
