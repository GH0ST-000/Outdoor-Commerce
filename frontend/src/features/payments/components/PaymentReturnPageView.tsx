"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { getOrder } from "@/features/orders/api/order-client";
import { getCurrentPaymentAttempt } from "@/features/payments/api/payment-client";
import { usePaymentCopy } from "@/features/payments/copy";
import type { Order } from "@/features/orders/types";
import type { PaymentAttempt } from "@/features/payments/types";

const POLL_MS = 2000;
const MAX_POLLS = 30;

export function PaymentReturnPageView() {
  const copy = usePaymentCopy();
  const params = useSearchParams();
  const headingRef = useRef<HTMLHeadingElement>(null);
  const [order, setOrder] = useState<Order | null>(null);
  const [attempt, setAttempt] = useState<PaymentAttempt | null>(null);
  const [polls, setPolls] = useState(0);
  const [error, setError] = useState<string | null>(null);

  const orderId = params.get("order_id");
  const querySuccess = params.get("success");

  const load = useCallback(async () => {
    if (!orderId) {
      setError(copy.loadError);
      return;
    }
    try {
      const next = await getOrder(orderId);
      setOrder(next);
      const current = await getCurrentPaymentAttempt(orderId);
      setAttempt(current);
      setError(null);
    } catch {
      setError(copy.loadError);
    }
  }, [copy.loadError, orderId]);

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void load();
    }, 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  const verifiedSuccess =
    order?.payment_status === "paid" || order?.status === "confirmed";
  const terminalFailure = ["failed", "cancelled", "expired"].includes(
    attempt?.status ?? "",
  );
  const manual =
    order?.status === "manual_review" || attempt?.status === "manual_review";
  const unknown = attempt?.status === "unknown";
  const done = verifiedSuccess || terminalFailure || manual;

  useEffect(() => {
    if (done || polls >= MAX_POLLS || !orderId) {
      return;
    }
    const timer = window.setTimeout(() => {
      setPolls((count) => count + 1);
      void load();
    }, POLL_MS);
    return () => window.clearTimeout(timer);
  }, [done, polls, orderId, load]);

  useEffect(() => {
    headingRef.current?.focus();
  }, [verifiedSuccess, terminalFailure, manual]);

  useEffect(() => {
    if (typeof window === "undefined" || !orderId) {
      return;
    }
    const clean = `/payment/return?order_id=${encodeURIComponent(orderId)}`;
    window.history.replaceState(null, "", clean);
  }, [orderId]);

  const title = useMemo(() => {
    if (verifiedSuccess) {
      return copy.successTitle;
    }
    if (manual) {
      return copy.manualReviewTitle;
    }
    if (attempt?.status === "cancelled") {
      return copy.cancelledTitle;
    }
    if (attempt?.status === "expired") {
      return copy.expiredTitle;
    }
    if (terminalFailure) {
      return copy.failedTitle;
    }
    if (unknown) {
      return copy.unknownTitle;
    }
    return copy.processingTitle;
  }, [
    attempt?.status,
    copy,
    manual,
    terminalFailure,
    unknown,
    verifiedSuccess,
  ]);

  const queryCannotSucceed = querySuccess === "true" && !verifiedSuccess;

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <h1
        ref={headingRef}
        tabIndex={-1}
        className="text-3xl font-semibold tracking-tight"
      >
        {title}
      </h1>
      {queryCannotSucceed ? (
        <div className="mt-4">
          <Alert tone="info">{copy.processingBody}</Alert>
        </div>
      ) : null}
      <div className="mt-4">
        {verifiedSuccess ? (
          <Alert tone="success">{copy.successBody}</Alert>
        ) : manual ? (
          <Alert tone="warning">{copy.manualReviewBody}</Alert>
        ) : terminalFailure ? (
          <Alert tone="danger">{copy.failedBody}</Alert>
        ) : unknown ? (
          <Alert tone="info">{copy.unknownBody}</Alert>
        ) : (
          <Alert tone="info">{copy.processingBody}</Alert>
        )}
      </div>
      {error ? (
        <div className="mt-3">
          <Alert tone="danger">{error}</Alert>
        </div>
      ) : null}
      <div className="mt-6 flex flex-col gap-3 sm:flex-row">
        {!done ? (
          <Button type="button" onClick={() => void load()}>
            {copy.refreshStatus}
          </Button>
        ) : null}
        {orderId ? (
          <Button asChild variant="secondary">
            <Link href={`/order-confirmation/${orderId}`}>
              {copy.returnHome}
            </Link>
          </Button>
        ) : (
          <Button asChild variant="secondary">
            <Link href="/catalog">{copy.returnHome}</Link>
          </Button>
        )}
      </div>
    </div>
  );
}
