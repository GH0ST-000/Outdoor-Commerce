"use client";

import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { brandConfig } from "@/features/storefront/config/brand";

export function LegalDemoBanner() {
  const { locale } = useStorefrontCopy();
  return (
    <p
      role="note"
      className="rounded-xl border border-[color-mix(in_oklab,var(--amber)_35%,transparent)] bg-[color-mix(in_oklab,var(--amber)_12%,transparent)] px-4 py-3 text-sm text-current/85"
    >
      {brandConfig.legalDemoDisclaimer[locale]}
    </p>
  );
}
