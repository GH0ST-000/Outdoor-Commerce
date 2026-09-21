import { notFound } from "next/navigation";
import { CatalogListingView } from "@/features/catalog/components/CatalogListingView";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import {
  loadBrandDetail,
  loadCatalogListing,
} from "@/features/catalog/lib/load-public-catalog";
import {
  parseCatalogSearchParams,
  type SearchParamsInput,
} from "@/features/catalog/query-state/catalog-search-params";

export const revalidate = 60;

export default async function Page({
  params,
  searchParams,
}: {
  params: Promise<{ brandSlug: string }>;
  searchParams: Promise<SearchParamsInput>;
}) {
  const { brandSlug } = await params;
  const locale = await getRequestCatalogLocale();
  const parsed = parseCatalogSearchParams(await searchParams);
  const query = {
    ...parsed,
    brand: parsed.brand.length > 0 ? parsed.brand : [brandSlug],
  };
  const loaded = await loadBrandDetail(brandSlug, locale);

  if ("error" in loaded) {
    throw new Error(loaded.error.code);
  }
  if (!loaded.brand) {
    notFound();
  }

  const list = await loadCatalogListing(query, locale, { brand: brandSlug });
  const pathname = loaded.brand.path || `/brands/${brandSlug}`;

  return (
    <CatalogListingView
      locale={locale}
      query={query}
      pathname={pathname}
      list={list}
      brandSlug={brandSlug}
      title={loaded.brand.name ?? brandSlug}
      lead={loaded.brand.description ?? undefined}
    />
  );
}
