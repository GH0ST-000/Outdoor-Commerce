"use client";

import { LegalDemoBanner } from "@/features/storefront/components/outdoor/LegalDemoBanner";
import { SeasonStatusBadge } from "@/features/storefront/components/outdoor/SeasonStatusBadge";
import { seasonDemo } from "@/features/storefront/fixtures/demo-catalog";
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
    <div className="sf-band-paper sf-section">
      <div className="sf-container space-y-6">
        <header className="max-w-2xl">
          <p className="sf-label text-[var(--copper)]">
            {locale === "ka" ? "კალენდარი" : "Calendar"}
          </p>
          <h1 className="sf-display mt-2 text-4xl sm:text-5xl">
            {t.calendar.title}
          </h1>
          <p className="mt-3 text-muted-foreground">{t.calendar.lead}</p>
        </header>
        <LegalDemoBanner />
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t.calendar.search}
          aria-label={t.calendar.search}
          className="max-w-md"
        />
        <div className="grid gap-3 md:hidden">
          {rows.map((row) => (
            <article
              key={row.id}
              className="rounded-2xl border border-border/70 bg-card p-4"
            >
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-semibold">{row.species[locale]}</p>
                  <p className="mt-1 text-sm text-muted-foreground">
                    {row.region[locale]} · {row.month[locale]}
                  </p>
                </div>
                <SeasonStatusBadge status={row.status} />
              </div>
              <p className="mt-3 text-sm text-muted-foreground">
                {row.limit[locale]}
              </p>
            </article>
          ))}
        </div>
        <div className="hidden overflow-hidden rounded-2xl border border-border/70 md:block">
          <table className="w-full text-left text-sm">
            <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th className="px-4 py-3">Species</th>
                <th className="px-4 py-3">Region</th>
                <th className="px-4 py-3">Month</th>
                <th className="px-4 py-3">Status</th>
                <th className="px-4 py-3">Limit</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr
                  key={row.id}
                  className="border-t border-border/50 transition-colors hover:bg-muted/40"
                >
                  <td className="px-4 py-3 font-medium">
                    {row.species[locale]}
                  </td>
                  <td className="px-4 py-3">{row.region[locale]}</td>
                  <td className="px-4 py-3">{row.month[locale]}</td>
                  <td className="px-4 py-3">
                    <SeasonStatusBadge status={row.status} />
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">
                    {row.limit[locale]}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
