import type { Metadata } from "next";
import { CatalogListingView } from "@/features/catalog/components/CatalogListingView";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import { loadCatalogListing } from "@/features/catalog/lib/load-public-catalog";
import {
  parseSearchPageParams,
  type SearchParamsInput,
} from "@/features/catalog/query-state/catalog-search-params";
import {
  catalogCanonicalPath,
  metadataFromPublicSeo,
} from "@/features/catalog/seo/catalog-seo";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";

type PageProps = {
  searchParams: Promise<SearchParamsInput>;
};

export async function generateMetadata({
  searchParams,
}: PageProps): Promise<Metadata> {
  const locale = await getRequestCatalogLocale();
  const query = parseSearchPageParams(await searchParams);
  const copy = storefrontCopy[locale];
  const title = query.q
    ? `${copy.search.resultsFor} “${query.q}”`
    : copy.search.title;

  return metadataFromPublicSeo(
    undefined,
    {
      title,
      description: copy.search.placeholder,
      canonical: catalogCanonicalPath("/search", query),
      locale: locale === "ka" ? "ka_GE" : "en_US",
    },
    { index: false, follow: true },
  );
}

export default async function Page({ searchParams }: PageProps) {
  const locale = await getRequestCatalogLocale();
  const query = parseSearchPageParams(await searchParams);
  const list = await loadCatalogListing(query, locale);
  const copy = storefrontCopy[locale];
  const title = query.q
    ? `${copy.search.resultsFor} “${query.q}”`
    : copy.search.title;

  return (
    <CatalogListingView
      locale={locale}
      query={query}
      pathname="/search"
      list={list}
      title={title}
      lead={query.q ? undefined : copy.search.minQuery}
    />
  );
}
