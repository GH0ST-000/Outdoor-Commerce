"use client";

import { FormEvent, useEffect, useId, useState } from "react";
import {
  archiveAdminAttributeValue,
  createAdminAttributeValue,
  fetchAdminAttributeValue,
  fetchAdminAttributeValues,
  restoreAdminAttributeValue,
  updateAdminAttributeValue,
} from "@/features/catalog/attributes/api/attributes-api";
import { ColorSwatch } from "@/features/catalog/attributes/components/ColorSwatch";
import { attributeValueFormSchema } from "@/features/catalog/attributes/schemas/attribute-schemas";
import type {
  AttributeType,
  AttributeValueListItem,
  AttributeValueStatus,
  AttributeValueWritePayload,
} from "@/features/catalog/attributes/types/attribute-types";
import {
  normalizeAttributeCode,
  normalizeColorHex,
} from "@/features/catalog/attributes/utils/attribute-format";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";
import { hasPermission } from "@/features/admin/permissions/has-permission";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import {
  AdminTable,
  adminTableClassName,
  adminTdClassName,
  adminThClassName,
} from "@/features/admin/ui/AdminTable";
import { StatusBadge } from "@/features/admin/ui/StatusBadge";
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

type ValueDraft = {
  code: string;
  status: AttributeValueStatus;
  sort_order: string;
  color_hex: string;
  name_ka: string;
  name_en: string;
};

function emptyDraft(): ValueDraft {
  return {
    code: "",
    status: "draft",
    sort_order: "0",
    color_hex: "",
    name_ka: "",
    name_en: "",
  };
}

function buildPayload(draft: ValueDraft): AttributeValueWritePayload {
  const translations: AttributeValueWritePayload["translations"] = [
    { locale: "ka", name: draft.name_ka.trim() },
  ];
  if (draft.name_en.trim() !== "") {
    translations.push({ locale: "en", name: draft.name_en.trim() });
  }

  return {
    code: draft.code.trim(),
    status: draft.status,
    sort_order: Number(draft.sort_order) || 0,
    color_hex:
      draft.color_hex.trim() === ""
        ? null
        : (normalizeColorHex(draft.color_hex) ?? draft.color_hex.trim()),
    translations,
  };
}

