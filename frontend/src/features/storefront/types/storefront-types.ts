import type { ResponsiveMedia } from "@/features/storefront/media/types";

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
  /** Day 9+ responsive manifest; fixtures may omit and use imageSrc. */
  imageMedia?: ResponsiveMedia;
  imageAlt: Record<StorefrontLocale, string>;
  badges?: Array<"featured" | "new">;
  attributePreview?: Record<StorefrontLocale, string>;
  /** Presentation only — not authoritative pricing. Prefer structured quote when present. */
  priceLabel?: Record<StorefrontLocale, string>;
  /** Backend-ready structured pricing (Day 11+). Prefer over priceLabel when set. */
  pricing?: {
    currency: string;
    amount_minor?: number | null;
    min_amount_minor?: number | null;
    max_amount_minor?: number | null;
    is_range?: boolean;
    base_amount_minor?: number | null;
    final_amount_minor?: number | null;
    discount_percentage_basis_points?: number | null;
  };
  availabilityLabel?: Record<StorefrontLocale, string>;
  availabilityStatus?:
    "in_stock" | "low_stock" | "out_of_stock" | "unavailable";
  defaultVariantId?: number | null;
  variantCount?: number;
  purchasable?: boolean;
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
  imageSrc: string;
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
  galleryMedia?: ResponsiveMedia[];
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
    filterHint: string;
    brand: string;
    status: string;
    inStock: string;
    onSale: string;
    minPrice: string;
    maxPrice: string;
    rateLimited: string;
    loadError: string;
    nextPage: string;
    previousPage: string;
    layout: string;
    layoutGrid: string;
    layoutComfortable: string;
    layoutList: string;
    sortFeatured: string;
    sortName: string;
    sortNewest: string;
    sortPriceAsc: string;
    sortPriceDesc: string;
    showing: string;
    allCategories: string;
    closeFilters: string;
    sortDefault: string;
    sortNameDesc: string;
    featuredFilter: string;
    emptyCategory: string;
    emptyCategoryDescription: string;
    emptyFiltersDescription: string;
    removeFilter: string;
    childCategories: string;
    productsCount: string;
    paginationLabel: string;
    resultsHeading: string;
    retryProducts: string;
    parentCategory: string;
    clearPrice: string;
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
    notFound: string;
    invalidCombination: string;
    home: string;
    breadcrumbs: string;
    featured: string;
    onSale: string;
    quantity: string;
    quantityDecrease: string;
    quantityIncrease: string;
    addToCart: string;
    cartSoon: string;
    unavailableAction: string;
    purchaseDisabledHint: string;
    share: string;
    shareCopied: string;
    shareFailed: string;
    description: string;
    brandHeading: string;
    moreFromBrand: string;
    visitBrand: string;
    categoryLabel: string;
    galleryPrevious: string;
    galleryNext: string;
    galleryOpen: string;
    galleryClose: string;
    galleryMissing: string;
    outOfStockOption: string;
    priceWas: string;
    priceNow: string;
  };
  calendar: {
    title: string;
    lead: string;
    search: string;
    open: string;
    closed: string;
    conditional: string;
    unknown: string;
    eyebrow: string;
    regulationsTitle: string;
    regulationsLead: string;
    checklist: string[];
    fieldKitTitle: string;
    fieldKit: string[];
  };
  map: {
    title: string;
    lead: string;
    legend: string;
    panel: string;
    unavailable: string;
    grounds: string;
    parks: string;
    species: string;
    access: string;
    legendOpen: string;
    legendPark: string;
    legendUnverified: string;
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
  search: {
    title: string;
    placeholder: string;
    viewAll: string;
    products: string;
    categories: string;
    brands: string;
    noResults: string;
    noResultsHint: string;
    minQuery: string;
    loading: string;
    unavailable: string;
    fallback: string;
    networkError: string;
    close: string;
    resultsFor: string;
  };
};
