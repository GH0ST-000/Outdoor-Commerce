import { cn } from "@/lib/utils";

export function AdminToolbar({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "grid gap-3 rounded-2xl border border-border/70 bg-card/80 p-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6",
        className,
      )}
    >
      {children}
    </div>
  );
}

export function AdminTable({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "overflow-hidden rounded-2xl border border-border/70 bg-card/95 shadow-[0_12px_40px_-24px_rgba(0,0,0,0.4)]",
        className,
      )}
    >
      <div className="overflow-x-auto">{children}</div>
    </div>
  );
}

export function adminTableClassName() {
  return "w-full min-w-[720px] text-left text-sm";
}

export function adminThClassName() {
  return "border-b border-border/60 bg-muted/40 px-4 py-3 text-[0.7rem] font-semibold uppercase tracking-[0.08em] text-muted-foreground";
}

export function adminTdClassName() {
  return "border-b border-border/40 px-4 py-3.5 align-middle last:border-0";
}
