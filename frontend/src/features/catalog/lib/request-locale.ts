import { cookies } from "next/headers";
import type { CatalogLocale } from "@/features/catalog/types/public-catalog";

export async function getRequestCatalogLocale(): Promise<CatalogLocale> {
  const store = await cookies();
  const value = store.get("outdoor-locale")?.value;
  return value === "en" || value === "ka" ? value : "ka";
}
