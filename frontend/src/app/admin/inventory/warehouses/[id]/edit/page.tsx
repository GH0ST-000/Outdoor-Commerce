"use client";

import { use } from "react";
import { WarehouseFormPage } from "@/features/inventory/components/WarehouseFormPage";

export default function AdminWarehouseEditPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  return <WarehouseFormPage mode="edit" warehouseId={id} />;
}
