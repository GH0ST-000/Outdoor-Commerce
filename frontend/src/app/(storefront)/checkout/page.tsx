import type { Metadata } from "next";
import { CheckoutPageView } from "@/features/checkout/components/CheckoutPageView";

export const metadata: Metadata = {
  title: "Checkout",
  robots: { index: false, follow: false },
};

export default function CheckoutPage() {
  return <CheckoutPageView />;
}
