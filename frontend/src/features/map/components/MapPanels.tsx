"use client";

import { useId, useState } from "react";
import type { MapCopy } from "@/features/map/copy/map-copy";
import {
  formatAttribution,
  isSafeHttpUrl,
} from "@/features/map/lib/attribution";
import {
  LAYER_ORDER,
  PROTECTED_CATEGORY_ORDER,
  paintForLegalState,
  protectedCategoryPaint,
  type LayerId,
} from "@/features/map/lib/cartography";
import type {
  ViewportFeature,
  SpatialEvaluation,
  ZoneDetails,
} from "@/features/spatial/types/spatial-types";
import { Button } from "@/components/ui/button";
import Link from "next/link";

export function OutcomeMark({
  outcome,
  label,
}: {
  outcome: string;
  label: string;
}) {
  const paint = paintForLegalState(outcome);
  return (
    <span className="inline-flex items-center gap-2 text-sm font-semibold">
      <span aria-hidden className="inline-flex items-center gap-1">
        <span
          className="inline-block size-3 rounded-sm border border-[var(--charcoal)]"
          style={{
            background: paint.fill,
            outline: `2px ${paint.dash.length > 1 ? "dashed" : "solid"} ${paint.line}`,
          }}
        />
        {paint.mark}
      </span>
      {label}
    </span>
  );
}

export function LayerControl({
  copy,
  layers,
  counts,
  onChange,
}: {
  copy: MapCopy;
  layers: LayerId[];
  counts: Record<string, number>;
  onChange: (layers: LayerId[]) => void;
}) {
  return (
    <fieldset className="space-y-2">
      <legend className="text-sm font-semibold">{copy.layers}</legend>
      {LAYER_ORDER.map((layer) => {
        const checked = layers.includes(layer);
        return (
          <label
            key={layer}
            className="flex min-h-11 items-center justify-between gap-3 text-sm"
          >
            <span>
              <span className="font-medium">{copy.layerNames[layer]}</span>
              <span className="mt-0.5 block text-xs text-muted-foreground">
                {copy.layerHelp[layer]}
                {counts[layer] ? ` · ${counts[layer]}` : ""}
              </span>
            </span>
            <input
              type="checkbox"
              className="size-5 accent-[var(--olive)]"
              checked={checked}
              aria-label={copy.layerNames[layer]}
              onChange={() => {
                onChange(
                  checked
                    ? layers.filter((item) => item !== layer)
                    : [...layers, layer],
                );
              }}
            />
          </label>
        );
      })}
      <div className="flex gap-2">
        <Button
          type="button"
          size="sm"
          variant="outline"
          onClick={() => onChange([...LAYER_ORDER])}
        >
          {copy.showAll}
        </Button>
        <Button
          type="button"
          size="sm"
          variant="ghost"
          onClick={() => onChange(layers.filter((layer) => layer !== "admin"))}
        >
          {copy.hideOptional}
        </Button>
      </div>
    </fieldset>
  );
}

export function MapLegend({
  copy,
  collapsed = false,
}: {
  copy: MapCopy;
  collapsed?: boolean;
}) {
  const items = ["prohibited", "conditional", "unknown", "conflict", "allowed"];
  if (collapsed) {
    return (
      <p className="text-xs text-muted-foreground">
        {copy.legendOutcomes}:{" "}
        {items.map((item) => copy.outcomes[item]).join(" · ")}
      </p>
    );
  }
  return (
    <section aria-label={copy.legend} className="space-y-2 text-sm">
      <h2 className="text-sm font-semibold">{copy.legend}</h2>
      <h3 className="text-xs font-semibold tracking-wide text-muted-foreground">
        {copy.legendCategories}
      </h3>
      <ul className="space-y-1">
        {PROTECTED_CATEGORY_ORDER.map((zoneType) => (
          <li key={zoneType} className="flex items-center gap-2">
            <span
              aria-hidden
              className="inline-block size-3 shrink-0 rounded-sm border"
              style={{
                background: protectedCategoryPaint[zoneType].fill,
                borderColor: protectedCategoryPaint[zoneType].line,
              }}
            />
            {copy.protectedCategories[zoneType]}
          </li>
        ))}
      </ul>
      <h3 className="text-xs font-semibold tracking-wide text-muted-foreground">
        {copy.legendOutcomes}
      </h3>
      <ul className="space-y-1">
        {items.map((item) => (
          <li key={item}>
            <OutcomeMark outcome={item} label={copy.outcomes[item] ?? item} />
          </li>
        ))}
        <li>{copy.boundary}</li>
        <li>{copy.selectedPoint}</li>
        <li>{copy.userLocation}</li>
      </ul>
    </section>
  );
}

