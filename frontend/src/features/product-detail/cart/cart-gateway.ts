import type { CartGateway } from "@/features/product-detail/types";

/**
 * Day 15 cart boundary. Production keeps this disabled until Day 17
 * implements a real gateway that revalidates price and inventory on the server.
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

export function getCartGateway(): CartGateway {
  return disabledCartGateway;
}
