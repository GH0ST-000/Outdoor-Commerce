"use client";

import Link from "next/link";
import { useMemo, useState, useTransition } from "react";
import { useRouter } from "next/navigation";
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
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { Button } from "@/components/ui/button";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Drawer,
  DrawerClose,
  DrawerContent,
  DrawerTitle,
} from "@/components/ui/drawer";
import { Pagination } from "@/components/ui/pagination";
import { EmptyState, ErrorState } from "@/components/ui/empty-state";
import { IconButton } from "@/components/ui/icon-button";
import {
  ActiveFilterChip,
  FilterCheckbox,
  FilterRange,
} from "@/components/commerce/filter-controls";
import { formatMoneyMinor } from "@/features/pricing/lib/money";
import {
  STOREFRONT_EVENTS,
  trackStorefrontEvent,
} from "@/features/storefront/analytics/events";
import {
  CATALOG_SORTS,
  catalogQueryHasFilters,
  emptyCatalogQuery,
  emptySearchQuery,
  catalogHref,
  type CatalogQuery,
} from "@/features/catalog/query-state/catalog-search-params";
import {
  gelMajorToMinor,
  minorToGelMajor,
} from "@/features/catalog/query-state/gel";
import type {
  CatalogSort,
  PublicCategoryDetail,
  PublicFacets,
} from "@/features/catalog/types/public-catalog";
import type { CatalogListState } from "@/features/catalog/lib/load-public-catalog";
import { ProductGridSkeleton } from "@/features/catalog/components/catalog-skeletons";

function toggleValue(
  values: string[],
  value: string,
  checked: boolean,
): string[] {
  if (checked) {
    return values.includes(value) ? values : [...values, value];
  }
  return values.filter((item) => item !== value);
}

function cloneQuery(query: CatalogQuery): CatalogQuery {
  return {
    ...query,
    brand: [...query.brand],
    attribute: Object.fromEntries(
      Object.entries(query.attribute).map(([key, values]) => [
        key,
        [...values],
      ]),
    ),
  };
}

function withPageReset(query: CatalogQuery): CatalogQuery {
  return { ...query, page: 1 };
}

