"use client";

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { fetchAdminContext } from "@/features/admin/api/admin-api";
import type { AdminContext as AdminContextData } from "@/features/admin/types/admin-types";
import { ApiClientError } from "@/lib/api-client";

type AdminContextStatus = "idle" | "loading" | "ready" | "forbidden" | "error";

type AdminContextValue = {
  context: AdminContextData | null;
  status: AdminContextStatus;
  error: string | null;
  permissions: string[];
  roles: string[];
  load: () => Promise<void>;
  refresh: () => Promise<void>;
};

const AdminContext = createContext<AdminContextValue | null>(null);

export function AdminContextProvider({ children }: { children: ReactNode }) {
  const [context, setContext] = useState<AdminContextData | null>(null);
  const [status, setStatus] = useState<AdminContextStatus>("idle");
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setStatus("loading");
    setError(null);
    try {
      const next = await fetchAdminContext();
      setContext(next);
      setStatus("ready");
    } catch (err) {
      setContext(null);
      if (
        err instanceof ApiClientError &&
        (err.code === "ADMIN_ACCESS_REQUIRED" ||
          err.code === "PERMISSION_DENIED" ||
          err.status === 403)
      ) {
        setStatus("forbidden");
        setError(err.message);
        return;
      }
      setStatus("error");
      setError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to load admin context.",
      );
    }
  }, []);

  const refresh = useCallback(async () => {
    try {
      const next = await fetchAdminContext();
      setContext(next);
      setStatus("ready");
      setError(null);
    } catch (err) {
      if (
        err instanceof ApiClientError &&
        (err.code === "ADMIN_ACCESS_REQUIRED" || err.status === 403)
      ) {
        setContext(null);
        setStatus("forbidden");
        setError(err.message);
        return;
      }
      throw err;
    }
  }, []);

  const value = useMemo(
    () => ({
      context,
      status,
      error,
      permissions: context?.permissions ?? [],
      roles: context?.roles ?? [],
      load,
      refresh,
    }),
    [context, status, error, load, refresh],
  );

  return (
    <AdminContext.Provider value={value}>{children}</AdminContext.Provider>
  );
}

export function useAdminContext(): AdminContextValue {
  const value = useContext(AdminContext);
  if (!value) {
    throw new Error("useAdminContext must be used within AdminContextProvider");
  }
  return value;
}
