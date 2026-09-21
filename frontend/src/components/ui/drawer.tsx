"use client";

import * as React from "react";
import * as DialogPrimitive from "@radix-ui/react-dialog";
import { cn } from "@/lib/utils";

export const Drawer = DialogPrimitive.Root;
export const DrawerTrigger = DialogPrimitive.Trigger;
export const DrawerClose = DialogPrimitive.Close;
export const DrawerTitle = DialogPrimitive.Title;
export const DrawerDescription = DialogPrimitive.Description;

export function DrawerContent({
  className,
  children,
  side = "right",
  ...props
}: React.ComponentProps<typeof DialogPrimitive.Content> & {
  side?: "right" | "left" | "bottom";
}) {
  return (
    <DialogPrimitive.Portal>
      <DialogPrimitive.Overlay className="fixed inset-0 z-[var(--z-drawer-backdrop)] bg-[color-mix(in_oklab,var(--night-forest)_56%,transparent)] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
      <DialogPrimitive.Content
        className={cn(
          "fixed z-[var(--z-drawer)] flex flex-col bg-card text-foreground shadow-[var(--shadow-overlay)] outline-none",
          "data-[state=open]:animate-in data-[state=closed]:animate-out",
          side === "right" &&
            "inset-y-0 end-0 w-[min(100%,22rem)] data-[state=open]:slide-in-from-right data-[state=closed]:slide-out-to-right",
          side === "left" &&
            "inset-y-0 start-0 w-[min(100%,22rem)] data-[state=open]:slide-in-from-left data-[state=closed]:slide-out-to-left",
          side === "bottom" &&
            "inset-x-0 bottom-0 max-h-[85dvh] rounded-t-[var(--radius-xl)] pb-[var(--space-safe-bottom)] data-[state=open]:slide-in-from-bottom data-[state=closed]:slide-out-to-bottom",
          className,
        )}
        {...props}
      >
        {children}
      </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
  );
}

export function BottomSheet(props: React.ComponentProps<typeof DrawerContent>) {
  return <DrawerContent side="bottom" {...props} />;
}
