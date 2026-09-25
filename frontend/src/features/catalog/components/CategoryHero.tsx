import Image from "next/image";
import { Breadcrumbs } from "@/components/navigation/breadcrumbs";
import { storefrontMedia } from "@/features/storefront/config/media";
import type { PublicCategoryDetail } from "@/features/catalog/types/public-catalog";

const categoryImages: Record<string, string> = {
  hunting: storefrontMedia.categories.hunting,
  fishing: storefrontMedia.categories.fishing,
  camping: storefrontMedia.categories.camping,
  clothing: storefrontMedia.categories.clothing,
  optics: storefrontMedia.categories.optics,
  knives: storefrontMedia.categories.knives,
};

export function CategoryHero({
  title,
  lead,
  count,
  countLabel,
  eyebrow,
  category,
  breadcrumbs,
}: {
  title: string;
  lead?: string;
  count?: number;
  countLabel: string;
  eyebrow: string;
  category?: PublicCategoryDetail | null;
  breadcrumbs: Array<{ href?: string; label: string }>;
}) {
  const image =
    (category?.slug && categoryImages[category.slug]) ||
    storefrontMedia.categories.hunting;

  return (
    <section className="relative isolate overflow-hidden">
      <div className="absolute inset-0">
        <Image
          src={image}
          alt=""
          fill
          sizes="100vw"
          className="object-cover object-center opacity-[0.22]"
        />
        <div className="absolute inset-0 bg-gradient-to-b from-[var(--alpine-slate)]/40 via-[var(--alpine-slate)]/85 to-[var(--alpine-slate)]" />
      </div>
      <div className="sf-container relative py-7 sm:py-10">
        <Breadcrumbs label="Breadcrumb" items={breadcrumbs} />
        <div className="mt-4 flex flex-wrap items-end justify-between gap-4">
          <div className="max-w-2xl">
            <p className="sf-label text-[var(--sand)]">{eyebrow}</p>
            <h1 className="sf-display mt-2 text-[clamp(2rem,6vw,3.75rem)] leading-[1.05]">
              {title}
            </h1>
            {lead ? (
              <p className="mt-3 max-w-xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                {lead}
              </p>
            ) : null}
          </div>
          {typeof count === "number" ? (
            <p className="rounded-full border border-border bg-card px-3 py-1.5 text-sm tabular-nums shadow-sm">
              {count} {countLabel}
            </p>
          ) : null}
        </div>
      </div>
    </section>
  );
}
