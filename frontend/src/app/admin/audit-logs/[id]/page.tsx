"use client";

import { use } from "react";
import { AuditLogDetailPage } from "@/features/admin/components/AuditLogDetailPage";

export default function AdminAuditLogDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  return <AuditLogDetailPage logId={id} />;
}
