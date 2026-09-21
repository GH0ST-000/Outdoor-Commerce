"use client";

import { Suspense } from "react";
import { PricesPage } from "@/features/pricing/components/PricesPage";

export default function AdminPricesPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading prices…
        </p>
      }
    >
      <PricesPage />
    </Suspense>
  );
}
