"use client";

import Image from "next/image";
import { useState } from "react";
import { LegalDemoBanner } from "@/features/storefront/components/outdoor/LegalDemoBanner";
import { storefrontMedia } from "@/features/storefront/config/media";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { MapLegend } from "@/components/outdoor-context/primitives";
import { Switch } from "@/components/ui/switch";

export default function MapPage() {
  const { t } = useStorefrontCopy();
  const [grounds, setGrounds] = useState(true);
  const [parks, setParks] = useState(true);
  const [species, setSpecies] = useState(false);
  const [access, setAccess] = useState(false);

  return (
    <div className="min-h-[calc(100svh-4rem)]">
      <div className="sf-container py-6">
        <header className="mb-4 max-w-2xl">
          <p className="sf-label text-[var(--sand)]">{t.nav.map}</p>
          <h1 className="sf-display mt-2 text-4xl sm:text-5xl">
            {t.map.title}
          </h1>
          <p className="mt-2 text-muted-foreground">{t.map.lead}</p>
        </header>
        <LegalDemoBanner />
      </div>
      <div className="relative min-h-[70svh] overflow-hidden border-y border-border">
        <Image
          src={storefrontMedia.map}
          alt=""
          fill
          sizes="100vw"
          className="object-cover brightness-[0.88] saturate-[0.9]"
        />
        <div
          aria-hidden
          className="absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_10%,rgba(26,26,26,0.28)_100%)]"
        />
        <p className="absolute bottom-4 left-4 z-[1] rounded-md border border-border bg-[var(--alpine-slate)]/85 px-3 py-2 text-xs text-muted-foreground backdrop-blur">
          {t.map.unavailable}
        </p>
        <aside
          className="absolute top-4 right-4 z-[1] w-[min(100%-2rem,18rem)] space-y-4 rounded-[var(--radius-xl)] border border-border bg-[var(--alpine-slate)]/90 p-4 backdrop-blur-md"
          aria-label={t.map.panel}
        >
          <h2 className="text-lg font-semibold">{t.map.panel}</h2>
          <ul className="space-y-3 text-sm">
            <LayerToggle
              id="map-grounds"
              label={t.map.grounds}
              checked={grounds}
              onCheckedChange={setGrounds}
            />
            <LayerToggle
              id="map-parks"
              label={t.map.parks}
              checked={parks}
              onCheckedChange={setParks}
            />
            <LayerToggle
              id="map-species"
              label={t.map.species}
              checked={species}
              onCheckedChange={setSpecies}
            />
            <LayerToggle
              id="map-access"
              label={t.map.access}
              checked={access}
              onCheckedChange={setAccess}
            />
          </ul>
          <div>
            <p className="sf-label text-[var(--sand)]">{t.map.legend}</p>
            <div className="mt-2">
              <MapLegend
                items={[
                  { label: t.map.legendOpen, swatch: "#556B2F" },
                  { label: t.map.legendPark, swatch: "#C4A484" },
                  { label: t.map.legendUnverified, swatch: "#E5E5E5" },
                ]}
              />
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
}

function LayerToggle({
  id,
  label,
  checked,
  onCheckedChange,
}: {
  id: string;
  label: string;
  checked: boolean;
  onCheckedChange: (value: boolean) => void;
}) {
  return (
    <li className="flex items-center justify-between gap-3">
      <label htmlFor={id} className="cursor-pointer text-foreground">
        {label}
      </label>
      <Switch
        id={id}
        checked={checked}
        onCheckedChange={onCheckedChange}
        aria-label={label}
      />
    </li>
  );
}
