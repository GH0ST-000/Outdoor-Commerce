import type { Metadata } from "next";
import { CatalogListingView } from "@/features/catalog/components/CatalogListingView";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import { loadCatalogListing } from "@/features/catalog/lib/load-public-catalog";
import {
  parseCatalogSearchParams,
  type SearchParamsInput,
} from "@/features/catalog/query-state/catalog-search-params";
import {
  catalogCanonicalPath,
  catalogRobots,
  metadataFromPublicSeo,
} from "@/features/catalog/seo/catalog-seo";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";

export const revalidate = 60;

type PageProps = {
  searchParams: Promise<SearchParamsInput>;
};

export async function generateMetadata({
  searchParams,
}: PageProps): Promise<Metadata> {
  const locale = await getRequestCatalogLocale();
  const query = parseCatalogSearchParams(await searchParams);
  const list = await loadCatalogListing(query, locale);
  const copy = storefrontCopy[locale];
  const canonical = catalogCanonicalPath("/catalog", query);

  return metadataFromPublicSeo(
    undefined,
    {
      title: copy.catalog.title,
      description: copy.catalog.lead,
      canonical,
      locale: locale === "ka" ? "ka_GE" : "en_US",
    },
    catalogRobots(query, list.total),
  );
}

export default async function Page({ searchParams }: PageProps) {
  const locale = await getRequestCatalogLocale();
  const query = parseCatalogSearchParams(await searchParams);
  const list = await loadCatalogListing(query, locale);
  const copy = storefrontCopy[locale];

  return (
    <CatalogListingView
      locale={locale}
      query={query}
      pathname="/catalog"
      list={list}
      title={copy.catalog.title}
      lead={copy.catalog.lead}
    />
  );
}
