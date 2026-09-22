import { apiRequest } from "@/lib/api-client";
import { createCartIdempotencyKey } from "@/features/cart/lib/idempotency";
import type { Order, OrderEnvelope } from "@/features/orders/types";

const IDEMPOTENCY_PREFIX = "outdoor-order-idempotency:";

function localeHeader(): Record<string, string> {
  if (typeof document === "undefined") {
    return { "X-Locale": "ka" };
  }
  const match = document.cookie.match(/(?:^|; )outdoor-locale=([^;]*)/);
  const raw = match ? decodeURIComponent(match[1]) : "ka";
  return { "X-Locale": raw === "en" ? "en" : "ka" };
}

export function orderIdempotencyStorageKey(quoteId: string): string {
  return `${IDEMPOTENCY_PREFIX}${quoteId}`;
}

export function rememberOrderIdempotencyKey(quoteId: string): string {
  if (typeof window === "undefined") {
    return createCartIdempotencyKey();
  }
  const stored = window.sessionStorage.getItem(
    orderIdempotencyStorageKey(quoteId),
  );
  if (stored) {
    return stored;
  }
  const key = createCartIdempotencyKey();
  window.sessionStorage.setItem(orderIdempotencyStorageKey(quoteId), key);
  return key;
}

export function clearOrderIdempotencyKey(quoteId: string): void {
  if (typeof window === "undefined") {
    return;
  }
  window.sessionStorage.removeItem(orderIdempotencyStorageKey(quoteId));
}

export async function createOrder(input: {
  checkoutSessionId: string;
  quoteId: string;
  checkoutVersion: number;
  idempotencyKey?: string;
}): Promise<Order> {
  const key =
    input.idempotencyKey ?? rememberOrderIdempotencyKey(input.quoteId);
  const envelope = await apiRequest<OrderEnvelope>("/v1/orders", {
    method: "POST",
    body: {
      checkout_session_id: input.checkoutSessionId,
      quote_id: input.quoteId,
      checkout_version: input.checkoutVersion,
    },
    headers: { "Idempotency-Key": key, ...localeHeader() },
  });
  return envelope.data;
}

export async function getOrder(orderPublicId: string): Promise<Order> {
  const envelope = await apiRequest<OrderEnvelope>(
    `/v1/orders/${orderPublicId}`,
    { method: "GET", headers: localeHeader() },
  );
  return envelope.data;
}

export async function cancelOrder(orderPublicId: string): Promise<Order> {
  const envelope = await apiRequest<OrderEnvelope>(
    `/v1/orders/${orderPublicId}/cancel`,
    {
      method: "POST",
      body: {},
      headers: {
        "Idempotency-Key": createCartIdempotencyKey(),
        ...localeHeader(),
      },
    },
  );
  return envelope.data;
}
