"use client";

import { issueMessage, useCartCopy } from "@/features/cart/copy";
import type { CartIssue } from "@/features/cart/types";

export function CartIssueList({
  issues,
  id,
}: {
  issues: CartIssue[];
  id?: string;
}) {
  const copy = useCartCopy();
  if (issues.length === 0) {
    return null;
  }

  return (
    <ul id={id} className="mt-2 space-y-1 text-sm text-muted-foreground">
      {issues.map((issue) => (
        <li key={`${issue.code}-${issue.item_id ?? "cart"}`}>
          <span className="me-1 inline-block size-1.5 rounded-full bg-[var(--status-warning)] align-middle" />
          {issueMessage(copy, issue.code, issue.message)}
        </li>
      ))}
    </ul>
  );
}
