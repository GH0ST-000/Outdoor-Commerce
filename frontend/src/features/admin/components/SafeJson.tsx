"use client";

/**
 * Renders JSON as escaped text. Never uses dangerouslySetInnerHTML.
 */
export function SafeJson({
  value,
  label,
  emptyLabel = "None",
}: {
  value: unknown;
  label?: string;
  emptyLabel?: string;
}) {
  const isEmpty =
    value === null ||
    value === undefined ||
    (typeof value === "object" &&
      !Array.isArray(value) &&
      Object.keys(value as object).length === 0);

  let text: string;
  try {
    text = isEmpty ? emptyLabel : JSON.stringify(value, null, 2);
  } catch {
    text = "[Unable to serialize value]";
  }

  return (
    <figure className="space-y-1">
      {label ? (
        <figcaption className="text-[0.72rem] font-semibold uppercase tracking-[0.08em] text-muted-foreground">
          {label}
        </figcaption>
      ) : null}
      <pre
        className="overflow-x-auto rounded-md border border-border/70 bg-muted/40 p-3 text-xs leading-relaxed text-foreground"
        tabIndex={0}
      >
        {text}
      </pre>
    </figure>
  );
}
