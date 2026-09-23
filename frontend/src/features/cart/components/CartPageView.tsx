"use client";

import Link from "next/link";
import { Button } from "@/components/ui/button";
import { CartIssueList } from "@/features/cart/components/CartIssueList";
import { CartLineRow } from "@/features/cart/components/CartLineRow";
import { CartSummary } from "@/features/cart/components/CartSummary";
import { useCartCopy } from "@/features/cart/copy";
import { useCart } from "@/features/cart/providers/CartProvider";
import { isCartEnabled } from "@/features/product-detail/cart/cart-gateway";

export function CartPageView() {
  const copy = useCartCopy();
  const cart = useCart();

  if (
    (cart.status === "loading" ||
      (cart.status === "idle" && isCartEnabled())) &&
    cart.cart.item_count === 0
  ) {
    return (
      <div className="sf-container sf-section">
        <p>{copy.loading}</p>
      </div>
    );
  }

  if (cart.status === "error" && cart.cart.item_count === 0) {
    return (
      <div className="sf-container sf-section">
        <h1 className="sf-display text-3xl">{copy.title}</h1>
        <p className="mt-3 text-muted-foreground">{copy.loadError}</p>
        <Button
          type="button"
          className="mt-4"
          onClick={() => void cart.refresh()}
        >
          {copy.retry}
        </Button>
      </div>
    );
  }

  if (cart.cart.item_count === 0) {
    return (
      <div className="sf-container sf-section">
        <h1 className="sf-display text-3xl">{copy.title}</h1>
        <p className="mt-3 text-lg font-semibold">{copy.emptyTitle}</p>
        <p className="mt-1 max-w-xl text-muted-foreground">{copy.emptyHint}</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Button asChild variant="primary">
            <Link href="/catalog">{copy.shopCatalog}</Link>
          </Button>
          <Button asChild variant="ghost">
            <Link href="/">{copy.shopHome}</Link>
          </Button>
          <Button asChild variant="ghost">
            <Link href="/search">{copy.search}</Link>
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="sf-container sf-section">
      <header className="mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="sf-display text-3xl md:text-4xl">{copy.title}</h1>
          <p className="mt-1 text-muted-foreground">
            {cart.cart.item_count} {copy.items}
          </p>
        </div>
        <Button type="button" variant="ghost" onClick={() => void cart.clear()}>
          {copy.clear}
        </Button>
      </header>
      <CartIssueList issues={cart.cart.issues} />
      <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div>
          {cart.cart.items.map((line) => (
            <CartLineRow
              key={line.id}
              line={line}
              layout="page"
              pending={cart.pendingLineIds.includes(line.id)}
              onQuantityChange={(quantity) => {
                void cart.updateQuantity(line.id, quantity);
              }}
              onRemove={() => {
                void cart.removeItem(line.id);
              }}
            />
          ))}
        </div>
        <div className="max-lg:sticky max-lg:bottom-0 max-lg:z-10 max-lg:border-t max-lg:border-border/60 max-lg:bg-background/95 max-lg:pb-[var(--space-safe-bottom)] max-lg:pt-3">
          <CartSummary cart={cart.cart} sticky showViewCart={false} />
          <Button asChild variant="ghost" size="sm" fullWidth className="mt-2">
            <Link href="/catalog">{copy.continueShopping}</Link>
          </Button>
        </div>
      </div>
    </div>
  );
}
