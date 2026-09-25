import type {
  LegalInformation,
  LegalOutcome,
} from "@/features/legal/types/legal-types";

const labels: Record<
  LegalOutcome,
  { ka: { title: string; badge: string }; en: { title: string; badge: string } }
> = {
  allowed: {
    ka: { title: "დაშვებულია გამოქვეყნებული წესებით", badge: "დაშვებული" },
    en: { title: "Supported as allowed by published rules", badge: "Allowed" },
  },
  prohibited: {
    ka: { title: "გამოქვეყნებული აკრძალვა მოქმედებს", badge: "აკრძალული" },
    en: { title: "A published prohibition applies", badge: "Prohibited" },
  },
  conditional: {
    ka: { title: "დამატებითი პირობებია საჭირო", badge: "პირობითი" },
    en: { title: "Additional stated conditions apply", badge: "Conditional" },
  },
  unknown: {
    ka: { title: "დადასტურებული წესი ჯერ არ არის", badge: "უცნობი" },
    en: { title: "Not enough verified published evidence", badge: "Unknown" },
  },
  conflict: {
    ka: { title: "გამოქვეყნებული წესები ეწინააღმდეგება", badge: "კონფლიქტი" },
    en: {
      title: "Published rules conflict and need review",
      badge: "Conflict",
    },
  },
};

export function SpeciesLegalOverview({
  locale,
  legal,
}: {
  locale: "ka" | "en";
  legal: LegalInformation;
}) {
  const outcome: LegalOutcome = legal.outcome ?? "unknown";
  const copy = labels[outcome][locale];
  const disclaimer =
    locale === "ka"
      ? "ეს არის საინფორმაციო მასალა და არ არის იურიდიული კონსულტაცია. აკრძალვის არარსებობა ნებართვას არ ნიშნავს."
      : "This is informational and is not legal advice. Absence of a prohibition is not permission.";

  return (
    <section
      id="legal"
      className="rounded-2xl border border-border bg-card p-6"
    >
      <h2 className="text-2xl font-semibold">
        {locale === "ka"
          ? "რეგულაციები და ოფიციალური წყაროები"
          : "Regulations & Official Sources"}
      </h2>
      <p
        className="mt-3 inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 text-sm"
        role="status"
      >
        <span aria-hidden>
          {outcome === "allowed"
            ? "✓"
            : outcome === "prohibited"
              ? "✕"
              : outcome === "conflict"
                ? "!"
                : outcome === "conditional"
                  ? "…"
                  : "?"}
        </span>
        <span>{copy.badge}</span>
      </p>
      <p className="mt-3">{legal.summary ?? copy.title}</p>
      {legal.rules?.length ? (
        <ul className="mt-4 space-y-3">
          {legal.rules.map((rule) => (
            <li key={rule.id} className="rounded-xl border border-border p-4">
              <p className="font-medium">{rule.title}</p>
              <p className="text-sm text-muted-foreground">
                {rule.interpretation_summary}
              </p>
            </li>
          ))}
        </ul>
      ) : null}
      {legal.limits?.length ? (
        <ul className="mt-4 list-disc pl-5 text-sm">
          {legal.limits.map((limit) => (
            <li key={`${limit.rule_id}-${limit.limit_type}`}>
              {limit.limit_type}: {limit.amount} {limit.unit} / {limit.period} /{" "}
              {limit.applies_per}
            </li>
          ))}
        </ul>
      ) : null}
      {legal.citations?.length ? (
        <ul className="mt-4 space-y-2 text-sm">
          {legal.citations.map((citation, index) => (
            <li key={`${citation.rule_id}-${index}`}>
              {citation.reference_code}
              {citation.excerpt ? ` — “${citation.excerpt}”` : ""}
              {citation.official_url ? (
                <>
                  {" "}
                  <a
                    href={citation.official_url}
                    rel="noopener noreferrer"
                    target="_blank"
                    className="underline"
                  >
                    {citation.source_name ?? citation.official_url}
                  </a>
                </>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
      {legal.last_verified_at ? (
        <p className="mt-3 text-sm text-muted-foreground">
          {locale === "ka" ? "ბოლო შემოწმება" : "Last verified"}:{" "}
          {legal.last_verified_at}
        </p>
      ) : null}
      <p className="mt-4 text-sm text-muted-foreground">{disclaimer}</p>
    </section>
  );
}
