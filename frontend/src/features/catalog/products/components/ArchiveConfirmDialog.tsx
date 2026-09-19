"use client";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export function ArchiveConfirmDialog({
  productLabel,
  pending,
  onConfirm,
  onCancel,
}: {
  productLabel: string;
  pending: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  return (
    <Card
      className="border-destructive/30"
      role="alertdialog"
      aria-labelledby="archive-confirm-title"
      aria-describedby="archive-confirm-desc"
    >
      <CardHeader>
        <CardTitle id="archive-confirm-title" className="text-xl">
          Archive product?
        </CardTitle>
        <CardDescription id="archive-confirm-desc">
          Archive {productLabel}? It will be soft-deleted and can be restored to
          draft later.
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
          {pending ? "Archiving…" : "Confirm archive"}
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
