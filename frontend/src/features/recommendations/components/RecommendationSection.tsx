"use client";

import { useEffect, useId, useRef, useState } from "react";
import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogTitle } from "@/components/ui/dialog";
import { addItemToCart } from "@/features/cart/state/cart-actions";
import {
  RECOMMENDATION_EVENTS,
  trackRecommendationEvent,
} from "@/features/recommendations/analytics/events";
import { fetchContextualRecommendations } from "@/features/recommendations/api/public-recommendations-api";
import { recommendationCopy } from "@/features/recommendations/copy/recommendation-copy";
import { recommendationContextKey } from "@/features/recommendations/lib/request";
import { formatMoneyMinor } from "@/features/pricing/lib/money";
import type {
  RecommendationCard,
  RecommendationRequest,
  RecommendationResponse,
} from "@/features/recommendations/types/recommendation-types";

type Loader = (
  request: RecommendationRequest,
) => Promise<RecommendationResponse>;

export function RecommendationSection({
  request,
  load = fetchContextualRecommendations,
}: {
  request: RecommendationRequest;
  load?: Loader;
}) {
  const copy = recommendationCopy(request.locale);
  const headingId = useId();
  const headingRef = useRef<HTMLHeadingElement>(null);
  const contextKey = recommendationContextKey(request);
  const requestRef = useRef(request);
  const [pageState, setPageState] = useState({ key: contextKey, page: 1 });
  const page = pageState.key === contextKey ? pageState.page : 1;
  const [loaded, setLoaded] = useState<{
    key: string;
    page: number;
    data: RecommendationResponse;
  } | null>(null);
  const [failedKey, setFailedKey] = useState<string | null>(null);
  const [cart, setCart] = useState<{ key: string; text: string } | null>(null);
  const [openWhy, setOpenWhy] = useState<string | null>(null);
  const [quickView, setQuickView] = useState<RecommendationCard | null>(null);
  const data = loaded?.data ?? null;
  const requestKey = `${contextKey}:${page}`;
  const current =
    loaded !== null && loaded.key === contextKey && loaded.page === page;
  const stale = loaded !== null && loaded.key !== contextKey;
  const status =
    failedKey === requestKey ? "error" : current ? "ready" : "loading";
  const cartMessage = cart?.key === contextKey ? cart.text : null;

  useEffect(() => {
    requestRef.current = request;
  });

  useEffect(() => {
    let cancelled = false;
    const snapshot = requestRef.current;
    const key = contextKey;
    const requestedPage = page;
    void load({ ...snapshot, page: requestedPage })
      .then((payload) => {
        if (cancelled) return;
        setFailedKey((currentKey) =>
          currentKey === `${key}:${requestedPage}` ? null : currentKey,
        );
        setLoaded({ key, page: requestedPage, data: payload });
        trackRecommendationEvent(RECOMMENDATION_EVENTS.section_viewed, {
          placement: snapshot.placement,
          gate: payload.gate,
          profile_version: payload.profile_version,
          framing: payload.context.framing,
        });
        if (payload.recommendations.length === 0) {
          const event =
            payload.gate === "recommendations_blocked"
              ? RECOMMENDATION_EVENTS.blocked
              : RECOMMENDATION_EVENTS.zero_result;
          trackRecommendationEvent(event, {
            placement: snapshot.placement,
            gate: payload.gate,
          });
        }
      })
      .catch(() => {
        if (!cancelled) setFailedKey(`${key}:${requestedPage}`);
      });
    return () => {
      cancelled = true;
    };
  }, [contextKey, load, page]);

  const framing = data?.context.framing;
  const title =
    framing === "species_related" || request.placement === "species_detail"
      ? copy.speciesTitle
      : copy.title;
  const showProducts =
    data !== null &&
    data.recommendations.length > 0 &&
    framing !== "blocked" &&
    framing !== "conflict" &&
    framing !== "suppressed";

  function goToPage(next: number) {
    setPageState({ key: contextKey, page: next });
    headingRef.current?.focus();
  }

  return (
    <section
      aria-labelledby={headingId}
      className="mt-6 space-y-3 border-t border-border pt-4"
    >
      <h2
        id={headingId}
        ref={headingRef}
        tabIndex={-1}
        className="text-lg font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        {title}
      </h2>
      {stale ? (
        <p role="status" className="text-sm font-medium">
          {copy.stale}
        </p>
      ) : null}
      {status === "loading" && data === null ? (
        <p role="status" className="text-sm">
          {copy.loading}
        </p>
      ) : null}
      {status === "error" ? (
        <p role="alert" className="text-sm">
          {copy.error}
        </p>
      ) : null}
      {data && framing === "conditional" ? (
        <p className="text-sm font-medium">
          {data.warnings[0]?.text || copy.conditional}
        </p>
      ) : null}
      {data &&
      (framing === "blocked" || data.gate === "recommendations_blocked") &&
      framing !== "conflict" ? (
        <p className="text-sm">{data.warnings[0]?.text || copy.blocked}</p>
      ) : null}
      {data && framing === "conflict" ? (
        <p className="text-sm">{data.warnings[0]?.text || copy.conflict}</p>
      ) : null}
      {data && framing === "suppressed" ? (
        <p className="text-sm">{copy.unknown}</p>
      ) : null}
      {data && framing === "species_related" ? (
        <p className="text-sm">{copy.speciesRelated}</p>
      ) : null}
      {showProducts ? (
        <ul className="space-y-3">
          {data.recommendations.map((product, index) => (
            <li key={product.slug}>
              <RecommendationCardView
                product={product}
                rank={index + 1}
                copy={copy}
                locale={request.locale}
                placement={request.placement}
                gate={data.gate}
                profileVersion={data.profile_version}
                open={openWhy === product.slug}
                onToggleWhy={() => {
                  setOpenWhy((current) =>
                    current === product.slug ? null : product.slug,
                  );
                  trackRecommendationEvent(
                    RECOMMENDATION_EVENTS.explanation_opened,
                    {
                      placement: request.placement,
                      confidence: product.confidence,
                      rank: index + 1,
                      promotion: product.is_promoted,
                    },
                  );
                }}
                onQuickView={() => {
                  setQuickView(product);
                  trackRecommendationEvent(
                    RECOMMENDATION_EVENTS.product_viewed,
                    {
                      placement: request.placement,
                      confidence: product.confidence,
                      rank: index + 1,
                    },
                  );
                }}
                onAdded={(message) =>
                  setCart({ key: contextKey, text: message })
                }
              />
            </li>
          ))}
        </ul>
      ) : null}
      {data &&
      status === "ready" &&
      !showProducts &&
      framing !== "blocked" &&
      framing !== "conflict" &&
      framing !== "suppressed" ? (
        <p className="text-sm">{copy.empty}</p>
      ) : null}
      {cartMessage ? (
        <p role="status" className="text-sm">
          {cartMessage}
        </p>
      ) : null}
      {data && (data.pagination.has_more || data.pagination.page > 1) ? (
        <nav aria-label={copy.page} className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={data.pagination.page <= 1}
            onClick={() => goToPage(data.pagination.page - 1)}
          >
            {copy.previous}
          </Button>
          <span className="inline-flex items-center text-sm">
            {copy.page} {data.pagination.page}
          </span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={!data.pagination.has_more}
            onClick={() => goToPage(data.pagination.page + 1)}
          >
            {copy.next}
          </Button>
        </nav>
      ) : null}
      {data ? (
        <p className="text-xs text-muted-foreground">{data.disclaimer}</p>
      ) : null}
      {framing === "suppressed" ||
      framing === "blocked" ||
      framing === "species_related" ||
      request.placement === "species_detail" ? (
        <p className="flex flex-wrap gap-3 text-sm">
          <Link
            href="/products"
            className="underline"
            onClick={() => {
              trackRecommendationEvent(RECOMMENDATION_EVENTS.general_catalog, {
                placement: request.placement,
                gate: data?.gate,
              });
            }}
          >
            {copy.generalCatalog}
          </Link>
          {request.placement === "species_detail" ? (
            <Link href="/map" className="underline">
              {copy.planner}
            </Link>
          ) : null}
        </p>
      ) : null}
      {quickView ? (
        <Dialog open onOpenChange={(open) => !open && setQuickView(null)}>
          <DialogContent closeLabel={copy.close}>
            <div className="space-y-2">
              <DialogTitle>{quickView.name}</DialogTitle>
              <p>{quickView.primary_reason_text}</p>
              <p>
                {copy.confidence}: {confidenceLabel(copy, quickView.confidence)}
              </p>
              <Link href={quickView.product_url} className="underline">
                {quickView.name}
              </Link>
            </div>
          </DialogContent>
        </Dialog>
      ) : null}
    </section>
  );
}

