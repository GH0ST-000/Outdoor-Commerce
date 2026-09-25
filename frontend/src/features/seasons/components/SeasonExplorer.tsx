"use client";

import Image from "next/image";
import Link from "next/link";
import { useEffect, useId, useMemo, useState } from "react";
import { SeasonStatusBadge } from "@/features/storefront/components/outdoor/SeasonStatusBadge";
import { storefrontMedia } from "@/features/storefront/config/media";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import {
  fetchAvailability,
  type AvailabilityMode,
  type AvailabilityResponse,
} from "@/features/seasons/api/public-seasons-api";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import type { SeasonStatus } from "@/features/storefront/types/storefront-types";

function isoDate(date: Date): string {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function presets(now: Date) {
  const startOfWeek = new Date(now);
  const weekday = (startOfWeek.getDay() + 6) % 7;
  startOfWeek.setDate(startOfWeek.getDate() - weekday);
  const endOfWeek = new Date(startOfWeek);
  endOfWeek.setDate(startOfWeek.getDate() + 6);
  const next7 = new Date(now);
  next7.setDate(now.getDate() + 6);
  const monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
  const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  const nextMonthStart = new Date(now.getFullYear(), now.getMonth() + 1, 1);
  const nextMonthEnd = new Date(now.getFullYear(), now.getMonth() + 2, 0);
  return {
    week: [isoDate(startOfWeek), isoDate(endOfWeek)] as const,
    next7: [isoDate(now), isoDate(next7)] as const,
    month: [isoDate(monthStart), isoDate(monthEnd)] as const,
    nextMonth: [isoDate(nextMonthStart), isoDate(nextMonthEnd)] as const,
  };
}

export function SeasonExplorer() {
  const { t, locale } = useStorefrontCopy();
  const formId = useId();
  const today = useMemo(() => new Date(), []);
  const ranges = useMemo(() => presets(today), [today]);
  const [activity, setActivity] = useState<"hunting" | "fishing">("hunting");
  const [from, setFrom] = useState(ranges.week[0]);
  const [to, setTo] = useState(ranges.week[1]);
  const [mode, setMode] = useState<AvailabilityMode>("any_date");
  const [region, setRegion] = useState("");
  const [species, setSpecies] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [data, setData] = useState<AvailabilityResponse | null>(null);
  const resultsId = `${formId}-results`;
  const errorFallback = t.calendar.errorTitle;

  function applyPreset(fromDate: string, toDate: string) {
    setFrom(fromDate);
    setTo(toDate);
  }

  async function search(event?: React.FormEvent) {
    event?.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const payload = await fetchAvailability(
        {
          activity,
          from,
          to,
          mode,
          region: region || undefined,
          species: species || undefined,
        },
        locale,
      );
      setData(payload);
    } catch (cause) {
      setData(null);
      setError(cause instanceof ApiClientError ? cause.message : errorFallback);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    let cancelled = false;

    void fetchAvailability(
      {
        activity: "hunting",
        from: ranges.week[0],
        to: ranges.week[1],
        mode: "any_date",
      },
      locale,
    )
      .then((payload) => {
        if (cancelled) {
          return;
        }
        setData(payload);
        setError(null);
      })
      .catch((cause) => {
        if (cancelled) {
          return;
        }
        setData(null);
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
  }, [errorFallback, locale, ranges]);

  const count = data?.pagination.total ?? 0;

  return (
    <div className="pb-16">
      <section className="relative isolate overflow-hidden">
        <div className="absolute inset-0">
          <Image
            src={
              activity === "fishing"
                ? storefrontMedia.categories.fishing
                : storefrontMedia.categories.hunting
            }
            alt=""
            fill
            sizes="100vw"
            className="object-cover object-center"
          />
          <div className="absolute inset-0 bg-gradient-to-b from-[var(--alpine-slate)]/55 via-[var(--alpine-slate)]/75 to-[var(--alpine-slate)]" />
        </div>
        <div className="sf-container relative py-16 sm:py-20">
          <p className="sf-label text-[var(--sand)]">{t.calendar.eyebrow}</p>
          <h1 className="sf-display mt-3 text-[clamp(2.25rem,7vw,4.5rem)] leading-[1.02] text-white">
            {t.calendar.title}
          </h1>
          <p className="mt-4 max-w-xl text-sm leading-relaxed text-white/90 sm:text-base">
            {t.calendar.lead}
          </p>
        </div>
      </section>

      <div className="sf-container space-y-8 pt-6">
        <form
          onSubmit={search}
          className="rounded-[var(--radius-xl)] border border-border bg-card p-4 sm:p-6"
          aria-describedby={`${formId}-mode-help ${formId}-disclaimer`}
        >
          <div
            role="group"
            aria-label={t.calendar.hunting}
            className="flex flex-wrap gap-2"
          >
            {(["hunting", "fishing"] as const).map((value) => (
              <Button
                key={value}
                type="button"
                variant={activity === value ? "default" : "outline"}
                aria-pressed={activity === value}
                onClick={() => setActivity(value)}
              >
                {value === "hunting" ? t.calendar.hunting : t.calendar.fishing}
              </Button>
            ))}
          </div>

          <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
              <Label htmlFor={`${formId}-from`}>{t.calendar.from}</Label>
              <Input
                id={`${formId}-from`}
                type="date"
                required
                value={from}
                onChange={(event) => setFrom(event.target.value)}
              />
            </div>
            <div>
              <Label htmlFor={`${formId}-to`}>{t.calendar.to}</Label>
              <Input
                id={`${formId}-to`}
                type="date"
                required
                value={to}
                min={from}
                onChange={(event) => setTo(event.target.value)}
              />
            </div>
            <div>
              <Label htmlFor={`${formId}-region`}>{t.calendar.region}</Label>
              <Input
                id={`${formId}-region`}
                value={region}
                onChange={(event) => setRegion(event.target.value)}
                placeholder={t.calendar.noRegion}
              />
            </div>
            <div>
              <Label htmlFor={`${formId}-species`}>{t.calendar.species}</Label>
              <Input
                id={`${formId}-species`}
                value={species}
                onChange={(event) => setSpecies(event.target.value)}
                placeholder={t.calendar.search}
              />
            </div>
          </div>

          <p className="mt-3 text-sm text-muted-foreground">
            {t.calendar.regionHint}
          </p>

          <div
            className="mt-4 flex flex-wrap gap-2"
            role="group"
            aria-label={t.calendar.custom}
          >
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => applyPreset(...ranges.week)}
            >
              {t.calendar.thisWeek}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => applyPreset(...ranges.next7)}
            >
              {t.calendar.next7}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => applyPreset(...ranges.month)}
            >
              {t.calendar.thisMonth}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => applyPreset(...ranges.nextMonth)}
            >
              {t.calendar.nextMonth}
            </Button>
          </div>

          <fieldset className="mt-5">
            <legend className="text-sm font-medium">
              {t.calendar.modeHelp}
            </legend>
            <div className="mt-2 grid gap-2 sm:grid-cols-3">
              {(
                [
                  ["any_date", t.calendar.modeAny],
                  ["entire_period", t.calendar.modeEntire],
                  ["timeline", t.calendar.modeTimeline],
                ] as const
              ).map(([value, label]) => (
                <label
                  key={value}
                  className="flex cursor-pointer items-start gap-2 rounded-md border border-border px-3 py-2 text-sm has-[:focus-visible]:ring-2"
                >
                  <input
                    type="radio"
                    name="availability-mode"
                    value={value}
                    checked={mode === value}
                    onChange={() => setMode(value)}
                    className="mt-1"
                  />
                  <span>{label}</span>
                </label>
              ))}
            </div>
            <p
              id={`${formId}-mode-help`}
              className="mt-2 text-sm text-muted-foreground"
            >
              {t.calendar.modeHelp}
            </p>
          </fieldset>

          <div className="mt-5">
            <Button type="submit" disabled={loading}>
              {t.calendar.searchAction}
            </Button>
          </div>
        </form>

        <p
          id={`${formId}-disclaimer`}
          className="text-sm text-muted-foreground"
        >
          {t.calendar.disclaimer}
        </p>

        <div id={resultsId} aria-live="polite" aria-atomic="true">
          {loading ? <p className="text-sm">{t.calendar.loading}</p> : null}
          {error ? (
            <p role="alert" className="text-sm text-destructive">
              {error}
            </p>
          ) : null}
          {data && !loading ? (
            <p className="text-sm text-muted-foreground">
              {count} {t.calendar.resultsCount} ·{" "}
              {
                t.calendar[
                  mode === "any_date"
                    ? "modeAny"
                    : mode === "entire_period"
                      ? "modeEntire"
                      : "modeTimeline"
                ]
              }
            </p>
          ) : null}
        </div>

        {data && data.results.length === 0 && !loading ? (
          <section className="rounded-[var(--radius-xl)] border border-border bg-card p-6">
            <h2 className="text-xl font-semibold">{t.calendar.emptyTitle}</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              {t.calendar.emptyLead}
            </p>
          </section>
        ) : null}

        <ul className="grid gap-4 sm:grid-cols-2">
          {data?.results.map((row) => (
            <li key={row.species.id}>
              <article className="overflow-hidden rounded-[var(--radius-xl)] border border-border bg-card">
                <div className="relative aspect-[16/10] bg-muted">
                  {row.species.media?.url ? (
                    <Image
                      src={row.species.media.url}
                      alt=""
                      fill
                      sizes="(max-width:1024px) 100vw, 50vw"
                      className="object-cover"
                    />
                  ) : null}
                  <div className="absolute top-3 right-3">
                    <SeasonStatusBadge
                      status={row.overall_state as SeasonStatus}
                    />
                  </div>
                </div>
                <div className="space-y-3 p-4">
                  <h2 className="text-lg font-semibold">
                    {row.species.common_name ?? row.species.scientific_name}
                  </h2>
                  <p className="text-sm text-muted-foreground">
                    {row.species.scientific_name} ·{" "}
                    {activity === "hunting"
                      ? t.calendar.hunting
                      : t.calendar.fishing}
                  </p>
                  {row.overall_state === "partially_open" ? (
                    <p className="text-sm">{t.calendar.partiallyOpen}</p>
                  ) : null}
                  {row.available_windows[0] ? (
                    <p className="text-sm">
                      {t.calendar.openWindow}: {row.available_windows[0].from} –{" "}
                      {row.available_windows[0].to}
                    </p>
                  ) : null}
                  {row.limits[0] ? (
                    <p className="text-sm text-muted-foreground">
                      {row.limits[0].limit_type} {row.limits[0].amount}{" "}
                      {row.limits[0].unit}
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
                    <p className="text-xs text-muted-foreground">
                      {t.calendar.lastVerified}:{" "}
                      {row.last_verified_at.slice(0, 10)}
                    </p>
                  ) : null}
                  {row.citations[0]?.official_url ? (
                    <a
                      href={row.citations[0].official_url}
                      rel="noopener noreferrer"
                      target="_blank"
                      className="text-sm underline"
                    >
                      {t.calendar.officialSource}
                    </a>
                  ) : null}
                  {mode === "timeline" && row.timeline.length > 0 ? (
                    <ol
                      aria-label={t.calendar.timelineLabel}
                      className="flex gap-1 overflow-x-auto pb-1"
                    >
                      {row.timeline.map((window) => (
                        <li
                          key={`${window.from}-${window.state}`}
                          className="min-w-[4.5rem] rounded border border-border px-2 py-1 text-xs"
                        >
                          <span className="block font-medium">
                            {window.state}
                          </span>
                          <span className="text-muted-foreground">
                            {window.from}
                          </span>
                        </li>
                      ))}
                    </ol>
                  ) : null}
                  <Link
                    href={`/species/${row.species.slug}`}
                    className="inline-flex text-sm font-medium underline"
                  >
                    {t.calendar.viewDetails}
                  </Link>
                </div>
              </article>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
