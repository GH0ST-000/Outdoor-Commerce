"use client";

import { use } from "react";
import { AdminSpeciesWorkspace } from "@/features/species/components/AdminSpeciesWorkspace";

export default function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return <AdminSpeciesWorkspace speciesId={id} />;
}
