import { storefrontMedia } from "@/features/storefront/config/media";

/**
 * Editorial homepage composition. Day 32 can replace this with managed content
 * by keeping the same slot ids and copy shape.
 */
export const homepageGatewaySlots = [
  { slug: "hunting", span: "wide" as const },
  { slug: "fishing", span: "tall" as const },
  { slug: "camping", span: "standard" as const },
  { slug: "clothing", span: "standard" as const },
  { slug: "optics", span: "standard" as const },
  { slug: "knives", span: "standard" as const },
] as const;

export type HomepageGatewaySlug = (typeof homepageGatewaySlots)[number]["slug"];

export const homepageGatewayImages: Record<HomepageGatewaySlug, string> = {
  hunting: storefrontMedia.categories.hunting,
  fishing: storefrontMedia.categories.fishing,
  camping: storefrontMedia.categories.camping,
  clothing: storefrontMedia.categories.clothing,
  optics: storefrontMedia.categories.optics,
  knives: storefrontMedia.categories.knives,
};

export const homepageRevalidateSeconds = 60;
