import type { HomePageContent } from "@/features/home/content/types";

export const homeContentEn: HomePageContent = {
  seo: {
    title: "Hunt — hunting, fishing, and field equipment for Georgia",
    description:
      "Equipment for hunting, fishing, and every route beyond the road. Catalog in Georgian and English, prices in GEL.",
  },
  hero: {
    eyebrow: "Field outfitter",
    headline:
      "Equipment for hunting, fishing, and every route beyond the road.",
    support:
      "Selected for Georgian terrain — forest, river, and ridge. Prices and availability come from the public catalog.",
    primaryCta: "Browse the catalog",
    secondaryCta: "Open field guide",
    scroll: "Scroll",
    fieldNote: "Caucasus · field note",
  },
  categories: {
    eyebrow: "Catalog",
    title: "Choose your field",
    lead: "Enter by pursuit — then refine by brand, price, and availability.",
  },
  gateway: {
    hunting: { description: "Terrain-ready systems" },
    fishing: { description: "Rivers to the Black Sea" },
    camping: { description: "Shelter and heat" },
    clothing: { description: "Layering that works" },
    optics: { description: "Clarity at distance" },
    knives: { description: "Edge and utility" },
  },
  featured: {
    eyebrow: "Selection",
    title: "Field-selected gear",
    lead: "Products the catalog marks as featured — with real GEL prices and availability.",
    catalogCta: "Full catalog",
  },
  offers: {
    eyebrow: "Offers",
    title: "Current discounts",
    lead: "Only products the public catalog returns with a real effective discount.",
  },
  season: {
    eyebrow: "Season",
    title: "Hunting-period context",
    question: "What may be hunted during this period?",
    lead: "Verified seasons, species, and limits will appear here later. This section previews the interface — it is not current law.",
    previewLabel: "Interface preview",
    disclaimer:
      "Demonstration material only — not official Georgian hunting law, and not a claim that any species may be hunted now.",
    calendarCta: "Hunting calendar",
    speciesCta: "Species coming later",
  },
  map: {
    title: "Terrain preview",
    lead: "Zones, access, and context belong on the map experience. This is a visual shell — not legal boundaries.",
    cta: "Open map shell",
    previewLabel: "Interface preview",
    disclaimer:
      "The terrain image is atmospheric. Protected areas and permitted zones are not connected yet.",
  },
  brands: {
    title: "Trusted makers",
    lead: "Brands from the public catalog — name, product count, and a link.",
  },
  journal: {
    eyebrow: "Journal",
    title: "Field guide",
    lead: "Preparation, safety, and equipment care. Editorial fixtures — not legal advice.",
    read: "Read guide",
  },
  trust: {
    title: "What we can confirm today",
    items: [
      {
        id: "authentic",
        heading: "Authentic selection",
        body: "Only active, publicly eligible products appear in the catalog.",
      },
      {
        id: "selection",
        heading: "Field-informed grouping",
        body: "Categories follow hunting, fishing, camping, and related gear.",
      },
      {
        id: "support",
        heading: "Account foundation",
        body: "Sign-in already works. Payments and delivery come in later platform days.",
      },
      {
        id: "legal",
        heading: "Legal context, labeled",
        body: "Seasons and maps will ship with sources. This page does not declare the law.",
      },
    ],
  },
  newsletter: {
    title: "Stay ahead of the season",
    lead: "Season notes, field guides, and new equipment — when the list goes live.",
    cta: "Notify me",
    hint: "Email capture is not connected yet.",
  },
  errors: {
    categories: "Categories could not be loaded.",
    retry: "Retry",
  },
};
