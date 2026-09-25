"use client";

import { useEffect, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Alert } from "@/components/ui/alert";
import {
  fulfillmentStatusLabel,
  shipmentStatusLabel,
  useOrderCopy,
} from "@/features/orders/copy";
import type { Order, OrderShipment } from "@/features/orders/types";

const POLL_MS = 15000;
const MAX_POLLS = 20;

function shipmentTone(
  status: string,
): "default" | "success" | "warning" | "danger" | "secondary" {
  if (["delivered", "collected"].includes(status)) {
    return "success";
  }
  if (["exception", "delivery_attempt_failed"].includes(status)) {
    return "warning";
  }
  if (status === "cancelled") {
    return "danger";
  }
  return "secondary";
}

export function FulfillmentTrackingSection({
  order,
  localeTag,
  onRefresh,
}: {
  order: Order;
  localeTag: string;
  onRefresh: () => Promise<void>;
}) {
  const copy = useOrderCopy();
  const progress = order.fulfillment_progress;
  const [polls, setPolls] = useState(0);
  const [open, setOpen] = useState<Record<string, boolean>>({});
  const [tabHidden, setTabHidden] = useState(false);

  const shouldPoll =
    Boolean(progress?.capabilities.poll) &&
    order.payment_status === "paid" &&
    !["fulfilled", "cancelled"].includes(order.fulfillment_status);

  useEffect(() => {
    const onVis = () => {
      setTabHidden(document.visibilityState === "hidden");
    };
    onVis();
    document.addEventListener("visibilitychange", onVis);
    return () => document.removeEventListener("visibilitychange", onVis);
  }, []);

  useEffect(() => {
    if (!shouldPoll || polls >= MAX_POLLS || tabHidden) {
      return;
    }
    const timer = window.setTimeout(() => {
      setPolls((count) => count + 1);
      void onRefresh();
    }, POLL_MS);
    return () => window.clearTimeout(timer);
  }, [shouldPoll, polls, onRefresh, tabHidden]);

  if (order.payment_status !== "paid" && order.status !== "confirmed") {
    return null;
  }

  const shipments = progress?.shipments ?? [];

  return (
    <section
      className="mt-8 pb-[env(safe-area-inset-bottom)]"
      aria-labelledby="fulfillment-heading"
    >
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 id="fulfillment-heading" className="text-lg font-semibold">
            {copy.trackingTitle}
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {copy.trackingBody}
          </p>
        </div>
        <Badge variant={shipmentTone(order.fulfillment_status)}>
          {fulfillmentStatusLabel(copy, order.fulfillment_status)}
        </Badge>
      </div>

      {order.fulfillment_status === "unfulfilled" ? (
        <p className="mt-4 text-sm text-muted-foreground">
          {copy.unfulfilledBody}
        </p>
      ) : null}

      {order.fulfillment_status === "exception" ? (
        <div className="mt-4">
          <Alert tone="warning" title={copy.exceptionTitle}>
            {copy.exceptionBody}
          </Alert>
        </div>
      ) : null}

      <ul className="mt-6 space-y-4">
        {shipments.map((shipment) => (
          <li key={shipment.id}>
            <ShipmentCard
              shipment={shipment}
              copy={copy}
              localeTag={localeTag}
              expanded={open[shipment.id] ?? true}
              onToggle={() =>
                setOpen((current) => ({
                  ...current,
                  [shipment.id]: !(current[shipment.id] ?? true),
                }))
              }
            />
          </li>
        ))}
      </ul>

      {(progress?.remaining_items.length ?? 0) > 0 ? (
        <div className="mt-4 rounded-[var(--radius-lg)] border border-border p-4">
          <h3 className="text-sm font-semibold">{copy.remainingTitle}</h3>
          <ul className="mt-2 space-y-1 text-sm">
            {progress?.remaining_items.map((item) => (
              <li key={item.order_item_id}>
                {item.name} · {item.variant_name} · {copy.quantity}{" "}
                {item.quantity}
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {progress?.capabilities.can_refresh ? (
        <div className="mt-4">
          <Button
            type="button"
            variant="ghost"
            onClick={() => void onRefresh()}
          >
            {copy.refreshTracking}
          </Button>
        </div>
      ) : null}
    </section>
  );
}

function ShipmentCard({
  shipment,
  copy,
  localeTag,
  expanded,
  onToggle,
}: {
  shipment: OrderShipment;
  copy: ReturnType<typeof useOrderCopy>;
  localeTag: string;
  expanded: boolean;
  onToggle: () => void;
}) {
  const title =
    shipment.type === "store_pickup"
      ? copy.pickupShipment
      : copy.deliveryShipment;
  const trackingSafe =
    typeof shipment.tracking.url === "string" &&
    shipment.tracking.url.startsWith("https://");

  return (
    <article className="rounded-[var(--radius-lg)] border border-border p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-sm font-semibold">
            {title} {shipment.shipment_number}
          </p>
          <p className="text-sm text-muted-foreground">
            {shipment.provider.name}
          </p>
        </div>
        <Badge variant={shipmentTone(shipment.status)}>
          {shipmentStatusLabel(copy, shipment.status)}
        </Badge>
      </div>
      {shipment.tracking.number ? (
        <p className="mt-2 text-sm">
          {copy.trackingNumber}: {shipment.tracking.number}
        </p>
      ) : null}
      {trackingSafe && shipment.tracking.url ? (
        <a
          className="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline"
          href={shipment.tracking.url}
          target="_blank"
          rel="noreferrer"
          aria-label={copy.externalTracking}
        >
          {copy.externalTracking}
        </a>
      ) : null}
      {shipment.exception ? (
        <p className="mt-2 text-sm">{shipment.exception.message}</p>
      ) : null}
      {shipment.pickup_location?.address ? (
        <p className="mt-2 text-sm text-muted-foreground">
          {shipment.pickup_location.name}
          {shipment.pickup_location.address
            ? ` · ${shipment.pickup_location.address}`
            : ""}
        </p>
      ) : null}
      <button
        type="button"
        className="mt-3 min-h-11 text-sm font-medium"
        onClick={onToggle}
        aria-expanded={expanded}
      >
        {expanded ? copy.hideDetails : copy.showDetails}
      </button>
      {expanded ? (
        <div className="mt-3">
          <ol className="space-y-3 border-l border-border pl-4 motion-reduce:transition-none">
            {shipment.timeline.map((event) => (
              <li key={`${event.status}-${event.occurred_at}`}>
                <p className="text-sm font-medium">{event.message}</p>
                <time
                  className="text-xs text-muted-foreground"
                  dateTime={event.occurred_at}
                >
                  {new Date(event.occurred_at).toLocaleString(localeTag)}
                </time>
              </li>
            ))}
          </ol>
          <ul className="mt-4 space-y-2">
            {shipment.items.map((item) => (
              <li
                key={`${item.order_item_id}-${item.quantity}`}
                className="flex gap-3"
              >
                {item.media?.url ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img
                    src={item.media.url}
                    alt={item.media.alt ?? item.name ?? ""}
                    className="size-12 rounded-md object-cover"
                  />
                ) : (
                  <div className="size-12 rounded-md bg-muted" />
                )}
                <p className="text-sm">
                  {item.name} · {item.variant_name} · {copy.quantity}{" "}
                  {item.quantity}
                </p>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </article>
  );
}
