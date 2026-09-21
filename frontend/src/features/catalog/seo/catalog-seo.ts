import type { Metadata } from "next";
import {
  catalogQueryHasFilters,
  type CatalogQuery,
} from "@/features/catalog/query-state/catalog-search-params";
import type {
  PublicMedia,
  PublicSeo,
} from "@/features/catalog/types/public-catalog";
import { getPublicEnv } from "@/lib/env";

export function siteOrigin(): string {
  return (
    getPublicEnv().NEXT_PUBLIC_APP_URL ?? "http://localhost:3000"
  ).replace(/\/$/, "");
}

export function absoluteUrl(path: string): string {
  if (path.startsWith("http://") || path.startsWith("https://")) {
    return path;
  }
  return `${siteOrigin()}${path.startsWith("/") ? path : `/${path}`}`;
}

export function catalogRobots(
  query: CatalogQuery,
  total: number,
): Metadata["robots"] {
  if (total === 0 || catalogQueryHasFilters(query)) {
    return { index: false, follow: true };
  }
  return { index: true, follow: true };
}

export function catalogCanonicalPath(
  pathname: string,
  query: CatalogQuery,
): string {
  if (query.page > 1 && !catalogQueryHasFilters(query)) {
    return `${pathname}?page=${query.page}`;
  }
  return pathname;
}

function firstMediaUrl(
  media: PublicMedia | null | undefined,
): string | undefined {
  if (!media?.sources) {
    return undefined;
  }
  for (const format of ["webp", "jpeg", "png", "avif"]) {
    const presets = media.sources[format]?.presets;
    if (!presets) {
      continue;
    }
    for (const preset of ["card_large", "detail", "card", "thumbnail"]) {
      const url = presets[preset]?.url;
      if (url) {
        return url;
      }
    }
    const fallback = Object.values(presets)[0]?.url;
    if (fallback) {
      return fallback;
    }
  }
  return undefined;
}

export function metadataFromPublicSeo(
  seo: PublicSeo | null | undefined,
  fallback: {
    title: string;
    description?: string;
    canonical: string;
    locale?: string;
    image?: string;
  },
  robots?: Metadata["robots"],
): Metadata {
  const title = seo?.title || fallback.title;
  const description = seo?.description ?? fallback.description;
  const canonical = seo?.canonical_path || fallback.canonical;
  const languages = seo?.alternate_locale_paths;
  const image = firstMediaUrl(seo?.open_graph_media) ?? fallback.image;

  return {
    title,
    description,
    alternates: {
      canonical,
      languages,
    },
    robots:
      robots ?? (seo?.robots ? seo.robots : { index: true, follow: true }),
    openGraph: {
      title,
      description,
      url: absoluteUrl(canonical),
      locale: fallback.locale,
      images: image ? [{ url: absoluteUrl(image) }] : undefined,
    },
  };
}

export function escapeJsonLd(value: unknown): string {
  return JSON.stringify(value).replace(/</g, "\\u003c");
}
