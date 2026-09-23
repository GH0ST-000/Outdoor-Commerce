import { apiRequest } from "@/lib/api-client";
import { createCartIdempotencyKey } from "@/features/cart/lib/idempotency";
import type {
  PaymentAttempt,
  PaymentAttemptEnvelope,
  PaymentMethod,
  PaymentMethodsEnvelope,
} from "@/features/payments/types";

const IDEMPOTENCY_PREFIX = "outdoor-payment-idempotency:";

function localeHeader(): Record<string, string> {
  if (typeof document === "undefined") {
    return { "X-Locale": "ka" };
  }
  const match = document.cookie.match(/(?:^|; )outdoor-locale=([^;]*)/);
  const raw = match ? decodeURIComponent(match[1]) : "ka";
  return { "X-Locale": raw === "en" ? "en" : "ka" };
}

export function paymentIdempotencyStorageKey(
  orderId: string,
  methodCode: string,
): string {
  return `${IDEMPOTENCY_PREFIX}${orderId}:${methodCode}`;
}

export function rememberPaymentIdempotencyKey(
  orderId: string,
  methodCode: string,
): string {
  if (typeof window === "undefined") {
    return createCartIdempotencyKey();
  }
  const stored = window.sessionStorage.getItem(
    paymentIdempotencyStorageKey(orderId, methodCode),
  );
  if (stored) {
    return stored;
  }
  const key = createCartIdempotencyKey();
  window.sessionStorage.setItem(
    paymentIdempotencyStorageKey(orderId, methodCode),
    key,
  );
  return key;
}

export async function listPaymentMethods(
  orderPublicId: string,
): Promise<PaymentMethod[]> {
  const envelope = await apiRequest<PaymentMethodsEnvelope>(
    `/v1/payment-methods?order_id=${encodeURIComponent(orderPublicId)}`,
    { method: "GET", headers: localeHeader() },
  );
  return envelope.data;
}

export async function createPaymentAttempt(input: {
  orderPublicId: string;
  paymentMethodCode: string;
  orderVersion?: number;
}): Promise<PaymentAttempt> {
  const key = rememberPaymentIdempotencyKey(
    input.orderPublicId,
    input.paymentMethodCode,
  );
  const envelope = await apiRequest<PaymentAttemptEnvelope>(
    `/v1/orders/${input.orderPublicId}/payment-attempts`,
    {
      method: "POST",
      body: {
        payment_method_code: input.paymentMethodCode,
        order_version: input.orderVersion,
      },
      headers: { "Idempotency-Key": key, ...localeHeader() },
    },
  );
  if (!envelope.data) {
    throw new Error("Payment attempt was not created.");
  }
  return envelope.data;
}

export async function getPaymentAttempt(
  attemptPublicId: string,
): Promise<PaymentAttempt> {
  const envelope = await apiRequest<PaymentAttemptEnvelope>(
    `/v1/payment-attempts/${attemptPublicId}`,
    { method: "GET", headers: localeHeader() },
  );
  if (!envelope.data) {
    throw new Error("Payment attempt was not found.");
  }
  return envelope.data;
}

export async function getCurrentPaymentAttempt(
  orderPublicId: string,
): Promise<PaymentAttempt | null> {
  const envelope = await apiRequest<PaymentAttemptEnvelope>(
    `/v1/orders/${orderPublicId}/payment-attempts/current`,
    { method: "GET", headers: localeHeader() },
  );
  return envelope.data;
}

export async function cancelPaymentAttempt(
  attemptPublicId: string,
): Promise<PaymentAttempt> {
  const envelope = await apiRequest<PaymentAttemptEnvelope>(
    `/v1/payment-attempts/${attemptPublicId}/cancel`,
    {
      method: "POST",
      body: {},
      headers: {
        "Idempotency-Key": createCartIdempotencyKey(),
        ...localeHeader(),
      },
    },
  );
  if (!envelope.data) {
    throw new Error("Payment attempt was not cancelled.");
  }
  return envelope.data;
}

export async function simulateTestPayment(
  attemptPublicId: string,
  outcome: string,
): Promise<PaymentAttempt> {
  const envelope = await apiRequest<PaymentAttemptEnvelope>(
    `/v1/payments/test/attempts/${attemptPublicId}/simulate`,
    {
      method: "POST",
      body: { outcome },
      headers: localeHeader(),
    },
  );
  if (!envelope.data) {
    throw new Error("Test payment could not be simulated.");
  }
  return envelope.data;
}

export function isSafeRedirectUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    if (parsed.protocol !== "https:" && parsed.protocol !== "http:") {
      return false;
    }
    if (parsed.username !== "" || parsed.password !== "") {
      return false;
    }
    return true;
  } catch {
    return false;
  }
}
