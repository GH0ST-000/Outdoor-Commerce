"use client";

import { useEffect, useState } from "react";
import {
  fetchAdminAttributeValues,
  fetchAdminAttributes,
} from "@/features/catalog/attributes/api/attributes-api";
import type {
  AttributeListItem,
  AttributeValueListItem,
} from "@/features/catalog/attributes/types/attribute-types";
import {
  archiveProductVariant,
  fetchProductVariantAxes,
  fetchProductVariants,
  restoreProductVariant,
  setDefaultProductVariant,
} from "@/features/catalog/variants/api/variants-api";
import { CombinationBuilder } from "@/features/catalog/variants/components/CombinationBuilder";
import { VariantArchiveDialog } from "@/features/catalog/variants/components/VariantArchiveDialog";
import { VariantAxesEditor } from "@/features/catalog/variants/components/VariantAxesEditor";
import { VariantFormDialog } from "@/features/catalog/variants/components/VariantFormDialog";
import { VariantMediaPanel } from "@/features/catalog/media/components/VariantMediaPanel";
import { VariantsTable } from "@/features/catalog/variants/components/VariantsTable";
import type {
  ProductVariantListItem,
  VariantAxis,
} from "@/features/catalog/variants/types/variant-types";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";

function messageFrom(error: unknown, fallback: string): string {
  return error instanceof ApiClientError ? error.message : fallback;
}

