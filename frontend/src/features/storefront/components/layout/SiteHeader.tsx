"use client";

import { useEffect, useId, useRef, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Menu,
  Search,
  ShoppingBag,
  Heart,
  User,
  X,
} from "lucide-react";
import { SiteControls } from "@/components/site-controls";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  primaryNav,
  searchSuggestions,
} from "@/features/storefront/fixtures/demo-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { cn } from "@/lib/utils";

export function SiteHeader() {
  const pathname = usePathname();
  const { t, brandName, locale } = useStorefrontCopy();
  const [scrolled, setScrolled] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [query, setQuery] = useState("");
  const searchInputRef = useRef<HTMLInputElement>(null);
  const searchTriggerRef = useRef<HTMLButtonElement>(null);
  const searchTitleId = useId();
  const isHome = pathname === "/";

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    if (!searchOpen) return;
    searchInputRef.current?.focus();
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        setSearchOpen(false);
        searchTriggerRef.current?.focus();
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [searchOpen]);

  useEffect(() => {
    if (!mobileOpen) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === "Escape") setMobileOpen(false);
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [mobileOpen]);

  function closeOverlays() {
    setMobileOpen(false);
    setSearchOpen(false);
  }

  const solid = scrolled || !isHome || mobileOpen;
  const suggestions = searchSuggestions[locale];

  return (
    <>
      <a
        href="#storefront-main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[80] focus:rounded-lg focus:bg-card focus:px-3 focus:py-2 focus:text-sm"
      >
        {t.nav.skipToContent}
      </a>
      <header
        className={cn(
          "sticky top-0 z-50 transition-[background-color,border-color,backdrop-filter] duration-[var(--duration-control)]",
          solid
            ? "border-b border-border/60 bg-[color-mix(in_oklab,var(--surface-light)_92%,transparent)] backdrop-blur-xl"
            : "border-b border-transparent bg-transparent",
        )}
      >
        <div className="sf-container-wide flex h-16 items-center justify-between gap-4 lg:h-[4.25rem]">
          <div className="flex items-center gap-2 lg:gap-3">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className={cn(
                "lg:hidden",
                !solid && isHome && "text-warm-bone text-[#eee9de] hover:bg-white/10",
              )}
              aria-expanded={mobileOpen}
              aria-controls="storefront-mobile-nav"
              aria-label={mobileOpen ? t.nav.closeMenu : t.nav.openMenu}
              onClick={() => setMobileOpen((open) => !open)}
            >
              {mobileOpen ? <X /> : <Menu />}
            </Button>
            <Link
              href="/"
              className={cn(
                "sf-display text-xl tracking-tight no-underline sm:text-2xl",
                solid || !isHome ? "text-foreground" : "text-[#eee9de]",
              )}
            >
              {brandName}
            </Link>
          </div>

          <nav
            aria-label="Primary"
            className="hidden items-center gap-1 xl:flex"
          >
            {primaryNav.slice(0, 6).map((item) => (
              <Link
                key={item.id}
                href={item.href}
                className={cn(
                  "rounded-md px-2.5 py-2 text-sm font-medium no-underline transition-colors",
                  solid || !isHome
                    ? "text-foreground/80 hover:bg-muted hover:text-foreground"
                    : "text-[#eee9de]/80 hover:bg-white/10 hover:text-[#eee9de]",
                  pathname.startsWith(item.href) && "text-foreground",
                )}
              >
                {t.nav[item.labelKey]}
              </Link>
            ))}
          </nav>

          <div className="flex items-center gap-1 sm:gap-2">
            <Button
              ref={searchTriggerRef}
              type="button"
              variant="ghost"
              size="icon"
              aria-expanded={searchOpen}
              aria-controls="storefront-search"
              aria-label={t.nav.search}
              className={cn(
                !solid && isHome && "text-[#eee9de] hover:bg-white/10",
              )}
              onClick={() => setSearchOpen(true)}
            >
              <Search />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              disabled
              aria-disabled="true"
              title={t.nav.wishlistSoon}
              className={cn(
                !solid && isHome && "text-[#eee9de]/50",
              )}
            >
              <Heart />
              <span className="sr-only">{t.nav.wishlistSoon}</span>
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              disabled
              aria-disabled="true"
              title={t.nav.cartSoon}
              className={cn(
                !solid && isHome && "text-[#eee9de]/50",
              )}
            >
              <ShoppingBag />
              <span className="sr-only">{t.nav.cartSoon}</span>
            </Button>
            <Button
              asChild
              variant="ghost"
              size="icon"
              className={cn(
                !solid && isHome && "text-[#eee9de] hover:bg-white/10",
              )}
            >
              <Link href="/account" aria-label={t.nav.account}>
                <User />
              </Link>
            </Button>
            <div className={cn(!solid && isHome && "[&_button]:text-[#eee9de]")}>
              <SiteControls tone={solid || !isHome ? "light" : "dark"} />
            </div>
          </div>
        </div>

        {mobileOpen ? (
          <nav
            id="storefront-mobile-nav"
            aria-label="Mobile"
            className="border-t border-border/50 bg-surface-light px-4 py-4 lg:hidden"
          >
            <ul className="flex flex-col gap-1">
              {primaryNav.map((item) => (
                <li key={item.id}>
                  <Link
                    href={item.href}
                    className="block rounded-lg px-3 py-3 text-sm font-medium text-foreground no-underline hover:bg-muted"
                    onClick={closeOverlays}
                  >
                    {t.nav[item.labelKey]}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        ) : null}
      </header>

      {searchOpen ? (
        <div
          id="storefront-search"
          role="dialog"
          aria-modal="true"
          aria-labelledby={searchTitleId}
          className="fixed inset-0 z-[60] bg-[color-mix(in_oklab,var(--night-forest)_72%,transparent)] p-4 backdrop-blur-sm sm:p-8"
          onClick={() => {
            setSearchOpen(false);
            searchTriggerRef.current?.focus();
          }}
        >
          <div
            className="mx-auto mt-8 max-w-2xl rounded-2xl border border-border/70 bg-card p-5 shadow-xl sm:mt-16 sm:p-6"
            onClick={(event) => event.stopPropagation()}
          >
            <div className="mb-4 flex items-center justify-between gap-3">
              <h2 id={searchTitleId} className="text-lg font-semibold">
                {t.nav.search}
              </h2>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={t.nav.closeMenu}
                onClick={() => {
                  setSearchOpen(false);
                  searchTriggerRef.current?.focus();
                }}
              >
                <X />
              </Button>
            </div>
            <Input
              ref={searchInputRef}
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder={t.nav.search}
              aria-label={t.nav.search}
            />
            <div className="mt-5 grid gap-4 sm:grid-cols-3">
              <div>
                <p className="sf-label text-muted-foreground">Products</p>
                <ul className="mt-2 space-y-1 text-sm">
                  {suggestions.products.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
              <div>
                <p className="sf-label text-muted-foreground">Categories</p>
                <ul className="mt-2 space-y-1 text-sm">
                  {suggestions.categories.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
              <div>
                <p className="sf-label text-muted-foreground">Guides</p>
                <ul className="mt-2 space-y-1 text-sm">
                  {suggestions.guides.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
            </div>
            <p className="mt-4 text-xs text-muted-foreground">
              Search prototype — Meilisearch connects later.
            </p>
          </div>
        </div>
      ) : null}
    </>
  );
}
