import Link from "next/link";
import { getRequestCatalogLocale } from "@/features/catalog/lib/request-locale";
import { loadPublicBrands } from "@/features/catalog/lib/load-public-catalog";

export default async function Page() {
  const locale = await getRequestCatalogLocale();
  const brands = await loadPublicBrands(locale).catch(() => null);

  return (
    <div className="sf-band-paper min-h-[70svh] pb-16">
      <div className="sf-container sf-section space-y-6">
        <h1 className="sf-display text-4xl">Brands</h1>
        <ul className="grid gap-3 sm:grid-cols-2 md:grid-cols-3">
          {(brands?.data ?? []).map((brand) => (
            <li key={brand.id}>
              <Link
                href={brand.path || `/brands/${brand.slug}`}
                className="sf-lift block rounded-2xl border border-border bg-card px-4 py-5 no-underline hover:border-[var(--sand)]/40"
              >
                <p className="font-semibold text-foreground">{brand.name}</p>
                <p className="mt-1 text-sm text-muted-foreground tabular-nums">
                  {brand.product_count}
                </p>
              </Link>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
