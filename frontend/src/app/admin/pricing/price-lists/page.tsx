"use client";

import { Suspense } from "react";
import { PriceListsPage } from "@/features/pricing/components/PriceListsPage";

export default function AdminPriceListsPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading price lists…
        </p>
      }
    >
      <PriceListsPage />
    </Suspense>
  );
}
