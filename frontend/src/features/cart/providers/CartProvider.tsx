"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useSyncExternalStore,
  type ReactNode,
} from "react";
import { isCartEnabled } from "@/features/product-detail/cart/cart-gateway";
import { useCartCopy } from "@/features/cart/copy";
import {
  CART_BROADCAST_CHANNEL,
  getCartSnapshot,
  setAnnouncement,
  subscribeCartStore,
  type CartStoreSnapshot,
} from "@/features/cart/state/cart-store";
import {
  addItemToCart,
  clearActiveCart,
  closeMiniCart,
  openMiniCart,
  refreshCart,
  removeItemFromCart,
  syncCartAfterAuthentication,
  updateItemQuantity,
} from "@/features/cart/state/cart-actions";

type CartContextValue = CartStoreSnapshot & {
  refresh: () => Promise<void>;
  openDrawer: () => void;
  closeDrawer: () => void;
  addItem: typeof addItemToCart;
  updateQuantity: typeof updateItemQuantity;
  removeItem: typeof removeItemFromCart;
  clear: typeof clearActiveCart;
  mergeAfterAuth: typeof syncCartAfterAuthentication;
};

const CartContext = createContext<CartContextValue | null>(null);

function useCartStore(): CartStoreSnapshot {
  return useSyncExternalStore(
    subscribeCartStore,
    getCartSnapshot,
    getCartSnapshot,
  );
}

export function CartProvider({ children }: { children: ReactNode }) {
  const snapshot = useCartStore();
  const copy = useCartCopy();

  const liveMessage =
    snapshot.announcement === "added"
      ? copy.added
      : snapshot.announcement === "conflict"
        ? copy.versionConflict
        : snapshot.announcement === "merge-adjusted"
          ? copy.mergeAdjusted
          : snapshot.announcement === "adding"
            ? copy.adding
            : null;

  useEffect(() => {
    if (!liveMessage) {
      return;
    }
    const timer = window.setTimeout(() => setAnnouncement(null), 4000);
    return () => window.clearTimeout(timer);
  }, [liveMessage]);

  const refresh = useCallback(async () => {
    if (!isCartEnabled()) {
      return;
    }
    await refreshCart().catch(() => undefined);
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  useEffect(() => {
    if (
      typeof window === "undefined" ||
      typeof BroadcastChannel === "undefined"
    ) {
      return;
    }

    const channel = new BroadcastChannel(CART_BROADCAST_CHANNEL);
    channel.onmessage = (event: MessageEvent<{ type?: string }>) => {
      if (event.data?.type === "cart-invalidated") {
        void refresh();
      }
    };

    return () => channel.close();
  }, [refresh]);

  const value = useMemo<CartContextValue>(
    () => ({
      ...snapshot,
      refresh,
      openDrawer: openMiniCart,
      closeDrawer: closeMiniCart,
      addItem: addItemToCart,
      updateQuantity: updateItemQuantity,
      removeItem: removeItemFromCart,
      clear: clearActiveCart,
      mergeAfterAuth: syncCartAfterAuthentication,
    }),
    [snapshot, refresh],
  );

  return (
    <CartContext.Provider value={value}>
      <div className="sr-only" aria-live="polite" aria-atomic="true">
        {liveMessage}
      </div>
      {children}
    </CartContext.Provider>
  );
}

export function useCart(): CartContextValue {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error("useCart must be used within CartProvider");
  }
  return context;
}