export function ProductVariantsSection({
  productId,
  canManage,
  canPublish,
}: {
  productId: string;
  canManage: boolean;
  canPublish: boolean;
}) {
  const [axes, setAxes] = useState<VariantAxis[] | null>(null);
  const [axesError, setAxesError] = useState<string | null>(null);
  const [attributes, setAttributes] = useState<AttributeListItem[]>([]);
  const [valuesByAxis, setValuesByAxis] = useState<
    Record<number, AttributeValueListItem[]>
  >({});
  const [variants, setVariants] = useState<ProductVariantListItem[] | null>(
    null,
  );
  const [variantsError, setVariantsError] = useState<string | null>(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const [editingVariant, setEditingVariant] =
    useState<ProductVariantListItem | null>(null);
  const [archiveTarget, setArchiveTarget] =
    useState<ProductVariantListItem | null>(null);
  const [pending, setPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [mediaVariant, setMediaVariant] =
    useState<ProductVariantListItem | null>(null);

  const [refreshNonce, setRefreshNonce] = useState(0);
  const reloadVariants = () => setRefreshNonce((value) => value + 1);

  useEffect(() => {
    let cancelled = false;
    void fetchProductVariantAxes(productId)
      .then((response) => {
        if (cancelled) return;
        setAxes(response.axes);
        setAxesError(null);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setAxes([]);
        setAxesError(messageFrom(err, "Unable to load variant axes."));
      });
    return () => {
      cancelled = true;
    };
  }, [productId]);

  useEffect(() => {
    let cancelled = false;
    void fetchAdminAttributes({ per_page: 50, sort: "sort_order" })
      .then((response) => {
        if (cancelled) return;
        setAttributes(response.data);
      })
      .catch(() => {
        if (cancelled) return;
        setAttributes([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    let cancelled = false;
    void fetchProductVariants(productId, {
      per_page: 50,
      sort: "sort_order",
      direction: "asc",
      include_deleted: "1",
    })
      .then((response) => {
        if (cancelled) return;
        setVariants(response.data);
        setVariantsError(null);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setVariants([]);
        setVariantsError(messageFrom(err, "Unable to load variants."));
      });
    return () => {
      cancelled = true;
    };
  }, [productId, refreshNonce]);

  const axisIdsKey = (axes ?? []).map((axis) => axis.attribute_id).join(",");

  useEffect(() => {
    let cancelled = false;
    const ids = axisIdsKey === "" ? [] : axisIdsKey.split(",").map(Number);

    void Promise.all(
      ids.map(async (attributeId) => {
        try {
          const response = await fetchAdminAttributeValues(attributeId, {
            per_page: 50,
            sort: "sort_order",
            direction: "asc",
          });
          return [attributeId, response.data] as const;
        } catch {
          return [attributeId, [] as AttributeValueListItem[]] as const;
        }
      }),
    ).then((entries) => {
      if (cancelled) return;
      setValuesByAxis(Object.fromEntries(entries));
    });

    return () => {
      cancelled = true;
    };
  }, [axisIdsKey]);

  async function onSetDefault(variant: ProductVariantListItem) {
    if (pending) return;
    setPending(true);
    setActionError(null);
    setNotice(null);
    try {
      await setDefaultProductVariant(productId, variant.id);
      setNotice(`${variant.sku} is now the default variant.`);
      reloadVariants();
    } catch (err) {
      setActionError(messageFrom(err, "Unable to set the default variant."));
    } finally {
      setPending(false);
    }
  }

  async function onArchiveConfirm(replacementVariantId: number | null) {
    if (!archiveTarget || pending) return;
    setPending(true);
    setActionError(null);
    setNotice(null);
    try {
      await archiveProductVariant(
        productId,
        archiveTarget.id,
        replacementVariantId,
      );
      setArchiveTarget(null);
      setNotice("Variant archived.");
      reloadVariants();
    } catch (err) {
      setActionError(messageFrom(err, "Unable to archive the variant."));
    } finally {
      setPending(false);
    }
  }

  async function onRestore(variant: ProductVariantListItem) {
    if (pending) return;
    setPending(true);
    setActionError(null);
    setNotice(null);
    try {
      await restoreProductVariant(productId, variant.id);
      setNotice("Variant restored to draft.");
      reloadVariants();
    } catch (err) {
      setActionError(messageFrom(err, "Unable to restore the variant."));
    } finally {
      setPending(false);
    }
  }

  const currentAxes = axes ?? [];

  return (
    <div className="space-y-6">
      {actionError ? (
        <p
          className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {actionError}
        </p>
      ) : null}
      {notice ? (
        <p
          className="rounded-xl border border-border/70 bg-muted/40 px-4 py-3 text-sm"
          role="status"
        >
          {notice}
        </p>
      ) : null}

      {axes === null ? (
        <p
          className="text-sm text-muted-foreground"
          role="status"
          aria-live="polite"
        >
          Loading variant axes…
        </p>
      ) : axesError ? (
        <p className="text-sm text-destructive" role="alert">
          {axesError}
        </p>
      ) : (
        <VariantAxesEditor
          key={axisIdsKey}
          productId={productId}
          axes={currentAxes}
          attributes={attributes}
          canManage={canManage}
          onSaved={(next) => {
            setAxes(next.axes);
            reloadVariants();
          }}
        />
      )}

      {currentAxes.length > 0 ? (
        <CombinationBuilder
          productId={productId}
          axes={currentAxes}
          valuesByAxis={valuesByAxis}
          canManage={canManage}
          onGenerated={(message) => {
            setNotice(message);
            reloadVariants();
          }}
        />
      ) : null}

      {editorOpen ? (
        <VariantFormDialog
          productId={productId}
          axes={currentAxes}
          valuesByAxis={valuesByAxis}
          variant={editingVariant}
          canPublish={canPublish}
          onSaved={(message) => {
            setEditorOpen(false);
            setEditingVariant(null);
            setNotice(message);
            reloadVariants();
          }}
          onCancel={() => {
            setEditorOpen(false);
            setEditingVariant(null);
          }}
        />
      ) : null}

      {mediaVariant ? (
        <VariantMediaPanel
          productId={productId}
          variantId={mediaVariant.id}
          variantSku={mediaVariant.sku}
          canManage={canManage}
          onClose={() => setMediaVariant(null)}
        />
      ) : null}

      {archiveTarget ? (
        <VariantArchiveDialog
          variant={archiveTarget}
          candidates={(variants ?? []).filter(
            (item) => item.id !== archiveTarget.id && !item.deleted_at,
          )}
          pending={pending}
          onConfirm={(replacementVariantId) =>
            void onArchiveConfirm(replacementVariantId)
          }
          onCancel={() => setArchiveTarget(null)}
        />
      ) : null}

      <AdminPanel>
        <AdminPanelHeader
          title="Variants"
          description="Every active variant needs one value per axis and a unique SKU."
          actions={
            canManage && currentAxes.length > 0 ? (
              <Button
                type="button"
                size="sm"
                disabled={pending}
                onClick={() => {
                  setEditingVariant(null);
                  setEditorOpen(true);
                }}
              >
                New variant
              </Button>
            ) : null
          }
        />
        <VariantsTable
          variants={variants}
          error={variantsError}
          canManage={canManage}
          pending={pending}
          onEdit={(variant) => {
            setEditingVariant(variant);
            setEditorOpen(true);
          }}
          onSetDefault={(variant) => void onSetDefault(variant)}
          onArchive={(variant) => setArchiveTarget(variant)}
          onRestore={(variant) => void onRestore(variant)}
          onManageMedia={(variant) => setMediaVariant(variant)}
        />
      </AdminPanel>
    </div>
  );
}
