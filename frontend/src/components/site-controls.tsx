"use client";

import { useTheme } from "next-themes";
import { Languages, Moon, Sun } from "lucide-react";
import { useSyncExternalStore } from "react";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useLocale } from "@/components/locale-provider";
import { localeLabels, locales, type Locale } from "@/i18n/dictionaries";
import { cn } from "@/lib/utils";

type SiteControlsProps = {
  className?: string;
  tone?: "light" | "dark";
};

const emptySubscribe = () => () => {};

export function SiteControls({ className, tone = "dark" }: SiteControlsProps) {
  const { locale, setLocale, t } = useLocale();
  const { theme, setTheme, resolvedTheme } = useTheme();
  const mounted = useSyncExternalStore(
    emptySubscribe,
    () => true,
    () => false,
  );

  const isDarkSurface = tone === "dark";

  return (
    <div className={cn("flex items-center gap-2", className)}>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant={isDarkSurface ? "secondary" : "outline"}
            size="sm"
            className={cn(
              "gap-2 backdrop-blur-md",
              isDarkSurface &&
                "border-white/20 bg-white/10 text-white hover:bg-white/15",
            )}
            aria-label={t.common.language}
          >
            <Languages className="size-4" />
            <span className="hidden sm:inline">{localeLabels[locale]}</span>
            <span className="sm:hidden">{locale.toUpperCase()}</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuLabel>{t.common.language}</DropdownMenuLabel>
          <DropdownMenuSeparator />
          {locales.map((code: Locale) => (
            <DropdownMenuItem
              key={code}
              onClick={() => setLocale(code)}
              className={locale === code ? "bg-muted" : undefined}
            >
              {localeLabels[code]}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant={isDarkSurface ? "secondary" : "outline"}
            size="icon"
            className={cn(
              "backdrop-blur-md",
              isDarkSurface &&
                "border-white/20 bg-white/10 text-white hover:bg-white/15",
            )}
            aria-label={t.common.theme}
          >
            {mounted && (resolvedTheme === "dark" || theme === "dark") ? (
              <Moon className="size-4" />
            ) : (
              <Sun className="size-4" />
            )}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuLabel>{t.common.theme}</DropdownMenuLabel>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={() => setTheme("light")}>
            {t.common.light}
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => setTheme("dark")}>
            {t.common.dark}
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => setTheme("system")}>
            {t.common.system}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  );
}
