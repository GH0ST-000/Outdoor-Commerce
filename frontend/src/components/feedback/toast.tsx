"use client";

export function Toast({ children, open }: { children: string; open: boolean }) {
  if (!open) {
    return null;
  }

  return (
    <div
      role="status"
      aria-live="polite"
      className="fixed end-4 bottom-[calc(1rem+var(--space-safe-bottom))] z-[var(--z-toast)] max-w-sm rounded-[var(--radius-lg)] border border-border bg-card px-4 py-3 text-sm shadow-[var(--shadow-overlay)]"
    >
      {children}
    </div>
  );
}

export function Progress({ value, label }: { value: number; label: string }) {
  return (
    <div>
      <p className="mb-1 text-xs text-muted-foreground">{label}</p>
      <div
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={value}
        aria-label={label}
        className="h-2 overflow-hidden rounded-full bg-muted"
      >
        <div
          className="h-full bg-primary"
          style={{ width: `${Math.min(100, Math.max(0, value))}%` }}
        />
      </div>
    </div>
  );
}
