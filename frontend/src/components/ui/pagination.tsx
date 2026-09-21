import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";

export function Pagination({
  page,
  lastPage,
  onPageChange,
  previousLabel,
  nextLabel,
  label,
  loading = false,
  compact = false,
}: {
  page: number;
  lastPage: number;
  onPageChange: (page: number) => void;
  previousLabel: string;
  nextLabel: string;
  label: string;
  loading?: boolean;
  compact?: boolean;
}) {
  if (lastPage <= 1) {
    return null;
  }

  const nearby = compact
    ? [page]
    : Array.from({ length: lastPage }, (_, index) => index + 1).filter(
        (item) => item === 1 || item === lastPage || Math.abs(item - page) <= 1,
      );

  return (
    <nav className="flex items-center justify-between gap-3" aria-label={label}>
      <Button
        type="button"
        variant="outline"
        disabled={page <= 1 || loading}
        onClick={() => onPageChange(Math.max(1, page - 1))}
      >
        {previousLabel}
      </Button>
      <ol className="flex items-center gap-1">
        {nearby.map((item, index) => {
          const previous = nearby[index - 1];
          return (
            <li key={item} className="flex items-center gap-1">
              {previous && item - previous > 1 ? (
                <span className="px-1 text-muted-foreground">…</span>
              ) : null}
              <Button
                type="button"
                size="sm"
                variant={item === page ? "secondary" : "ghost"}
                aria-current={item === page ? "page" : undefined}
                aria-label={`${label} ${item}`}
                disabled={loading}
                onClick={() => onPageChange(item)}
                className={cn("min-w-9 tabular-nums")}
              >
                {item}
              </Button>
            </li>
          );
        })}
      </ol>
      <Button
        type="button"
        variant="outline"
        disabled={page >= lastPage || loading}
        onClick={() => onPageChange(Math.min(lastPage, page + 1))}
      >
        {nextLabel}
      </Button>
    </nav>
  );
}
