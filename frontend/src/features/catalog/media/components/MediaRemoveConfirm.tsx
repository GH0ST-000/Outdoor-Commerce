"use client";

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
  return (
    <Card
      className="border-destructive/30"
      role="alertdialog"
      aria-labelledby="media-remove-title"
      aria-describedby="media-remove-desc"
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
  );
}
