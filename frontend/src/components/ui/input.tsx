import * as React from "react";
import { cn } from "@/lib/utils";

const Input = React.forwardRef<HTMLInputElement, React.ComponentProps<"input">>(
  function Input({ className, type, ...props }, ref) {
    return (
      <input
        ref={ref}
        type={type}
        data-slot="input"
        className={cn(
          "flex h-10 w-full rounded-lg border border-[var(--input-border)] bg-background px-3 text-sm text-foreground shadow-[var(--shadow-subtle)] outline-none transition-[border-color,box-shadow,background-color] duration-[var(--duration-control)]",
          "placeholder:text-muted-foreground/80",
          "hover:border-border",
          "focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/25",
          "disabled:cursor-not-allowed disabled:opacity-50",
          "aria-[invalid=true]:border-destructive aria-[invalid=true]:ring-3 aria-[invalid=true]:ring-destructive/20",
          className,
        )}
        {...props}
      />
    );
  },
);

export { Input };
