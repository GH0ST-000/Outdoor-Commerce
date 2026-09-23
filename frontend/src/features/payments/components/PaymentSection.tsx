"use client";

import { useEffect, useState } from "react";
import { ApiClientError } from "@/lib/api-client";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import {
  cancelPaymentAttempt,
  createPaymentAttempt,
  isSafeRedirectUrl,
  listPaymentMethods,
} from "@/features/payments/api/payment-client";
import { usePaymentCopy } from "@/features/payments/copy";
import type { PaymentMethod } from "@/features/payments/types";
import type { Order } from "@/features/orders/types";

export function PaymentSection({
  order,
  localeTag,
  onOrderRefresh,
}: {
  order: Order;
  localeTag: string;
  onOrderRefresh: () => Promise<void>;
}) {
  const copy = usePaymentCopy();
  const [methods, setMethods] = useState<PaymentMethod[]>([]);
  const [selected, setSelected] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    void listPaymentMethods(order.id)
      .then((next) => {
        if (cancelled) {
          return;
        }
        setMethods(next);
        setSelected((current) => current ?? next[0]?.code ?? null);
      })
      .catch(() => {
        if (!cancelled) {
          setMethods([]);
        }
      });
    return () => {
      cancelled = true;
    };
  }, [order.id]);

  const payable = Boolean(order.can_pay || order.can_retry_payment);
  const current = order.current_payment_attempt ?? null;
  const processing =
    current !== null &&
    ["created", "pending", "requires_action", "processing", "unknown"].includes(
      current.status,
    );

  async function onPay() {
    if (!selected || pending) {
      return;
    }
    setPending(true);
    setError(null);
    try {
      const attempt = await createPaymentAttempt({
        orderPublicId: order.id,
        paymentMethodCode: selected,
      });
      const url = attempt.action?.url;
      if (
        attempt.action?.type === "redirect" &&
        url &&
        isSafeRedirectUrl(url)
      ) {
        window.location.assign(url);
        return;
      }
      if (attempt.action?.type === "redirect") {
        setError(copy.invalidRedirect);
        return;
      }
      await onOrderRefresh();
    } catch (caught) {
      setError(
        caught instanceof ApiClientError ? caught.message : copy.loadError,
      );
    } finally {
      setPending(false);
    }
  }

  async function onCancelAttempt() {
    if (!current || pending) {
      return;
    }
    setPending(true);
    try {
      await cancelPaymentAttempt(current.id);
      await onOrderRefresh();
    } catch (caught) {
      setError(
        caught instanceof ApiClientError ? caught.message : copy.loadError,
      );
    } finally {
      setPending(false);
    }
  }

  if (order.payment_status === "paid" || order.status === "confirmed") {
    return (
      <Alert tone="success" title={copy.successTitle}>
        {copy.successBody}
      </Alert>
    );
  }

  if (order.status === "manual_review") {
    return (
      <Alert tone="warning" title={copy.manualReviewTitle}>
        {copy.manualReviewBody}
      </Alert>
    );
  }

  if (!payable && !processing) {
    return null;
  }

  return (
    <section className="mt-8 rounded-[var(--radius-lg)] border border-border p-4">
      <h2 className="text-lg font-semibold">{copy.title}</h2>
      <p className="mt-1 text-sm text-muted-foreground">{copy.explanation}</p>
      <p className="mt-2 text-sm">
        {copy.deadline}:{" "}
        {order.payment_expires_at
          ? new Date(order.payment_expires_at).toLocaleString(localeTag)
          : "—"}
      </p>
      <p className="mt-1 text-base font-semibold">
        <PriceDisplay
          currency={order.totals.currency}
          locale={localeTag}
          amountMinor={order.totals.grand_total_minor}
        />
      </p>
      <p className="mt-2 text-xs text-muted-foreground">{copy.secureNote}</p>
      {error ? (
        <div className="mt-3">
          <Alert tone="danger">{error}</Alert>
        </div>
      ) : null}
      {methods.length === 0 ? (
        <div className="mt-4">
          <Alert tone="info">{copy.noMethods}</Alert>
        </div>
      ) : (
        <fieldset className="mt-4 space-y-2">
          <legend className="text-sm font-medium">{copy.selectMethod}</legend>
          {methods.map((method) => (
            <label
              key={method.code}
              className="flex cursor-pointer items-start gap-3 rounded-lg border border-border px-3 py-2"
            >
              <input
                type="radio"
                name="payment-method"
                value={method.code}
                checked={selected === method.code}
                onChange={() => setSelected(method.code)}
              />
              <span>
                <span className="block font-medium">{method.name}</span>
                <span className="block text-sm text-muted-foreground">
                  {method.description}
                </span>
                {method.development_only ? (
                  <span className="mt-1 inline-block rounded bg-muted px-2 py-0.5 text-xs">
                    {copy.testOnly}
                  </span>
                ) : null}
              </span>
            </label>
          ))}
        </fieldset>
      )}
      <div className="mt-4 flex flex-col gap-2 sm:flex-row">
        {payable && methods.length > 0 ? (
          <Button type="button" disabled={pending} onClick={() => void onPay()}>
            {pending
              ? copy.paying
              : order.can_retry_payment
                ? copy.retry
                : copy.pay}
          </Button>
        ) : null}
        {processing && current ? (
          <Button
            type="button"
            variant="secondary"
            disabled={pending}
            onClick={() => void onCancelAttempt()}
          >
            {copy.cancelAttempt}
          </Button>
        ) : null}
      </div>
    </section>
  );
}
