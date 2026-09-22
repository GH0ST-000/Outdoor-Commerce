"use client";

import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { useCheckoutCopy } from "@/features/checkout/copy";
import { useLocale } from "@/components/locale-provider";
import type { CheckoutQuote } from "@/features/checkout/types";

export function QuoteSummary({
  quote,
  expired,
  remainingLabel,
}: {
  quote: CheckoutQuote;
  expired: boolean;
  remainingLabel: string;
}) {
  const copy = useCheckoutCopy();
  const { locale } = useLocale();
  const localeTag = locale === "ka" ? "ka-GE" : "en";

  return (
    <aside className="rounded-[var(--radius-2xl)] border border-border/60 bg-card p-5 lg:sticky lg:top-24">
      <h2 className="text-lg font-semibold">{copy.summary}</h2>
      <ul className="mt-4 space-y-3">
        {quote.items.map((line) => (
          <li key={line.id} className="flex gap-3">
            {line.media?.url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={line.media.url}
                alt={line.media.alt ?? line.name}
                className="size-14 rounded-lg object-cover"
              />
            ) : (
              <div className="size-14 rounded-lg bg-muted" />
            )}
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">{line.name}</p>
              <p className="text-xs text-muted-foreground">
                {line.variant_label} · {copy.quantity} {line.quantity}
              </p>
              <PriceDisplay
                currency={line.pricing.currency}
                locale={localeTag}
                amountMinor={line.pricing.line_total_minor}
              />
            </div>
          </li>
        ))}
      </ul>
      <dl className="mt-5 space-y-2 text-sm">
        <div className="flex justify-between gap-3">
          <dt>{copy.subtotal}</dt>
          <dd>
            <PriceDisplay
              currency={quote.totals.currency}
              locale={localeTag}
              amountMinor={quote.totals.items_subtotal_minor}
            />
          </dd>
        </div>
        {quote.totals.discount_total_minor > 0 ? (
          <div className="flex justify-between gap-3 text-[var(--status-warning)]">
            <dt>{copy.savings}</dt>
            <dd>
              <PriceDisplay
                currency={quote.totals.currency}
                locale={localeTag}
                amountMinor={quote.totals.discount_total_minor}
              />
            </dd>
          </div>
        ) : null}
        <div className="flex justify-between gap-3">
          <dt>{copy.shipping}</dt>
          <dd>
            <PriceDisplay
              currency={quote.totals.currency}
              locale={localeTag}
              amountMinor={quote.totals.delivery_total_minor}
            />
          </dd>
        </div>
        <div className="flex justify-between gap-3 text-base font-semibold">
          <dt>{copy.total}</dt>
          <dd>
            <PriceDisplay
              currency={quote.totals.currency}
              locale={localeTag}
              amountMinor={quote.totals.grand_total_minor}
            />
          </dd>
        </div>
      </dl>
      <p className="mt-3 text-xs text-muted-foreground">{copy.taxIncluded}</p>
      {quote.adjustments
        .filter((item) => item.type === "promotion")
        .map((item) => (
          <p key={`${item.code}-${item.label}`} className="mt-1 text-xs">
            {item.label}
          </p>
        ))}
      <p className="mt-2 text-sm" aria-live={expired ? "polite" : "off"}>
        {expired ? copy.expired : `${copy.expires}: ${remainingLabel}`}
      </p>
    </aside>
  );
}
