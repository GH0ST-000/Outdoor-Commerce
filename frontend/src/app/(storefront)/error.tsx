"use client";

import { ErrorState } from "@/components/ui/empty-state";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";

export default function Error({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="sf-container sf-section">
      <ErrorState
        title={storefrontCopy.ka.common.error}
        actionLabel={storefrontCopy.ka.common.retry}
        onAction={reset}
      />
    </div>
  );
}
