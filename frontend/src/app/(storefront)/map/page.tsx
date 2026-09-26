import type { Metadata } from "next";
import { metadataFromPublicSeo } from "@/features/catalog/seo/catalog-seo";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import { getMapCopy } from "@/features/map/copy/map-copy";
import { LegalMapLoader } from "@/features/map/components/LegalMapLoader";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getRequestCatalogLocale();
  const copy = getMapCopy(locale);
  return metadataFromPublicSeo(
    {
      title: copy.metaTitle,
      description: copy.metaDescription,
      canonical_path: "/map",
      alternate_locale_paths: { ka: "/map", en: "/map" },
      open_graph_media: null,
      robots: "index, follow",
    },
    {
      title: copy.metaTitle,
      description: copy.metaDescription,
      canonical: "/map",
      locale: locale === "ka" ? "ka_GE" : "en_US",
    },
  );
}

export default async function MapPage() {
  const locale = await getRequestCatalogLocale();
  const copy = getMapCopy(locale);
  return (
    <>
      <h1 className="sr-only">{copy.title}</h1>
      <p className="sr-only">{copy.summary}</p>
      <LegalMapLoader />
      <noscript>
        <div className="sf-container py-8">
          <h2>{copy.title}</h2>
          <p>{copy.summary}</p>
          <p>{copy.disclaimer}</p>
        </div>
      </noscript>
    </>
  );
}
