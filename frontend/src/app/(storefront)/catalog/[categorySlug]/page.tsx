import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { CatalogListingView } from "@/features/catalog/components/CatalogListingView";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import {
  loadCatalogListing,
  loadCategoryDetail,
} from "@/features/catalog/lib/load-public-catalog";
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

type PageProps = {
  params: Promise<{ categorySlug: string }>;
  searchParams: Promise<SearchParamsInput>;
};

export async function generateMetadata({
  params,
  searchParams,
}: PageProps): Promise<Metadata> {
  const { categorySlug } = await params;
  const locale = await getRequestCatalogLocale();
  const query = parseCatalogSearchParams(await searchParams);
  const loaded = await loadCategoryDetail(categorySlug, locale);
  if ("error" in loaded || !loaded.category) {
    notFound();
  }

  const list = await loadCatalogListing(query, locale, {
    category: categorySlug,
  });
  const pathname = loaded.category.canonical_path || `/catalog/${categorySlug}`;
  const canonical = catalogCanonicalPath(pathname, query);

  return metadataFromPublicSeo(
    loaded.category.seo,
    {
      title: loaded.category.name ?? categorySlug,
      description: loaded.category.description ?? undefined,
      canonical,
      locale: locale === "ka" ? "ka_GE" : "en_US",
    },
    catalogRobots(query, list.total),
  );
}

export default async function Page({ params, searchParams }: PageProps) {
  const { categorySlug } = await params;
  const locale = await getRequestCatalogLocale();
  const query = parseCatalogSearchParams(await searchParams);
  const loaded = await loadCategoryDetail(categorySlug, locale);

  if ("error" in loaded) {
    throw new Error(loaded.error.code);
  }
  if (!loaded.category) {
    notFound();
  }

  const list = await loadCatalogListing(query, locale, {
    category: categorySlug,
  });
  const copy = storefrontCopy[locale];
  const pathname = loaded.category.canonical_path || `/catalog/${categorySlug}`;

  return (
    <CatalogListingView
      locale={locale}
      query={query}
      pathname={pathname}
      list={list}
      category={loaded.category}
      categorySlug={categorySlug}
      title={loaded.category.name ?? copy.catalog.title}
      lead={loaded.category.description ?? copy.catalog.lead}
    />
  );
}
