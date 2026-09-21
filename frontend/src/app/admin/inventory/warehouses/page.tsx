"use client";

import { Suspense } from "react";
import { WarehousesListPage } from "@/features/inventory/components/WarehousesListPage";

export default function AdminWarehousesPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading warehouses…
        </p>
      }
    >
      <WarehousesListPage />
    </Suspense>
  );
}
