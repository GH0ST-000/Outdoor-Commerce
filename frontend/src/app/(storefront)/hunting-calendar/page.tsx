"use client";

import Image from "next/image";
import { LegalDemoBanner } from "@/features/storefront/components/outdoor/LegalDemoBanner";
import { SeasonStatusBadge } from "@/features/storefront/components/outdoor/SeasonStatusBadge";
import { seasonDemo } from "@/features/storefront/fixtures/demo-catalog";
import { storefrontMedia } from "@/features/storefront/config/media";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { Input } from "@/components/ui/input";
import { useMemo, useState } from "react";

export default function HuntingCalendarPage() {
  const { t, locale } = useStorefrontCopy();
  const [query, setQuery] = useState("");
  const rows = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return seasonDemo;
    return seasonDemo.filter((row) =>
      row.species[locale].toLowerCase().includes(q),
    );
  }, [locale, query]);

  return (
    <div className="pb-16">
      <section className="relative isolate overflow-hidden">
        <div className="absolute inset-0">
          <Image
            src={storefrontMedia.categories.hunting}
            alt=""
            fill
            sizes="100vw"
            className="object-cover object-center"
          />
          <div className="absolute inset-0 bg-gradient-to-b from-[var(--alpine-slate)]/55 via-[var(--alpine-slate)]/75 to-[var(--alpine-slate)]" />
        </div>
        <div className="sf-container relative py-16 sm:py-20">
          <p className="sf-label text-[var(--sand)]">{t.calendar.eyebrow}</p>
          <h1 className="sf-display mt-3 text-[clamp(2.25rem,7vw,4.5rem)] leading-[1.02]">
            {t.calendar.title}
          </h1>
          <p className="mt-4 max-w-xl text-sm leading-relaxed text-muted-foreground sm:text-base">
            {t.calendar.lead}
          </p>
        </div>
      </section>

      <div className="sf-container space-y-10 pt-6">
        <LegalDemoBanner />
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t.calendar.search}
          aria-label={t.calendar.search}
          className="max-w-md"
        />

        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {rows.map((row) => (
            <article
              key={row.id}
              className="overflow-hidden rounded-[var(--radius-xl)] border border-border bg-card"
            >
              <div className="relative aspect-[16/10]">
                <Image
                  src={row.imageSrc}
                  alt=""
                  fill
                  sizes="(max-width:1024px) 100vw, 33vw"
                  className="object-cover"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-[var(--alpine-slate)]/85 via-transparent to-transparent" />
                <div className="absolute top-3 right-3">
                  <SeasonStatusBadge status={row.status} />
                </div>
              </div>
              <div className="space-y-2 p-4">
                <h2 className="text-lg font-semibold">{row.species[locale]}</h2>
                <p className="text-sm text-muted-foreground">
                  {row.region[locale]} · {row.month[locale]}
                </p>
                <p className="text-sm text-muted-foreground">
                  {row.limit[locale]}
                </p>
              </div>
            </article>
          ))}
        </div>

        <section className="grid gap-4 lg:grid-cols-2">
          <div className="rounded-[var(--radius-xl)] border border-border bg-card p-5 sm:p-6">
            <h2 className="sf-display text-2xl">
              {t.calendar.regulationsTitle}
            </h2>
            <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
              {t.calendar.regulationsLead}
            </p>
            <ul className="mt-5 space-y-3">
              {t.calendar.checklist.map((item) => (
                <li
                  key={item}
                  className="flex gap-3 text-sm leading-relaxed text-foreground"
                >
                  <span
                    className="mt-1 size-1.5 shrink-0 rounded-full bg-[var(--olive)]"
                    aria-hidden
                  />
                  {item}
                </li>
              ))}
            </ul>
          </div>
          <div className="rounded-[var(--radius-xl)] border border-border bg-[var(--alpine-slate)] p-5 sm:p-6">
            <h2 className="sf-display text-2xl">{t.calendar.fieldKitTitle}</h2>
            <ul className="mt-5 space-y-3">
              {t.calendar.fieldKit.map((item) => (
                <li
                  key={item}
                  className="flex gap-3 rounded-md border border-border/70 bg-card/40 px-3 py-3 text-sm leading-relaxed"
                >
                  <span
                    className="mt-1 size-1.5 shrink-0 rounded-full bg-[var(--sand)]"
                    aria-hidden
                  />
                  {item}
                </li>
              ))}
            </ul>
          </div>
        </section>
      </div>
    </div>
  );
}
