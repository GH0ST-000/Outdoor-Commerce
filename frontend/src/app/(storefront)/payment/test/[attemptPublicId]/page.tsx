import type { Metadata } from "next";
import { TestPaymentPageView } from "@/features/payments/components/TestPaymentPageView";

export const metadata: Metadata = {
  title: "Test payment",
  robots: { index: false, follow: false },
};

export default async function TestPaymentPage({
  params,
}: {
  params: Promise<{ attemptPublicId: string }>;
}) {
  const { attemptPublicId } = await params;

  return <TestPaymentPageView attemptPublicId={attemptPublicId} />;
}
