"use client";

import { useEffect, useRef } from "react";
import Link from "next/link";
import {
  Drawer,
  DrawerContent,
  DrawerDescription,
  DrawerTitle,
} from "@/components/ui/drawer";
import { Button } from "@/components/ui/button";
import { CartIssueList } from "@/features/cart/components/CartIssueList";
import { CartLineRow } from "@/features/cart/components/CartLineRow";
import { CartSummary } from "@/features/cart/components/CartSummary";
import { useCartCopy } from "@/features/cart/copy";
import { useCart } from "@/features/cart/providers/CartProvider";
import { isCartEnabled } from "@/features/product-detail/cart/cart-gateway";

export function MiniCartDrawer({
  triggerRef,
}: {
  triggerRef: React.RefObject<HTMLButtonElement | null>;
}) {
  const copy = useCartCopy();
  const cart = useCart();
  const closeRef = useRef<HTMLButtonElement>(null);
  const { refresh, status, drawerOpen: open } = cart;

  useEffect(() => {
    if (open) {
      closeRef.current?.focus();
    }
  }, [open]);

  useEffect(() => {
    if (open && (status === "idle" || status === "error")) {
      void refresh();
    }
  }, [open, status, refresh]);

  return (
    <Drawer
      open={open}
      onOpenChange={(next) => {
        if (next) {
          cart.openDrawer();
          return;
        }
        cart.closeDrawer();
        window.setTimeout(() => {
          triggerRef.current?.focus();
        }, 0);
      }}
    >
      <DrawerContent
        side="right"
        className="w-[min(100%,26rem)] pb-[var(--space-safe-bottom)]"
        aria-labelledby="mini-cart-title"
      >
        <div className="flex items-center justify-between gap-3 border-b border-border/60 px-4 py-3">
          <div>
            <DrawerTitle id="mini-cart-title" className="text-lg font-semibold">
              {copy.title}
            </DrawerTitle>
            <DrawerDescription className="text-sm text-muted-foreground">
              {cart.cart.item_count} {copy.items}
            </DrawerDescription>
          </div>
          <Button
            ref={closeRef}
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => cart.closeDrawer()}
          >
            {copy.close}
          </Button>
        </div>
        <div className="flex-1 overflow-y-auto px-4">
          {(cart.status === "loading" ||
            (cart.status === "idle" && isCartEnabled())) &&
          cart.cart.item_count === 0 ? (
            <p className="py-8 text-sm text-muted-foreground">{copy.loading}</p>
          ) : null}
          {cart.status === "error" ? (
            <div className="py-8">
              <p className="text-sm text-muted-foreground">{copy.loadError}</p>
              <Button
                type="button"
                variant="ghost"
                className="mt-2"
                onClick={() => void cart.refresh()}
              >
                {copy.retry}
              </Button>
            </div>
          ) : null}
          {cart.cart.item_count === 0 && cart.status === "ready" ? (
            <div className="py-10 text-center">
              <p className="font-semibold">{copy.emptyTitle}</p>
              <p className="mt-1 text-sm text-muted-foreground">
                {copy.emptyHint}
              </p>
              <Button asChild variant="primary" className="mt-4">
                <Link href="/catalog" onClick={() => cart.closeDrawer()}>
                  {copy.shopCatalog}
                </Link>
              </Button>
            </div>
          ) : null}
          <CartIssueList issues={cart.cart.issues} />
          {cart.cart.items.map((line) => (
            <CartLineRow
              key={line.id}
              line={line}
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
        {cart.cart.item_count > 0 ? (
          <div className="px-4 pb-4">
            <CartSummary cart={cart.cart} />
            <Button
              asChild
              variant="ghost"
              size="sm"
              fullWidth
              className="mt-2"
            >
              <Link href="/catalog" onClick={() => cart.closeDrawer()}>
                {copy.continueShopping}
              </Link>
            </Button>
          </div>
        ) : null}
      </DrawerContent>
    </Drawer>
  );
}
