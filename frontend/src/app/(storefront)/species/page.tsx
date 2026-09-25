import type { Metadata } from "next";
import { SpeciesDirectoryView } from "@/features/species/components/SpeciesDirectoryView";
import {
  getPublicSpeciesFilters,
  getPublicSpeciesList,
} from "@/features/species/api/public-species-client";
import {
  parseSpeciesSearchParams,
  type SearchParamsInput,
} from "@/features/species/query-state/species-search-params";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import { metadataFromPublicSeo } from "@/features/catalog/seo/catalog-seo";
import type { SpeciesFilters } from "@/features/species/types/species-types";
import { ApiClientError } from "@/lib/api-client";

type PageProps = { searchParams: Promise<SearchParamsInput> };

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getRequestCatalogLocale();
  return metadataFromPublicSeo(
    {
      title: locale === "ka" ? "სახეობები" : "Species",
      description:
        locale === "ka"
          ? "ბიოლოგიური იდენტიფიკაცია, არა სანადირო ნებართვა."
          : "Biological identification, not hunting permission.",
      canonical_path: "/species",
      alternate_locale_paths: { ka: "/species", en: "/species" },
      open_graph_media: null,
      robots: "index, follow",
    },
    {
      title: locale === "ka" ? "სახეობები" : "Species",
      description:
        locale === "ka"
          ? "ბიოლოგიური იდენტიფიკაცია, არა სანადირო ნებართვა."
          : "Biological identification, not hunting permission.",
      canonical: `/species`,
      locale: locale === "ka" ? "ka_GE" : "en_US",
    },
  );
}

export default async function SpeciesIndexPage({ searchParams }: PageProps) {
  const locale = await getRequestCatalogLocale();
  const query = parseSpeciesSearchParams(await searchParams);
  let cards: Awaited<ReturnType<typeof getPublicSpeciesList>>["data"] = [];
  let total = 0;
  let lastPage = 1;
  let filters: SpeciesFilters | null = null;
  let error: string | null = null;

  try {
    const [list, filterPayload] = await Promise.all([
      getPublicSpeciesList(query, locale),
      getPublicSpeciesFilters(locale),
    ]);
    cards = list.data;
    total = list.meta.pagination.total;
    lastPage = list.meta.pagination.last_page;
    filters = filterPayload;
  } catch (caught) {
    error = caught instanceof ApiClientError ? caught.code : "SERVER_ERROR";
  }

  return (
    <SpeciesDirectoryView
      locale={locale}
      query={query}
      cards={cards}
      total={total}
      lastPage={lastPage}
      filters={filters}
      error={error}
    />
  );
}