export function LocationResult({
  copy,
  evaluation,
  loading,
  accuracyMeters,
}: {
  copy: MapCopy;
  evaluation: SpatialEvaluation | null;
  loading: boolean;
  accuracyMeters: number | null;
}) {
  const [trace, setTrace] = useState(false);
  if (loading) {
    return <p role="status">{copy.loading}</p>;
  }
  if (!evaluation) return null;
  const outcome = evaluation.outcome;
  const seasons = evaluation.seasonal_availability?.results ?? [];
  const season = seasons[0];
  const grouped = {
    permit: evaluation.conditions.filter((row) => row.type === "permit"),
    license: evaluation.conditions.filter((row) => row.type === "license"),
    method: evaluation.conditions.filter((row) => row.type === "method"),
    equipment: evaluation.conditions.filter((row) => row.type === "equipment"),
  };
  return (
    <article aria-live="polite" className="space-y-3">
      <OutcomeMark
        outcome={outcome}
        label={copy.outcomes[outcome] ?? outcome}
      />
      <p className="text-sm">{copy.explain[outcome] ?? copy.notAllowed}</p>
      {evaluation.boundary_warning ? (
        <p className="text-sm font-medium">{copy.boundary}</p>
      ) : null}
      {accuracyMeters !== null && accuracyMeters > 100 ? (
        <p className="text-sm font-medium">{copy.locationLowAccuracy}</p>
      ) : null}
      {accuracyMeters !== null ? (
        <p className="text-xs text-muted-foreground">
          {copy.accuracy}: {Math.round(accuracyMeters)} m
        </p>
      ) : null}
      <section className="space-y-2">
        <h3 className="text-sm font-semibold">{copy.seasonForDate}</h3>
        {seasons.length === 0 ? (
          <p className="text-sm">{copy.seasonNeedsSpecies}</p>
        ) : (
          <ul className="space-y-3 text-sm">
            {seasons.map((row) => {
              const name =
                row.species?.common_name ?? row.species?.scientific_name;
              const included = (row.conditions ?? [])
                .filter((condition) => condition.operator !== "not_equals")
                .map((condition) => condition.string_value)
                .filter((value): value is string => Boolean(value));
              const excluded = (row.conditions ?? [])
                .filter((condition) => condition.operator === "not_equals")
                .map((condition) => condition.string_value)
                .filter((value): value is string => Boolean(value));
              const window =
                row.season_windows?.[0] ??
                row.conditional_windows?.[0] ??
                row.available_windows?.[0];
              const amount = row.limits?.[0]?.amount;
              const limit =
                amount === null ||
                amount === undefined ||
                Number.isNaN(Number(amount))
                  ? null
                  : String(Number(amount));
              return (
                <li key={row.species?.slug ?? name}>
                  <p className="font-medium">
                    {name} ·{" "}
                    {copy.outcomes[row.overall_state ?? ""] ??
                      row.overall_state}
                  </p>
                  {row.species?.scientific_name &&
                  row.species.scientific_name !== name ? (
                    <p className="text-muted-foreground">
                      {row.species.scientific_name}
                    </p>
                  ) : null}
                  {window?.from && window.to ? (
                    <p>
                      {window.from} – {window.to}
                    </p>
                  ) : null}
                  {included.length > 0 ? (
                    <p>
                      {copy.onlyIn}: {included.join(", ")}
                    </p>
                  ) : null}
                  {excluded.length > 0 ? (
                    <p>
                      {copy.excluding}: {excluded.join(", ")}
                    </p>
                  ) : null}
                  {limit !== null ? (
                    <p>
                      {copy.dailyLimit}: {limit} {row.limits?.[0]?.unit}
                    </p>
                  ) : null}
                </li>
              );
            })}
          </ul>
        )}
        {outcome === "unknown" && seasons.length > 0 ? (
          <p className="text-sm">{copy.seasonNotThisPoint}</p>
        ) : null}
      </section>
      <h3 className="text-sm font-semibold">{copy.zonesAtPoint}</h3>
      <ul className="space-y-1 text-sm">
        {evaluation.matching_zones.length === 0 ? (
          <li>{copy.noZoneMatch}</li>
        ) : null}
        {evaluation.matching_zones.map((match) => (
          <li key={match.zone.id}>
            {match.zone.name} · {match.classification.relation}
            {match.classification.near_boundary ? ` · ${copy.boundary}` : ""}
          </li>
        ))}
      </ul>
      <ConditionList title={copy.conditions} rows={evaluation.conditions} />
      <ConditionList
        title={copy.limits}
        rows={evaluation.limits.map((row) => ({
          type: row.type,
          value: row.amount == null ? null : String(row.amount),
        }))}
      />
      <ConditionList title={copy.permits} rows={grouped.permit} />
      <ConditionList title={copy.licenses} rows={grouped.license} />
      <ConditionList title={copy.methods} rows={grouped.method} />
      <ConditionList title={copy.equipment} rows={grouped.equipment} />
      <h3 className="text-sm font-semibold">{copy.citations}</h3>
      <ul className="space-y-2 text-sm">
        {evaluation.citations.map((citation, index) => (
          <li key={`${citation.reference_code ?? "cite"}-${index}`}>
            <span className="font-medium">{citation.source_name}</span>
            {citation.reference_code ? ` · ${citation.reference_code}` : ""}
            {citation.excerpt ? (
              <span className="mt-1 block text-muted-foreground">
                {citation.excerpt}
              </span>
            ) : null}
          </li>
        ))}
      </ul>
      {evaluation.last_verified_at ? (
        <p className="text-xs text-muted-foreground">
          {copy.verified}: {evaluation.last_verified_at}
        </p>
      ) : null}
      <p className="text-xs text-muted-foreground">
        {evaluation.disclaimer || copy.disclaimer}
      </p>
      <button
        type="button"
        className="text-sm underline"
        aria-expanded={trace}
        onClick={() => setTrace((open) => !open)}
      >
        {copy.why}
      </button>
      {trace ? (
        <pre className="max-h-48 overflow-auto whitespace-pre-wrap text-xs">
          {JSON.stringify(
            {
              rules: evaluation.applied_rules,
              conflicts: evaluation.conflicts,
              season,
              boundary: evaluation.boundary_warning,
            },
            null,
            2,
          )}
        </pre>
      ) : null}
    </article>
  );
}

