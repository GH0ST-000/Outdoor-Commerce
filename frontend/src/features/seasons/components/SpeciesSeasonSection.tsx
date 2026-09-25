"use client";

import { FormEvent, useEffect, useState } from "react";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import {
  fetchSpeciesSeasons,
  type AvailabilityResult,
} from "@/features/seasons/api/public-seasons-api";
import { SeasonStatusBadge } from "@/features/storefront/components/outdoor/SeasonStatusBadge";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { SeasonStatus } from "@/features/storefront/types/storefront-types";

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

function inDays(days: number): string {
  const date = new Date();
  date.setDate(date.getDate() + days);
  return date.toISOString().slice(0, 10);
}

export function SpeciesSeasonSection({ slug }: { slug: string }) {
  const { t, locale } = useStorefrontCopy();
  const [from, setFrom] = useState(today());
  const [to, setTo] = useState(inDays(30));
  const [region, setRegion] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [row, setRow] = useState<AvailabilityResult | null>(null);
  const errorFallback = t.calendar.errorTitle;

  async function load(event?: FormEvent) {
    event?.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const payload = await fetchSpeciesSeasons(
        slug,
        { from, to, region: region || undefined },
        locale,
      );
      setRow(payload.availability);
    } catch (cause) {
      setError(cause instanceof ApiClientError ? cause.message : errorFallback);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let cancelled = false;

    void fetchSpeciesSeasons(slug, { from: today(), to: inDays(30) }, locale)
      .then((payload) => {
        if (cancelled) {
          return;
        }
        setRow(payload.availability);
        setError(null);
      })
      .catch((cause) => {
        if (cancelled) {
          return;
        }
        setError(
          cause instanceof ApiClientError ? cause.message : errorFallback,
        );
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [errorFallback, locale, slug]);

  return (
    <section className="rounded-2xl border border-border bg-card p-6">
      <h2 className="text-2xl font-semibold">{t.calendar.seasonsHeading}</h2>
      <p className="mt-2 text-sm text-muted-foreground">
        {t.calendar.disclaimer}
      </p>
      <form onSubmit={load} className="mt-4 grid gap-3 sm:grid-cols-4">
        <div>
          <Label htmlFor="species-season-from">{t.calendar.from}</Label>
          <Input
            id="species-season-from"
            type="date"
            value={from}
            onChange={(event) => setFrom(event.target.value)}
          />
        </div>
        <div>
          <Label htmlFor="species-season-to">{t.calendar.to}</Label>
          <Input
            id="species-season-to"
            type="date"
            value={to}
            min={from}
            onChange={(event) => setTo(event.target.value)}
          />
        </div>
        <div>
          <Label htmlFor="species-season-region">{t.calendar.region}</Label>
          <Input
            id="species-season-region"
            value={region}
            onChange={(event) => setRegion(event.target.value)}
            placeholder={t.calendar.noRegion}
          />
        </div>
        <div className="flex items-end">
          <Button type="submit">{t.calendar.searchAction}</Button>
        </div>
      </form>
      {loading ? <p className="mt-4 text-sm">{t.calendar.loading}</p> : null}
      {error ? (
        <p role="alert" className="mt-4 text-sm text-destructive">
          {error}
        </p>
      ) : null}
      {row && !loading ? (
        <div className="mt-4 space-y-2">
          <SeasonStatusBadge status={row.overall_state as SeasonStatus} />
          {row.available_windows[0] ? (
            <p className="text-sm">
              {t.calendar.openWindow}: {row.available_windows[0].from} –{" "}
              {row.available_windows[0].to}
            </p>
          ) : null}
          {row.next_opening ? (
            <p className="text-sm">
              {t.calendar.nextOpen}: {row.next_opening.local_date}
            </p>
          ) : null}
          {row.next_closing ? (
            <p className="text-sm">
              {t.calendar.nextClose}:{" "}
              {row.next_closing.local_end_date_inclusive}
            </p>
          ) : null}
          {row.last_verified_at ? (
            <p className="text-sm text-muted-foreground">
              {t.calendar.lastVerified}: {row.last_verified_at.slice(0, 10)}
            </p>
          ) : null}
          {row.citations[0]?.official_url ? (
            <a
              href={row.citations[0].official_url}
              className="text-sm underline"
              rel="noopener noreferrer"
              target="_blank"
            >
              {t.calendar.officialSource}
            </a>
          ) : null}
        </div>
      ) : null}
    </section>
  );
}
