import Link from "next/link";
import { Button } from "@/components/ui/button";
import type { PublicCategorySummary } from "@/features/catalog/types/public-catalog";

export function ChildCategoryNav({
  items,
  activeSlug,
  allLabel,
  allHref = "/catalog",
  heading,
}: {
  items: PublicCategorySummary[];
  activeSlug?: string;
  allLabel: string;
  allHref?: string;
  heading?: string;
}) {
  if (items.length === 0 && !allLabel) {
    return null;
  }

  return (
    <nav aria-label={heading ?? allLabel} className="space-y-2">
      {heading && items.length > 0 ? (
        <p className="sf-label text-muted-foreground">{heading}</p>
      ) : null}
      <div className="sf-scroll-x -mx-1 px-1 pb-1">
        <Button
          asChild
          size="sm"
          variant={!activeSlug ? "accent" : "outline"}
          className="shrink-0"
        >
          <Link href={allHref}>{allLabel}</Link>
        </Button>
        {items.map((item) => {
          const active = item.slug === activeSlug;
          return (
            <Button
              key={item.id}
              asChild
              size="sm"
              variant={active ? "accent" : "outline"}
              className="shrink-0"
            >
              <Link
                href={item.path ?? `/catalog/${item.slug}`}
                aria-current={active ? "page" : undefined}
              >
                {item.name}
              </Link>
            </Button>
          );
        })}
      </div>
    </nav>
  );
}
