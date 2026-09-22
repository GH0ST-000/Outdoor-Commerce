import { cookies } from "next/headers";
import type { CatalogLocale } from "@/features/catalog/types/public-catalog";

function parseLocale(value: string | undefined): CatalogLocale {
  return value === "en" || value === "ka" ? value : "ka";
}

export async function getRequestCatalogLocale(): Promise<CatalogLocale> {
  try {
    const store = await cookies();
    return parseLocale(store.get("outdoor-locale")?.value);
  } catch {
    // Next prerender / ISR cannot read request cookies. Default to Georgian.
    return "ka";
  }
}
