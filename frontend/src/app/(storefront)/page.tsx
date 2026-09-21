import type { Metadata } from "next";
import { HomeView } from "@/features/home/components/HomeView";
import { getHomePageData } from "@/features/home/api/get-home-page-data";
import { getHomePageContent } from "@/features/home/content";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import { metadataFromPublicSeo } from "@/features/catalog/seo/catalog-seo";
import { storefrontMedia } from "@/features/storefront/config/media";

export const revalidate = 60;

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getRequestCatalogLocale();
  const content = getHomePageContent(locale);
  return metadataFromPublicSeo(
    {
      title: content.seo.title,
      description: content.seo.description,
      canonical_path: "/",
      alternate_locale_paths: { ka: "/", en: "/" },
      open_graph_media: null,
      robots: "index,follow",
    },
    {
      title: content.seo.title,
      description: content.seo.description,
      canonical: "/",
      locale: locale === "ka" ? "ka_GE" : "en_US",
      image: storefrontMedia.hero,
    },
  );
}

export default async function Page() {
  const locale = await getRequestCatalogLocale();
  const data = await getHomePageData(locale);
  const content = getHomePageContent(locale);

  return <HomeView data={data} content={content} />;
}
