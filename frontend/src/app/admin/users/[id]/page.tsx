"use client";

import { use } from "react";
import { UserDetailPage } from "@/features/admin/components/UserDetailPage";

export default function AdminUserDetailRoute({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  return <UserDetailPage userId={id} />;
}
