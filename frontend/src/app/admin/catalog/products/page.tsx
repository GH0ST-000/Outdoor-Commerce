"use client";

import { Suspense } from "react";
import { ProductsListPage } from "@/features/catalog/products/components/ProductsListPage";

export default function AdminProductsPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading products…
        </p>
      }
    >
      <ProductsListPage />
    </Suspense>
  );
}
