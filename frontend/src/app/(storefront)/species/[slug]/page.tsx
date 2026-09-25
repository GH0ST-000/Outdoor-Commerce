import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { SpeciesDetailView } from "@/features/species/components/SpeciesDetailView";
import { getPublicSpecies } from "@/features/species/api/public-species-client";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import {
  absoluteUrl,
  metadataFromPublicSeo,
} from "@/features/catalog/seo/catalog-seo";
import { ApiClientError } from "@/lib/api-client";

type PageProps = { params: Promise<{ slug: string }> };

export async function generateMetadata({
  params,
}: PageProps): Promise<Metadata> {
  const locale = await getRequestCatalogLocale();
  const { slug } = await params;
  try {
    const species = await getPublicSpecies(slug, locale);
    const image = species.media.find((item) => item.is_primary)?.derivatives[0]
      ?.url;
    return metadataFromPublicSeo(
      {
        title:
          species.seo.title || species.common_name || species.scientific_name,
        description: species.seo.description || species.summary || "",
        canonical_path: `/species/${species.slug}`,
        alternate_locale_paths: {
          ka: `/species/${species.slug}`,
          en: `/species/${species.slug}`,
        },
        open_graph_media: null,
        robots: "index, follow",
      },
      {
        title:
          species.seo.title || species.common_name || species.scientific_name,
        description: species.seo.description || species.summary || "",
        canonical: `/species/${species.slug}`,
        locale: locale === "ka" ? "ka_GE" : "en_US",
        image,
      },
      { index: true, follow: true },
    );
  } catch {
    return { title: "Species", robots: { index: false, follow: false } };
  }
}

export default async function SpeciesDetailPage({ params }: PageProps) {
  const locale = await getRequestCatalogLocale();
  const { slug } = await params;
  let species;
  try {
    species = await getPublicSpecies(slug, locale);
  } catch (error) {
    if (error instanceof ApiClientError && error.status === 404) {
      notFound();
    }
    throw error;
  }

  const jsonLd = {
    "@context": "https://schema.org",
    "@type": "Article",
    headline: species.common_name,
    about: species.scientific_name,
    dateModified: species.meta.updated_at,
    inLanguage: species.locale,
    mainEntityOfPage: absoluteUrl(`/species/${species.slug}`),
  };

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      <SpeciesDetailView locale={locale} species={species} />
    </>
  );
}
