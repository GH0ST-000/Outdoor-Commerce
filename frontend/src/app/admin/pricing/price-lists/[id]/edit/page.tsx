"use client";

import { use } from "react";
import { PriceListFormPage } from "@/features/pricing/components/PriceListFormPage";

export default function AdminEditPriceListPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  return <PriceListFormPage mode="edit" priceListId={id} />;
}
