import { cn } from "@/lib/utils";

type AdminPanelProps = {
  children: React.ReactNode;
  className?: string;
  padding?: "none" | "sm" | "md";
};

export function AdminPanel({
  children,
  className,
  padding = "md",
}: AdminPanelProps) {
  return (
    <section
      className={cn(
        "overflow-hidden rounded-2xl border border-border/70 bg-card/95 shadow-[0_1px_0_rgba(255,255,255,0.04)_inset,0_12px_40px_-24px_rgba(0,0,0,0.45)]",
        padding === "sm" && "p-4",
        padding === "md" && "p-5 sm:p-6",
        padding === "none" && "p-0",
        className,
      )}
    >
      {children}
    </section>
  );
}

type AdminPanelHeaderProps = {
  title: string;
  description?: string;
  actions?: React.ReactNode;
  className?: string;
};

export function AdminPanelHeader({
  title,
  description,
  actions,
  className,
}: AdminPanelHeaderProps) {
  return (
    <div
      className={cn(
        "mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between",
        className,
      )}
    >
      <div className="space-y-1">
        <h2 className="text-base font-semibold tracking-tight">{title}</h2>
        {description ? (
          <p className="text-sm text-muted-foreground">{description}</p>
        ) : null}
      </div>
      {actions ? (
        <div className="flex flex-wrap items-center gap-2">{actions}</div>
      ) : null}
    </div>
  );
}
