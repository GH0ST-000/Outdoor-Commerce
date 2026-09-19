"use client";

import { use } from "react";
import { ProductFormPage } from "@/features/catalog/products/components/ProductFormPage";

export default function AdminEditProductPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  return <ProductFormPage mode="edit" productId={id} />;
}