function RecommendationCardView({
  product,
  rank,
  copy,
  locale,
  placement,
  gate,
  profileVersion,
  open,
  onToggleWhy,
  onQuickView,
  onAdded,
}: {
  product: RecommendationCard;
  rank: number;
  copy: ReturnType<typeof recommendationCopy>;
  locale: "ka" | "en";
  placement: string;
  gate: string;
  profileVersion: number | null;
  open: boolean;
  onToggleWhy: () => void;
  onQuickView: () => void;
  onAdded: (message: string) => void;
}) {
  const [pending, setPending] = useState(false);
  const price = product.sale_price ?? product.price;
  const moneyLocale = locale === "ka" ? "ka-GE" : "en";
  const required = [
    product.primary_reason,
    ...product.supporting_reasons.map((row) => row.code),
  ].includes("required_equipment_match");
  const variant = product.eligible_variants.find(
    (row) => row.sku === product.recommended_variant && row.in_stock,
  );

  return (
    <article className="space-y-2 rounded-xl border border-border p-3">
      <h3 className="font-medium">
        <Link
          href={product.product_url}
          className="underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring"
          onClick={() =>
            trackRecommendationEvent(RECOMMENDATION_EVENTS.clicked, {
              placement,
              confidence: product.confidence,
              rank,
              promotion: product.is_promoted,
              gate,
              profile_version: profileVersion,
            })
          }
        >
          {product.name}
        </Link>
      </h3>
      {product.brand ? (
        <p className="text-sm text-muted-foreground">{product.brand}</p>
      ) : null}
      <p className="text-sm">{product.primary_reason_text}</p>
      <p className="text-sm">
        {copy.confidence}: {confidenceLabel(copy, product.confidence)}
      </p>
      {required ? (
        <p className="text-sm font-medium">{copy.requiredBadge}</p>
      ) : null}
      {product.is_promoted ? (
        <p className="text-sm font-medium">
          {product.promotion_label || copy.promoted}
        </p>
      ) : null}
      {product.warnings.map((warning) => (
        <p key={warning.code} className="text-sm" role="note">
          {warning.text}
        </p>
      ))}
      {price ? (
        <p className="text-sm">
          {formatMoneyMinor(price.amount_minor, price.currency, moneyLocale)}
        </p>
      ) : null}
      <p className="text-sm">
        {copy.stock[product.stock_status] ?? product.stock_status}
      </p>
      <p className="text-sm">
        {copy.variants}:{" "}
        {product.eligible_variants
          .map(
            (row) =>
              `${row.sku} (${copy.stock[row.stock_status] ?? row.stock_status})`,
          )
          .join(", ") || "—"}
      </p>
      {product.recommended_variant === null ? (
        <p className="text-sm">{copy.chooseVariant}</p>
      ) : null}
      <div className="flex flex-wrap gap-2">
        <Button
          type="button"
          size="sm"
          variant="outline"
          aria-expanded={open}
          onClick={onToggleWhy}
        >
          {open ? copy.hideWhy : copy.why}
        </Button>
        <Button type="button" size="sm" variant="outline" onClick={onQuickView}>
          {copy.quickView}
        </Button>
        {product.add_to_cart_eligible && variant ? (
          <Button
            type="button"
            size="sm"
            disabled={pending}
            onClick={() => {
              setPending(true);
              void addItemToCart({
                variant_id: variant.variant_id,
                quantity: 1,
              })
                .then(() => {
                  onAdded(copy.added);
                  trackRecommendationEvent(
                    RECOMMENDATION_EVENTS.added_to_cart,
                    {
                      placement,
                      confidence: product.confidence,
                      rank,
                      promotion: product.is_promoted,
                      gate,
                      profile_version: profileVersion,
                    },
                  );
                })
                .catch(() => onAdded(copy.cartError))
                .finally(() => setPending(false));
            }}
          >
            {pending ? copy.adding : copy.add}
          </Button>
        ) : null}
      </div>
      {open ? (
        <div className="space-y-1 text-sm">
          <ul>
            {product.supporting_reasons.map((reason) => (
              <li key={reason.code}>{reason.text}</li>
            ))}
          </ul>
        </div>
      ) : null}
    </article>
  );
}

function confidenceLabel(
  copy: ReturnType<typeof recommendationCopy>,
  confidence: RecommendationCard["confidence"],
): string {
  if (confidence === "high") return copy.confidenceHigh;
  if (confidence === "medium") return copy.confidenceMedium;
  if (confidence === "low") return copy.confidenceLow;
  return copy.confidenceInsufficient;
}
