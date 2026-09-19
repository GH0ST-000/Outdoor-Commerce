"use client";

import Link from "next/link";
import { useMemo, useState } from "react";
import { ProductGrid } from "@/features/storefront/components/commerce/ProductCard";
import { listCatalogProducts } from "@/features/storefront/adapters/fixture-catalog";
import { categoryGateway } from "@/features/storefront/fixtures/demo-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

export function CatalogPage({ categorySlug }: { categorySlug?: string }) {
  const { t, locale } = useStorefrontCopy();
  const [sort, setSort] = useState("featured");
  const [drawerOpen, setDrawerOpen] = useState(false);
  const products = useMemo(
    () => listCatalogProducts(categorySlug),
    [categorySlug],
  );
  const category = categoryGateway.find((item) => item.slug === categorySlug);

  return (
    <div className="sf-section">
      <div className="sf-container space-y-8">
        <nav aria-label="Breadcrumb" className="text-sm text-muted-foreground">
          <Link href="/" className="no-underline hover:text-foreground">
            {t.nav.catalog}
          </Link>
          {category ? (
            <>
              <span className="mx-2">/</span>
              <span className="text-foreground">{category.name[locale]}</span>
            </>
          ) : null}
        </nav>

        <header className="max-w-2xl">
          <h1 className="sf-display text-4xl sm:text-5xl">
            {category ? category.name[locale] : t.catalog.title}
          </h1>
          <p className="mt-3 text-muted-foreground">
            {category ? category.label[locale] : t.catalog.lead}
          </p>
          <p className="mt-2 text-sm text-muted-foreground">
            {products.length} {t.catalog.results}
          </p>
        </header>

        {!categorySlug ? (
          <div className="flex flex-wrap gap-2">
            {categoryGateway.map((item) => (
              <Button key={item.id} asChild variant="outline" size="sm">
                <Link href={item.href}>{item.name[locale]}</Link>
              </Button>
            ))}
          </div>
        ) : null}

        <div className="flex flex-wrap items-center justify-between gap-3">
          <Button
            type="button"
            variant="outline"
            className="lg:hidden"
            onClick={() => setDrawerOpen(true)}
          >
            {t.catalog.filters}
          </Button>
          <div className="ml-auto flex items-center gap-2">
            <span className="text-sm text-muted-foreground">{t.catalog.sort}</span>
            <Select value={sort} onValueChange={setSort}>
              <SelectTrigger className="w-40" aria-label={t.catalog.sort}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="featured">Featured</SelectItem>
                <SelectItem value="name">Name</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="grid gap-8 lg:grid-cols-[240px_1fr]">
          <aside className="hidden space-y-4 lg:block" aria-label={t.catalog.filters}>
            <p className="text-sm font-semibold">{t.catalog.filters}</p>
            <p className="text-sm text-muted-foreground">
              Prototype filters — not connected to the catalog API yet.
            </p>
            <ul className="space-y-2 text-sm">
              <li>Brand</li>
              <li>Color</li>
              <li>Size</li>
              <li>Activity</li>
            </ul>
          </aside>

          <div>
            {products.length === 0 ? (
              <div
                className="rounded-2xl border border-dashed border-border px-6 py-16 text-center"
                role="status"
              >
                <p className="font-medium">{t.catalog.empty}</p>
              </div>
            ) : (
              <ProductGrid products={products} />
            )}
          </div>
        </div>
      </div>

      {drawerOpen ? (
        <div
          className="fixed inset-0 z-50 bg-black/50 lg:hidden"
          role="dialog"
          aria-modal="true"
          aria-label={t.catalog.filters}
          onClick={() => setDrawerOpen(false)}
        >
          <div
            className="absolute inset-x-0 bottom-0 rounded-t-2xl bg-card p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))]"
            onClick={(event) => event.stopPropagation()}
          >
            <p className="text-lg font-semibold">{t.catalog.filters}</p>
            <p className="mt-2 text-sm text-muted-foreground">
              Prototype only — API wiring comes later.
            </p>
            <div className="mt-4 flex gap-2">
              <Button
                type="button"
                variant="outline"
                className="flex-1"
                onClick={() => setDrawerOpen(false)}
              >
                {t.catalog.clearFilters}
              </Button>
              <Button
                type="button"
                className="flex-1"
                onClick={() => setDrawerOpen(false)}
              >
                {t.catalog.applyFilters}
              </Button>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
