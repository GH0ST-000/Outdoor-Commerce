"use client";

import { Suspense } from "react";
import { PromotionsPage } from "@/features/pricing/components/PromotionsPage";

export default function AdminPromotionsPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading promotions…
        </p>
      }
    >
      <PromotionsPage />
    </Suspense>
  );
}
