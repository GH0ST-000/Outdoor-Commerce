"use client";

import { FormEvent, useEffect, useId, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  createAdminPriceList,
  fetchAdminPriceList,
  updateAdminPriceList,
} from "@/features/pricing/api/price-lists-api";
import { mapApiFieldErrors } from "@/features/pricing/schemas/pricing-schemas";
import type {
  PriceListStatus,
  PriceListWritePayload,
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
import { Switch } from "@/components/ui/switch";

type FormState = {
  code: string;
  name: string;
  currency_code: string;
  status: PriceListStatus;
  is_default: boolean;
  priority: string;
  prices_include_tax: boolean;
};

function emptyForm(): FormState {
  return {
    code: "",
    name: "",
    currency_code: "GEL",
    status: "draft",
    is_default: false,
    priority: "0",
    prices_include_tax: true,
  };
}

function toPayload(form: FormState): PriceListWritePayload {
  return {
    code: form.code.trim(),
    name: form.name.trim(),
    currency_code: form.currency_code.trim().toUpperCase(),
    status: form.status,
    is_default: form.is_default,
    priority: Number(form.priority) || 0,
    prices_include_tax: form.prices_include_tax,
  };
}

export function PriceListFormPage({
  mode,
  priceListId,
}: {
  mode: "create" | "edit";
  priceListId?: string;
}) {
  const router = useRouter();
  const formId = useId();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.PRICING_MANAGE);

  const [form, setForm] = useState<FormState>(emptyForm);
  const [loading, setLoading] = useState(mode === "edit");
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    if (mode !== "edit" || !priceListId) {
      return;
    }
    let cancelled = false;
    void fetchAdminPriceList(priceListId)
      .then((priceList) => {
        if (cancelled) return;
        setForm({
          code: priceList.code,
          name: priceList.name,
          currency_code: priceList.currency_code,
          status: priceList.status,
          is_default: priceList.is_default,
          priority: String(priceList.priority),
          prices_include_tax: priceList.prices_include_tax,
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setFormError(
          err instanceof ApiClientError
            ? err.message
            : "Unable to load price list.",
        );
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [mode, priceListId]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!canManage || pending) return;

    const nextErrors: Record<string, string> = {};
    if (form.code.trim() === "") nextErrors.code = "Code is required.";
    if (form.name.trim() === "") nextErrors.name = "Name is required.";
    if (form.currency_code.trim().length !== 3) {
      nextErrors.currency_code = "Currency must be a 3-letter ISO code.";
    }
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;

    setPending(true);
    setFormError(null);
    try {
      const payload = toPayload(form);
      if (mode === "create") {
        const created = await createAdminPriceList(payload);
        router.push(`/admin/pricing/price-lists/${created.id}/edit`);
        return;
      }
      if (priceListId) {
        await updateAdminPriceList(priceListId, payload);
        router.push("/admin/pricing/price-lists");
      }
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to save price list.");
      }
    } finally {
      setPending(false);
    }
  }

  if (!canManage) {
    return (
      <p className="text-sm text-muted-foreground" role="status">
        You do not have permission to manage price lists.
      </p>
    );
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title={mode === "create" ? "New price list" : "Edit price list"}
        description="Configure currency, tax display, and default list priority."
        actions={
          <Button asChild variant="outline" size="sm">
            <Link href="/admin/pricing/price-lists">Back to list</Link>
          </Button>
        }
      />

      {loading ? (
        <p className="text-sm text-muted-foreground" role="status">
          Loading price list…
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
        <AdminPanel>
          <AdminPanelHeader title="Details" />
          <form className="space-y-4" onSubmit={(event) => void onSubmit(event)}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Code" htmlFor={`${formId}-code`} error={errors.code}>
                <Input
                  id={`${formId}-code`}
                  value={form.code}
                  onChange={(event) =>
                    setForm((prev) => ({ ...prev, code: event.target.value }))
                  }
                  disabled={mode === "edit"}
                />
              </Field>
              <Field label="Name" htmlFor={`${formId}-name`} error={errors.name}>
                <Input
                  id={`${formId}-name`}
                  value={form.name}
                  onChange={(event) =>
                    setForm((prev) => ({ ...prev, name: event.target.value }))
                  }
                />
              </Field>
              <Field
                label="Currency"
                htmlFor={`${formId}-currency`}
                error={errors.currency_code}
              >
                <Input
                  id={`${formId}-currency`}
                  value={form.currency_code}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      currency_code: event.target.value.toUpperCase(),
                    }))
                  }
                  maxLength={3}
                />
              </Field>
              <Field label="Priority" htmlFor={`${formId}-priority`}>
                <Input
                  id={`${formId}-priority`}
                  type="number"
                  min={0}
                  value={form.priority}
                  onChange={(event) =>
                    setForm((prev) => ({ ...prev, priority: event.target.value }))
                  }
                />
              </Field>
              <Field label="Status">
                <Select
                  value={form.status}
                  onValueChange={(value) =>
                    setForm((prev) => ({
                      ...prev,
                      status: value as PriceListStatus,
                    }))
                  }
                >
                  <SelectTrigger id={`${formId}-status`} aria-label="Status">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="draft">Draft</SelectItem>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="archived">Archived</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
            </div>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <div className="flex items-center gap-2">
                <Switch
                  id={`${formId}-default`}
                  checked={form.is_default}
                  onCheckedChange={(checked) =>
                    setForm((prev) => ({ ...prev, is_default: checked }))
                  }
                />
                <label htmlFor={`${formId}-default`} className="text-sm">
                  Default price list for this currency
                </label>
              </div>
              <div className="flex items-center gap-2">
                <Switch
                  id={`${formId}-tax`}
                  checked={form.prices_include_tax}
                  onCheckedChange={(checked) =>
                    setForm((prev) => ({
                      ...prev,
                      prices_include_tax: checked,
                    }))
                  }
                />
                <label htmlFor={`${formId}-tax`} className="text-sm">
                  Prices include tax
                </label>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button type="submit" size="sm" disabled={pending}>
                {pending ? "Saving…" : mode === "create" ? "Create" : "Save"}
              </Button>
              <Button asChild type="button" variant="outline" size="sm">
                <Link href="/admin/pricing/price-lists">Cancel</Link>
              </Button>
            </div>
          </form>
        </AdminPanel>
      ) : null}
    </div>
  );
}
