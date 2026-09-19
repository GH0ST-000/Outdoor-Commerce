"use client";

import { use } from "react";
import { AttributeFormPage } from "@/features/catalog/attributes/components/AttributeFormPage";

export default function AdminEditAttributePage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  return <AttributeFormPage mode="edit" attributeId={id} />;
}
