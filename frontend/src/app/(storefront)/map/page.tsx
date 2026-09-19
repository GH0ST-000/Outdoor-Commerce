"use client";

import Image from "next/image";
import { LegalDemoBanner } from "@/features/storefront/components/outdoor/LegalDemoBanner";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";

export default function MapPage() {
  const { t } = useStorefrontCopy();

  return (
    <div className="min-h-[calc(100svh-4rem)]">
      <div className="sf-container py-6">
        <header className="mb-4 max-w-2xl">
          <h1 className="sf-display text-4xl">{t.map.title}</h1>
          <p className="mt-2 text-muted-foreground">{t.map.lead}</p>
        </header>
        <LegalDemoBanner />
      </div>
      <div className="relative grid min-h-[70svh] lg:grid-cols-[1fr_320px]">
        <div className="relative min-h-[50svh] border-y border-border/60 lg:border-r">
          <Image
            src="/storefront/map-surface.svg"
            alt=""
            fill
            sizes="100vw"
            className="object-cover"
          />
          <p className="absolute bottom-4 left-4 rounded-lg bg-card/90 px-3 py-2 text-xs text-muted-foreground backdrop-blur">
            {t.map.unavailable}
          </p>
        </div>
        <aside className="space-y-4 bg-card p-5" aria-label={t.map.panel}>
          <h2 className="text-lg font-semibold">{t.map.panel}</h2>
          <p className="text-sm text-muted-foreground">
            Select a zone when geospatial layers connect. This shell is
            presentation-only.
          </p>
          <div>
            <p className="sf-label text-muted-foreground">{t.map.legend}</p>
            <ul className="mt-2 space-y-2 text-sm">
              <li>Open season zone</li>
              <li>Restricted access</li>
              <li>Unverified boundary</li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  );
}
