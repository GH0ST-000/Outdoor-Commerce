"use client";

import { Suspense } from "react";
import { InventoryListPage } from "@/features/inventory/components/InventoryListPage";

export default function AdminInventoryPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading inventory…
        </p>
      }
    >
      <InventoryListPage />
    </Suspense>
  );
}
