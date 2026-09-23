import { ApiClientError } from "@/lib/api-client";
import {
  addCartItem,
  cartFromConflict,
  clearCart as clearCartRequest,
  getCart,
  mergeCart as mergeCartRequest,
  removeCartItem as removeCartItemRequest,
  updateCartItem as updateCartItemRequest,
} from "@/features/cart/api/cart-client";
import { createCartIdempotencyKey } from "@/features/cart/lib/idempotency";
import {
  broadcastCartInvalidated,
  getCartSnapshot,
  replaceCart,
  setAddPending,
  setAnnouncement,
  setCartSnapshot,
  setDrawerOpen,
  setLinePending,
} from "@/features/cart/state/cart-store";
import type { AddCartItemPayload, PublicCart } from "@/features/cart/types";

let refreshInFlight: Promise<PublicCart> | null = null;
const mutationLocks = new Set<string>();

function applyCanonical(cart: PublicCart): PublicCart {
  replaceCart(cart);
  broadcastCartInvalidated();
  return cart;
}

function applyConflict(error: unknown): PublicCart | null {
  const cart = cartFromConflict(error);
  if (cart) {
    replaceCart(cart);
    return cart;
  }
  return null;
}

export async function refreshCart(): Promise<PublicCart> {
  if (refreshInFlight) {
    return refreshInFlight;
  }

  setCartSnapshot({ status: "loading", error: null });
  refreshInFlight = getCart()
    .then((cart) => {
      replaceCart(cart);
      return cart;
    })
    .catch((error: unknown) => {
      const message =
        error instanceof ApiClientError
          ? error.message
          : "The cart could not be loaded.";
      setCartSnapshot({ status: "error", error: message });
      throw error;
    })
    .finally(() => {
      refreshInFlight = null;
    });

  return refreshInFlight;
}

export async function syncCartAfterAuthentication(
  authenticated: boolean,
): Promise<void> {
  try {
    if (authenticated) {
      const cart = await mergeCartRequest(getCartSnapshot().cart.version);
      applyCanonical(cart);
      const adjusted = cart.issues.some(
        (issue) => issue.code === "QUANTITY_ADJUSTED_ON_MERGE",
      );
      if (adjusted) {
        setAnnouncement("merge-adjusted");
      }
      return;
    }

    await refreshCart();
  } catch {
    await refreshCart().catch(() => undefined);
  }
}

export async function addItemToCart(
  payload: Omit<AddCartItemPayload, "cart_version">,
  options: { openDrawer?: boolean; idempotencyKey?: string } = {},
): Promise<PublicCart> {
  if (getCartSnapshot().addPending) {
    return getCartSnapshot().cart;
  }

  const key = options.idempotencyKey ?? createCartIdempotencyKey();
  setAddPending(true);
  try {
    const cart = await addCartItem(
      { ...payload, cart_version: getCartSnapshot().cart.version },
      key,
    );
    applyCanonical(cart);
    if (options.openDrawer !== false) {
      setDrawerOpen(true);
    }
    setAnnouncement("added");
    return cart;
  } catch (error) {
    const recovered = applyConflict(error);
    if (recovered) {
      setAnnouncement("conflict");
      throw error;
    }
    throw error;
  } finally {
    setAddPending(false);
  }
}

export async function updateItemQuantity(
  itemId: string,
  quantity: number,
): Promise<PublicCart> {
  if (mutationLocks.has(itemId)) {
    return getCartSnapshot().cart;
  }

  mutationLocks.add(itemId);
  setLinePending(itemId, true);
  try {
    const cart = await updateCartItemRequest(itemId, {
      quantity,
      cart_version: getCartSnapshot().cart.version,
    });
    applyCanonical(cart);
    return cart;
  } catch (error) {
    const recovered = applyConflict(error);
    if (recovered) {
      setAnnouncement("conflict");
      throw error;
    }
    throw error;
  } finally {
    mutationLocks.delete(itemId);
    setLinePending(itemId, false);
  }
}

export async function removeItemFromCart(itemId: string): Promise<PublicCart> {
  if (mutationLocks.has(itemId)) {
    return getCartSnapshot().cart;
  }

  mutationLocks.add(itemId);
  setLinePending(itemId, true);
  try {
    const cart = await removeCartItemRequest(
      itemId,
      getCartSnapshot().cart.version,
    );
    applyCanonical(cart);
    return cart;
  } catch (error) {
    const recovered = applyConflict(error);
    if (recovered) {
      setAnnouncement("conflict");
      throw error;
    }
    throw error;
  } finally {
    mutationLocks.delete(itemId);
    setLinePending(itemId, false);
  }
}

export async function clearActiveCart(): Promise<PublicCart> {
  const cart = await clearCartRequest(getCartSnapshot().cart.version);
  applyCanonical(cart);
  return cart;
}

export function openMiniCart(): void {
  setDrawerOpen(true);
}

export function closeMiniCart(): void {
  setDrawerOpen(false);
}
