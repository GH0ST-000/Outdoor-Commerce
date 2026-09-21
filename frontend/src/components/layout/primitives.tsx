import type { ComponentProps, ReactNode } from "react";
import { cn } from "@/lib/utils";
import type { SurfaceName } from "@/lib/design-system/tokens";

const widths = {
  readable: "sf-container-readable",
  standard: "sf-container",
  wide: "sf-container-wide",
  full: "w-full",
} as const;

export function Container({
  width = "standard",
  className,
  ...props
}: ComponentProps<"div"> & { width?: keyof typeof widths }) {
  return <div className={cn(widths[width], className)} {...props} />;
}

export function Section({
  className,
  surface,
  ...props
}: ComponentProps<"section"> & { surface?: SurfaceName }) {
  return (
    <section
      data-surface={surface}
      className={cn("sf-section", className)}
      {...props}
    />
  );
}

export function Surface({
  name,
  className,
  ...props
}: ComponentProps<"div"> & { name: SurfaceName }) {
  return <div data-surface={name} className={className} {...props} />;
}

const stackGaps = {
  1: "gap-1",
  2: "gap-2",
  3: "gap-3",
  4: "gap-4",
  5: "gap-5",
  6: "gap-6",
  8: "gap-8",
} as const;

const inlineGaps = {
  1: "gap-1",
  2: "gap-2",
  3: "gap-3",
  4: "gap-4",
  6: "gap-6",
} as const;

export function Stack({
  gap = 4,
  className,
  ...props
}: ComponentProps<"div"> & { gap?: keyof typeof stackGaps }) {
  return (
    <div
      className={cn("flex flex-col", stackGaps[gap], className)}
      {...props}
    />
  );
}

export function Inline({
  gap = 2,
  className,
  ...props
}: ComponentProps<"div"> & { gap?: keyof typeof inlineGaps }) {
  return (
    <div
      className={cn("flex flex-wrap items-center", inlineGaps[gap], className)}
      {...props}
    />
  );
}

export function Cluster(props: ComponentProps<typeof Inline>) {
  return <Inline {...props} />;
}

export function Grid({
  className,
  columns = "responsive",
  ...props
}: ComponentProps<"div"> & {
  columns?: "2" | "3" | "4" | "responsive";
}) {
  return (
    <div
      className={cn(
        "grid gap-4",
        columns === "2" && "grid-cols-1 sm:grid-cols-2",
        columns === "3" && "grid-cols-1 min-[420px]:grid-cols-2 md:grid-cols-3",
        columns === "4" && "grid-cols-2 md:grid-cols-3 xl:grid-cols-4",
        columns === "responsive" &&
          "grid-cols-1 min-[420px]:grid-cols-2 md:grid-cols-3 lg:grid-cols-4",
        className,
      )}
      {...props}
    />
  );
}

export function ResponsiveGrid(props: ComponentProps<typeof Grid>) {
  return <Grid columns="responsive" {...props} />;
}

export function Bleed({ className, ...props }: ComponentProps<"div">) {
  return (
    <div
      className={cn("relative start-1/2 w-screen -translate-x-1/2", className)}
      {...props}
    />
  );
}

export function SidebarLayout({
  sidebar,
  children,
  className,
}: {
  sidebar: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18.75rem]",
        className,
      )}
    >
      <div className="min-w-0">{children}</div>
      <aside className="hidden lg:block">{sidebar}</aside>
    </div>
  );
}

export function SplitLayout({ className, ...props }: ComponentProps<"div">) {
  return (
    <div
      className={cn("grid gap-8 lg:grid-cols-2 lg:items-center", className)}
      {...props}
    />
  );
}

export function AspectRatio({
  ratio = "4/5",
  className,
  ...props
}: ComponentProps<"div"> & { ratio?: "4/5" | "16/11" | "1/1" | "16/9" }) {
  return (
    <div
      className={cn(
        "relative overflow-hidden",
        ratio === "4/5" && "aspect-[4/5]",
        ratio === "16/11" && "aspect-[16/11]",
        ratio === "1/1" && "aspect-square",
        ratio === "16/9" && "aspect-video",
        className,
      )}
      {...props}
    />
  );
}
