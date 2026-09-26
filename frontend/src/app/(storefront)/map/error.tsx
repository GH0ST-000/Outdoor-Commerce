"use client";

import { Button } from "@/components/ui/button";

export default function MapError({
  reset,
}: {
  error: Error;
  reset: () => void;
}) {
  return (
    <div
      className="grid h-[calc(100dvh-4rem)] place-items-center px-6 text-center"
      role="alert"
    >
      <div className="max-w-md space-y-3">
        <h1 className="text-2xl font-semibold">
          The legal map could not be shown.
        </h1>
        <p className="text-sm text-muted-foreground">
          Published zone information is still available from the species and
          season pages. A failed map is not permission to hunt or fish.
        </p>
        <Button type="button" onClick={reset}>
          Try again
        </Button>
      </div>
    </div>
  );
}
