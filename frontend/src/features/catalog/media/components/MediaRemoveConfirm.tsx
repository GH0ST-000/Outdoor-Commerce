"use client";

import { useEffect, useRef } from "react";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export function MediaRemoveConfirm({
  filename,
  pending,
  onConfirm,
  onCancel,
}: {
  filename: string;
  pending: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  const confirmRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    confirmRef.current?.focus();

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape" && !pending) {
        onCancel();
      }
    }

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [onCancel, pending]);

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
      onClick={() => {
        if (!pending) {
          onCancel();
        }
      }}
    >
      <Card
        className="w-full max-w-md border-destructive/30 shadow-lg"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="media-remove-title"
        aria-describedby="media-remove-desc"
        onClick={(event) => event.stopPropagation()}
      >
        <CardHeader>
          <CardTitle id="media-remove-title" className="text-lg">
            Remove image?
          </CardTitle>
          <CardDescription id="media-remove-desc">
            Remove {filename}? This deletes the attachment from the gallery.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-2">
          <Button
            ref={confirmRef}
            type="button"
            variant="destructive"
            size="sm"
            disabled={pending}
            onClick={onConfirm}
          >
            {pending ? "Removing…" : "Remove"}
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={pending}
            onClick={onCancel}
          >
            Cancel
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
