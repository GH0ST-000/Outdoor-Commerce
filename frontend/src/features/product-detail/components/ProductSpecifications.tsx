import Link from "next/link";
import type { PublicProductDetail } from "@/features/catalog/types/public-catalog";
import type { PublicVariantCombination } from "@/features/catalog/types/public-catalog";

export function ProductSpecifications({
  detail,
  variant,
  heading,
  labels,
}: {
  detail: PublicProductDetail;
  variant: PublicVariantCombination | null;
  heading: string;
  labels: {
    brand: string;
    model: string;
    sku: string;
    category: string;
    variant: string;
  };
}) {
  const rows: Array<{ label: string; value: string; href?: string }> = [];

  if (detail.brand?.name) {
    rows.push({
      label: labels.brand,
      value: detail.brand.name,
      href: detail.brand.path,
    });
  }
  if (detail.model_number) {
    rows.push({ label: labels.model, value: detail.model_number });
  }
  if (variant?.sku) {
    rows.push({ label: labels.sku, value: variant.sku });
  }
  if (detail.primary_category?.name) {
    rows.push({
      label: labels.category,
      value: detail.primary_category.name,
      href: detail.primary_category.path,
    });
  }
  if (variant?.combination_label) {
    rows.push({ label: labels.variant, value: variant.combination_label });
  }

  if (rows.length === 0) {
    return null;
  }

  return (
    <section className="space-y-3" aria-labelledby="product-specs-heading">
      <h2 id="product-specs-heading" className="font-display text-2xl">
        {heading}
      </h2>
      <dl className="divide-y divide-border/70 border-y border-border/70">
        {rows.map((row) => (
          <div
            key={row.label}
            className="grid grid-cols-1 gap-1 py-3 sm:grid-cols-[10rem_minmax(0,1fr)] sm:gap-6"
          >
            <dt className="text-sm text-muted-foreground">{row.label}</dt>
            <dd className="text-sm font-medium">
              {row.href ? (
                <Link href={row.href} className="no-underline hover:underline">
                  {row.value}
                </Link>
              ) : (
                row.value
              )}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  );
}
