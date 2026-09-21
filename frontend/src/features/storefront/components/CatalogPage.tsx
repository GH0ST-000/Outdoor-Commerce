"use client";

import Image from "next/image";
import Link from "next/link";
import { useMemo, useState } from "react";
import {
  LayoutGrid,
  LayoutList,
  Rows3,
  SlidersHorizontal,
  X,
} from "lucide-react";
import {
  ProductGrid,
  type ProductLayout,
} from "@/features/storefront/components/commerce/ProductCard";
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
import { cn } from "@/lib/utils";

type SortKey = "featured" | "name" | "brand";

function toggleValue(values: string[], value: string): string[] {
  return values.includes(value)
    ? values.filter((item) => item !== value)
    : [...values, value];
}

function FilterCheckbox({
  id,
  label,
  checked,
  onChange,
  count,
}: {
  id: string;
  label: string;
  checked: boolean;
  onChange: () => void;
  count?: number;
}) {
  return (
    <label
      htmlFor={id}
      className={cn(
        "flex cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-sm transition-colors",
        checked
          ? "border-[var(--field-green)]/35 bg-[color-mix(in_oklab,var(--field-green)_8%,white)]"
          : "border-transparent hover:bg-[color-mix(in_oklab,var(--charcoal)_4%,transparent)]",
      )}
    >
      <span className="flex min-w-0 items-center gap-2.5">
        <input
          id={id}
          type="checkbox"
          checked={checked}
          onChange={onChange}
          className="size-4 shrink-0 rounded border-[color-mix(in_oklab,var(--charcoal)_25%,transparent)] accent-[var(--field-green)]"
        />
        <span className="truncate font-medium text-[var(--charcoal)]">
          {label}
        </span>
      </span>
      {typeof count === "number" ? (
        <span className="rounded-full bg-[color-mix(in_oklab,var(--charcoal)_8%,transparent)] px-2 py-0.5 text-[0.7rem] text-[color-mix(in_oklab,var(--charcoal)_55%,transparent)] tabular-nums">
          {count}
        </span>
      ) : null}
    </label>
  );
}

function CatalogFilters({
  brands,
  brandCounts,
  selectedBrands,
  onToggleBrand,
  selectedStatuses,
  onToggleStatus,
  statusCounts,
  onClear,
  className,
}: {
  brands: string[];
  brandCounts: Record<string, number>;
  selectedBrands: string[];
  onToggleBrand: (brand: string) => void;
  selectedStatuses: string[];
  onToggleStatus: (status: string) => void;
  statusCounts: Record<string, number>;
  onClear: () => void;
  className?: string;
}) {
  const { t } = useStorefrontCopy();
  const activeCount = selectedBrands.length + selectedStatuses.length;

  return (
    <div className={cn("space-y-5", className)}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-base font-semibold tracking-tight text-[var(--charcoal)]">
            {t.catalog.filters}
          </p>
          <p className="mt-1 text-xs leading-relaxed text-[color-mix(in_oklab,var(--charcoal)_55%,transparent)]">
            {t.catalog.filterHint}
          </p>
        </div>
        {activeCount > 0 ? (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="text-[var(--charcoal)]"
            onClick={onClear}
          >
            {t.catalog.clearFilters}
          </Button>
        ) : null}
      </div>

      <section aria-labelledby="catalog-filter-brand">
        <h2
          id="catalog-filter-brand"
          className="sf-label mb-2 text-[color-mix(in_oklab,var(--charcoal)_45%,transparent)]"
        >
          {t.catalog.brand}
        </h2>
        <div className="space-y-1">
          {brands.map((brand) => (
            <FilterCheckbox
              key={brand}
              id={`filter-brand-${brand}`}
              label={brand}
              checked={selectedBrands.includes(brand)}
              onChange={() => onToggleBrand(brand)}
              count={brandCounts[brand] ?? 0}
            />
          ))}
        </div>
      </section>

      <section aria-labelledby="catalog-filter-status">
        <h2
          id="catalog-filter-status"
          className="sf-label mb-2 text-[color-mix(in_oklab,var(--charcoal)_45%,transparent)]"
        >
          {t.catalog.status}
        </h2>
        <div className="space-y-1">
          {(["featured", "new"] as const).map((status) =>
            (statusCounts[status] ?? 0) > 0 ? (
              <FilterCheckbox
                key={status}
                id={`filter-status-${status}`}
                label={status}
                checked={selectedStatuses.includes(status)}
                onChange={() => onToggleStatus(status)}
                count={statusCounts[status] ?? 0}
              />
            ) : null,
          )}
        </div>
      </section>
    </div>
  );
}

