"use client";

import { useLocale } from "@/components/locale-provider";
import { brandConfig } from "@/features/storefront/config/brand";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";
import type { StorefrontCopy } from "@/features/storefront/types/storefront-types";
import type { Locale } from "@/i18n/dictionaries";

export function useStorefrontCopy(): {
  locale: Locale;
  t: StorefrontCopy;
  brandName: string;
  tagline: string;
  support: string;
} {
  const { locale } = useLocale();
  return {
    locale,
    t: storefrontCopy[locale],
    brandName: brandConfig.displayName[locale],
    tagline: brandConfig.tagline[locale],
    support: brandConfig.support[locale],
  };
}
