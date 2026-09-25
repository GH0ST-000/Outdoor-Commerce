import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

const LINKS = [
  { href: "/admin/legal", label: "Dashboard" },
  { href: "/admin/legal/sources", label: "Sources" },
  { href: "/admin/legal/documents", label: "Documents" },
  { href: "/admin/legal/rules", label: "Rules" },
  { href: "/admin/legal/conflicts", label: "Conflicts" },
  { href: "/admin/legal/evaluate", label: "Evaluation preview" },
] as const;

export function LegalWorkspaceNav() {
  const pathname = usePathname();

  return (
    <nav aria-label="Legal workspace" className="flex flex-wrap gap-2">
      {LINKS.map((link) => {
        const active =
          link.href === "/admin/legal"
            ? pathname === "/admin/legal"
            : pathname.startsWith(link.href);
        return (
          <Link
            key={link.href}
            href={link.href}
            className={cn(
              "rounded-full border px-3 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2",
              active
                ? "border-foreground bg-foreground text-background"
                : "border-border hover:bg-muted",
            )}
          >
            {link.label}
          </Link>
        );
      })}
    </nav>
  );
}
