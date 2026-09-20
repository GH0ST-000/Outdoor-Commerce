"use client";

import { use } from "react";
import { PromotionFormPage } from "@/features/pricing/components/PromotionFormPage";

export default function AdminEditPromotionPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  return <PromotionFormPage mode="edit" promotionId={id} />;
}
