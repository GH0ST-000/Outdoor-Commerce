"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { simulateTestPayment } from "@/features/payments/api/payment-client";
import { usePaymentCopy } from "@/features/payments/copy";

export function TestPaymentPageView({
  attemptPublicId,
}: {
  attemptPublicId: string;
}) {
  const copy = usePaymentCopy();
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function run(outcome: string) {
    if (pending) {
      return;
    }
    setPending(true);
    setError(null);
    try {
      const attempt = await simulateTestPayment(attemptPublicId, outcome);
      router.replace(`/payment/return?order_id=${attempt.order_id}`);
    } catch {
      setError(copy.loadError);
      setPending(false);
    }
  }

  return (
    <div className="mx-auto max-w-lg px-4 py-10">
      <Alert tone="warning" title={copy.testOnly}>
        {copy.explanation}
      </Alert>
      <h1 className="mt-6 text-2xl font-semibold">{copy.title}</h1>
      <p className="mt-2 text-sm text-muted-foreground">{copy.secureNote}</p>
      {error ? (
        <div className="mt-4">
          <Alert tone="danger">{error}</Alert>
        </div>
      ) : null}
      <div className="mt-6 flex flex-col gap-3">
        <Button
          type="button"
          disabled={pending}
          onClick={() => void run("success")}
        >
          Simulate success
        </Button>
        <Button
          type="button"
          variant="secondary"
          disabled={pending}
          onClick={() => void run("failure")}
        >
          Simulate failure
        </Button>
        <Button
          type="button"
          variant="ghost"
          disabled={pending}
          onClick={() => void run("cancelled")}
        >
          Simulate cancel
        </Button>
      </div>
      <p className="mt-4 text-xs text-muted-foreground">
        No card number, CVV, or bank login is collected.
      </p>
    </div>
  );
}