export function CatalogPage({
  categorySlug,
  brandSlug,
  pathname: pathnameProp,
  query: queryProp,
  category,
  initial,
}: {
  categorySlug?: string;
  brandSlug?: string;
  pathname?: string;
  query?: CatalogQuery;
  category?: PublicCategoryDetail | null;
  initial?: CatalogListState;
}) {
  const { t, locale } = useStorefrontCopy();
  const router = useRouter();
  const [isPending, startTransition] = useTransition();
  const query = queryProp ?? emptyCatalogQuery();
  const pathname =
    pathnameProp ??
    (categorySlug
      ? `/catalog/${categorySlug}`
      : brandSlug
        ? `/brands/${brandSlug}`
        : "/catalog");
  const isSearchPage = pathname === "/search";
  const defaultSort = isSearchPage ? "default" : "featured";
  const [layout, setLayout] = useState<ProductLayout>("grid");
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [draft, setDraft] = useState<CatalogQuery>(() => cloneQuery(query));
  const priceKey = `${query.min_price ?? ""}:${query.max_price ?? ""}`;
  const [priceDraft, setPriceDraft] = useState({
    key: priceKey,
    min: minorToGelMajor(query.min_price),
    max: minorToGelMajor(query.max_price),
  });
  const minMajor =
    priceDraft.key === priceKey
      ? priceDraft.min
      : minorToGelMajor(query.min_price);
  const maxMajor =
    priceDraft.key === priceKey
      ? priceDraft.max
      : minorToGelMajor(query.max_price);
  const [expanded, setExpanded] = useState<Record<string, boolean>>({});
  const [groupQuery, setGroupQuery] = useState<Record<string, string>>({});

  function setMinMajor(value: string) {
    setPriceDraft({ key: priceKey, min: value, max: maxMajor });
  }

  function setMaxMajor(value: string) {
    setPriceDraft({ key: priceKey, min: minMajor, max: value });
  }

  function clearPriceDraft() {
    setPriceDraft({ key: ":", min: "", max: "" });
  }

  function openFilters() {
    setDraft(cloneQuery(query));
    setDrawerOpen(true);
  }

  function navigate(
    next: CatalogQuery,
    event?:
      | "filter_applied"
      | "filter_cleared"
      | "sort_changed"
      | "pagination_changed",
  ) {
    startTransition(() => {
      router.push(catalogHref(pathname, next, { defaultSort }), {
        scroll: false,
      });
    });
    if (event) {
      trackStorefrontEvent(
        event === "filter_applied"
          ? STOREFRONT_EVENTS.filter_applied
          : event === "filter_cleared"
            ? STOREFRONT_EVENTS.filter_cleared
            : event === "sort_changed"
              ? STOREFRONT_EVENTS.sort_changed
              : STOREFRONT_EVENTS.pagination_changed,
        { sort: next.sort, page: next.page },
      );
    }
  }

  function resetFilters(): CatalogQuery {
    if (isSearchPage) {
      return emptySearchQuery(query.q);
    }
    return emptyCatalogQuery();
  }

  const products = initial?.products ?? [];
  const total = initial?.total ?? 0;
  const lastPage = initial?.lastPage ?? 1;
  const facets = initial?.facets ?? null;
  const displayError = initial?.error ?? null;
  const hasFilters = catalogQueryHasFilters(query);
  const resultsId = "catalog-results";

  function commitPrice(
    source: CatalogQuery,
    apply: (next: CatalogQuery) => void,
  ) {
    const min = gelMajorToMinor(minMajor);
    const max = gelMajorToMinor(maxMajor);
    let nextMin = min;
    let nextMax = max;
    if (nextMin !== undefined && nextMax !== undefined && nextMin > nextMax) {
      nextMin = max;
      nextMax = min;
    }
    apply(
      withPageReset({
        ...source,
        min_price: nextMin,
        max_price: nextMax,
      }),
    );
  }

  const activeChips = useMemo(
    () => buildActiveChips(query, facets, t, locale),
    [query, facets, t, locale],
  );

  const sortLabels: Record<CatalogSort, string> = {
    default: t.catalog.sortDefault,
    featured: t.catalog.sortFeatured,
    newest: t.catalog.sortNewest,
    price_asc: t.catalog.sortPriceAsc,
    price_desc: t.catalog.sortPriceDesc,
    name_asc: t.catalog.sortName,
    name_desc: t.catalog.sortNameDesc,
  };

  const filterPanel = (
    source: CatalogQuery,
    apply: (
      next: CatalogQuery,
      event?: "filter_applied" | "filter_cleared",
    ) => void,
    idPrefix: string,
  ) => (
    <div className="space-y-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-base font-semibold tracking-tight text-foreground">
            {t.catalog.filters}
          </p>
          <p className="mt-1 text-xs leading-relaxed text-muted-foreground">
            {t.catalog.filterHint}
          </p>
        </div>
        {catalogQueryHasFilters(source) ? (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="text-foreground"
            onClick={() => {
              const cleared = emptyCatalogQuery();
              apply(cleared, "filter_cleared");
              clearPriceDraft();
            }}
          >
            {t.catalog.clearFilters}
          </Button>
        ) : null}
      </div>

      <section aria-labelledby={`${idPrefix}-filter-brand`}>
        <h2
          id={`${idPrefix}-filter-brand`}
          className="sf-label mb-2 text-muted-foreground"
        >
          {t.catalog.brand}
        </h2>
        <div className="space-y-1">
          {(facets?.brands ?? []).map((brand) => {
            const code = brand.code ?? String(brand.id);
            return (
              <FilterCheckbox
                key={code}
                id={`${idPrefix}-filter-brand-${code}`}
                label={brand.name ?? code}
                checked={source.brand.includes(code)}
                onChange={(checked) =>
                  apply(
                    withPageReset({
                      ...source,
                      brand: toggleValue(source.brand, code, checked),
                    }),
                    "filter_applied",
                  )
                }
                count={brand.count}
              />
            );
          })}
        </div>
      </section>

      <section aria-labelledby={`${idPrefix}-filter-status`}>
        <h2
          id={`${idPrefix}-filter-status`}
          className="sf-label mb-2 text-muted-foreground"
        >
          {t.catalog.status}
        </h2>
        <div className="space-y-1">
          <FilterCheckbox
            id={`${idPrefix}-filter-in-stock`}
            label={t.catalog.inStock}
            checked={source.in_stock === true}
            onChange={(checked) =>
              apply(
                withPageReset({ ...source, in_stock: checked || undefined }),
                "filter_applied",
              )
            }
            count={facets?.in_stock_count}
          />
          <FilterCheckbox
            id={`${idPrefix}-filter-on-sale`}
            label={t.catalog.onSale}
            checked={source.on_sale === true}
            onChange={(checked) =>
              apply(
                withPageReset({ ...source, on_sale: checked || undefined }),
                "filter_applied",
              )
            }
            count={facets?.on_sale_count}
          />
          <FilterCheckbox
            id={`${idPrefix}-filter-featured`}
            label={t.catalog.featuredFilter}
            checked={source.featured === true}
            onChange={(checked) =>
              apply(
                withPageReset({ ...source, featured: checked || undefined }),
                "filter_applied",
              )
            }
          />
        </div>
      </section>

      <section aria-labelledby={`${idPrefix}-filter-price`}>
        <h2
          id={`${idPrefix}-filter-price`}
          className="sf-label mb-2 text-muted-foreground"
        >
          {t.catalog.minPrice}
        </h2>
        <FilterRange
          minId={`${idPrefix}-filter-min-price`}
          maxId={`${idPrefix}-filter-max-price`}
          minLabel={t.catalog.minPrice}
          maxLabel={t.catalog.maxPrice}
          minValue={minMajor}
          maxValue={maxMajor}
          onMinChange={setMinMajor}
          onMaxChange={setMaxMajor}
        />
        <div className="mt-2 flex gap-2">
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() =>
              commitPrice(source, (next) => apply(next, "filter_applied"))
            }
          >
            {t.catalog.applyFilters}
          </Button>
          {source.min_price !== undefined || source.max_price !== undefined ? (
            <Button
              type="button"
              size="sm"
              variant="ghost"
              onClick={() => {
                clearPriceDraft();
                apply(
                  withPageReset({
                    ...source,
                    min_price: undefined,
                    max_price: undefined,
                  }),
                  "filter_cleared",
                );
              }}
            >
              {t.catalog.clearPrice}
            </Button>
          ) : null}
        </div>
      </section>

      {(facets?.attributes ?? []).map((attribute) => {
        const search = (groupQuery[attribute.code] ?? "").toLowerCase();
        const values = attribute.values.filter((value) =>
          search
            ? (value.name ?? value.code).toLowerCase().includes(search)
            : true,
        );
        const collapsed = values.length > 8 && !expanded[attribute.code];
        const visible = collapsed ? values.slice(0, 8) : values;

        return (
          <section
            key={attribute.code}
            aria-labelledby={`${idPrefix}-filter-attr-${attribute.code}`}
          >
            <h2
              id={`${idPrefix}-filter-attr-${attribute.code}`}
              className="sf-label mb-2 text-muted-foreground"
            >
              {attribute.name}
            </h2>
            {attribute.values.length > 12 ? (
              <input
                value={groupQuery[attribute.code] ?? ""}
                onChange={(event) =>
                  setGroupQuery((current) => ({
                    ...current,
                    [attribute.code]: event.target.value,
                  }))
                }
                className="mb-2 flex h-9 w-full rounded-lg border border-[var(--input-border)] bg-background px-3 text-sm"
                aria-label={attribute.name}
              />
            ) : null}
            <div className="space-y-1">
              {visible.map((value) => (
                <FilterCheckbox
                  key={value.code}
                  id={`${idPrefix}-filter-${attribute.code}-${value.code}`}
                  label={value.name ?? value.code}
                  checked={(source.attribute[attribute.code] ?? []).includes(
                    value.code,
                  )}
                  onChange={(checked) =>
                    apply(
                      withPageReset({
                        ...source,
                        attribute: {
                          ...source.attribute,
                          [attribute.code]: toggleValue(
                            source.attribute[attribute.code] ?? [],
                            value.code,
                            checked,
                          ),
                        },
                      }),
                      "filter_applied",
                    )
                  }
                  count={value.count}
                />
              ))}
            </div>
            {values.length > 8 ? (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="mt-1"
                onClick={() =>
                  setExpanded((current) => ({
                    ...current,
                    [attribute.code]: !current[attribute.code],
                  }))
                }
              >
                {expanded[attribute.code] ? "–" : `+ ${values.length - 8}`}
              </Button>
            ) : null}
          </section>
        );
      })}
    </div>
  );

  const emptyTitle = isSearchPage
    ? t.search.noResults
    : hasFilters
      ? t.catalog.empty
      : t.catalog.emptyCategory;
  const emptyDescription = isSearchPage
    ? t.search.noResultsHint
    : hasFilters
      ? t.catalog.emptyFiltersDescription
      : t.catalog.emptyCategoryDescription;

  return (
    <div className="min-w-0 space-y-4">
      {activeChips.length > 0 ? (
        <div className="flex flex-wrap gap-2" aria-label={t.catalog.filters}>
          {activeChips.map((chip) => (
            <ActiveFilterChip
              key={chip.key}
              label={chip.label}
              removeLabel={`${t.catalog.removeFilter}: ${chip.label}`}
              onRemove={() =>
                navigate(withPageReset(chip.next), "filter_cleared")
              }
            />
          ))}
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => {
              clearPriceDraft();
              navigate(resetFilters(), "filter_cleared");
            }}
          >
            {t.catalog.clearFilters}
          </Button>
        </div>
      ) : null}

      <div className="sticky top-[3.5rem] z-[var(--z-sticky)] rounded-2xl border border-border/70 bg-card/85 px-3 py-3 shadow-[var(--shadow-raised)] backdrop-blur-xl sm:top-16 sm:px-4 lg:top-[4.25rem]">
        <div className="flex items-center justify-between gap-3">
          <p
            className="min-w-0 text-sm text-muted-foreground"
            id={`${resultsId}-summary`}
          >
            <span className="font-semibold text-foreground tabular-nums">
              {total}
            </span>{" "}
            {t.catalog.results}
          </p>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="shrink-0 border-border bg-card text-foreground lg:hidden"
            onClick={openFilters}
          >
            <SlidersHorizontal />
            {t.catalog.filters}
            {hasFilters ? (
              <span className="rounded-full bg-foreground px-1.5 text-[0.65rem] text-background tabular-nums">
                {activeChips.length}
              </span>
            ) : null}
          </Button>
        </div>

        <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3">
          <div
            className="flex items-center rounded-lg border border-border bg-card p-0.5"
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
                  className="size-8 text-foreground sm:size-9"
                  onClick={() => setLayout(option.id)}
                >
                  <Icon />
                </Button>
              );
            })}
          </div>

          <Select
            value={query.sort}
            onValueChange={(value) =>
              navigate(
                withPageReset({ ...query, sort: value as CatalogSort }),
                "sort_changed",
              )
            }
          >
            <SelectTrigger
              className="h-8 min-w-0 flex-1 border-border bg-card text-foreground sm:h-9 sm:max-w-[12rem] sm:flex-none"
              aria-label={t.catalog.sort}
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {CATALOG_SORTS.map((sort) => (
                <SelectItem key={sort} value={sort}>
                  {sortLabels[sort]}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="grid items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
        <aside className="hidden lg:block" aria-label={t.catalog.filters}>
          <div className="sticky top-24 rounded-[var(--radius-xl)] border border-border bg-card p-5">
            {filterPanel(
              query,
              (next, event) => navigate(next, event),
              "desktop",
            )}
          </div>
        </aside>
        <div className="min-w-0 space-y-4">
          <h2 id={resultsId} className="sr-only">
            {t.catalog.resultsHeading}
          </h2>
          <div
            tabIndex={-1}
            aria-live="polite"
            aria-busy={isPending}
            aria-labelledby={resultsId}
          >
            {initial?.fallbackUsed ? (
              <p className="rounded-xl border border-border/70 bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                {t.search.fallback}
              </p>
            ) : null}
            {displayError ? (
              <ErrorState
                title={
                  displayError.code === "CATALOG_RATE_LIMITED"
                    ? t.catalog.rateLimited
                    : t.catalog.loadError
                }
                actionLabel={t.catalog.retryProducts}
                onAction={() => router.refresh()}
              />
            ) : isPending ? (
              <ProductGridSkeleton />
            ) : products.length === 0 ? (
              <EmptyState
                title={emptyTitle}
                description={emptyDescription}
                actionLabel={hasFilters ? t.catalog.clearFilters : undefined}
                onAction={
                  hasFilters
                    ? () => navigate(resetFilters(), "filter_cleared")
                    : undefined
                }
              />
            ) : (
              <ProductGrid products={products} layout={layout} />
            )}
            {!hasFilters && products.length === 0 && !displayError ? (
              <div className="mt-3 flex flex-wrap justify-center gap-2">
                {category?.parent ? (
                  <Button asChild variant="outline" size="sm">
                    <Link
                      href={
                        category.parent.path ??
                        `/catalog/${category.parent.slug}`
                      }
                    >
                      {t.catalog.parentCategory}
                    </Link>
                  </Button>
                ) : null}
                <Button asChild variant="outline" size="sm">
                  <Link href="/catalog">{t.catalog.allCategories}</Link>
                </Button>
              </div>
            ) : null}
          </div>

          {lastPage > 1 && !displayError ? (
            <Pagination
              page={query.page}
              lastPage={lastPage}
              compact
              onPageChange={(page) => {
                navigate({ ...query, page }, "pagination_changed");
                document.getElementById(resultsId)?.focus();
              }}
              previousLabel={t.catalog.previousPage}
              nextLabel={t.catalog.nextPage}
              label={t.catalog.paginationLabel}
              loading={isPending}
            />
          ) : null}
        </div>
      </div>

      <Drawer
        open={drawerOpen}
        onOpenChange={(open) => {
          if (open) {
            setDraft(cloneQuery(query));
          }
          setDrawerOpen(open);
        }}
      >
        <DrawerContent side="bottom" className="lg:hidden">
          <div className="flex items-center justify-between border-b border-border px-4 py-3">
            <DrawerTitle className="font-semibold">
              {t.catalog.filters}
            </DrawerTitle>
            <DrawerClose asChild>
              <IconButton
                label={t.catalog.closeFilters}
                variant="ghost"
                className="text-foreground"
              >
                <X />
              </IconButton>
            </DrawerClose>
          </div>
          <p className="px-4 pt-3 text-sm text-muted-foreground">
            <span className="font-semibold text-foreground tabular-nums">
              {total}
            </span>{" "}
            {t.catalog.results}
          </p>
          <div className="flex-1 overflow-y-auto px-4 py-4">
            {filterPanel(draft, (next) => setDraft(next), "mobile")}
          </div>
          <div className="flex gap-2 border-t border-border p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <Button
              type="button"
              variant="outline"
              className="flex-1"
              onClick={() => {
                setDraft(emptyCatalogQuery());
                clearPriceDraft();
              }}
            >
              {t.catalog.clearFilters}
            </Button>
            <Button
              type="button"
              className="flex-1"
              onClick={() => {
                commitPrice(draft, (next) => {
                  navigate(next, "filter_applied");
                  setDrawerOpen(false);
                });
              }}
            >
              {t.catalog.applyFilters}
            </Button>
          </div>
        </DrawerContent>
      </Drawer>
    </div>
  );
}

function buildActiveChips(
  query: CatalogQuery,
  facets: PublicFacets | null,
  t: ReturnType<typeof useStorefrontCopy>["t"],
  locale: string,
) {
  const chips: Array<{ key: string; label: string; next: CatalogQuery }> = [];
  const moneyLocale = locale === "ka" ? "ka-GE" : "en";

  for (const brand of query.brand) {
    const name =
      facets?.brands.find((item) => (item.code ?? String(item.id)) === brand)
        ?.name ?? brand;
    chips.push({
      key: `brand:${brand}`,
      label: name,
      next: { ...query, brand: query.brand.filter((item) => item !== brand) },
    });
  }

  for (const [code, values] of Object.entries(query.attribute)) {
    const attribute = facets?.attributes.find((item) => item.code === code);
    for (const value of values) {
      const name =
        attribute?.values.find((item) => item.code === value)?.name ?? value;
      chips.push({
        key: `attr:${code}:${value}`,
        label: `${attribute?.name ?? code}: ${name}`,
        next: {
          ...query,
          attribute: {
            ...query.attribute,
            [code]: values.filter((item) => item !== value),
          },
        },
      });
    }
  }

  if (query.min_price !== undefined || query.max_price !== undefined) {
    const min =
      query.min_price !== undefined
        ? formatMoneyMinor(query.min_price, "GEL", moneyLocale)
        : "…";
    const max =
      query.max_price !== undefined
        ? formatMoneyMinor(query.max_price, "GEL", moneyLocale)
        : "…";
    chips.push({
      key: "price",
      label: `${min} – ${max}`,
      next: { ...query, min_price: undefined, max_price: undefined },
    });
  }

  if (query.in_stock) {
    chips.push({
      key: "in_stock",
      label: t.catalog.inStock,
      next: { ...query, in_stock: undefined },
    });
  }
  if (query.on_sale) {
    chips.push({
      key: "on_sale",
      label: t.catalog.onSale,
      next: { ...query, on_sale: undefined },
    });
  }
  if (query.featured) {
    chips.push({
      key: "featured",
      label: t.catalog.featuredFilter,
      next: { ...query, featured: undefined },
    });
  }
  if (query.q) {
    chips.push({
      key: "q",
      label: query.q,
      next: { ...query, q: undefined },
    });
  }

  return chips;
}
