import type { Metadata } from "next";
import { Suspense } from "react";
import { PaymentReturnPageView } from "@/features/payments/components/PaymentReturnPageView";

export const metadata: Metadata = {
  title: "Payment return",
  robots: { index: false, follow: false },
};

export default function PaymentReturnPage() {
  return (
    <Suspense fallback={<p className="p-6">Loading</p>}>
      <PaymentReturnPageView />
    </Suspense>
  );
}
