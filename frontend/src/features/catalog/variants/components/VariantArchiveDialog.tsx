"use client";

import { useState } from "react";
import type { ProductVariantListItem } from "@/features/catalog/variants/types/variant-types";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Field } from "@/components/ui/field";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

export function VariantArchiveDialog({
  variant,
  candidates,
  pending,
  onConfirm,
  onCancel,
}: {
  variant: ProductVariantListItem;
  candidates: ProductVariantListItem[];
  pending: boolean;
  onConfirm: (replacementVariantId: number | null) => void;
  onCancel: () => void;
}) {
  const [replacement, setReplacement] = useState<string>("auto");

  return (
    <Card
      className="border-destructive/30"
      role="alertdialog"
      aria-labelledby="archive-variant-title"
      aria-describedby="archive-variant-desc"
    >
      <CardHeader>
        <CardTitle id="archive-variant-title" className="text-xl">
          Archive variant?
        </CardTitle>
        <CardDescription id="archive-variant-desc">
          Archive {variant.sku}? It will be soft-deleted and can be restored
          later.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {variant.is_default ? (
          <Field
            label="Replacement default"
            htmlFor="archive-variant-replacement"
            hint="This is the default variant. Pick which variant takes over, or let the backend choose."
            className="max-w-sm"
          >
            <Select value={replacement} onValueChange={setReplacement}>
              <SelectTrigger
                id="archive-variant-replacement"
                aria-label="Replacement default"
              >
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="auto">Choose automatically</SelectItem>
                {candidates.map((candidate) => (
                  <SelectItem key={candidate.id} value={String(candidate.id)}>
                    {candidate.sku}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </Field>
        ) : null}
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="destructive"
            size="sm"
            disabled={pending}
            onClick={() =>
              onConfirm(replacement === "auto" ? null : Number(replacement))
            }
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
        </div>
      </CardContent>
    </Card>
  );
}
