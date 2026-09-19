"use client";

import { Suspense } from "react";
import { AuditLogsListPage } from "@/features/admin/components/AuditLogsListPage";

export default function AdminAuditLogsPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading audit logs…
        </p>
      }
    >
      <AuditLogsListPage />
    </Suspense>
  );
}
