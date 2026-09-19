"use client";

import type { ProductVariantListItem } from "@/features/catalog/variants/types/variant-types";
import { ColorSwatch } from "@/features/catalog/attributes/components/ColorSwatch";
import {
  AdminTable,
  adminTableClassName,
  adminTdClassName,
  adminThClassName,
} from "@/features/admin/ui/AdminTable";
import { StatusBadge } from "@/features/admin/ui/StatusBadge";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";

export function VariantsTable({
  variants,
  error,
  canManage,
  pending,
  onEdit,
  onSetDefault,
  onArchive,
  onRestore,
}: {
  variants: ProductVariantListItem[] | null;
  error: string | null;
  canManage: boolean;
  pending: boolean;
  onEdit: (variant: ProductVariantListItem) => void;
  onSetDefault: (variant: ProductVariantListItem) => void;
  onArchive: (variant: ProductVariantListItem) => void;
  onRestore: (variant: ProductVariantListItem) => void;
}) {
  if (error) {
    return (
      <p className="text-sm text-destructive" role="alert">
        {error}
      </p>
    );
  }

  if (variants === null) {
    return (
      <p
        className="text-sm text-muted-foreground"
        role="status"
        aria-live="polite"
      >
        Loading variants…
      </p>
    );
  }

  if (variants.length === 0) {
    return (
      <p className="text-sm text-muted-foreground" role="status">
        No variants yet.
      </p>
    );
  }

  return (
    <AdminTable>
      <table className={adminTableClassName()}>
        <thead>
          <tr>
            <th className={adminThClassName()}>SKU</th>
            <th className={adminThClassName()}>Combination</th>
            <th className={adminThClassName()}>Barcode</th>
            <th className={adminThClassName()}>Status</th>
            <th className={adminThClassName()}>Actions</th>
          </tr>
        </thead>
        <tbody>
          {variants.map((variant) => {
            const isDeleted = Boolean(variant.deleted_at);
            return (
              <tr key={variant.id}>
                <td className={adminTdClassName()}>
                  <div className="flex flex-wrap items-center gap-2">
                    <span className="font-mono text-xs">{variant.sku}</span>
                    {variant.is_default ? (
                      <Badge variant="default">Default</Badge>
                    ) : null}
                  </div>
                </td>
                <td className={adminTdClassName()}>
                  <div className="flex flex-wrap items-center gap-2 text-sm">
                    {variant.attribute_values.length === 0 ? (
                      <span className="text-muted-foreground">—</span>
                    ) : (
                      variant.attribute_values.map((pair) => (
                        <span
                          key={`${pair.attribute_id}-${pair.attribute_value_id}`}
                          className="inline-flex items-center gap-1 rounded-md border border-border/70 bg-muted/40 px-2 py-0.5 text-xs"
                        >
                          <span className="text-muted-foreground">
                            {pair.attribute_name ?? pair.attribute_code}:
                          </span>
                          <span>
                            {pair.attribute_value_name ??
                              pair.attribute_value_code}
                          </span>
                          {pair.color_hex ? (
                            <ColorSwatch hex={pair.color_hex} />
                          ) : null}
                        </span>
                      ))
                    )}
                  </div>
                </td>
                <td className={`${adminTdClassName()} text-muted-foreground`}>
                  {variant.barcode ?? "—"}
                </td>
                <td className={adminTdClassName()}>
                  <StatusBadge status={variant.status} />
                </td>
                <td className={adminTdClassName()}>
                  <div className="flex flex-wrap gap-2">
                    {canManage && !isDeleted ? (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={pending}
                        onClick={() => onEdit(variant)}
                      >
                        Edit
                      </Button>
                    ) : null}
                    {canManage && !isDeleted && !variant.is_default ? (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={pending}
                        onClick={() => onSetDefault(variant)}
                      >
                        Set default
                      </Button>
                    ) : null}
                    {canManage && !isDeleted ? (
                      <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        disabled={pending}
                        onClick={() => onArchive(variant)}
                      >
                        Archive
                      </Button>
                    ) : null}
                    {canManage && isDeleted ? (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={pending}
                        onClick={() => onRestore(variant)}
                      >
                        Restore
                      </Button>
                    ) : null}
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </AdminTable>
  );
}
