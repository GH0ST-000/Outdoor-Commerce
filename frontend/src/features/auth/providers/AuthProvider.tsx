"use client";

import {
  createContext,
  startTransition,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import {
  fetchCurrentUser,
  loginCustomer,
  logoutCustomer,
  registerCustomer,
} from "@/features/auth/api/auth-api";
import { syncCartAfterAuthentication } from "@/features/cart/state/cart-actions";
import type {
  AuthenticatedUser,
  LoginPayload,
  RegisterPayload,
} from "@/features/auth/api/auth-types";
import { ApiClientError } from "@/lib/api-client";

type AuthStatus = "loading" | "authenticated" | "unauthenticated";

type AuthContextValue = {
  user: AuthenticatedUser | null;
  status: AuthStatus;
  login: (payload: LoginPayload) => Promise<AuthenticatedUser>;
  register: (payload: RegisterPayload) => Promise<AuthenticatedUser>;
  logout: () => Promise<void>;
  refresh: () => Promise<void>;
  setUser: (user: AuthenticatedUser | null) => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthenticatedUser | null>(null);
  const [status, setStatus] = useState<AuthStatus>("loading");

  const applyUser = useCallback((next: AuthenticatedUser | null) => {
    setUser(next);
    setStatus(next ? "authenticated" : "unauthenticated");
  }, []);

  const refresh = useCallback(async () => {
    try {
      const current = await fetchCurrentUser();
      applyUser(current);
    } catch (error) {
      applyUser(null);
      if (!(error instanceof ApiClientError && error.status === 403)) {
        throw error;
      }
    }
  }, [applyUser]);

  useEffect(() => {
    let cancelled = false;

    startTransition(() => {
      void fetchCurrentUser()
        .then((current) => {
          if (!cancelled) {
            applyUser(current);
          }
        })
        .catch(() => {
          if (!cancelled) {
            applyUser(null);
          }
        });
    });

    return () => {
      cancelled = true;
    };
  }, [applyUser]);

  const login = useCallback(
    async (payload: LoginPayload) => {
      const next = await loginCustomer(payload);
      applyUser(next);
      void syncCartAfterAuthentication(true);
      return next;
    },
    [applyUser],
  );

  const register = useCallback(
    async (payload: RegisterPayload) => {
      const next = await registerCustomer(payload);
      applyUser(next);
      void syncCartAfterAuthentication(true);
      return next;
    },
    [applyUser],
  );

  const logout = useCallback(async () => {
    await logoutCustomer();
    applyUser(null);
    void syncCartAfterAuthentication(false);
  }, [applyUser]);

  const value = useMemo(
    () => ({
      user,
      status,
      login,
      register,
      logout,
      refresh,
      setUser: applyUser,
    }),
    [user, status, login, register, logout, refresh, applyUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within AuthProvider");
  }
  return context;
}
