"use client";

import { useState } from "react";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import {
  LayoutDashboard,
  LogOut,
  Menu,
  Package,
  ScrollText,
  Shield,
  Tags,
  Users,
  X,
} from "lucide-react";
import { useAuth } from "@/features/auth/hooks/use-auth";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";
import {
  filterAdminNav,
  groupAdminNav,
  isAdminNavActive,
  type AdminNavItem,
} from "@/features/admin/navigation/admin-nav";
import { roleLabel } from "@/features/admin/permissions/permission-labels";
import { SiteControls } from "@/components/site-controls";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const NAV_ICONS: Record<string, typeof LayoutDashboard> = {
  dashboard: LayoutDashboard,
  products: Package,
  attributes: Tags,
  users: Users,
  roles: Shield,
  "audit-logs": ScrollText,
};

function NavLink({
  item,
  onNavigate,
}: {
  item: AdminNavItem;
  onNavigate?: () => void;
}) {
  const pathname = usePathname();
  const active = isAdminNavActive(pathname, item.href);
  const Icon = NAV_ICONS[item.id] ?? LayoutDashboard;

  return (
    <Link
      href={item.href}
      onClick={onNavigate}
      aria-current={active ? "page" : undefined}
      className={cn(
        "group relative flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium no-underline transition-colors",
        active
          ? "bg-white/12 text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.08)]"
          : "text-white/65 hover:bg-white/6 hover:text-white",
      )}
    >
      {active ? (
        <span
          aria-hidden
          className="absolute top-1/2 left-0 h-5 w-0.5 -translate-y-1/2 rounded-full bg-[var(--accent)]"
        />
      ) : null}
      <Icon className="size-4 shrink-0 opacity-90" aria-hidden />
      <span>{item.label}</span>
    </Link>
  );
}

function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
  const { permissions } = useAdminContext();
  const sections = groupAdminNav(filterAdminNav(permissions));

  return (
    <div className="flex flex-col gap-6">
      {sections.map((section) => (
        <div key={section.id}>
          <p className="mb-2 px-3 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-white/35">
            {section.label}
          </p>
          <ul className="flex flex-col gap-1">
            {section.items.map((item) => (
              <li key={item.id}>
                <NavLink item={item} onNavigate={onNavigate} />
              </li>
            ))}
          </ul>
        </div>
      ))}
    </div>
  );
}

export function AdminShell({ children }: { children: React.ReactNode }) {
  const { context, roles } = useAdminContext();
  const { logout } = useAuth();
  const router = useRouter();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [signingOut, setSigningOut] = useState(false);

  const user = context?.user;
  const displayName = user
    ? `${user.first_name} ${user.last_name}`
    : "Administrator";
  const roleText =
    roles.length > 0 ? roles.map(roleLabel).join(" · ") : "No roles";

  async function onLogout() {
    if (signingOut) return;
    setSigningOut(true);
    try {
      await logout();
      router.replace("/login");
    } catch {
      setSigningOut(false);
    }
  }

  return (
    <div className="min-h-screen bg-[radial-gradient(1200px_600px_at_10%_-10%,rgba(47,111,94,0.14),transparent_55%),radial-gradient(900px_500px_at_90%_0%,rgba(212,161,92,0.1),transparent_50%),var(--background)]">
      <a
        href="#admin-main"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-card focus:px-3 focus:py-2 focus:text-sm focus:shadow"
      >
        Skip to main content
      </a>

      <div className="mx-auto flex min-h-screen max-w-[1400px]">
        <aside
          className="sticky top-0 hidden h-screen w-[272px] shrink-0 flex-col bg-[linear-gradient(180deg,#0c1a16_0%,#10231d_55%,#0d1b17_100%)] px-4 py-5 text-white md:flex"
          aria-label="Admin navigation"
        >
          <div className="mb-8 px-2">
            <p className="font-[family-name:var(--font-display)] text-2xl font-medium tracking-tight">
              Hunt
            </p>
            <p className="mt-1 text-xs text-white/45">Operations console</p>
          </div>

          <nav aria-label="Primary" className="flex-1 overflow-y-auto">
            <NavLinks />
          </nav>

          <div className="mt-6 rounded-2xl border border-white/8 bg-white/4 p-3">
            <p className="truncate text-sm font-medium">{displayName}</p>
            <p className="mt-0.5 truncate text-xs text-white/45">{roleText}</p>
            <p className="mt-1 truncate text-xs text-white/35">{user?.email}</p>
          </div>
        </aside>

        <div className="flex min-w-0 flex-1 flex-col">
          <header className="sticky top-0 z-20 border-b border-border/50 bg-background/75 px-4 py-3 backdrop-blur-xl sm:px-6">
            <div className="flex items-center justify-between gap-3">
              <div className="flex items-center gap-2 md:hidden">
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  aria-expanded={mobileOpen}
                  aria-controls="admin-mobile-nav"
                  aria-label={mobileOpen ? "Close menu" : "Open menu"}
                  onClick={() => setMobileOpen((open) => !open)}
                >
                  {mobileOpen ? <X /> : <Menu />}
                </Button>
                <div>
                  <p className="font-[family-name:var(--font-display)] text-lg font-medium leading-none">
                    Hunt
                  </p>
                  <p className="mt-1 text-[0.7rem] text-muted-foreground">
                    Operations
                  </p>
                </div>
              </div>

              <div className="hidden min-w-0 flex-1 md:block">
                <p className="truncate text-sm font-medium">{displayName}</p>
                <p className="truncate text-xs text-muted-foreground">
                  {roleText}
                </p>
              </div>

              <div className="flex items-center gap-2">
                <SiteControls tone="light" />
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => void onLogout()}
                  disabled={signingOut}
                >
                  <LogOut />
                  {signingOut ? "Signing out…" : "Sign out"}
                </Button>
              </div>
            </div>

            {mobileOpen ? (
              <nav
                id="admin-mobile-nav"
                aria-label="Mobile"
                className="mt-3 rounded-2xl border border-border/60 bg-[linear-gradient(180deg,#0c1a16_0%,#10231d_100%)] p-3 md:hidden"
              >
                <NavLinks onNavigate={() => setMobileOpen(false)} />
              </nav>
            ) : null}
          </header>

          <main
            id="admin-main"
            tabIndex={-1}
            className="flex-1 px-4 py-6 outline-none sm:px-6 sm:py-8"
          >
            <div className="mx-auto w-full max-w-6xl animate-[rise_420ms_var(--ease-out)]">
              {children}
            </div>
          </main>
        </div>
      </div>
    </div>
  );
}
