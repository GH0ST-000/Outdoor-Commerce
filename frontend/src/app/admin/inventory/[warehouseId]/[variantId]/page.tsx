"use client";

import { use } from "react";
import { InventoryDetailPage } from "@/features/inventory/components/InventoryDetailPage";

export default function AdminInventoryDetailPage({
  params,
}: {
  params: Promise<{ warehouseId: string; variantId: string }>;
}) {
  const { warehouseId, variantId } = use(params);
  return (
    <InventoryDetailPage warehouseId={warehouseId} variantId={variantId} />
  );
}
