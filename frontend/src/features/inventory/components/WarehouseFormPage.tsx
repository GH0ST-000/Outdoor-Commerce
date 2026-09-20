"use client";

import { FormEvent, useEffect, useId, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  createAdminWarehouse,
  fetchAdminWarehouse,
  updateAdminWarehouse,
} from "@/features/inventory/api/warehouses-api";
import { mapApiFieldErrors } from "@/features/inventory/schemas/inventory-schemas";
import type {
  WarehouseStatus,
  WarehouseWritePayload,
} from "@/features/inventory/types/inventory-types";
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
  status: WarehouseStatus;
  is_default: boolean;
  country_code: string;
  city: string;
  address_line_1: string;
  address_line_2: string;
  postal_code: string;
};

function emptyForm(): FormState {
  return {
    code: "",
    name: "",
    status: "active",
    is_default: false,
    country_code: "GE",
    city: "",
    address_line_1: "",
    address_line_2: "",
    postal_code: "",
  };
}

function toPayload(form: FormState): WarehouseWritePayload {
  return {
    code: form.code.trim(),
    name: form.name.trim(),
    status: form.status,
    is_default: form.is_default,
    country_code: form.country_code.trim() || undefined,
    city: form.city.trim() || null,
    address_line_1: form.address_line_1.trim() || null,
    address_line_2: form.address_line_2.trim() || null,
    postal_code: form.postal_code.trim() || null,
  };
}

export function WarehouseFormPage({
  mode,
  warehouseId,
}: {
  mode: "create" | "edit";
  warehouseId?: string;
}) {
  const router = useRouter();
  const formId = useId();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.INVENTORY_MANAGE);

  const [form, setForm] = useState<FormState>(emptyForm);
  const [loading, setLoading] = useState(mode === "edit");
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    if (mode !== "edit" || !warehouseId) {
      return;
    }
    let cancelled = false;
    void fetchAdminWarehouse(warehouseId)
      .then((warehouse) => {
        if (cancelled) return;
        setForm({
          code: warehouse.code,
          name: warehouse.name,
          status: warehouse.status,
          is_default: warehouse.is_default,
          country_code: warehouse.country_code ?? "GE",
          city: warehouse.city ?? "",
          address_line_1: warehouse.address_line_1 ?? "",
          address_line_2: warehouse.address_line_2 ?? "",
          postal_code: warehouse.postal_code ?? "",
        });
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setFormError(
          err instanceof ApiClientError
            ? err.message
            : "Unable to load warehouse.",
        );
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [mode, warehouseId]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!canManage || pending) return;

    const nextErrors: Record<string, string> = {};
    if (form.code.trim() === "") nextErrors.code = "Code is required.";
    if (form.name.trim() === "") nextErrors.name = "Name is required.";
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;

    setPending(true);
    setFormError(null);
    try {
      const payload = toPayload(form);
      if (mode === "create") {
        const created = await createAdminWarehouse(payload);
        router.push(`/admin/inventory/warehouses/${created.id}/edit`);
        return;
      }
      if (warehouseId) {
        await updateAdminWarehouse(warehouseId, payload);
        router.push("/admin/inventory/warehouses");
      }
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to save warehouse.");
      }
    } finally {
      setPending(false);
    }
  }

  if (!canManage) {
    return (
      <p className="text-sm text-muted-foreground" role="status">
        You do not have permission to manage warehouses.
      </p>
    );
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title={mode === "create" ? "New warehouse" : "Edit warehouse"}
        description="Configure fulfillment location details."
        actions={
          <Button asChild variant="outline" size="sm">
            <Link href="/admin/inventory/warehouses">Back to list</Link>
          </Button>
        }
      />

      {loading ? (
        <p className="text-sm text-muted-foreground" role="status">
          Loading warehouse…
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
          <form
            className="space-y-4"
            onSubmit={(event) => void onSubmit(event)}
          >
            <div className="grid gap-4 sm:grid-cols-2">
              <Field
                label="Code"
                htmlFor={`${formId}-code`}
                error={errors.code}
              >
                <Input
                  id={`${formId}-code`}
                  value={form.code}
                  onChange={(event) =>
                    setForm((prev) => ({ ...prev, code: event.target.value }))
                  }
                  disabled={mode === "edit"}
                />
              </Field>
              <Field
                label="Name"
                htmlFor={`${formId}-name`}
                error={errors.name}
              >
                <Input
                  id={`${formId}-name`}
                  value={form.name}
                  onChange={(event) =>
                    setForm((prev) => ({ ...prev, name: event.target.value }))
                  }
                />
              </Field>
              <Field label="Status">
                <Select
                  value={form.status}
                  onValueChange={(value) =>
                    setForm((prev) => ({
                      ...prev,
                      status: value as WarehouseStatus,
                    }))
                  }
                >
                  <SelectTrigger id={`${formId}-status`} aria-label="Status">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="inactive">Inactive</SelectItem>
                    <SelectItem value="archived">Archived</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
              <Field label="Country code" htmlFor={`${formId}-country`}>
                <Input
                  id={`${formId}-country`}
                  value={form.country_code}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      country_code: event.target.value.toUpperCase(),
                    }))
                  }
                  maxLength={2}
                />
              </Field>
              <Field label="City" htmlFor={`${formId}-city`}>
                <Input
                  id={`${formId}-city`}
                  value={form.city}
                  onChange={(event) =>
                    setForm((prev) => ({ ...prev, city: event.target.value }))
                  }
                />
              </Field>
              <Field label="Postal code" htmlFor={`${formId}-postal`}>
                <Input
                  id={`${formId}-postal`}
                  value={form.postal_code}
                  onChange={(event) =>
                    setForm((prev) => ({
                      ...prev,
                      postal_code: event.target.value,
                    }))
                  }
                />
              </Field>
            </div>
            <Field label="Address line 1" htmlFor={`${formId}-address1`}>
              <Input
                id={`${formId}-address1`}
                value={form.address_line_1}
                onChange={(event) =>
                  setForm((prev) => ({
                    ...prev,
                    address_line_1: event.target.value,
                  }))
                }
              />
            </Field>
            <Field label="Address line 2" htmlFor={`${formId}-address2`}>
              <Input
                id={`${formId}-address2`}
                value={form.address_line_2}
                onChange={(event) =>
                  setForm((prev) => ({
                    ...prev,
                    address_line_2: event.target.value,
                  }))
                }
              />
            </Field>
            <div className="flex items-center gap-2">
              <Switch
                id={`${formId}-default`}
                checked={form.is_default}
                onCheckedChange={(checked) =>
                  setForm((prev) => ({ ...prev, is_default: checked }))
                }
              />
              <label htmlFor={`${formId}-default`} className="text-sm">
                Default warehouse
              </label>
            </div>
            <div className="flex flex-wrap gap-2">
              <Button type="submit" size="sm" disabled={pending}>
                {pending ? "Saving…" : mode === "create" ? "Create" : "Save"}
              </Button>
              <Button asChild type="button" variant="outline" size="sm">
                <Link href="/admin/inventory/warehouses">Cancel</Link>
              </Button>
            </div>
          </form>
        </AdminPanel>
      ) : null}
    </div>
  );
}
