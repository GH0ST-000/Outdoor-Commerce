import * as React from "react";
import Link from "next/link";
import { cn } from "@/lib/utils";

const variants = {
  inline: "underline underline-offset-4",
  navigation: "no-underline hover:underline",
  subtle:
    "text-muted-foreground no-underline hover:text-foreground hover:underline",
  editorial:
    "underline decoration-transparent underline-offset-4 hover:decoration-current",
  standalone: "font-semibold no-underline hover:underline",
} as const;

export type TextLinkProps = React.ComponentProps<typeof Link> & {
  variant?: keyof typeof variants;
  external?: boolean;
};

export function TextLink({
  className,
  variant = "inline",
  external = false,
  ...props
}: TextLinkProps) {
  return (
    <Link
      className={cn(
        "rounded-sm text-inherit outline-none focus-visible:ring-3 focus-visible:ring-ring/30",
        variants[variant],
        className,
      )}
      {...(external
        ? { target: "_blank", rel: "noopener noreferrer" }
        : undefined)}
      {...props}
    />
  );
}
