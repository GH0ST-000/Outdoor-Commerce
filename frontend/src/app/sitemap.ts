import type { MetadataRoute } from "next";
import { getPublicSpeciesList } from "@/features/species/api/public-species-client";
import { emptySpeciesQuery } from "@/features/species/query-state/species-search-params";
import { absoluteUrl } from "@/features/catalog/seo/catalog-seo";

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const entries: MetadataRoute.Sitemap = [
    {
      url: absoluteUrl("/species"),
      changeFrequency: "weekly",
      priority: 0.7,
    },
  ];

  try {
    const list = await getPublicSpeciesList(emptySpeciesQuery(), "ka");
    for (const card of list.data) {
      entries.push({
        url: absoluteUrl(`/species/${card.slug}`),
        changeFrequency: "weekly",
        priority: 0.6,
      });
    }
  } catch {
    // Published species are omitted when the API is cold; /species remains.
  }

  return entries;
}
