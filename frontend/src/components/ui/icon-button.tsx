import * as React from "react";
import { Button, type ButtonProps } from "@/components/ui/button";

type IconButtonProps = Omit<
  ButtonProps,
  "size" | "children" | "leadingIcon" | "trailingIcon"
> & {
  label: string;
  children: React.ReactNode;
};

export const IconButton = React.forwardRef<HTMLButtonElement, IconButtonProps>(
  function IconButton({ label, children, ...props }, ref) {
    return (
      <Button ref={ref} size="icon" aria-label={label} {...props}>
        {children}
      </Button>
    );
  },
);
