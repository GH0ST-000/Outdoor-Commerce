import { emptyCart } from "@/features/cart/lib/empty-cart";
import type { PublicCart } from "@/features/cart/types";

export const CART_BROADCAST_CHANNEL = "outdoor-cart";

type CartStatus = "idle" | "loading" | "ready" | "error";

export type CartStoreSnapshot = {
  cart: PublicCart;
  status: CartStatus;
  error: string | null;
  drawerOpen: boolean;
  announcement: string | null;
  pendingLineIds: string[];
  addPending: boolean;
};

type Listener = () => void;

const listeners = new Set<Listener>();

let snapshot: CartStoreSnapshot = {
  cart: emptyCart(),
  status: "idle",
  error: null,
  drawerOpen: false,
  announcement: null,
  pendingLineIds: [],
  addPending: false,
};

function emit(): void {
  listeners.forEach((listener) => listener());
}

export function getCartSnapshot(): CartStoreSnapshot {
  return snapshot;
}

export function subscribeCartStore(listener: Listener): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function setCartSnapshot(partial: Partial<CartStoreSnapshot>): void {
  snapshot = { ...snapshot, ...partial };
  emit();
}

export function replaceCart(cart: PublicCart): void {
  snapshot = {
    ...snapshot,
    cart,
    status: "ready",
    error: null,
  };
  emit();
}

export function setDrawerOpen(open: boolean): void {
  snapshot = { ...snapshot, drawerOpen: open };
  emit();
}

export function setAnnouncement(announcement: string | null): void {
  snapshot = { ...snapshot, announcement };
  emit();
}

export function setLinePending(itemId: string, pending: boolean): void {
  const next = new Set(snapshot.pendingLineIds);
  if (pending) {
    next.add(itemId);
  } else {
    next.delete(itemId);
  }
  snapshot = { ...snapshot, pendingLineIds: Array.from(next) };
  emit();
}

export function setAddPending(pending: boolean): void {
  snapshot = { ...snapshot, addPending: pending };
  emit();
}

export function broadcastCartInvalidated(): void {
  if (
    typeof window === "undefined" ||
    typeof BroadcastChannel === "undefined"
  ) {
    return;
  }

  try {
    const channel = new BroadcastChannel(CART_BROADCAST_CHANNEL);
    channel.postMessage({ type: "cart-invalidated" });
    channel.close();
  } catch {
    // BroadcastChannel is optional.
  }
}

export function resetCartStoreForTests(): void {
  snapshot = {
    cart: emptyCart(),
    status: "idle",
    error: null,
    drawerOpen: false,
    announcement: null,
    pendingLineIds: [],
    addPending: false,
  };
  emit();
}
