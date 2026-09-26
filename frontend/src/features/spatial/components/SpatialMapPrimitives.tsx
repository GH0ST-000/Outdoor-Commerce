import { cn } from "@/lib/utils";

const LABELS: Record<string, { en: string; ka: string }> = {
  protected_area: { en: "Protected area", ka: "დაცული ტერიტორია" },
  national_park: { en: "National park", ka: "ეროვნული პარკი" },
  hunting_restricted_area: {
    en: "Hunting-restricted area",
    ka: "ნადირობის შეზღუდული ზონა",
  },
  fishing_restricted_area: {
    en: "Fishing-restricted area",
    ka: "თევზაობის შეზღუდული ზონა",
  },
  other: { en: "Other zone", ka: "სხვა ზონა" },
};

export function ZoneTypeBadge({
  zoneType,
  locale = "en",
}: {
  zoneType: string;
  locale?: "en" | "ka";
}) {
  const label = LABELS[zoneType]?.[locale] ?? zoneType.replaceAll("_", " ");
  return (
    <span className="inline-flex rounded-full border border-border px-2 py-0.5 text-xs">
      {label}
    </span>
  );
}

export function LegalSpatialState({
  outcome,
  locale = "en",
}: {
  outcome: string;
  locale?: "en" | "ka";
}) {
  const labels: Record<string, { en: string; ka: string }> = {
    allowed: { en: "Allowed", ka: "ნებადართული" },
    prohibited: { en: "Prohibited", ka: "აკრძალული" },
    conditional: { en: "Conditional", ka: "პირობითი" },
    unknown: { en: "Unknown", ka: "უცნობი" },
    conflict: { en: "Conflict", ka: "კონფლიქტი" },
  };
  const text = labels[outcome]?.[locale] ?? outcome;
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium",
        outcome === "prohibited" && "bg-destructive/10 text-destructive",
        outcome === "unknown" && "bg-muted text-muted-foreground",
        outcome === "conflict" && "bg-amber-100 text-amber-900",
        outcome === "allowed" && "bg-emerald-100 text-emerald-900",
        outcome === "conditional" && "bg-sky-100 text-sky-900",
      )}
    >
      <span aria-hidden="true">●</span>
      {text}
    </span>
  );
}

export function SourceAttribution({
  attribution,
}: {
  attribution?: {
    source_name?: string | null;
    publisher_name?: string | null;
    attribution_text?: string | null;
    verified_at?: string | null;
  } | null;
}) {
  if (!attribution) {
    return (
      <p className="text-sm text-muted-foreground">
        Source attribution unavailable.
      </p>
    );
  }
  return (
    <div className="space-y-1 text-sm">
      <p>
        <span className="text-muted-foreground">Source: </span>
        {attribution.source_name ?? "Unnamed source"}
      </p>
      {attribution.publisher_name ? (
        <p>
          <span className="text-muted-foreground">Publisher: </span>
          {attribution.publisher_name}
        </p>
      ) : null}
      {attribution.attribution_text ? (
        <p>{attribution.attribution_text}</p>
      ) : null}
    </div>
  );
}

export function BoundaryWarning({
  visible,
  locale = "en",
}: {
  visible: boolean;
  locale?: "en" | "ka";
}) {
  if (!visible) {
    return null;
  }
  return (
    <p
      role="status"
      className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm"
    >
      {locale === "ka"
        ? "წერტილი საზღვართან ახლოსაა. დასკვნა არ არის ზუსტი აღრიცხვა."
        : "This location is on or near a boundary. The conclusion is uncertain and not survey-grade."}
    </p>
  );
}

export function SpatialDataFreshness({
  verifiedAt,
  locale = "en",
}: {
  verifiedAt?: string | null;
  locale?: "en" | "ka";
}) {
  return (
    <p className="text-sm text-muted-foreground">
      {locale === "ka" ? "ბოლოს გადამოწმდა: " : "Last verified: "}
      {verifiedAt ?? (locale === "ka" ? "უცნობია" : "unknown")}
    </p>
  );
}

export function ZoneDetailsPanel({
  name,
  officialName,
  zoneType,
  outcome,
  attribution,
  verifiedAt,
  warning,
  locale = "en",
}: {
  name: string;
  officialName: string;
  zoneType: string;
  outcome?: string;
  attribution?: {
    source_name?: string | null;
    publisher_name?: string | null;
    attribution_text?: string | null;
  } | null;
  verifiedAt?: string | null;
  warning?: boolean;
  locale?: "en" | "ka";
}) {
  return (
    <article className="space-y-3 rounded-xl border border-border p-4">
      <header className="space-y-1">
        <h2 className="text-lg font-semibold">{name}</h2>
        <p className="text-sm text-muted-foreground">
          Official name: {officialName}
        </p>
        <ZoneTypeBadge zoneType={zoneType} locale={locale} />
        {outcome ? (
          <LegalSpatialState outcome={outcome} locale={locale} />
        ) : null}
      </header>
      <BoundaryWarning visible={Boolean(warning)} locale={locale} />
      <SourceAttribution attribution={attribution} />
      <SpatialDataFreshness verifiedAt={verifiedAt} locale={locale} />
    </article>
  );
}
