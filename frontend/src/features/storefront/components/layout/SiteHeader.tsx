"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Menu, Search, ShoppingBag, Heart, User, X } from "lucide-react";
import { SiteControls } from "@/components/site-controls";
import { Button } from "@/components/ui/button";
import { primaryNav } from "@/features/storefront/fixtures/demo-catalog";
import { useStorefrontCopy } from "@/features/storefront/hooks/use-storefront-copy";
import { SearchOverlay } from "@/features/search/components/SearchOverlay";
import { MiniCartDrawer } from "@/features/cart/components/MiniCartDrawer";
import { useCartCopy } from "@/features/cart/copy";
import { useCart } from "@/features/cart/providers/CartProvider";
import { isCartEnabled } from "@/features/product-detail/cart/cart-gateway";
import { cn } from "@/lib/utils";

export function SiteHeader() {
  const pathname = usePathname();
  const { t, brandName } = useStorefrontCopy();
  const cartCopy = useCartCopy();
  const cart = useCart();
  const cartEnabled = isCartEnabled();
  const cartTriggerRef = useRef<HTMLButtonElement>(null);
  const [scrolled, setScrolled] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const searchInputRef = useRef<HTMLInputElement>(null);
  const searchTriggerRef = useRef<HTMLButtonElement>(null);
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

  return (
    <>
      <a
        href="#storefront-main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[var(--z-critical)] focus:rounded-lg focus:bg-card focus:px-3 focus:py-2 focus:text-sm"
      >
        {t.nav.skipToContent}
      </a>
      <header
        className={cn(
          "sticky top-0 z-[var(--z-header)] transition-[background-color,border-color,backdrop-filter,box-shadow] duration-[var(--duration-control)] ease-[var(--ease-standard)]",
          solid
            ? "border-b border-border/50 bg-[var(--header-background)] shadow-[0_10px_30px_-24px_rgba(0,0,0,0.55)] backdrop-blur-xl"
            : "border-b border-transparent bg-transparent",
        )}
      >
        <div className="sf-container-wide flex h-14 min-w-0 items-center justify-between gap-2 sm:h-16 sm:gap-3 lg:h-[4.25rem]">
          <div className="flex min-w-0 items-center gap-1.5 sm:gap-2 lg:gap-3">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              className={cn(
                "shrink-0 lg:hidden",
                !solid &&
                  isHome &&
                  "text-[var(--text-inverse)] hover:bg-white/10",
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
                "sf-display truncate text-lg tracking-tight no-underline sm:text-xl md:text-2xl",
                solid || !isHome
                  ? "text-foreground"
                  : "text-[var(--text-inverse)]",
              )}
            >
              {brandName}
            </Link>
          </div>

          <nav
            aria-label="Primary"
            className="hidden items-center gap-0.5 xl:flex"
          >
            {primaryNav.slice(0, 6).map((item) => (
              <Link
                key={item.id}
                href={item.href}
                className={cn(
                  "sf-nav-label rounded-md px-2 py-2 text-[0.8125rem] font-medium no-underline transition-colors",
                  solid || !isHome
                    ? "text-foreground/80 hover:bg-muted hover:text-foreground"
                    : "text-[var(--text-inverse)]/80 hover:bg-white/10 hover:text-[var(--text-inverse)]",
                  pathname.startsWith(item.href) &&
                    (solid || !isHome
                      ? "bg-muted text-foreground"
                      : "bg-white/10 text-[var(--text-inverse)]"),
                )}
              >
                {t.nav[item.labelKey]}
              </Link>
            ))}
          </nav>

          <div className="flex shrink-0 items-center gap-0.5 sm:gap-1.5">
            <Button
              ref={searchTriggerRef}
              type="button"
              variant="ghost"
              size="icon"
              aria-expanded={searchOpen}
              aria-controls="storefront-search"
              aria-label={t.nav.search}
              className={cn(
                "size-9 sm:size-10",
                !solid &&
                  isHome &&
                  "text-[var(--text-inverse)] hover:bg-white/10",
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
                "hidden size-9 sm:inline-flex sm:size-10",
                !solid && isHome && "text-[var(--text-inverse)]/50",
              )}
            >
              <Heart />
              <span className="sr-only">{t.nav.wishlistSoon}</span>
            </Button>
            {cartEnabled ? (
              <Button
                ref={cartTriggerRef}
                type="button"
                variant="ghost"
                size="icon"
                aria-expanded={cart.drawerOpen}
                aria-controls="mini-cart-title"
                aria-label={`${cartCopy.open}, ${cart.cart.item_count} ${cartCopy.items}`}
                className={cn(
                  "relative size-9 sm:size-10",
                  !solid &&
                    isHome &&
                    "text-[var(--text-inverse)] hover:bg-white/10",
                )}
                onClick={() => cart.openDrawer()}
              >
                <ShoppingBag />
                {cart.cart.item_count > 0 ? (
                  <span className="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-[var(--button-primary-background)] px-1 text-[0.625rem] font-semibold text-[var(--button-primary-foreground)]">
                    {cart.cart.item_count > 99 ? "99+" : cart.cart.item_count}
                    <span className="sr-only">{cartCopy.badge}</span>
                  </span>
                ) : null}
              </Button>
            ) : (
              <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled
                aria-disabled="true"
                title={t.nav.cartSoon}
                className={cn(
                  "hidden size-9 sm:inline-flex sm:size-10",
                  !solid && isHome && "text-[var(--text-inverse)]/50",
                )}
              >
                <ShoppingBag />
                <span className="sr-only">{t.nav.cartSoon}</span>
              </Button>
            )}
            <Button
              asChild
              variant="ghost"
              size="icon"
              className={cn(
                "size-9 sm:size-10",
                !solid &&
                  isHome &&
                  "text-[var(--text-inverse)] hover:bg-white/10",
              )}
            >
              <Link href="/account" aria-label={t.nav.account}>
                <User />
              </Link>
            </Button>
            <div
              className={cn(
                !solid && isHome && "[&_button]:text-[var(--text-inverse)]",
              )}
            >
              <SiteControls tone="dark" />
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

      <SearchOverlay
        open={searchOpen}
        onClose={() => {
          setSearchOpen(false);
          searchTriggerRef.current?.focus();
        }}
        inputRef={searchInputRef}
      />
      {cartEnabled ? <MiniCartDrawer triggerRef={cartTriggerRef} /> : null}
    </>
  );
}