export function CatalogPage({ categorySlug }: { categorySlug?: string }) {
  const { t, locale } = useStorefrontCopy();
  const [sort, setSort] = useState<SortKey>("featured");
  const [layout, setLayout] = useState<ProductLayout>("grid");
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [selectedBrands, setSelectedBrands] = useState<string[]>([]);
  const [selectedStatuses, setSelectedStatuses] = useState<string[]>([]);

  const sourceProducts = useMemo(
    () => listCatalogProducts(categorySlug),
    [categorySlug],
  );
  const category = categoryGateway.find((item) => item.slug === categorySlug);

  const brands = useMemo(
    () =>
      [...new Set(sourceProducts.map((product) => product.brand))].sort(
        (a, b) => a.localeCompare(b),
      ),
    [sourceProducts],
  );

  const brandCounts = useMemo(() => {
    const counts: Record<string, number> = {};
    for (const product of sourceProducts) {
      counts[product.brand] = (counts[product.brand] ?? 0) + 1;
    }
    return counts;
  }, [sourceProducts]);

  const statusCounts = useMemo(() => {
    const counts: Record<string, number> = { featured: 0, new: 0 };
    for (const product of sourceProducts) {
      for (const badge of product.badges ?? []) {
        counts[badge] = (counts[badge] ?? 0) + 1;
      }
    }
    return counts;
  }, [sourceProducts]);

  const products = useMemo(() => {
    let next = [...sourceProducts];

    if (selectedBrands.length > 0) {
      next = next.filter((product) => selectedBrands.includes(product.brand));
    }

    if (selectedStatuses.length > 0) {
      next = next.filter((product) =>
        (product.badges ?? []).some((badge) =>
          selectedStatuses.includes(badge),
        ),
      );
    }

    next.sort((a, b) => {
      if (sort === "name") {
        return a.name[locale].localeCompare(b.name[locale], locale);
      }
      if (sort === "brand") {
        return (
          a.brand.localeCompare(b.brand) ||
          a.name[locale].localeCompare(b.name[locale], locale)
        );
      }
      const rank = (badges?: Array<"featured" | "new">) => {
        if (badges?.includes("featured")) return 0;
        if (badges?.includes("new")) return 1;
        return 2;
      };
      return rank(a.badges) - rank(b.badges);
    });

    return next;
  }, [sourceProducts, selectedBrands, selectedStatuses, sort, locale]);

  const activeFilterCount = selectedBrands.length + selectedStatuses.length;

  function clearFilters() {
    setSelectedBrands([]);
    setSelectedStatuses([]);
  }

  const filterProps = {
    brands,
    brandCounts,
    selectedBrands,
    onToggleBrand: (brand: string) =>
      setSelectedBrands((current) => toggleValue(current, brand)),
    selectedStatuses,
    onToggleStatus: (status: string) =>
      setSelectedStatuses((current) => toggleValue(current, status)),
    statusCounts,
    onClear: clearFilters,
  };

  return (
    <div className="sf-band-paper min-h-[70svh] pb-16">
      <section className="relative isolate overflow-hidden bg-[var(--night-forest)] text-[var(--warm-bone)]">
        {category ? (
          <Image
            src={category.imageSrc}
            alt=""
            fill
            priority
            sizes="100vw"
            className="object-cover opacity-45"
          />
        ) : null}
        <div
          aria-hidden
          className="absolute inset-0 bg-gradient-to-r from-[var(--night-forest)] via-[var(--night-forest)]/88 to-[var(--night-forest)]/50"
        />
        <div className="sf-container relative py-8 sm:py-10">
          <nav
            aria-label="Breadcrumb"
            className="text-sm text-[var(--warm-bone)]/55"
          >
            <Link
              href="/catalog"
              className="no-underline hover:text-[var(--warm-bone)]"
            >
              {t.nav.catalog}
            </Link>
            {category ? (
              <>
                <span className="mx-2 opacity-50">/</span>
                <span className="text-[var(--warm-bone)]">
                  {category.name[locale]}
                </span>
              </>
            ) : null}
          </nav>
          <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
            <div>
              <h1 className="sf-display text-3xl sm:text-4xl md:text-5xl">
                {category ? category.name[locale] : t.catalog.title}
              </h1>
              <p className="mt-2 max-w-xl text-sm text-[var(--warm-bone)]/68 sm:text-base">
                {category ? category.label[locale] : t.catalog.lead}
              </p>
            </div>
            <p className="rounded-full border border-white/15 bg-white/5 px-3 py-1.5 text-sm text-[var(--warm-bone)]/80 tabular-nums">
              {sourceProducts.length} {t.catalog.results}
            </p>
          </div>
        </div>
      </section>

      <div className="sf-container space-y-5 pt-5 sm:pt-6">
        <div className="sf-scroll-x -mx-1 px-1 pb-1">
          <Button
            asChild
            size="sm"
            variant={!categorySlug ? "default" : "outline"}
            className="shrink-0"
          >
            <Link href="/catalog">{t.catalog.allCategories}</Link>
          </Button>
          {categoryGateway.map((item) => {
            const active = item.slug === categorySlug;
            return (
              <Button
                key={item.id}
                asChild
                size="sm"
                variant={active ? "default" : "outline"}
                className="shrink-0"
              >
                <Link href={item.href}>{item.name[locale]}</Link>
              </Button>
            );
          })}
        </div>

        <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
          <div className="min-w-0 space-y-4">
            <div className="rounded-2xl border border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] bg-white/80 px-3 py-3 shadow-[0_10px_30px_-24px_rgba(11,15,12,0.45)] sm:px-4">
              <div className="flex items-center justify-between gap-3">
                <p className="min-w-0 text-sm text-[color-mix(in_oklab,var(--charcoal)_58%,transparent)]">
                  <span className="font-semibold text-[var(--charcoal)] tabular-nums">
                    {products.length}
                  </span>{" "}
                  {t.catalog.results}
                  {activeFilterCount > 0 ? (
                    <span className="ml-1 hidden text-xs sm:inline">
                      · {t.catalog.showing} {sourceProducts.length}
                    </span>
                  ) : null}
                </p>

                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="shrink-0 border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] bg-white text-[var(--charcoal)] lg:hidden"
                  onClick={() => setDrawerOpen(true)}
                >
                  <SlidersHorizontal />
                  {t.catalog.filters}
                  {activeFilterCount > 0 ? (
                    <span className="rounded-full bg-[var(--charcoal)] px-1.5 text-[0.65rem] text-[var(--warm-bone)] tabular-nums">
                      {activeFilterCount}
                    </span>
                  ) : null}
                </Button>
              </div>

              <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-[color-mix(in_oklab,var(--charcoal)_8%,transparent)] pt-3">
                <div
                  className="flex items-center rounded-lg border border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] bg-white p-0.5"
                  role="group"
                  aria-label={t.catalog.layout}
                >
                  {(
                    [
                      {
                        id: "grid" as const,
                        icon: LayoutGrid,
                        label: t.catalog.layoutGrid,
                      },
                      {
                        id: "comfortable" as const,
                        icon: Rows3,
                        label: t.catalog.layoutComfortable,
                      },
                      {
                        id: "list" as const,
                        icon: LayoutList,
                        label: t.catalog.layoutList,
                      },
                    ] as const
                  ).map((option) => {
                    const Icon = option.icon;
                    return (
                      <Button
                        key={option.id}
                        type="button"
                        size="icon"
                        variant={layout === option.id ? "secondary" : "ghost"}
                        aria-label={option.label}
                        aria-pressed={layout === option.id}
                        className="size-8 text-[var(--charcoal)] sm:size-9"
                        onClick={() => setLayout(option.id)}
                      >
                        <Icon />
                      </Button>
                    );
                  })}
                </div>

                <Select
                  value={sort}
                  onValueChange={(value) => setSort(value as SortKey)}
                >
                  <SelectTrigger
                    className="h-8 min-w-0 flex-1 border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] bg-white text-[var(--charcoal)] sm:h-9 sm:max-w-[10rem] sm:flex-none"
                    aria-label={t.catalog.sort}
                  >
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="featured">
                      {t.catalog.sortFeatured}
                    </SelectItem>
                    <SelectItem value="name">{t.catalog.sortName}</SelectItem>
                    <SelectItem value="brand">{t.catalog.sortBrand}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            {activeFilterCount > 0 ? (
              <div className="flex flex-wrap gap-2">
                {selectedBrands.map((brand) => (
                  <button
                    key={brand}
                    type="button"
                    className="rounded-full border border-[color-mix(in_oklab,var(--charcoal)_14%,transparent)] bg-white px-3 py-1 text-xs font-medium text-[var(--charcoal)]"
                    onClick={() =>
                      setSelectedBrands((current) =>
                        toggleValue(current, brand),
                      )
                    }
                  >
                    {brand} ×
                  </button>
                ))}
                {selectedStatuses.map((status) => (
                  <button
                    key={status}
                    type="button"
                    className="rounded-full border border-[color-mix(in_oklab,var(--charcoal)_14%,transparent)] bg-white px-3 py-1 text-xs font-medium text-[var(--charcoal)] capitalize"
                    onClick={() =>
                      setSelectedStatuses((current) =>
                        toggleValue(current, status),
                      )
                    }
                  >
                    {status} ×
                  </button>
                ))}
              </div>
            ) : null}

            {products.length === 0 ? (
              <div
                className="rounded-2xl border border-dashed border-[color-mix(in_oklab,var(--charcoal)_18%,transparent)] bg-white/70 px-6 py-16 text-center"
                role="status"
              >
                <p className="font-medium text-[var(--charcoal)]">
                  {t.catalog.empty}
                </p>
                {activeFilterCount > 0 ? (
                  <Button
                    type="button"
                    variant="outline"
                    className="mt-4"
                    onClick={clearFilters}
                  >
                    {t.catalog.clearFilters}
                  </Button>
                ) : null}
              </div>
            ) : (
              <ProductGrid products={products} layout={layout} tone="on-ink" />
            )}
          </div>

          <aside className="hidden lg:block" aria-label={t.catalog.filters}>
            <div className="sticky top-24 rounded-2xl border border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] bg-white p-5 shadow-[0_18px_40px_-28px_rgba(11,15,12,0.35)]">
              <CatalogFilters {...filterProps} />
            </div>
          </aside>
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
            className="absolute inset-y-0 right-0 flex w-[min(100%,22rem)] flex-col bg-[var(--paper)] shadow-2xl"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="flex items-center justify-between border-b border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] px-4 py-3">
              <p className="font-semibold text-[var(--charcoal)]">
                {t.catalog.filters}
              </p>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={t.catalog.closeFilters}
                className="text-[var(--charcoal)]"
                onClick={() => setDrawerOpen(false)}
              >
                <X />
              </Button>
            </div>
            <div className="flex-1 overflow-y-auto px-4 py-4">
              <CatalogFilters {...filterProps} />
            </div>
            <div className="flex gap-2 border-t border-[color-mix(in_oklab,var(--charcoal)_12%,transparent)] p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
              <Button
                type="button"
                variant="outline"
                className="flex-1"
                onClick={clearFilters}
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
