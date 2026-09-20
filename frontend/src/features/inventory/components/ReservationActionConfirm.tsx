"use client";

import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export function ReservationActionConfirm({
  title,
  description,
  confirmLabel,
  pending,
  onConfirm,
  onCancel,
}: {
  title: string;
  description: string;
  confirmLabel: string;
  pending: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  return (
    <Card
      className="border-destructive/30"
      role="alertdialog"
      aria-labelledby="reservation-action-title"
      aria-describedby="reservation-action-desc"
    >
      <CardHeader>
        <CardTitle id="reservation-action-title" className="text-xl">
          {title}
        </CardTitle>
        <CardDescription id="reservation-action-desc">
          {description}
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
          {pending ? "Working…" : confirmLabel}
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
