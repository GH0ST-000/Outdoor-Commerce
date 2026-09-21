import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@/lib/utils";

const badgeVariants = cva(
  "inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-[0.7rem] font-semibold tracking-wide",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary/12 text-primary dark:bg-primary/20 dark:text-primary",
        secondary: "border-border/70 bg-muted text-muted-foreground",
        success:
          "border-transparent bg-emerald-500/12 text-emerald-800 dark:text-emerald-300",
        warning:
          "border-transparent bg-amber-500/15 text-amber-900 dark:text-amber-200",
        danger: "border-transparent bg-destructive/12 text-destructive",
        outline: "border-border/80 bg-transparent text-foreground",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
);

function Badge({
  className,
  variant,
  ...props
}: React.ComponentProps<"span"> & VariantProps<typeof badgeVariants>) {
  return (
    <span
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  );
}

export { Badge, badgeVariants };
