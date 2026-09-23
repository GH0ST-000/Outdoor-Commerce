import type { Metadata } from "next";
import { OrderConfirmationPageView } from "@/features/orders/components/OrderConfirmationPageView";

export const metadata: Metadata = {
  title: "Order confirmation",
  robots: { index: false, follow: false },
};

export default async function OrderConfirmationPage({
  params,
}: {
  params: Promise<{ orderPublicId: string }>;
}) {
  const { orderPublicId } = await params;

  return <OrderConfirmationPageView orderPublicId={orderPublicId} />;
}
