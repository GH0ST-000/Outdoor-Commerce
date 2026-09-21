import type { CatalogLocale } from "@/features/catalog/types/public-catalog";
import { homeContentEn } from "@/features/home/content/en";
import { homeContentKa } from "@/features/home/content/ka";
import type { HomePageContent } from "@/features/home/content/types";

export type {
  HomePageContent,
  HomeTrustItem,
} from "@/features/home/content/types";

export function getHomePageContent(locale: CatalogLocale): HomePageContent {
  return locale === "en" ? homeContentEn : homeContentKa;
}
