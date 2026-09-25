"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { ApiClientError } from "@/lib/api-client";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { useLocale } from "@/components/locale-provider";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { useCart } from "@/features/cart/providers/CartProvider";
import { cancelOrder, getOrder } from "@/features/orders/api/order-client";
import {
  fulfillmentStatusLabel,
  orderStatusLabel,
  paymentStatusLabel,
  useOrderCopy,
} from "@/features/orders/copy";
import type { Order } from "@/features/orders/types";
import { PaymentSection } from "@/features/payments/components/PaymentSection";
import { FulfillmentTrackingSection } from "@/features/orders/components/FulfillmentTrackingSection";

export function OrderConfirmationPageView({
  orderPublicId,
}: {
  orderPublicId: string;
}) {
  const copy = useOrderCopy();
  const cart = useCart();
  const { locale } = useLocale();
  const localeTag = locale === "ka" ? "ka-GE" : "en";
  const headingRef = useRef<HTMLHeadingElement>(null);
  const [order, setOrder] = useState<Order | null>(null);
  const [status, setStatus] = useState<
    "loading" | "ready" | "missing" | "error"
  >("loading");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [live, setLive] = useState<string | null>(null);

  const { refresh } = cart;

  const load = useCallback(async () => {
    setStatus("loading");
    try {
      const next = await getOrder(orderPublicId);
      setOrder(next);
      setError(null);
      setStatus("ready");
      void refresh();
    } catch (caught) {
      if (caught instanceof ApiClientError && caught.status === 404) {
        setStatus("missing");
        return;
      }
      setError(copy.loadError);
      setStatus("error");
    }
  }, [copy.loadError, orderPublicId, refresh]);

  const refreshOrder = useCallback(async () => {
    try {
      const next = await getOrder(orderPublicId);
      setOrder(next);
    } catch {
      setError(copy.loadError);
    }
  }, [copy.loadError, orderPublicId]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void load();
    }, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  useEffect(() => {
    headingRef.current?.focus();
  }, [status, order?.status]);

  async function onCancel() {
    if (!order || pending) {
      return;
    }
    setPending(true);
    setError(null);
    try {
      const next = await cancelOrder(order.id);
      setOrder(next);
      setLive(copy.cancelledBody);
      void refresh();
    } catch (caught) {
      setError(
        caught instanceof ApiClientError ? caught.message : copy.loadError,
      );
    } finally {
      setPending(false);
    }
  }

  if (status === "loading") {
    return <p className="p-6">{copy.loading}</p>;
  }

  if (status === "missing") {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <EmptyState title={copy.notFound} />
        <div className="mt-4 text-center">
          <Button asChild>
            <Link href="/catalog">{copy.continueShopping}</Link>
          </Button>
        </div>
      </div>
    );
  }

  if (status === "error" || !order) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <EmptyState title={copy.loadError} />
        <div className="mt-4 text-center">
          <Button type="button" onClick={() => void load()}>
            {copy.retry}
          </Button>
        </div>
      </div>
    );
  }

  const pendingPayment = order.status === "pending_payment";
  const expired = order.status === "expired";
  const cancelled = order.status === "cancelled";
  const deadline =
    order.payment_expires_at !== null
      ? new Date(order.payment_expires_at).toLocaleString(localeTag)
      : null;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 lg:px-8">
      <p className="sr-only" aria-live="polite">
        {live}
      </p>
      <h1
        ref={headingRef}
        tabIndex={-1}
        className="text-3xl font-semibold tracking-tight"
      >
        {expired
          ? copy.expiredTitle
          : cancelled
            ? copy.cancelledTitle
            : copy.confirmationTitle}
      </h1>
      <div className="mt-4 space-y-3">
        {pendingPayment ? (
          <Alert tone="warning" title={copy.pendingTitle}>
            {copy.pendingBody}
          </Alert>
        ) : null}
        {expired ? <Alert tone="danger">{copy.expiredBody}</Alert> : null}
        {cancelled ? <Alert tone="info">{copy.cancelledBody}</Alert> : null}
        {error ? <Alert tone="danger">{error}</Alert> : null}
      </div>

      <dl className="mt-6 grid gap-3 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-muted-foreground">{copy.orderNumber}</dt>
          <dd className="font-semibold">{order.order_number}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{copy.status}</dt>
          <dd>{orderStatusLabel(copy, order.status)}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{copy.paymentStatus}</dt>
          <dd>{paymentStatusLabel(copy, order.payment_status)}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">{copy.fulfillmentStatus}</dt>
          <dd>{fulfillmentStatusLabel(copy, order.fulfillment_status)}</dd>
        </div>
        {deadline && pendingPayment ? (
          <div>
            <dt className="text-muted-foreground">{copy.paymentDeadline}</dt>
            <dd>
              {deadline}
              <p className="text-xs text-muted-foreground">{copy.holdNotice}</p>
            </dd>
          </div>
        ) : null}
      </dl>

      <ul className="mt-8 space-y-4">
        {order.items.map((item) => (
          <li key={item.id} className="flex gap-3">
            {item.media?.url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={item.media.url}
                alt={item.media.alt ?? item.name}
                className="size-16 rounded-lg object-cover"
              />
            ) : (
              <div className="size-16 rounded-lg bg-muted" />
            )}
            <div className="min-w-0 flex-1">
              <p className="font-medium">{item.name}</p>
              <p className="text-sm text-muted-foreground">
                {item.variant_name} · {copy.quantity} {item.quantity}
              </p>
              <PriceDisplay
                currency={item.pricing.currency}
                locale={localeTag}
                amountMinor={item.pricing.line_total_minor}
              />
            </div>
          </li>
        ))}
      </ul>

      <dl className="mt-6 space-y-2 text-sm">
        <div className="flex justify-between gap-3">
          <dt>{copy.subtotal}</dt>
          <dd>
            <PriceDisplay
              currency={order.totals.currency}
              locale={localeTag}
              amountMinor={order.totals.items_subtotal_minor}
            />
          </dd>
        </div>
        {order.totals.discount_total_minor > 0 ? (
          <div className="flex justify-between gap-3">
            <dt>{copy.savings}</dt>
            <dd>
              <PriceDisplay
                currency={order.totals.currency}
                locale={localeTag}
                amountMinor={order.totals.discount_total_minor}
              />
            </dd>
          </div>
        ) : null}
        <div className="flex justify-between gap-3">
          <dt>{copy.shipping}</dt>
          <dd>
            <PriceDisplay
              currency={order.totals.currency}
              locale={localeTag}
              amountMinor={order.totals.delivery_total_minor}
            />
          </dd>
        </div>
        <div className="flex justify-between gap-3 text-base font-semibold">
          <dt>{copy.total}</dt>
          <dd>
            <PriceDisplay
              currency={order.totals.currency}
              locale={localeTag}
              amountMinor={order.totals.grand_total_minor}
            />
          </dd>
        </div>
      </dl>
      <p className="mt-2 text-xs text-muted-foreground">{copy.taxIncluded}</p>

      {pendingPayment ||
      order.status === "payment_processing" ||
      order.status === "confirmed" ||
      order.status === "manual_review" ? (
        <PaymentSection
          order={order}
          localeTag={localeTag}
          onOrderRefresh={async () => {
            await load();
          }}
        />
      ) : null}

      <FulfillmentTrackingSection
        order={order}
        localeTag={localeTag}
        onRefresh={refreshOrder}
      />

      <section className="mt-8 grid gap-4 text-sm sm:grid-cols-2">
        <div>
          <h2 className="font-semibold">{copy.contact}</h2>
          <p>
            {order.contact.first_name} {order.contact.last_name}
          </p>
          <p>{order.contact.email}</p>
        </div>
        {order.fulfillment.pickup_location ? (
          <div>
            <h2 className="font-semibold">{copy.pickup}</h2>
            <p>{order.fulfillment.pickup_location.name}</p>
            <p>{order.fulfillment.pickup_location.address}</p>
          </div>
        ) : order.address ? (
          <div>
            <h2 className="font-semibold">{copy.address}</h2>
            <p>
              {order.address.street} {order.address.house_number}
            </p>
            <p>
              {order.address.municipality_or_city} {order.address.postal_code}
            </p>
          </div>
        ) : null}
        <div>
          <h2 className="font-semibold">{order.fulfillment.name}</h2>
        </div>
      </section>

      <div className="mt-8 flex flex-col gap-3 sm:flex-row">
        {order.can_cancel ? (
          <Button
            type="button"
            variant="secondary"
            disabled={pending}
            onClick={() => void onCancel()}
          >
            {pending ? copy.cancelling : copy.cancel}
          </Button>
        ) : null}
        <Button asChild variant={order.can_cancel ? "ghost" : "default"}>
          <Link href="/catalog">{copy.continueShopping}</Link>
        </Button>
      </div>
    </div>
  );
}
