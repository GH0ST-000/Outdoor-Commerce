import { apiRequest, ApiClientError } from "@/lib/api-client";
import { createCartIdempotencyKey } from "@/features/cart/lib/idempotency";
import { isPublicCart } from "@/features/cart/lib/empty-cart";
import type {
  AddCartItemPayload,
  CartEnvelope,
  PublicCart,
  UpdateCartItemPayload,
} from "@/features/cart/types";

function mutationHeaders(idempotencyKey: string): Record<string, string> {
  return { "Idempotency-Key": idempotencyKey };
}

async function unwrap(
  path: string,
  options: Parameters<typeof apiRequest<CartEnvelope>>[1],
): Promise<PublicCart> {
  const envelope = await apiRequest<CartEnvelope>(path, options);
  return envelope.data;
}

export async function getCart(): Promise<PublicCart> {
  return unwrap("/v1/cart", { method: "GET" });
}

export async function addCartItem(
  payload: AddCartItemPayload,
  idempotencyKey = createCartIdempotencyKey(),
): Promise<PublicCart> {
  return unwrap("/v1/cart/items", {
    method: "POST",
    body: payload,
    headers: mutationHeaders(idempotencyKey),
  });
}

export async function updateCartItem(
  itemId: string,
  payload: UpdateCartItemPayload,
  idempotencyKey = createCartIdempotencyKey(),
): Promise<PublicCart> {
  return unwrap(`/v1/cart/items/${itemId}`, {
    method: "PATCH",
    body: payload,
    headers: mutationHeaders(idempotencyKey),
  });
}

export async function removeCartItem(
  itemId: string,
  cartVersion?: number,
  idempotencyKey = createCartIdempotencyKey(),
): Promise<PublicCart> {
  return unwrap(`/v1/cart/items/${itemId}`, {
    method: "DELETE",
    body: cartVersion === undefined ? {} : { cart_version: cartVersion },
    headers: mutationHeaders(idempotencyKey),
  });
}

export async function clearCart(
  cartVersion?: number,
  idempotencyKey = createCartIdempotencyKey(),
): Promise<PublicCart> {
  return unwrap("/v1/cart", {
    method: "DELETE",
    body: cartVersion === undefined ? {} : { cart_version: cartVersion },
    headers: mutationHeaders(idempotencyKey),
  });
}

export async function mergeCart(
  cartVersion?: number,
  idempotencyKey = createCartIdempotencyKey(),
): Promise<PublicCart> {
  return unwrap("/v1/cart/merge", {
    method: "POST",
    body: cartVersion === undefined ? {} : { cart_version: cartVersion },
    headers: mutationHeaders(idempotencyKey),
  });
}

export function cartFromConflict(error: unknown): PublicCart | null {
  if (
    !(error instanceof ApiClientError) ||
    error.code !== "CART_VERSION_CONFLICT"
  ) {
    return null;
  }

  const cart = error.conflictCart;
  return isPublicCart(cart) ? cart : null;
}
