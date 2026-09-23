"use client";

import { useEffect, useId, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Search, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { getSearchSuggestions } from "@/features/catalog/api/public-catalog-client";
import { toProductCardData } from "@/features/catalog/adapters/public-catalog-adapter";
import { serializeCatalogSearchParams } from "@/features/catalog/query-state/catalog-search-params";
import type {
  PublicProductCard,
  PublicSearchEntityHit,
} from "@/features/catalog/types/public-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { ResponsiveProductImage } from "@/features/storefront/components/commerce/ResponsiveProductImage";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { ApiClientError } from "@/lib/api-client";
import { cn } from "@/lib/utils";
import { HighlightedText } from "@/features/search/lib/highlight";

type OverlayState =
  | "idle"
  | "min"
  | "loading"
  | "results"
  | "empty"
  | "fallback"
  | "unavailable"
  | "error";

type Option =
  | {
      type: "product";
      id: string;
      href: string;
      card: ReturnType<typeof toProductCardData>;
    }
  | { type: "category"; id: string; href: string; hit: PublicSearchEntityHit }
  | { type: "brand"; id: string; href: string; hit: PublicSearchEntityHit }
  | { type: "view-all"; id: "view-all"; href: string };

export function SearchOverlay({
  open,
  onClose,
  inputRef,
}: {
  open: boolean;
  onClose: () => void;
  inputRef: React.RefObject<HTMLInputElement | null>;
}) {
  const router = useRouter();
  const { t, locale } = useStorefrontCopy();
  const titleId = useId();
  const listId = useId();
  const statusId = useId();
  const [query, setQuery] = useState("");
  const [asyncState, setAsyncState] =
    useState<Exclude<OverlayState, "idle" | "min">>("loading");
  const [products, setProducts] = useState<PublicProductCard[]>([]);
  const [categories, setCategories] = useState<PublicSearchEntityHit[]>([]);
  const [brands, setBrands] = useState<PublicSearchEntityHit[]>([]);
  const [activeIndex, setActiveIndex] = useState(0);
  const requestId = useRef(0);
  const trimmedQuery = query.trim();
  const state: OverlayState =
    trimmedQuery.length === 0
      ? "idle"
      : trimmedQuery.length < 2
        ? "min"
        : asyncState;

  useEffect(() => {
    if (!open) return;
    const trimmed = query.trim();
    if (trimmed.length < 2) {
      return;
    }

    const controller = new AbortController();
    const current = ++requestId.current;
    const timer = window.setTimeout(async () => {
      setAsyncState("loading");
      try {
        const response = await getSearchSuggestions(
          { q: trimmed, locale, limit: 8 },
          { locale, signal: controller.signal, cache: "no-store" },
        );
        if (current !== requestId.current) return;
        setProducts(response.data.products);
        setCategories(response.data.categories);
        setBrands(response.data.brands);
        const empty =
          response.data.products.length === 0 &&
          response.data.categories.length === 0 &&
          response.data.brands.length === 0;
        if (response.meta?.fallback_used) {
          setAsyncState(empty ? "empty" : "fallback");
        } else {
          setAsyncState(empty ? "empty" : "results");
        }
        setActiveIndex(0);
      } catch (error) {
        if (controller.signal.aborted || current !== requestId.current) return;
        if (error instanceof ApiClientError && error.status === 503) {
          setAsyncState("unavailable");
          return;
        }
        setAsyncState("error");
      }
    }, 250);

    return () => {
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [open, query, locale]);

  const options = useMemo<Option[]>(() => {
    if (query.trim().length < 2) {
      return [];
    }
    const next: Option[] = [];
    for (const product of products) {
      const card = toProductCardData(product);
      next.push({
        type: "product",
        id: `product-${card.id}`,
        href: card.href,
        card,
      });
    }
    for (const hit of categories) {
      next.push({
        type: "category",
        id: `category-${hit.id ?? hit.slug}`,
        href: hit.path || `/catalog/${hit.slug ?? ""}`,
        hit,
      });
    }
    for (const hit of brands) {
      next.push({
        type: "brand",
        id: `brand-${hit.id ?? hit.slug}`,
        href: hit.path || `/brands/${hit.slug ?? ""}`,
        hit,
      });
    }
    const trimmed = query.trim();
    if (trimmed.length >= 2) {
      const search = serializeCatalogSearchParams(
        {
          brand: [],
          attribute: {},
          q: trimmed,
          sort: "default",
          page: 1,
        },
        { defaultSort: "default" },
      );
      next.push({
        type: "view-all",
        id: "view-all",
        href: search ? `/search?${search}` : "/search",
      });
    }
    return next;
  }, [products, categories, brands, query]);

  function closeAndReset() {
    setQuery("");
    setProducts([]);
    setCategories([]);
    setBrands([]);
    setAsyncState("loading");
    setActiveIndex(0);
    onClose();
  }

  function select(option: Option) {
    closeAndReset();
    router.push(option.href);
  }

  function onKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key === "Escape") {
      event.preventDefault();
      closeAndReset();
      return;
    }
    if (options.length === 0) {
      if (event.key === "Enter" && query.trim().length >= 2) {
        event.preventDefault();
        closeAndReset();
        router.push(
          `/search?${serializeCatalogSearchParams(
            {
              brand: [],
              attribute: {},
              q: query.trim(),
              sort: "default",
              page: 1,
            },
            { defaultSort: "default" },
          )}`,
        );
      }
      return;
    }
    if (event.key === "ArrowDown") {
      event.preventDefault();
      setActiveIndex((index) => (index + 1) % options.length);
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      setActiveIndex((index) => (index - 1 + options.length) % options.length);
    } else if (event.key === "Enter") {
      event.preventDefault();
      const option = options[activeIndex];
      if (option) select(option);
    }
  }

  if (!open) return null;

  const statusLabel =
    state === "loading"
      ? t.search.loading
      : state === "empty"
        ? t.search.noResults
        : state === "min"
          ? t.search.minQuery
          : state === "unavailable"
            ? t.search.unavailable
            : state === "error"
              ? t.search.networkError
              : state === "fallback"
                ? t.search.fallback
                : "";

  const activeOption = options[activeIndex];

  return (
    <div
      id="storefront-search"
      role="dialog"
      aria-modal="true"
      aria-labelledby={titleId}
      className="fixed inset-0 z-[var(--z-modal)] bg-[color-mix(in_oklab,var(--night-forest)_72%,transparent)] backdrop-blur-sm"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) closeAndReset();
      }}
    >
      <div className="flex h-[100dvh] flex-col sm:block sm:h-auto sm:p-8">
        <div className="flex min-h-0 flex-1 flex-col border-border/70 bg-card shadow-xl sm:mx-auto sm:mt-16 sm:max-h-[min(40rem,80vh)] sm:max-w-2xl sm:rounded-2xl sm:border">
          <div className="flex items-center justify-between gap-3 border-b border-border/60 px-4 py-3 sm:px-5">
            <h2 id={titleId} className="text-lg font-semibold">
              {t.search.title}
            </h2>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              aria-label={t.search.close}
              onClick={closeAndReset}
            >
              <X />
            </Button>
          </div>
          <div className="px-4 pt-3 sm:px-5">
            <div className="relative">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                ref={inputRef}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                onKeyDown={onKeyDown}
                placeholder={t.search.placeholder}
                aria-label={t.nav.search}
                aria-autocomplete="list"
                aria-controls={listId}
                aria-activedescendant={
                  activeOption ? `${listId}-${activeOption.id}` : undefined
                }
                aria-describedby={statusId}
                role="combobox"
                aria-expanded={options.length > 0}
                autoComplete="off"
                className="h-12 pl-10 text-base sm:text-sm"
              />
            </div>
            <p id={statusId} className="sr-only" aria-live="polite">
              {statusLabel}
            </p>
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto px-2 py-3 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-3">
            {state === "idle" ? (
              <p className="px-3 py-6 text-sm text-muted-foreground">
                {t.search.placeholder}
              </p>
            ) : null}
            {state === "min" ? (
              <p className="px-3 py-6 text-sm text-muted-foreground">
                {t.search.minQuery}
              </p>
            ) : null}
            {state === "loading" ? (
              <p className="px-3 py-6 text-sm text-muted-foreground">
                {t.search.loading}…
              </p>
            ) : null}
            {state === "unavailable" || state === "error" ? (
              <p className="px-3 py-6 text-sm text-muted-foreground">
                {state === "unavailable"
                  ? t.search.unavailable
                  : t.search.networkError}
              </p>
            ) : null}
            {state === "empty" ? (
              <p className="px-3 py-6 text-sm text-muted-foreground">
                {t.search.noResultsHint}
              </p>
            ) : null}
            {state === "fallback" ? (
              <p className="px-3 pb-2 text-xs text-muted-foreground">
                {t.search.fallback}
              </p>
            ) : null}
            {options.length > 0 ? (
              <ul id={listId} role="listbox" className="space-y-4">
                {options.some((option) => option.type === "product") ? (
                  <li className="space-y-1">
                    <p className="px-3 sf-label text-muted-foreground">
                      {t.search.products}
                    </p>
                    <ul className="space-y-0.5">
                      {options
                        .filter((option) => option.type === "product")
                        .map((option) => {
                          const index = options.indexOf(option);
                          const active = index === activeIndex;
                          if (option.type !== "product") return null;
                          return (
                            <li key={option.id} role="none">
                              <Link
                                id={`${listId}-${option.id}`}
                                role="option"
                                aria-selected={active}
                                href={option.href}
                                className={cn(
                                  "flex items-center gap-3 rounded-xl px-3 py-2 no-underline",
                                  active ? "bg-muted" : "hover:bg-muted/70",
                                )}
                                onMouseEnter={() => setActiveIndex(index)}
                                onClick={closeAndReset}
                              >
                                <div className="relative size-12 shrink-0 overflow-hidden rounded-lg bg-muted">
                                  <ResponsiveProductImage
                                    media={
                                      option.card.imageMedia ??
                                      option.card.imageSrc
                                    }
                                    alt={option.card.imageAlt}
                                    locale={locale}
                                    preset="thumbnail"
                                    sizes="48px"
                                    className="size-12"
                                  />
                                </div>
                                <div className="min-w-0 flex-1">
                                  <p className="truncate text-sm font-medium text-foreground">
                                    <HighlightedText
                                      text={option.card.name[locale]}
                                      query={query}
                                    />
                                  </p>
                                  <p className="truncate text-xs text-muted-foreground">
                                    {option.card.brand}
                                  </p>
                                </div>
                                <PriceDisplay
                                  currency={option.card.pricing?.currency}
                                  locale={locale === "ka" ? "ka-GE" : "en"}
                                  finalAmountMinor={
                                    option.card.pricing?.final_amount_minor
                                  }
                                  baseAmountMinor={
                                    option.card.pricing?.base_amount_minor
                                  }
                                  minAmountMinor={
                                    option.card.pricing?.min_amount_minor
                                  }
                                  maxAmountMinor={
                                    option.card.pricing?.max_amount_minor
                                  }
                                  isRange={option.card.pricing?.is_range}
                                  className="shrink-0 text-sm"
                                />
                              </Link>
                            </li>
                          );
                        })}
                    </ul>
                  </li>
                ) : null}
                {options.some((option) => option.type === "category") ? (
                  <li className="space-y-1">
                    <p className="px-3 sf-label text-muted-foreground">
                      {t.search.categories}
                    </p>
                    <ul>
                      {options
                        .filter((option) => option.type === "category")
                        .map((option) => {
                          const index = options.indexOf(option);
                          const active = index === activeIndex;
                          if (option.type !== "category") return null;
                          return (
                            <li key={option.id}>
                              <Link
                                id={`${listId}-${option.id}`}
                                role="option"
                                aria-selected={active}
                                href={option.href}
                                className={cn(
                                  "block rounded-xl px-3 py-2 text-sm no-underline",
                                  active ? "bg-muted" : "hover:bg-muted/70",
                                )}
                                onMouseEnter={() => setActiveIndex(index)}
                                onClick={closeAndReset}
                              >
                                <HighlightedText
                                  text={option.hit.name ?? ""}
                                  query={query}
                                />
                              </Link>
                            </li>
                          );
                        })}
                    </ul>
                  </li>
                ) : null}
                {options.some((option) => option.type === "brand") ? (
                  <li className="space-y-1">
                    <p className="px-3 sf-label text-muted-foreground">
                      {t.search.brands}
                    </p>
                    <ul>
                      {options
                        .filter((option) => option.type === "brand")
                        .map((option) => {
                          const index = options.indexOf(option);
                          const active = index === activeIndex;
                          if (option.type !== "brand") return null;
                          return (
                            <li key={option.id}>
                              <Link
                                id={`${listId}-${option.id}`}
                                role="option"
                                aria-selected={active}
                                href={option.href}
                                className={cn(
                                  "block rounded-xl px-3 py-2 text-sm no-underline",
                                  active ? "bg-muted" : "hover:bg-muted/70",
                                )}
                                onMouseEnter={() => setActiveIndex(index)}
                                onClick={closeAndReset}
                              >
                                <HighlightedText
                                  text={option.hit.name ?? ""}
                                  query={query}
                                />
                              </Link>
                            </li>
                          );
                        })}
                    </ul>
                  </li>
                ) : null}
                {options.some((option) => option.type === "view-all") ? (
                  <li>
                    {options
                      .filter((option) => option.type === "view-all")
                      .map((option) => {
                        const index = options.indexOf(option);
                        const active = index === activeIndex;
                        return (
                          <Link
                            key={option.id}
                            id={`${listId}-${option.id}`}
                            role="option"
                            aria-selected={active}
                            href={option.href}
                            className={cn(
                              "block rounded-xl px-3 py-3 text-sm font-medium no-underline",
                              active ? "bg-muted" : "hover:bg-muted/70",
                            )}
                            onMouseEnter={() => setActiveIndex(index)}
                            onClick={closeAndReset}
                          >
                            {t.search.viewAll}
                          </Link>
                        );
                      })}
                  </li>
                ) : null}
              </ul>
            ) : null}
          </div>
        </div>
      </div>
    </div>
  );
}
