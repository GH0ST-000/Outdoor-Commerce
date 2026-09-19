"use client";

import { Suspense } from "react";
import { UsersListPage } from "@/features/admin/components/UsersListPage";

export default function AdminUsersPage() {
  return (
    <Suspense
      fallback={
        <p className="text-sm text-muted-foreground" role="status">
          Loading users…
        </p>
      }
    >
      <UsersListPage />
    </Suspense>
  );
}
