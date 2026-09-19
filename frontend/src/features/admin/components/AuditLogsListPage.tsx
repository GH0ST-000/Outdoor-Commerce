"use client";

import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { fetchAuditLogs } from "@/features/admin/api/admin-api";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import {
  AdminTable,
  AdminToolbar,
  adminTableClassName,
  adminTdClassName,
  adminThClassName,
} from "@/features/admin/ui/AdminTable";
import type {
  AuditLog,
  AuditLogListParams,
  PaginationMeta,
} from "@/features/admin/types/admin-types";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const EVENT_OPTIONS = [
  "all",
  "administrator_created",
  "administrator_promoted",
  "user_status_changed",
  "user_roles_changed",
  "admin_access_denied",
  "permission_configuration_synchronized",
] as const;

const EVENT_LABELS: Record<string, string> = {
  all: "All events",
  administrator_created: "Administrator created",
  administrator_promoted: "Administrator promoted",
  user_status_changed: "User status changed",
  user_roles_changed: "User roles changed",
  admin_access_denied: "Admin access denied",
  permission_configuration_synchronized: "Permissions synchronized",
};

type LogsResult = {
  key: string;
  logs: AuditLog[];
  meta: PaginationMeta | null;
  error: string | null;
};

function readParams(searchParams: URLSearchParams): AuditLogListParams {
  return {
    event: searchParams.get("event") ?? "",
    actor_user_id: searchParams.get("actor_user_id") ?? "",
    subject_type: searchParams.get("subject_type") ?? "",
    subject_id: searchParams.get("subject_id") ?? "",
    from: searchParams.get("from") ?? "",
    to: searchParams.get("to") ?? "",
    direction: searchParams.get("direction") === "asc" ? "asc" : "desc",
    per_page: Number(searchParams.get("per_page") ?? 20) || 20,
    page: Number(searchParams.get("page") ?? 1) || 1,
  };
}

function paramsKey(params: AuditLogListParams): string {
  return JSON.stringify(params);
}

