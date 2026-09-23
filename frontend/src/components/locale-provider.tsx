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
const COOKIE_MAX_AGE = 31536000;

function emptySubscribe(): () => void {
  return () => undefined;
}

function isLocale(value: string | null | undefined): value is Locale {
  return (
    value !== null &&
    value !== undefined &&
    (locales as string[]).includes(value)
  );
}

function readStoredLocale(): string | null {
  try {
    return window.localStorage.getItem(STORAGE_KEY);
  } catch {
    return null;
  }
}

function writeStoredLocale(next: Locale): void {
  try {
    window.localStorage.setItem(STORAGE_KEY, next);
  } catch {
    // Private mode / sandboxed contexts can deny storage.
  }
}

function writeLocaleCookie(next: Locale): void {
  document.cookie = `${STORAGE_KEY}=${next}; path=/; max-age=${COOKIE_MAX_AGE}; samesite=lax`;
}

function readCookieLocale(): string | null {
  const match = document.cookie.match(/(?:^|; )outdoor-locale=([^;]*)/);
  return match ? decodeURIComponent(match[1]) : null;
}

function readLocale(): Locale {
  const cookie = readCookieLocale();
  if (isLocale(cookie)) {
    return cookie;
  }
  const stored = readStoredLocale();
  if (isLocale(stored)) {
    return stored;
  }
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

export function LocaleProvider({
  children,
  initialLocale = "ka",
}: {
  children: ReactNode;
  initialLocale?: Locale;
}) {
  const router = useRouter();
  const serverLocale = isLocale(initialLocale) ? initialLocale : "ka";
  const hydrated = useSyncExternalStore(
    emptySubscribe,
    () => true,
    () => false,
  );
  const stored = useSyncExternalStore(
    subscribe,
    readLocale,
    () => serverLocale,
  );
  const locale = hydrated ? stored : serverLocale;

  useEffect(() => {
    document.documentElement.lang = locale;
  }, [locale]);

  useEffect(() => {
    if (!isLocale(readCookieLocale())) {
      const storedLocale = readStoredLocale();
      if (isLocale(storedLocale)) {
        writeLocaleCookie(storedLocale);
      }
    }
  }, []);

  const setLocale = useCallback(
    (next: Locale) => {
      writeStoredLocale(next);
      writeLocaleCookie(next);
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