function ConditionList({
  title,
  rows,
}: {
  title: string;
  rows: Array<{ type?: string; value?: string | null }>;
}) {
  if (rows.length === 0) return null;
  return (
    <section>
      <h3 className="text-sm font-semibold">{title}</h3>
      <ul className="text-sm">
        {rows.map((row, index) => (
          <li key={`${row.type}-${index}`}>
            {row.type}
            {row.value ? `: ${row.value}` : ""}
          </li>
        ))}
      </ul>
    </section>
  );
}

export function ZoneDetail({
  copy,
  zone,
  onZoom,
  onShare,
}: {
  copy: MapCopy;
  zone: ZoneDetails | null;
  onZoom: () => void;
  onShare: () => void;
}) {
  if (!zone) return <p>{copy.removedZone}</p>;
  const source = formatAttribution(zone.attribution);
  const href = isSafeHttpUrl(zone.attribution.official_url)
    ? zone.attribution.official_url
    : null;
  return (
    <article className="space-y-2 text-sm" aria-labelledby="zone-detail-title">
      <h2 id="zone-detail-title" className="text-lg font-semibold">
        {zone.name}
      </h2>
      <p>{zone.official_name}</p>
      <p>
        {zone.zone_type}
        {zone.legal_state
          ? ` · ${copy.outcomes[zone.legal_state] ?? zone.legal_state}`
          : ""}
      </p>
      {zone.short_description ? <p>{zone.short_description}</p> : null}
      {zone.effective_from ? (
        <p>
          {zone.effective_from}
          {zone.effective_until ? ` – ${zone.effective_until}` : ""}
        </p>
      ) : null}
      {source ? <p>{source}</p> : null}
      {zone.dataset_name ? <p>{zone.dataset_name}</p> : null}
      {zone.dataset_version_id ? <p>{zone.dataset_version_id}</p> : null}
      {zone.last_verified_at ? (
        <p>
          {copy.verified}: {zone.last_verified_at}
        </p>
      ) : null}
      <p className="text-xs text-muted-foreground">
        {zone.disclaimer || copy.disclaimer}
      </p>
      <div className="flex flex-wrap gap-2">
        <Button type="button" size="sm" variant="outline" onClick={onZoom}>
          {copy.zoomTo}
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={onShare}>
          {copy.share}
        </Button>
        {href ? (
          <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex h-9 items-center rounded-full border px-3 text-xs font-semibold"
          >
            {copy.openSource}
          </a>
        ) : null}
        <Link
          href="/species"
          className="inline-flex h-9 items-center rounded-full border px-3 text-xs font-semibold"
        >
          {copy.viewSpecies}
        </Link>
        <Link
          href="/seasons"
          className="inline-flex h-9 items-center rounded-full border px-3 text-xs font-semibold"
        >
          {copy.viewSeason}
        </Link>
      </div>
      <ul>
        {zone.assignments.map((assignment) => (
          <li key={assignment.id}>
            {assignment.rule?.title} · {assignment.rule?.effect}
          </li>
        ))}
      </ul>
    </article>
  );
}

