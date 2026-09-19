"use client";

import { usePathname } from "next/navigation";
import { AdminAccessGuard } from "@/features/admin/components/AdminAccessGuard";
import { AdminShell } from "@/features/admin/layouts/AdminShell";

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();

  if (pathname === "/admin/forbidden") {
    return children;
  }

  return (
    <AdminAccessGuard>
      <AdminShell>{children}</AdminShell>
    </AdminAccessGuard>
  );
}
