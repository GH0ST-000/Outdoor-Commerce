"use client";

import { ProductMediaSection } from "@/features/catalog/media/components/ProductMediaSection";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { Button } from "@/components/ui/button";

export function VariantMediaPanel({
  productId,
  variantId,
  variantSku,
  canManage,
  onClose,
}: {
  productId: string | number;
  variantId: number;
  variantSku: string;
  canManage: boolean;
  onClose: () => void;
}) {
  return (
    <AdminPanel className="border-primary/20 shadow-lg">
      <AdminPanelHeader
        title={`Variant media · ${variantSku}`}
        description="Optional gallery overrides for this variant. Inherits product media when empty."
        actions={
          <Button type="button" size="sm" variant="outline" onClick={onClose}>
            Close
          </Button>
        }
      />
      <ProductMediaSection
        scope={{ kind: "variant", productId, variantId }}
        canManage={canManage}
        title="Variant gallery"
        description="Images here apply only to this variant SKU."
      />
    </AdminPanel>
  );
}
