"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useSyncExternalStore,
  type ReactNode,
} from "react";
import { useRouter } from "next/navigation";
import { dictionaries, type Locale, locales } from "@/i18n/dictionaries";

type LocaleContextValue = {
  locale: Locale;
  setLocale: (locale: Locale) => void;
  t: (typeof dictionaries)[Locale];
};

const LocaleContext = createContext<LocaleContextValue | null>(null);
const STORAGE_KEY = "outdoor-locale";
const LOCALE_EVENT = "outdoor-locale";

function isLocale(value: string | null): value is Locale {
  return value !== null && (locales as string[]).includes(value);
}

function readLocale(): Locale {
  const match = document.cookie.match(/(?:^|; )outdoor-locale=([^;]*)/);
  const cookie = match ? decodeURIComponent(match[1]) : null;
  if (isLocale(cookie)) {
    return cookie;
  }
  const stored = window.localStorage.getItem(STORAGE_KEY);
  if (isLocale(stored)) {
    return stored;
  }
  return "ka";
}

function getServerLocale(): Locale {
  return "ka";
}

function subscribe(callback: () => void) {
  window.addEventListener("storage", callback);
  window.addEventListener(LOCALE_EVENT, callback);
  return () => {
    window.removeEventListener("storage", callback);
    window.removeEventListener(LOCALE_EVENT, callback);
  };
}

export function LocaleProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const locale = useSyncExternalStore(subscribe, readLocale, getServerLocale);

  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  const setLocale = useCallback(
    (next: Locale) => {
      window.localStorage.setItem(STORAGE_KEY, next);
      document.cookie = `outdoor-locale=${next}; path=/; max-age=31536000; samesite=lax`;
      document.documentElement.lang = next;
      window.dispatchEvent(new Event(LOCALE_EVENT));
      router.refresh();
    },
    [router],
  );

  const value = useMemo(
    () => ({
      locale,
      setLocale,
      t: dictionaries[locale],
    }),
    [locale, setLocale],
  );

  return (
    <LocaleContext.Provider value={value}>{children}</LocaleContext.Provider>
  );
}

export function useLocale() {
  const context = useContext(LocaleContext);
  if (!context) {
    throw new Error("useLocale must be used within LocaleProvider");
  }
  return context;
}