export function AttributeValuesPanel({
  attributeId,
  attributeType,
}: {
  attributeId: number;
  attributeType: AttributeType;
}) {
  const fieldId = useId();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.CATALOG_MANAGE);
  const canPublish = hasPermission(permissions, PERMISSIONS.CATALOG_PUBLISH);

  const [values, setValues] = useState<AttributeValueListItem[] | null>(null);
  const [listError, setListError] = useState<string | null>(null);
  const [editorOpen, setEditorOpen] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [draft, setDraft] = useState<ValueDraft>(emptyDraft);
  const [draftErrors, setDraftErrors] = useState<Record<string, string>>({});
  const [pending, setPending] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const [refreshNonce, setRefreshNonce] = useState(0);

  useEffect(() => {
    let cancelled = false;
    void fetchAdminAttributeValues(attributeId, {
      per_page: 50,
      sort: "sort_order",
      direction: "asc",
      include_deleted: "1",
    })
      .then((response) => {
        if (cancelled) return;
        setValues(response.data);
        setListError(null);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setValues([]);
        setListError(
          err instanceof ApiClientError
            ? err.message
            : "Unable to load values.",
        );
      });
    return () => {
      cancelled = true;
    };
  }, [attributeId, refreshNonce]);

  const reload = () => setRefreshNonce((value) => value + 1);

  const statusOptions: AttributeValueStatus[] = canPublish
    ? ["draft", "active"]
    : ["draft"];

  function openCreate() {
    setEditingId(null);
    setDraft(emptyDraft());
    setDraftErrors({});
    setActionError(null);
    setEditorOpen(true);
  }

  async function openEdit(value: AttributeValueListItem) {
    setEditingId(value.id);
    setDraftErrors({});
    setActionError(null);
    setEditorOpen(true);
    setDraft({
      code: value.code,
      status: value.status,
      sort_order: String(value.sort_order ?? 0),
      color_hex: value.color_hex ?? "",
      name_ka: value.name ?? "",
      name_en: "",
    });

    try {
      const detail = await fetchAdminAttributeValue(attributeId, value.id);
      setDraft({
        code: detail.code,
        status: detail.status,
        sort_order: String(detail.sort_order ?? 0),
        color_hex: detail.color_hex ?? "",
        name_ka:
          detail.translations.find((row) => row.locale === "ka")?.name ?? "",
        name_en:
          detail.translations.find((row) => row.locale === "en")?.name ?? "",
      });
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to load the value.",
      );
    }
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (pending || !canManage) return;

    setActionError(null);
    setNotice(null);

    const parsed = attributeValueFormSchema(attributeType).safeParse({
      code: draft.code,
      status: draft.status,
      sort_order: Number(draft.sort_order) || 0,
      color_hex: draft.color_hex,
      translations: {
        ka: { locale: "ka" as const, name: draft.name_ka },
        en: { locale: "en" as const, name: draft.name_en },
      },
    });

    if (!parsed.success) {
      const next: Record<string, string> = {};
      for (const issue of parsed.error.issues) {
        const key = issue.path.join(".");
        if (key && !next[key]) {
          next[key] = issue.message;
        }
      }
      setDraftErrors(next);
      return;
    }

    setDraftErrors({});
    setPending(true);
    try {
      if (editingId === null) {
        await createAdminAttributeValue(attributeId, buildPayload(draft));
        setNotice("Value created.");
      } else {
        await updateAdminAttributeValue(
          attributeId,
          editingId,
          buildPayload(draft),
        );
        setNotice("Value saved.");
      }
      setEditorOpen(false);
      setEditingId(null);
      setDraft(emptyDraft());
      reload();
    } catch (err) {
      if (err instanceof ApiClientError) {
        const mapped: Record<string, string> = {};
        for (const [key, messages] of Object.entries(err.details ?? {})) {
          if (messages[0]) {
            mapped[key] = messages[0];
          }
        }
        setDraftErrors(mapped);
        setActionError(err.message);
      } else {
        setActionError("Unable to save the value.");
      }
    } finally {
      setPending(false);
    }
  }

  async function onArchive(value: AttributeValueListItem) {
    if (pending || !canManage) return;
    setPending(true);
    setActionError(null);
    setNotice(null);
    try {
      await archiveAdminAttributeValue(attributeId, value.id);
      setNotice("Value archived.");
      reload();
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to archive the value.",
      );
    } finally {
      setPending(false);
    }
  }

  async function onRestore(value: AttributeValueListItem) {
    if (pending || !canManage) return;
    setPending(true);
    setActionError(null);
    setNotice(null);
    try {
      await restoreAdminAttributeValue(attributeId, value.id);
      setNotice("Value restored to draft.");
      reload();
    } catch (err) {
      setActionError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to restore the value.",
      );
    } finally {
      setPending(false);
    }
  }

  const loading = values === null;

  return (
    <AdminPanel>
      <AdminPanelHeader
        title="Values"
        description={
          attributeType === "color"
            ? "Color values store a #RRGGBB hex used by storefront swatches."
            : "Values are the options shoppers pick on a variant axis."
        }
        actions={
          canManage ? (
            <Button type="button" size="sm" onClick={openCreate}>
              New value
            </Button>
          ) : null
        }
      />

      {actionError ? (
        <p
          className="mb-4 rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {actionError}
        </p>
      ) : null}
      {notice ? (
        <p
          className="mb-4 rounded-xl border border-border/70 bg-muted/40 px-4 py-3 text-sm"
          role="status"
        >
          {notice}
        </p>
      ) : null}

      {editorOpen ? (
        <form
          className="mb-6 space-y-4 rounded-2xl border border-border/70 bg-muted/20 p-4"
          onSubmit={onSubmit}
          noValidate
          aria-label={editingId === null ? "New value" : "Edit value"}
        >
          <div className="grid gap-4 md:grid-cols-2">
            <Field
              label="Code"
              htmlFor={`${fieldId}-code`}
              error={draftErrors.code}
            >
              <Input
                id={`${fieldId}-code`}
                value={draft.code}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    code: event.target.value,
                  }))
                }
                onBlur={(event) =>
                  setDraft((current) => ({
                    ...current,
                    code: normalizeAttributeCode(event.target.value),
                  }))
                }
                aria-invalid={Boolean(draftErrors.code)}
              />
            </Field>
            <Field
              label="Name (ka)"
              htmlFor={`${fieldId}-name-ka`}
              error={
                draftErrors["translations.ka.name"] ??
                draftErrors["translations.0.name"] ??
                null
              }
            >
              <Input
                id={`${fieldId}-name-ka`}
                value={draft.name_ka}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    name_ka: event.target.value,
                  }))
                }
                aria-invalid={Boolean(draftErrors["translations.ka.name"])}
              />
            </Field>
            <Field
              label="Name (en)"
              htmlFor={`${fieldId}-name-en`}
              error={draftErrors["translations.en.name"]}
            >
              <Input
                id={`${fieldId}-name-en`}
                value={draft.name_en}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    name_en: event.target.value,
                  }))
                }
              />
            </Field>
            <Field
              label="Status"
              htmlFor={`${fieldId}-status`}
              hint={
                !canPublish
                  ? "Activate is hidden without catalog.publish."
                  : undefined
              }
              error={draftErrors.status}
            >
              <Select
                value={draft.status}
                onValueChange={(value) =>
                  setDraft((current) => ({
                    ...current,
                    status: value as AttributeValueStatus,
                  }))
                }
              >
                <SelectTrigger
                  id={`${fieldId}-status`}
                  aria-label="Value status"
                >
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {statusOptions.map((status) => (
                    <SelectItem key={status} value={status}>
                      {status}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field
              label="Sort order"
              htmlFor={`${fieldId}-sort`}
              error={draftErrors.sort_order}
            >
              <Input
                id={`${fieldId}-sort`}
                type="number"
                min={0}
                value={draft.sort_order}
                onChange={(event) =>
                  setDraft((current) => ({
                    ...current,
                    sort_order: event.target.value,
                  }))
                }
              />
            </Field>
            {attributeType === "color" ? (
              <Field
                label="Color hex"
                htmlFor={`${fieldId}-color`}
                hint="3- or 6-digit hex, for example #1A2B3C."
                error={draftErrors.color_hex}
              >
                <div className="flex items-center gap-3">
                  <Input
                    id={`${fieldId}-color`}
                    value={draft.color_hex}
                    placeholder="#1A2B3C"
                    onChange={(event) =>
                      setDraft((current) => ({
                        ...current,
                        color_hex: event.target.value,
                      }))
                    }
                    aria-invalid={Boolean(draftErrors.color_hex)}
                  />
                  <ColorSwatch hex={normalizeColorHex(draft.color_hex)} />
                </div>
              </Field>
            ) : null}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="submit" size="sm" disabled={pending}>
              {pending
                ? "Saving…"
                : editingId === null
                  ? "Create value"
                  : "Save value"}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={pending}
              onClick={() => {
                setEditorOpen(false);
                setEditingId(null);
                setDraftErrors({});
              }}
            >
              Cancel
            </Button>
          </div>
        </form>
      ) : null}

      {loading ? (
        <p
          className="text-sm text-muted-foreground"
          role="status"
          aria-live="polite"
        >
          Loading values…
        </p>
      ) : null}

      {listError ? (
        <p className="text-sm text-destructive" role="alert">
          {listError}
        </p>
      ) : null}

      {!loading && !listError && values.length === 0 ? (
        <p className="text-sm text-muted-foreground" role="status">
          No values yet.
        </p>
      ) : null}

      {!loading && !listError && values.length > 0 ? (
        <AdminTable>
          <table className={adminTableClassName()}>
            <thead>
              <tr>
                <th className={adminThClassName()}>Name</th>
                <th className={adminThClassName()}>Code</th>
                <th className={adminThClassName()}>Status</th>
                {attributeType === "color" ? (
                  <th className={adminThClassName()}>Color</th>
                ) : null}
                <th className={adminThClassName()}>Order</th>
                <th className={adminThClassName()}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {values.map((value) => {
                const isDeleted = Boolean(value.deleted_at);
                return (
                  <tr key={value.id}>
                    <td className={adminTdClassName()}>
                      {value.name ?? `Value #${value.id}`}
                    </td>
                    <td
                      className={`${adminTdClassName()} font-mono text-xs text-muted-foreground`}
                    >
                      {value.code}
                    </td>
                    <td className={adminTdClassName()}>
                      <StatusBadge status={value.status} />
                    </td>
                    {attributeType === "color" ? (
                      <td className={adminTdClassName()}>
                        <ColorSwatch hex={value.color_hex} />
                      </td>
                    ) : null}
                    <td
                      className={`${adminTdClassName()} text-muted-foreground`}
                    >
                      {value.sort_order}
                    </td>
                    <td className={adminTdClassName()}>
                      <div className="flex flex-wrap gap-2">
                        {canManage && !isDeleted ? (
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={pending}
                            onClick={() => void openEdit(value)}
                          >
                            Edit
                          </Button>
                        ) : null}
                        {canManage && !isDeleted ? (
                          <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            disabled={pending}
                            onClick={() => void onArchive(value)}
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
                            onClick={() => void onRestore(value)}
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
      ) : null}
    </AdminPanel>
  );
}
