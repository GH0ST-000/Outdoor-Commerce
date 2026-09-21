import Link from "next/link";
import type { PublicBrandDetail } from "@/features/catalog/types/public-catalog";
import { isExternalHttpUrl } from "@/features/product-detail/utils/sanitize-product-html";

export function ProductBrandSection({
  brand,
  heading,
  moreLabel,
  websiteLabel,
}: {
  brand: PublicBrandDetail | null | undefined;
  heading: string;
  moreLabel: string;
  websiteLabel: string;
}) {
  if (!brand?.name) {
    return null;
  }

  const website = isExternalHttpUrl(brand.website_url)
    ? brand.website_url
    : null;

  return (
    <section
      className="space-y-2 rounded-[1.25rem] border border-border/70 bg-card p-5"
      aria-labelledby="product-brand-heading"
    >
      <h2 id="product-brand-heading" className="font-display text-xl">
        {heading}
      </h2>
      <p className="text-lg font-medium">{brand.name}</p>
      {brand.description ? (
        <p className="max-w-prose text-sm leading-relaxed text-muted-foreground">
          {brand.description}
        </p>
      ) : null}
      <div className="flex flex-wrap gap-x-4 gap-y-2 text-sm">
        <Link
          href={brand.path}
          className="text-foreground underline-offset-4 hover:underline"
        >
          {moreLabel}
        </Link>
        {website ? (
          <a
            href={website}
            target="_blank"
            rel="noopener noreferrer"
            className="text-foreground underline-offset-4 hover:underline"
          >
            {websiteLabel}
          </a>
        ) : null}
      </div>
    </section>
  );
}

export function ProductOutdoorContextSlot() {
  return <div hidden data-slot="product-outdoor-context" />;
}
