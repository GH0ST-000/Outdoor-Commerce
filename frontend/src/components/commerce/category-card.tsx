import type { ComponentProps } from "react";
import Link from "next/link";
import { cn } from "@/lib/utils";
import { Skeleton } from "@/components/ui/skeleton";

export function CategoryCard({
  href,
  name,
  description,
  imageSrc,
  countLabel,
  className,
}: {
  href: string;
  name: string;
  description?: string;
  imageSrc?: string;
  countLabel?: string;
  className?: string;
}) {
  return (
    <article
      className={cn(
        "overflow-hidden rounded-[var(--radius-xl)] border border-border bg-card",
        className,
      )}
    >
      <Link href={href} className="block no-underline">
        <div className="relative aspect-[16/11] bg-[var(--product-card-image-background)]">
          {imageSrc ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={imageSrc} alt="" className="size-full object-cover" />
          ) : (
            <div className="flex size-full items-center justify-center text-sm text-muted-foreground">
              {name}
            </div>
          )}
        </div>
        <div className="space-y-1 p-4">
          <h3 className="font-semibold text-foreground">{name}</h3>
          {description ? (
            <p className="sf-line-clamp-2 text-sm text-muted-foreground">
              {description}
            </p>
          ) : null}
          {countLabel ? (
            <p className="text-xs text-muted-foreground tabular-nums">
              {countLabel}
            </p>
          ) : null}
        </div>
      </Link>
    </article>
  );
}

export function CategoryEditorialCard(
  props: ComponentProps<typeof CategoryCard>,
) {
  return (
    <CategoryCard
      {...props}
      className={cn("bg-transparent", props.className)}
    />
  );
}

export function CategoryNavigationItem({
  href,
  name,
  active = false,
}: {
  href: string;
  name: string;
  active?: boolean;
}) {
  return (
    <Link
      href={href}
      aria-current={active ? "page" : undefined}
      className={cn(
        "rounded-lg px-3 py-2 text-sm no-underline",
        active ? "bg-muted font-semibold" : "hover:bg-muted/70",
      )}
    >
      {name}
    </Link>
  );
}

export function CategoryCardSkeleton() {
  return (
    <Skeleton className="aspect-[16/11] w-full rounded-[var(--radius-xl)]" />
  );
}
