"use client";

import { Suspense } from "react";
import { AttributesListPage } from "@/features/catalog/attributes/components/AttributesListPage";

export default function AdminAttributesPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading attributes…
        </p>
      }
    >
      <AttributesListPage />
    </Suspense>
  );
}
