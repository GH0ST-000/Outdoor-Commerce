import type { CartGateway } from "@/features/product-detail/types";
import { addItemToCart } from "@/features/cart/state/cart-actions";

/**
 * Day 17 cart boundary. Laravel remains authoritative for price, stock, and totals.
 */
export function isCartEnabled(): boolean {
  return process.env.NEXT_PUBLIC_CART_ENABLED === "true";
}

export class CartNotImplementedError extends Error {
  readonly code = "CART_NOT_IMPLEMENTED";

  constructor() {
    super("Cart is not implemented.");
    this.name = "CartNotImplementedError";
  }
}

export const disabledCartGateway: CartGateway = {
  async addToCart(): Promise<void> {
    throw new CartNotImplementedError();
  },
};

export const liveCartGateway: CartGateway = {
  async addToCart(command): Promise<void> {
    await addItemToCart({
      variant_id: command.product_variant_id,
      product_id: command.product_id,
      quantity: command.quantity,
    });
  },
};

export function getCartGateway(): CartGateway {
  return isCartEnabled() ? liveCartGateway : disabledCartGateway;
}
