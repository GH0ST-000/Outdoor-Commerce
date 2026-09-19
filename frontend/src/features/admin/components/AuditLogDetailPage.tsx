"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { fetchAuditLog } from "@/features/admin/api/admin-api";
import { SafeJson } from "@/features/admin/components/SafeJson";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import type { AuditLog } from "@/features/admin/types/admin-types";
import { ApiClientError } from "@/lib/api-client";

type LogResult = {
  key: string;
  log: AuditLog | null;
  error: string | null;
};

export function AuditLogDetailPage({ logId }: { logId: string }) {
  const [result, setResult] = useState<LogResult | null>(null);

  useEffect(() => {
    let cancelled = false;

    void fetchAuditLog(logId)
      .then((next) => {
        if (!cancelled) setResult({ key: logId, log: next, error: null });
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setResult({
            key: logId,
            log: null,
            error:
              err instanceof ApiClientError
                ? err.message
                : "Unable to load audit log.",
          });
        }
      });

    return () => {
      cancelled = true;
    };
  }, [logId]);

  const loading = result?.key !== logId;
  const log = result?.key === logId ? result.log : null;
  const error = result?.key === logId ? result.error : null;

  if (loading) {
    return (
      <p
        className="text-sm text-muted-foreground"
        role="status"
        aria-live="polite"
      >
        Loading audit log…
      </p>
    );
  }

  if (!log) {
    return (
      <div className="space-y-3">
        <p className="text-sm text-destructive" role="alert">
          {error ?? "Audit log not found."}
        </p>
        <Link
          href="/admin/audit-logs"
          className="text-sm font-medium text-foreground no-underline hover:text-primary"
        >
          Back to audit logs
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <Link
          href="/admin/audit-logs"
          className="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground no-underline hover:text-foreground"
        >
          ← Audit logs
        </Link>
        <AdminPageHeader
          className="mt-3"
          title={log.event}
          description={`Read-only record · ID ${log.id}`}
        />
      </div>

      <AdminPanel>
        <AdminPanelHeader
          title="Summary"
          description="Sensitive keys are already redacted by the API."
        />
        <dl className="grid gap-4 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Created
            </dt>
            <dd className="mt-1.5">
              {log.created_at
                ? new Date(log.created_at).toLocaleString()
                : "—"}
            </dd>
          </div>
          <div>
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Actor user ID
            </dt>
            <dd className="mt-1.5">{log.actor_user_id ?? "—"}</dd>
          </div>
          <div>
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Subject
            </dt>
            <dd className="mt-1.5">
              {log.subject_type
                ? `${log.subject_type}:${log.subject_id ?? ""}`
                : "—"}
            </dd>
          </div>
          <div>
            <dt className="text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
              Request ID
            </dt>
            <dd className="mt-1.5 break-all">{log.request_id ?? "—"}</dd>
          </div>
        </dl>
      </AdminPanel>

      <div className="grid gap-4 lg:grid-cols-3">
        <SafeJson label="Old values" value={log.old_values} />
        <SafeJson label="New values" value={log.new_values} />
        <SafeJson label="Metadata" value={log.metadata} />
      </div>
    </div>
  );
}
