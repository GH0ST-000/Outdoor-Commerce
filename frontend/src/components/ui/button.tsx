import * as React from "react";
import { Slot } from "@radix-ui/react-slot";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@/lib/utils";
import { Spinner } from "@/components/ui/spinner";

const buttonVariants = cva(
  "relative inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-sm font-semibold tracking-tight transition-[transform,background-color,box-shadow,opacity,color] duration-[var(--duration-control)] ease-[var(--ease-standard)] disabled:pointer-events-none disabled:opacity-55 [&_svg]:pointer-events-none [&_svg]:size-4 shrink-0 outline-none focus-visible:ring-3 focus-visible:ring-ring/30 hover:-translate-y-px active:translate-y-0",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow-sm hover:bg-[var(--action-primary-hover)] hover:shadow-[var(--shadow-raised)]",
        primary:
          "bg-[var(--button-primary-background)] text-[var(--button-primary-foreground)] shadow-sm hover:bg-[var(--button-primary-hover)] hover:shadow-[var(--shadow-raised)]",
        secondary:
          "border border-border/80 bg-secondary/80 text-secondary-foreground hover:bg-secondary",
        outline:
          "border border-border/90 bg-card/70 text-foreground hover:bg-muted/80 hover:border-border",
        ghost: "text-foreground hover:bg-muted/80",
        accent:
          "bg-accent text-accent-foreground shadow-sm hover:brightness-105 hover:shadow-[var(--shadow-raised)]",
        danger:
          "bg-destructive text-destructive-foreground shadow-sm hover:brightness-110",
        destructive:
          "bg-destructive text-destructive-foreground shadow-sm hover:brightness-110",
        link: "h-auto rounded-sm px-0 text-foreground underline underline-offset-4 hover:text-accent",
      },
      size: {
        default: "h-10 px-4",
        sm: "h-9 rounded-full px-3 text-xs",
        lg: "h-11 rounded-full px-5 text-base",
        icon: "size-10",
      },
      fullWidth: {
        true: "w-full",
        false: "",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
      fullWidth: false,
    },
  },
);

export type ButtonProps = React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean;
    loading?: boolean;
    loadingLabel?: string;
    leadingIcon?: React.ReactNode;
    trailingIcon?: React.ReactNode;
  };

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  {
    className,
    variant,
    size,
    fullWidth,
    asChild = false,
    loading = false,
    loadingLabel,
    leadingIcon,
    trailingIcon,
    children,
    disabled,
    ...props
  },
  ref,
) {
  const isDisabled = disabled || loading;
  const classes = cn(buttonVariants({ variant, size, fullWidth, className }));

  if (asChild) {
    return (
      <Slot
        data-slot="button"
        className={classes}
        ref={ref}
        aria-disabled={isDisabled || undefined}
        {...props}
      >
        {children}
      </Slot>
    );
  }

  return (
    <button
      data-slot="button"
      className={classes}
      ref={ref}
      disabled={isDisabled}
      aria-busy={loading || undefined}
      {...props}
    >
      {loading ? (
        <Spinner label={loadingLabel} className="absolute" />
      ) : (
        leadingIcon
      )}
      <span
        className={cn("inline-flex items-center gap-2", loading && "invisible")}
      >
        {children}
      </span>
      {loading ? null : trailingIcon}
    </button>
  );
});

export { Button, buttonVariants };