export function ResultsList({
  copy,
  features,
  selectedId,
  onSelect,
}: {
  copy: MapCopy;
  features: ViewportFeature[];
  selectedId: string | null;
  onSelect: (id: string) => void;
}) {
  const [page, setPage] = useState(1);
  const pageSize = 8;
  const visible = features.slice(0, page * pageSize);
  return (
    <section aria-label={copy.results}>
      <h2 className="text-sm font-semibold">{copy.results}</h2>
      {features.length === 0 ? (
        <p className="text-sm">{copy.emptyList}</p>
      ) : null}
      <ul className="mt-2 space-y-2">
        {visible.map((feature) => {
          const id = String(feature.properties.id);
          return (
            <li key={id}>
              <button
                type="button"
                className="w-full rounded-[var(--radius-lg)] border border-border px-3 py-2 text-left text-sm focus-visible:ring-3 focus-visible:ring-ring/30"
                aria-current={selectedId === id}
                onClick={() => onSelect(id)}
              >
                <span className="font-medium">{feature.properties.name}</span>
                <span className="mt-1 block text-xs text-muted-foreground">
                  {feature.properties.zone_type}
                  {feature.properties.legal_state
                    ? ` · ${copy.outcomes[feature.properties.legal_state] ?? feature.properties.legal_state}`
                    : ""}
                  {feature.properties.region_code
                    ? ` · ${feature.properties.region_code}`
                    : ""}
                </span>
              </button>
            </li>
          );
        })}
      </ul>
      {visible.length < features.length ? (
        <Button
          type="button"
          size="sm"
          variant="ghost"
          className="mt-2"
          onClick={() => setPage((value) => value + 1)}
        >
          {copy.results}
        </Button>
      ) : null}
    </section>
  );
}

export function ShareDialog({
  copy,
  open,
  url,
  failed,
  onCancel,
  onConfirm,
}: {
  copy: MapCopy;
  open: boolean;
  url: string;
  failed: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}) {
  const titleId = useId();
  if (!open) return null;
  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby={titleId}
      className="space-y-2 rounded-[var(--radius-lg)] border border-border bg-card p-3"
    >
      <h2 id={titleId} className="text-sm font-semibold">
        {copy.sharePoint}
      </h2>
      <p className="text-sm">{copy.sharePointNotice}</p>
      <p className="break-all text-xs">{url}</p>
      {failed ? <p className="text-sm">{copy.shareFailed}</p> : null}
      <div className="flex gap-2">
        <Button type="button" size="sm" onClick={onConfirm}>
          {copy.shareConfirm}
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={onCancel}>
          {copy.shareCancel}
        </Button>
      </div>
    </div>
  );
}
