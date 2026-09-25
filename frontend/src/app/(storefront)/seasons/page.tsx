import type { Metadata } from "next";
import { SeasonExplorer } from "@/features/seasons/components/SeasonExplorer";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import { metadataFromPublicSeo } from "@/features/catalog/seo/catalog-seo";

export async function generateMetadata(): Promise<Metadata> {
  const locale = await getRequestCatalogLocale();
  return metadataFromPublicSeo(
    {
      title:
        locale === "ka"
          ? "სანადირო და სათევზაო სეზონები"
          : "Hunting and fishing seasons",
      description:
        locale === "ka"
          ? "გამოქვეყნებული ოფიციალური წყაროებით პერიოდის ხელმისაწვდომობა. ეს არ არის იურიდიული კონსულტაცია."
          : "Period availability from published official sources. This is not legal advice.",
      canonical_path: "/seasons",
      alternate_locale_paths: { ka: "/seasons", en: "/seasons" },
      open_graph_media: null,
      robots: "index, follow",
    },
    {
      title:
        locale === "ka"
          ? "სანადირო და სათევზაო სეზონები"
          : "Hunting and fishing seasons",
      description:
        locale === "ka"
          ? "გამოქვეყნებული ოფიციალური წყაროებით პერიოდის ხელმისაწვდომობა."
          : "Period availability from published official sources.",
      canonical: "/seasons",
      locale: locale === "ka" ? "ka_GE" : "en_US",
    },
  );
}

export default function SeasonsPage() {
  return <SeasonExplorer />;
}
