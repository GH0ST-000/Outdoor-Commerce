"use client";

import { FormEvent, useEffect, useId, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  activateAdminPromotion,
  createAdminPromotion,
  fetchAdminPromotion,
  previewAdminPromotion,
  previewAdminPromotionById,
  updateAdminPromotion,
  updateAdminPromotionTargets,
} from "@/features/pricing/api/promotions-api";
import { basisPointsToPercentLabel, formatMoneyMinor } from "@/features/pricing/lib/money";
import {
  buildPromotionWritePayload,
  mapApiFieldErrors,
} from "@/features/pricing/schemas/pricing-schemas";
import type {
  DiscountType,
  PromotionPreviewResult,
  PromotionTarget,
  PromotionTargetMode,
  PromotionTargetType,
  StackingMode,
} from "@/features/pricing/types/pricing-types";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";
import { hasPermission } from "@/features/admin/permissions/has-permission";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";

function toDatetimeLocalValue(iso: string | null | undefined): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function emptyTarget(mode: PromotionTargetMode): PromotionTarget {
  return {
    target_type: "product",
    target_id: null,
    mode,
  };
}

export function PromotionFormPage({
  mode,
  promotionId,
}: {
  mode: "create" | "edit";
  promotionId?: string;
}) {
  const router = useRouter();
  const formId = useId();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.PROMOTIONS_MANAGE);
  const canPublish = hasPermission(
    permissions,
    PERMISSIONS.PROMOTIONS_PUBLISH,
  );

  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [discountType, setDiscountType] = useState<DiscountType>("percentage");
  const [percentageInput, setPercentageInput] = useState("");
  const [fixedAmountInput, setFixedAmountInput] = useState("");
  const [maximumDiscountInput, setMaximumDiscountInput] = useState("");
  const [currencyCode, setCurrencyCode] = useState("GEL");
  const [priority, setPriority] = useState("0");
  const [stackingMode, setStackingMode] = useState<StackingMode>("exclusive");
  const [startsAt, setStartsAt] = useState(
    toDatetimeLocalValue(new Date().toISOString()),
  );
  const [endsAt, setEndsAt] = useState("");
  const [includeTargets, setIncludeTargets] = useState<PromotionTarget[]>([
    emptyTarget("include"),
  ]);
  const [excludeTargets, setExcludeTargets] = useState<PromotionTarget[]>([]);
  const [previewVariantIds, setPreviewVariantIds] = useState("");
  const [previewResult, setPreviewResult] = useState<PromotionPreviewResult | null>(
    null,
  );

  const [loading, setLoading] = useState(mode === "edit");
  const [pending, setPending] = useState(false);
  const [previewPending, setPreviewPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [status, setStatus] = useState<string>("draft");

  useEffect(() => {
    if (mode !== "edit" || !promotionId) return;
    let cancelled = false;
    void fetchAdminPromotion(promotionId)
      .then((promotion) => {
        if (cancelled) return;
        setCode(promotion.code);
        setName(promotion.name);
        setDescription(promotion.description ?? "");
        setDiscountType(promotion.discount_type);
        setCurrencyCode(promotion.currency_code ?? "GEL");
        setPriority(String(promotion.priority));
        setStackingMode(promotion.stacking_mode);
        setStartsAt(toDatetimeLocalValue(promotion.starts_at));
        setEndsAt(toDatetimeLocalValue(promotion.ends_at));
        setStatus(promotion.status);
        if (promotion.discount_type === "percentage") {
          setPercentageInput(
            basisPointsToPercentLabel(
              promotion.percentage_basis_points ?? 0,
            ).replace("%", ""),
          );
        } else if (promotion.fixed_amount_minor != null) {
          setFixedAmountInput(String(promotion.fixed_amount_minor / 100));
        }
        if (promotion.maximum_discount_minor != null) {
          setMaximumDiscountInput(String(promotion.maximum_discount_minor / 100));
        }
        const targets = promotion.targets ?? [];
        const includes = targets.filter((target) => target.mode === "include");
        const excludes = targets.filter((target) => target.mode === "exclude");
        setIncludeTargets(includes.length > 0 ? includes : [emptyTarget("include")]);
        setExcludeTargets(excludes);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setFormError(
          err instanceof ApiClientError
            ? err.message
            : "Unable to load promotion.",
        );
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [mode, promotionId]);

  function buildPayload() {
    return buildPromotionWritePayload({
      general: {
        code,
        name,
        description,
        priority,
        stacking_mode: stackingMode,
        starts_at: startsAt,
        ends_at: endsAt,
        currency_code: currencyCode,
      },
      discount: {
        discount_type: discountType,
        percentage_input: percentageInput,
        fixed_amount_input: fixedAmountInput,
        currency_code: currencyCode,
        maximum_discount_input: maximumDiscountInput,
      },
    });
  }

  function normalizeTargets(targets: PromotionTarget[]): PromotionTarget[] {
    return targets
      .filter(
        (target) =>
          target.target_type === "all_products" || target.target_id != null,
      )
      .map((target) => ({
        target_type: target.target_type,
        target_id:
          target.target_type === "all_products" ? null : target.target_id,
        mode: target.mode,
      }));
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!canManage || pending) return;

    const built = buildPayload();
    setErrors(built.errors);
    if (!built.payload) return;

    setPending(true);
    setFormError(null);
    try {
      const targets = normalizeTargets([
        ...includeTargets,
        ...excludeTargets,
      ]);
      let savedId = promotionId;
      if (mode === "create") {
        const created = await createAdminPromotion(built.payload);
        savedId = String(created.id);
      } else if (promotionId) {
        await updateAdminPromotion(promotionId, built.payload);
      }
      if (savedId) {
        await updateAdminPromotionTargets(savedId, { targets });
      }
      router.push("/admin/pricing/promotions");
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to save promotion.");
      }
    } finally {
      setPending(false);
    }
  }

  async function onPreview() {
    if (previewPending) return;
    const built = buildPayload();
    if (!built.payload) {
      setErrors(built.errors);
      return;
    }
    const variantIds = previewVariantIds
      .split(/[,;\s]+/)
      .map((value) => Number(value))
      .filter((value) => Number.isFinite(value) && value > 0);

    setPreviewPending(true);
    setFormError(null);
    try {
      const payload = {
        variant_ids: variantIds,
        promotion: {
          ...built.payload,
          targets: normalizeTargets([...includeTargets, ...excludeTargets]),
        },
      };
      const result =
        mode === "edit" && promotionId
          ? await previewAdminPromotionById(promotionId, payload)
          : await previewAdminPromotion(payload);
      setPreviewResult(result);
    } catch (err) {
      setFormError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to run promotion preview.",
      );
    } finally {
      setPreviewPending(false);
    }
  }

  async function onActivate() {
    if (!canPublish || !promotionId || pending) return;
    setPending(true);
    setFormError(null);
    try {
      await activateAdminPromotion(promotionId);
      setStatus("active");
    } catch (err) {
      setFormError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to activate promotion.",
      );
    } finally {
      setPending(false);
    }
  }

  function renderTargetRows(
    targets: PromotionTarget[],
    modeLabel: PromotionTargetMode,
    onChange: (next: PromotionTarget[]) => void,
  ) {
    return (
      <div className="space-y-3">
        {targets.map((target, index) => (
          <div
            key={`${modeLabel}-${index}`}
            className="grid gap-3 sm:grid-cols-12"
          >
            <Field label="Type" className="sm:col-span-4">
              <Select
                value={target.target_type}
                onValueChange={(value) => {
                  const next = [...targets];
                  next[index] = {
                    ...target,
                    target_type: value as PromotionTargetType,
                    target_id:
                      value === "all_products" ? null : target.target_id,
                  };
                  onChange(next);
                }}
              >
                <SelectTrigger aria-label="Target type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all_products">All products</SelectItem>
                  <SelectItem value="product">Product</SelectItem>
                  <SelectItem value="product_variant">Variant</SelectItem>
                  <SelectItem value="category">Category</SelectItem>
                  <SelectItem value="brand">Brand</SelectItem>
                </SelectContent>
              </Select>
            </Field>
            <Field label="Target id" className="sm:col-span-4">
              <Input
                type="number"
                min={1}
                disabled={target.target_type === "all_products"}
                value={target.target_id ?? ""}
                onChange={(event) => {
                  const next = [...targets];
                  next[index] = {
                    ...target,
                    target_id: event.target.value
                      ? Number(event.target.value)
                      : null,
                  };
                  onChange(next);
                }}
              />
            </Field>
            <div className="flex items-end sm:col-span-4">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange(targets.filter((_, i) => i !== index))}
              >
                Remove
              </Button>
            </div>
          </div>
        ))}
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => onChange([...targets, emptyTarget(modeLabel)])}
        >
          Add target
        </Button>
      </div>
    );
  }

  if (!canManage) {
    return (
      <p className="text-sm text-muted-foreground" role="status">
        You do not have permission to manage promotions.
      </p>
    );
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title={mode === "create" ? "New promotion" : "Edit promotion"}
        description="Configure discount rules, eligibility targets, and publishing."
        actions={
          <Button asChild variant="outline" size="sm">
            <Link href="/admin/pricing/promotions">Back to list</Link>
          </Button>
        }
      />

      {loading ? (
        <p className="text-sm text-muted-foreground" role="status">
          Loading promotion…
        </p>
      ) : null}

      {formError ? (
        <p
          className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {formError}
        </p>
      ) : null}

      {!loading ? (
        <form className="space-y-6" onSubmit={(event) => void onSubmit(event)}>
          <AdminPanel>
            <AdminPanelHeader title="General" />
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Code" htmlFor={`${formId}-code`} error={errors.code}>
                <Input
                  id={`${formId}-code`}
                  value={code}
                  onChange={(event) => setCode(event.target.value)}
                  disabled={mode === "edit"}
                />
              </Field>
              <Field label="Name" htmlFor={`${formId}-name`} error={errors.name}>
                <Input
                  id={`${formId}-name`}
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                />
              </Field>
              <Field label="Description" className="sm:col-span-2">
                <Textarea
                  value={description}
                  onChange={(event) => setDescription(event.target.value)}
                  rows={3}
                />
              </Field>
            </div>
          </AdminPanel>

          <AdminPanel>
            <AdminPanelHeader title="Discount" />
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Discount type">
                <Select
                  value={discountType}
                  onValueChange={(value) =>
                    setDiscountType(value as DiscountType)
                  }
                >
                  <SelectTrigger aria-label="Discount type">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="percentage">Percentage</SelectItem>
                    <SelectItem value="fixed_amount">Fixed amount</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
              <Field label="Currency" htmlFor={`${formId}-currency`}>
                <Input
                  id={`${formId}-currency`}
                  value={currencyCode}
                  onChange={(event) =>
                    setCurrencyCode(event.target.value.toUpperCase())
                  }
                  maxLength={3}
                />
              </Field>
              {discountType === "percentage" ? (
                <Field
                  label="Percentage"
                  htmlFor={`${formId}-percent`}
                  error={errors.percentage}
                >
                  <Input
                    id={`${formId}-percent`}
                    value={percentageInput}
                    onChange={(event) => setPercentageInput(event.target.value)}
                    placeholder="15 or 15.5"
                  />
                </Field>
              ) : (
                <Field
                  label="Fixed amount"
                  htmlFor={`${formId}-fixed`}
                  error={errors.fixed_amount}
                >
                  <Input
                    id={`${formId}-fixed`}
                    value={fixedAmountInput}
                    onChange={(event) => setFixedAmountInput(event.target.value)}
                    placeholder="10.00"
                  />
                </Field>
              )}
              <Field
                label="Maximum discount cap (optional)"
                htmlFor={`${formId}-max`}
                error={errors.maximum_discount}
              >
                <Input
                  id={`${formId}-max`}
                  value={maximumDiscountInput}
                  onChange={(event) =>
                    setMaximumDiscountInput(event.target.value)
                  }
                />
              </Field>
            </div>
          </AdminPanel>

          <AdminPanel>
            <AdminPanelHeader title="Schedule" />
            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                label="Starts at"
                htmlFor={`${formId}-starts`}
                error={errors.starts_at}
              >
                <Input
                  id={`${formId}-starts`}
                  type="datetime-local"
                  value={startsAt}
                  onChange={(event) => setStartsAt(event.target.value)}
                />
              </Field>
              <Field label="Ends at (optional)" htmlFor={`${formId}-ends`}>
                <Input
                  id={`${formId}-ends`}
                  type="datetime-local"
                  value={endsAt}
                  onChange={(event) => setEndsAt(event.target.value)}
                />
              </Field>
            </div>
          </AdminPanel>

          <AdminPanel>
            <AdminPanelHeader title="Targets" />
            {renderTargetRows(includeTargets, "include", setIncludeTargets)}
          </AdminPanel>

          <AdminPanel>
            <AdminPanelHeader title="Exclusions" />
            {excludeTargets.length === 0 ? (
              <p className="mb-3 text-sm text-muted-foreground">
                No exclusions configured.
              </p>
            ) : null}
            {renderTargetRows(excludeTargets, "exclude", setExcludeTargets)}
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="mt-3"
              onClick={() =>
                setExcludeTargets([...excludeTargets, emptyTarget("exclude")])
              }
            >
              Add exclusion
            </Button>
          </AdminPanel>

          <AdminPanel>
            <AdminPanelHeader title="Stacking" />
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Stacking mode">
                <Select
                  value={stackingMode}
                  onValueChange={(value) =>
                    setStackingMode(value as StackingMode)
                  }
                >
                  <SelectTrigger aria-label="Stacking mode">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="exclusive">Exclusive</SelectItem>
                    <SelectItem value="combinable">Combinable</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
              <Field label="Priority" htmlFor={`${formId}-priority`}>
                <Input
                  id={`${formId}-priority`}
                  type="number"
                  min={0}
                  value={priority}
                  onChange={(event) => setPriority(event.target.value)}
                />
              </Field>
            </div>
          </AdminPanel>

          <AdminPanel>
            <AdminPanelHeader title="Preview" />
            <Field label="Variant ids (comma-separated)" htmlFor={`${formId}-preview`}>
              <Input
                id={`${formId}-preview`}
                value={previewVariantIds}
                onChange={(event) => setPreviewVariantIds(event.target.value)}
                placeholder="101, 102"
              />
            </Field>
            <Button
              type="button"
              size="sm"
              variant="secondary"
              className="mt-3"
              disabled={previewPending}
              onClick={() => void onPreview()}
            >
              {previewPending ? "Previewing…" : "Run preview"}
            </Button>
            {previewResult ? (
              <div className="mt-4 space-y-2 text-sm">
                {previewResult.warnings.map((warning) => (
                  <p key={warning.code} className="text-amber-700">
                    {warning.message}
                  </p>
                ))}
                <ul className="space-y-2">
                  {previewResult.samples.map((sample) => (
                    <li
                      key={sample.variant_id}
                      className="rounded-lg border border-border/60 px-3 py-2"
                    >
                      Variant {sample.variant_id}:{" "}
                      {sample.eligible ? "eligible" : "not eligible"}
                      {sample.final_amount_minor != null &&
                      sample.base_amount_minor != null ? (
                        <span className="text-muted-foreground">
                          {" "}
                          — {formatMoneyMinor(sample.base_amount_minor, currencyCode)}{" "}
                          →{" "}
                          {formatMoneyMinor(
                            sample.final_amount_minor,
                            currencyCode,
                          )}
                        </span>
                      ) : null}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </AdminPanel>

          <AdminPanel>
            <AdminPanelHeader title="Publishing" />
            <p className="text-sm text-muted-foreground">
              Current status: <strong>{status}</strong>
            </p>
            {canPublish && mode === "edit" && status === "draft" ? (
              <Button
                type="button"
                size="sm"
                className="mt-3"
                disabled={pending}
                onClick={() => void onActivate()}
              >
                Activate promotion
              </Button>
            ) : null}
          </AdminPanel>

          <div className="flex flex-wrap gap-2">
            <Button type="submit" size="sm" disabled={pending}>
              {pending ? "Saving…" : mode === "create" ? "Create" : "Save"}
            </Button>
            <Button asChild type="button" variant="outline" size="sm">
              <Link href="/admin/pricing/promotions">Cancel</Link>
            </Button>
          </div>
        </form>
      ) : null}
    </div>
  );
}
