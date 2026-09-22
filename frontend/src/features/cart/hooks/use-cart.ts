"use client";

import { useCallback, useState } from "react";
import { ApiClientError } from "@/lib/api-client";
import { useCart } from "@/features/cart/providers/CartProvider";
import { createCartIdempotencyKey } from "@/features/cart/lib/idempotency";
import type { PublicCart } from "@/features/cart/types";

export function useAddToCart() {
  const cart = useCart();
  const [error, setError] = useState<string | null>(null);

  const add = useCallback(
    async (input: {
      variantId: number;
      quantity: number;
      productId?: number;
      openDrawer?: boolean;
    }): Promise<PublicCart | null> => {
      setError(null);
      try {
        return await cart.addItem(
          {
            variant_id: input.variantId,
            quantity: input.quantity,
            product_id: input.productId,
          },
          {
            openDrawer: input.openDrawer,
            idempotencyKey: createCartIdempotencyKey(),
          },
        );
      } catch (caught) {
        setError(
          caught instanceof ApiClientError
            ? caught.message
            : "The item could not be added.",
        );
        return null;
      }
    },
    [cart],
  );

  return {
    add,
    pending: cart.addPending,
    error,
    cart: cart.cart,
  };
}

export function useUpdateCartItem() {
  const cart = useCart();
  return {
    update: cart.updateQuantity,
    pendingLineIds: cart.pendingLineIds,
  };
}

export function useRemoveCartItem() {
  const cart = useCart();
  return {
    remove: cart.removeItem,
    pendingLineIds: cart.pendingLineIds,
  };
}

export function useClearCart() {
  const cart = useCart();
  return { clear: cart.clear };
}

export function useMergeCart() {
  const cart = useCart();
  return { merge: cart.mergeAfterAuth };
}
