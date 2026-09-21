"use client";

import Link from "next/link";
import { forwardRef, type ComponentProps } from "react";
import {
  trackStorefrontEvent,
  type StorefrontEventName,
  type StorefrontEventPayload,
} from "@/features/storefront/analytics/events";

export const TrackedLink = forwardRef<
  HTMLAnchorElement,
  ComponentProps<typeof Link> & {
    event: StorefrontEventName;
    payload?: StorefrontEventPayload;
  }
>(function TrackedLink({ event, payload, ...props }, ref) {
  return (
    <Link
      ref={ref}
      {...props}
      onClick={(click) => {
        trackStorefrontEvent(event, {
          href: typeof props.href === "string" ? props.href : undefined,
          ...payload,
        });
        props.onClick?.(click);
      }}
    />
  );
});
