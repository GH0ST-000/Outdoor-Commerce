import type { ProductReadiness } from "@/features/catalog/products/types/product-types";

export function ProductReadinessPanel({
  readiness,
  compact = false,
}: {
  readiness: ProductReadiness | { ready: boolean; issue_count: number } | null;
  compact?: boolean;
}) {
  if (!readiness) {
    return (
      <span className="text-xs text-muted-foreground" data-testid="readiness">
        —
      </span>
    );
  }

  if (compact) {
    return (
      <span
        className={
          readiness.ready
            ? "inline-flex items-center rounded-md border border-border/70 bg-muted/40 px-2 py-0.5 text-xs font-medium"
            : "inline-flex items-center rounded-md border border-accent/40 bg-accent/10 px-2 py-0.5 text-xs font-medium"
        }
        data-testid="readiness"
      >
        {readiness.ready
          ? "Ready"
          : `${readiness.issue_count} issue${readiness.issue_count === 1 ? "" : "s"}`}
      </span>
    );
  }

  const issues =
    "issues" in readiness && readiness.issues ? readiness.issues : {};
  const entries = Object.entries(issues);

  return (
    <div className="space-y-2" data-testid="readiness-panel">
      <p
        className={
          readiness.ready
            ? "text-sm font-medium"
            : "text-sm font-medium text-accent-foreground"
        }
        data-testid="readiness"
      >
        {readiness.ready
          ? "Ready for activation"
          : `Not ready · ${readiness.issue_count} issue${readiness.issue_count === 1 ? "" : "s"}`}
      </p>
      {entries.length > 0 ? (
        <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
          {entries.map(([key, messages]) => (
            <li key={key}>
              <span className="font-medium text-foreground/80">{key}: </span>
              {messages.join(" ")}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