export function AuditLogsListPage() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const params = useMemo(() => readParams(searchParams), [searchParams]);
  const requestKey = paramsKey(params);

  const [draft, setDraft] = useState<AuditLogListParams>(params);
  const [draftKey, setDraftKey] = useState(requestKey);
  const [result, setResult] = useState<LogsResult | null>(null);

  if (draftKey !== requestKey) {
    setDraftKey(requestKey);
    setDraft(params);
  }

  const syncUrl = useCallback(
    (next: AuditLogListParams) => {
      const qs = new URLSearchParams();
      if (next.event) qs.set("event", String(next.event));
      if (next.actor_user_id) {
        qs.set("actor_user_id", String(next.actor_user_id));
      }
      if (next.subject_type) qs.set("subject_type", next.subject_type);
      if (next.subject_id) qs.set("subject_id", next.subject_id);
      if (next.from) qs.set("from", next.from);
      if (next.to) qs.set("to", next.to);
      if (next.direction && next.direction !== "desc") {
        qs.set("direction", next.direction);
      }
      if (next.per_page && next.per_page !== 20) {
        qs.set("per_page", String(next.per_page));
      }
      if (next.page && next.page !== 1) qs.set("page", String(next.page));
      const query = qs.toString();
      router.replace(query ? `${pathname}?${query}` : pathname);
    },
    [pathname, router],
  );

  useEffect(() => {
    let cancelled = false;

    void fetchAuditLogs(params)
      .then((response) => {
        if (cancelled) return;
        setResult({
          key: requestKey,
          logs: response.data,
          meta: response.meta,
          error: null,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setResult({
          key: requestKey,
          logs: [],
          meta: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load audit logs.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, [params, requestKey]);

  const loading = result?.key !== requestKey;
  const logs = result?.key === requestKey ? result.logs : [];
  const meta = result?.key === requestKey ? result.meta : null;
  const error = result?.key === requestKey ? result.error : null;

  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    syncUrl({ ...draft, page: 1 });
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Audit logs"
        description="Append-only history of security and admin actions."
      />

      <form onSubmit={onSubmit}>
        <AdminToolbar className="lg:grid-cols-12">
          <Field label="Event" className="lg:col-span-4">
            <Select
              value={(draft.event as string) || "all"}
              onValueChange={(value) =>
                setDraft((current) => ({
                  ...current,
                  event: value === "all" ? "" : value,
                }))
              }
            >
              <SelectTrigger aria-label="Event">
                <SelectValue placeholder="All events" />
              </SelectTrigger>
              <SelectContent>
                {EVENT_OPTIONS.map((event) => (
                  <SelectItem key={event} value={event}>
                    {EVENT_LABELS[event] ?? event}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
          <Field label="Actor user ID" htmlFor="audit-actor" className="lg:col-span-2">
            <Input
              id="audit-actor"
              value={draft.actor_user_id ?? ""}
              onChange={(event) =>
                setDraft((current) => ({
                  ...current,
                  actor_user_id: event.target.value,
                }))
              }
            />
          </Field>
          <Field
            label="Subject type"
            htmlFor="audit-subject-type"
            className="lg:col-span-2"
          >
            <Input
              id="audit-subject-type"
              value={draft.subject_type ?? ""}
              onChange={(event) =>
                setDraft((current) => ({
                  ...current,
                  subject_type: event.target.value,
                }))
              }
            />
          </Field>
          <Field
            label="Subject ID"
            htmlFor="audit-subject-id"
            className="lg:col-span-2"
          >
            <Input
              id="audit-subject-id"
              value={draft.subject_id ?? ""}
              onChange={(event) =>
                setDraft((current) => ({
                  ...current,
                  subject_id: event.target.value,
                }))
              }
            />
          </Field>
          <Field label="From" htmlFor="audit-from" className="lg:col-span-2">
            <Input
              id="audit-from"
              type="date"
              value={draft.from ?? ""}
              onChange={(event) =>
                setDraft((current) => ({
                  ...current,
                  from: event.target.value,
                }))
              }
            />
          </Field>
          <Field label="To" htmlFor="audit-to" className="lg:col-span-2">
            <Input
              id="audit-to"
              type="date"
              value={draft.to ?? ""}
              onChange={(event) =>
                setDraft((current) => ({
                  ...current,
                  to: event.target.value,
                }))
              }
            />
          </Field>
          <div className="flex items-end lg:col-span-2">
            <Button type="submit" className="w-full">
              Apply filters
            </Button>
          </div>
        </AdminToolbar>
      </form>

      {loading ? (
        <p
          className="text-sm text-muted-foreground"
          role="status"
          aria-live="polite"
        >
          Loading audit logs…
        </p>
      ) : null}

      {error ? (
        <p
          className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {error}
        </p>
      ) : null}

      {!loading && !error && logs.length === 0 ? (
        <div
          className="rounded-2xl border border-dashed border-border/80 bg-card/50 px-6 py-16 text-center"
          role="status"
        >
          <p className="text-sm font-medium">No audit logs match</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Adjust the filters and try again.
          </p>
        </div>
      ) : null}

      {!loading && !error && logs.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>When</th>
                <th className={adminThClassName()}>Event</th>
                <th className={adminThClassName()}>Actor</th>
                <th className={adminThClassName()}>Subject</th>
                <th className={adminThClassName()}>Detail</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr
                  key={log.id}
                  className="transition-colors hover:bg-muted/30"
                >
                  <td className={`${adminTdClassName()} text-muted-foreground`}>
                    {log.created_at
                      ? new Date(log.created_at).toLocaleString()
                      : "—"}
                  </td>
                  <td className={`${adminTdClassName()} font-medium`}>
                    {EVENT_LABELS[log.event] ?? log.event}
                  </td>
                  <td className={adminTdClassName()}>
                    {log.actor_user_id ?? "—"}
                  </td>
                  <td className={adminTdClassName()}>
                    {log.subject_type
                      ? `${log.subject_type}:${log.subject_id ?? ""}`
                      : "—"}
                  </td>
                  <td className={adminTdClassName()}>
                    <Link
                      href={`/admin/audit-logs/${log.id}`}
                      className="font-medium text-foreground no-underline hover:text-primary"
                    >
                      View
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </AdminTable>
      ) : null}

      {meta && meta.last_page > 1 ? (
        <div className="flex items-center justify-between gap-3">
          <p className="text-xs text-muted-foreground">
            Page {meta.current_page} of {meta.last_page} ({meta.total} total)
          </p>
          <div className="flex gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={meta.current_page <= 1}
              onClick={() =>
                syncUrl({ ...params, page: Math.max(1, meta.current_page - 1) })
              }
            >
              Previous
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={meta.current_page >= meta.last_page}
              onClick={() =>
                syncUrl({
                  ...params,
                  page: Math.min(meta.last_page, meta.current_page + 1),
                })
              }
            >
              Next
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
