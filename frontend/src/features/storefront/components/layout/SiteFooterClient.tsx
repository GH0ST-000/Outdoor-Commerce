"use client";

import { useLocale } from "@/components/locale-provider";
import { SiteFooter as FooterView } from "@/features/storefront/components/layout/SiteFooter";

export function SiteFooterClient() {
  const { locale } = useLocale();
  return <FooterView locale={locale} />;
}
