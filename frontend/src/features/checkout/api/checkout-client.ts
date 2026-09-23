import { apiRequest, ApiClientError } from "@/lib/api-client";
import { createCartIdempotencyKey } from "@/features/cart/lib/idempotency";
import type {
  CheckoutEnvelope,
  CheckoutSession,
} from "@/features/checkout/types";

const SESSION_KEY = "outdoor-checkout-session-id";

export function rememberCheckoutSessionId(id: string): void {
  if (typeof window === "undefined") {
    return;
  }
  window.sessionStorage.setItem(SESSION_KEY, id);
}

export function rememberedCheckoutSessionId(): string | null {
  if (typeof window === "undefined") {
    return null;
  }
  return window.sessionStorage.getItem(SESSION_KEY);
}

export function clearRememberedCheckoutSessionId(): void {
  if (typeof window === "undefined") {
    return;
  }
  window.sessionStorage.removeItem(SESSION_KEY);
}

async function unwrap(
  path: string,
  options: Parameters<typeof apiRequest<CheckoutEnvelope>>[1],
): Promise<CheckoutSession> {
  const envelope = await apiRequest<CheckoutEnvelope>(path, options);
  const session = envelope.data.checkout_session;
  if (envelope.data.quote) {
    session.quote = envelope.data.quote;
  }
  rememberCheckoutSessionId(session.id);
  return session;
}

function localeHeader(): Record<string, string> {
  if (typeof document === "undefined") {
    return { "X-Locale": "ka" };
  }
  const match = document.cookie.match(/(?:^|; )outdoor-locale=([^;]*)/);
  const raw = match ? decodeURIComponent(match[1]) : "ka";
  return { "X-Locale": raw === "en" ? "en" : "ka" };
}

function headers(key = createCartIdempotencyKey()): Record<string, string> {
  return { "Idempotency-Key": key, ...localeHeader() };
}

export async function createCheckoutSession(): Promise<CheckoutSession> {
  return unwrap("/v1/checkout/sessions", {
    method: "POST",
    body: {},
    headers: headers(),
  });
}

export async function getCheckoutSession(id: string): Promise<CheckoutSession> {
  return unwrap(`/v1/checkout/sessions/${id}`, {
    method: "GET",
    headers: localeHeader(),
  });
}

export async function updateCheckoutContact(
  id: string,
  payload: Record<string, unknown>,
): Promise<CheckoutSession> {
  return unwrap(`/v1/checkout/sessions/${id}/contact`, {
    method: "PATCH",
    body: payload,
    headers: headers(),
  });
}

export async function updateCheckoutAddress(
  id: string,
  payload: Record<string, unknown>,
): Promise<CheckoutSession> {
  return unwrap(`/v1/checkout/sessions/${id}/address`, {
    method: "PATCH",
    body: payload,
    headers: headers(),
  });
}

export async function updateCheckoutFulfillment(
  id: string,
  payload: Record<string, unknown>,
): Promise<CheckoutSession> {
  return unwrap(`/v1/checkout/sessions/${id}/fulfillment`, {
    method: "PATCH",
    body: payload,
    headers: headers(),
  });
}

export async function createCheckoutQuote(
  id: string,
  checkoutVersion: number,
  cartVersion: number,
): Promise<CheckoutSession> {
  return unwrap(`/v1/checkout/sessions/${id}/quote`, {
    method: "POST",
    body: { checkout_version: checkoutVersion, cart_version: cartVersion },
    headers: headers(),
  });
}

export function checkoutConflictVersion(error: unknown): number | null {
  if (!(error instanceof ApiClientError)) {
    return null;
  }
  const session = error.conflictCheckout;
  if (
    session &&
    typeof session === "object" &&
    "version" in session &&
    typeof session.version === "number"
  ) {
    return session.version;
  }
  return null;
}
