"use client";

import { Suspense } from "react";
import { ReservationsListPage } from "@/features/inventory/components/ReservationsListPage";

export default function AdminReservationsPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading reservations…
        </p>
      }
    >
      <ReservationsListPage />
    </Suspense>
  );
}
