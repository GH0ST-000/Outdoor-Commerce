"use client";

import { FormEvent, useEffect, useId, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  archiveAdminAttribute,
  createAdminAttribute,
  fetchAdminAttribute,
  updateAdminAttribute,
} from "@/features/catalog/attributes/api/attributes-api";
import { ArchiveAttributeDialog } from "@/features/catalog/attributes/components/ArchiveAttributeDialog";
import { AttributeValuesPanel } from "@/features/catalog/attributes/components/AttributeValuesPanel";
import { attributeFormSchema } from "@/features/catalog/attributes/schemas/attribute-schemas";
import type {
  AttributeDetail,
  AttributeStatus,
  AttributeTranslation,
  AttributeType,
  AttributeWritePayload,
} from "@/features/catalog/attributes/types/attribute-types";
import { normalizeAttributeCode } from "@/features/catalog/attributes/utils/attribute-format";
import { useAdminContext } from "@/features/admin/hooks/use-admin-context";
import { hasPermission } from "@/features/admin/permissions/has-permission";
import { PERMISSIONS } from "@/features/admin/permissions/permissions";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";

type LocaleTab = "ka" | "en";

type TranslationDraft = {
  name: string;
  description: string;
};

type FormState = {
  code: string;
  type: AttributeType;
  status: AttributeStatus;
  is_filterable: boolean;
  sort_order: string;
  translations: Record<LocaleTab, TranslationDraft>;
};

function emptyTranslation(): TranslationDraft {
  return { name: "", description: "" };
}

function translationFrom(
  translations: AttributeTranslation[],
  locale: LocaleTab,
): TranslationDraft {
  const row = translations.find((item) => item.locale === locale);
  if (!row) {
    return emptyTranslation();
  }
  return { name: row.name ?? "", description: row.description ?? "" };
}

function emptyForm(): FormState {
  return {
    code: "",
    type: "select",
    status: "draft",
    is_filterable: false,
    sort_order: "0",
    translations: { ka: emptyTranslation(), en: emptyTranslation() },
  };
}

function formFromAttribute(attribute: AttributeDetail): FormState {
  return {
    code: attribute.code,
    type: attribute.type,
    status: attribute.status,
    is_filterable: attribute.is_filterable,
    sort_order: String(attribute.sort_order ?? 0),
    translations: {
      ka: translationFrom(attribute.translations, "ka"),
      en: translationFrom(attribute.translations, "en"),
    },
  };
}

function mapFieldErrors(
  details: Record<string, string[]> | undefined,
): Record<string, string> {
  const next: Record<string, string> = {};
  if (!details) return next;
  for (const [key, messages] of Object.entries(details)) {
    if (messages[0]) {
      next[key] = messages[0];
    }
  }
  return next;
}

function buildPayload(form: FormState): AttributeWritePayload {
  const translations: AttributeWritePayload["translations"] = [
    {
      locale: "ka",
      name: form.translations.ka.name.trim(),
      description: form.translations.ka.description.trim() || null,
    },
  ];

  const en = form.translations.en;
  if (en.name.trim() !== "" || en.description.trim() !== "") {
    translations.push({
      locale: "en",
      name: en.name.trim(),
      description: en.description.trim() || null,
    });
  }

  return {
    code: form.code.trim(),
    type: form.type,
    status: form.status,
    is_filterable: form.is_filterable,
    sort_order: Number(form.sort_order) || 0,
    translations,
  };
}

export function AttributeFormPage({
  mode,
  attributeId,
}: {
  mode: "create" | "edit";
  attributeId?: string;
}) {
  const router = useRouter();
  const formId = useId();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.CATALOG_MANAGE);
  const canPublish = hasPermission(permissions, PERMISSIONS.CATALOG_PUBLISH);

  const [localeTab, setLocaleTab] = useState<LocaleTab>("ka");
  const [form, setForm] = useState<FormState>(emptyForm);
  const [loadResult, setLoadResult] = useState<{
    key: string;
    attribute: AttributeDetail | null;
    error: string | null;
  } | null>(
    mode === "create" ? { key: "create", attribute: null, error: null } : null,
  );
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [confirmArchive, setConfirmArchive] = useState(false);

  useEffect(() => {
    if (mode !== "edit" || !attributeId) {
      return;
    }
    let cancelled = false;
    void fetchAdminAttribute(attributeId)
      .then((next) => {
        if (cancelled) return;
        setLoadResult({ key: attributeId, attribute: next, error: null });
        setForm(formFromAttribute(next));
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setLoadResult({
          key: attributeId,
          attribute: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load attribute.",
        });
      });
    return () => {
      cancelled = true;
    };
  }, [mode, attributeId]);

  const loading = mode === "edit" && loadResult?.key !== attributeId;
  const loaded =
    mode === "edit" && attributeId && loadResult?.key === attributeId
      ? loadResult
      : null;
  const attribute = loaded?.attribute ?? null;
  const loadError = loaded?.error ?? null;

  const statusOptions = useMemo(() => {
    const options: AttributeStatus[] = ["draft"];
    if (canPublish || form.status === "active") {
      options.push("active");
    }
    if (form.status === "archived") {
      options.push("archived");
    }
    return options;
  }, [canPublish, form.status]);

  function updateTranslation(
    locale: LocaleTab,
    patch: Partial<TranslationDraft>,
  ) {
    setForm((current) => ({
      ...current,
      translations: {
        ...current.translations,
        [locale]: { ...current.translations[locale], ...patch },
      },
    }));
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting || !canManage) {
      return;
    }

    setFormError(null);
    setNotice(null);

    const parsed = attributeFormSchema.safeParse({
      code: form.code,
      type: form.type,
      status: form.status,
      is_filterable: form.is_filterable,
      sort_order: Number(form.sort_order) || 0,
      translations: {
        ka: {
          locale: "ka" as const,
          name: form.translations.ka.name,
          description: form.translations.ka.description,
        },
        en: {
          locale: "en" as const,
          name: form.translations.en.name,
          description: form.translations.en.description,
        },
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
      setFieldErrors(next);
      if (next["translations.ka.name"]) {
        setLocaleTab("ka");
      } else if (next["translations.en.name"]) {
        setLocaleTab("en");
      }
      return;
    }

    if (form.status === "active" && !canPublish) {
      setFormError(
        "Publishing permission is required to activate an attribute.",
      );
      return;
    }

    setFieldErrors({});
    setSubmitting(true);

    try {
      if (mode === "create") {
        const created = await createAdminAttribute(buildPayload(form));
        setNotice("Attribute created.");
        router.replace(`/admin/catalog/attributes/${created.id}/edit`);
        return;
      }

      if (!attributeId) {
        setFormError("Missing attribute id.");
        return;
      }

      const updated = await updateAdminAttribute(
        attributeId,
        buildPayload(form),
      );
      setLoadResult({ key: attributeId, attribute: updated, error: null });
      setForm(formFromAttribute(updated));
      setNotice("Attribute saved.");
    } catch (error) {
      if (error instanceof ApiClientError) {
        setFieldErrors(mapFieldErrors(error.details));
        setFormError(error.message);
      } else {
        setFormError("Unable to save attribute.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function onArchiveConfirm() {
    if (!attributeId || submitting || !canManage) return;
    setSubmitting(true);
    setFormError(null);
    try {
      await archiveAdminAttribute(attributeId);
      router.replace("/admin/catalog/attributes?include_deleted=1");
    } catch (error) {
      setFormError(
        error instanceof ApiClientError
          ? error.message
          : "Unable to archive attribute.",
      );
      setConfirmArchive(false);
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <p
        className="text-sm text-muted-foreground"
        role="status"
        aria-live="polite"
      >
        Loading attribute…
      </p>
    );
  }

  if (mode === "edit" && !attribute) {
    return (
      <div className="space-y-3">
        <p className="text-sm text-destructive" role="alert">
          {loadError ?? "Attribute not found."}
        </p>
        <Link
          href="/admin/catalog/attributes"
          className="text-sm font-medium text-primary underline-offset-4 hover:underline"
        >
          Back to attributes
        </Link>
      </div>
    );
  }

  const activeTranslation = form.translations[localeTab];
  const title =
    mode === "create"
      ? "New attribute"
      : form.translations.ka.name || form.code || `Attribute #${attributeId}`;

  return (
    <div className="space-y-6 pb-24">
      <div>
        <Link
          href="/admin/catalog/attributes"
          className="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground no-underline hover:text-foreground"
        >
          ← Attributes
        </Link>
        <AdminPageHeader
          className="mt-3"
          title={title}
          description={
            mode === "create"
              ? "Create an attribute, then add its values."
              : "Update the attribute and manage its values."
          }
        />
      </div>

      {formError ? (
        <p
          className="rounded-xl border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {formError}
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

      {confirmArchive ? (
        <ArchiveAttributeDialog
          title="Archive attribute?"
          label={
            form.translations.ka.name
              ? `"${form.translations.ka.name}"`
              : `attribute #${attributeId}`
          }
          pending={submitting}
          onConfirm={() => void onArchiveConfirm()}
          onCancel={() => setConfirmArchive(false)}
        />
      ) : null}

      <form id={formId} className="space-y-6" onSubmit={onSubmit} noValidate>
        <fieldset disabled={!canManage || submitting} className="space-y-6">
          <AdminPanel>
            <AdminPanelHeader
              title="Attribute"
              description="Code is stable and used by variant combinations. Type cannot mix color values into select attributes."
            />
            <div className="grid gap-4 md:grid-cols-2">
              <Field
                label="Code"
                htmlFor={`${formId}-code`}
                hint="Lowercase letters, numbers, and underscores."
                error={fieldErrors.code}
              >
                <Input
                  id={`${formId}-code`}
                  value={form.code}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      code: event.target.value,
                    }))
                  }
                  onBlur={(event) =>
                    setForm((current) => ({
                      ...current,
                      code: normalizeAttributeCode(event.target.value),
                    }))
                  }
                  aria-invalid={Boolean(fieldErrors.code)}
                />
              </Field>
              <Field
                label="Type"
                htmlFor={`${formId}-type`}
                error={fieldErrors.type}
              >
                <Select
                  value={form.type}
                  onValueChange={(value) =>
                    setForm((current) => ({
                      ...current,
                      type: value as AttributeType,
                    }))
                  }
                >
                  <SelectTrigger
                    id={`${formId}-type`}
                    aria-label="Type"
                    aria-invalid={Boolean(fieldErrors.type)}
                  >
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="select">select</SelectItem>
                    <SelectItem value="color">color</SelectItem>
                  </SelectContent>
                </Select>
              </Field>
              <Field
                label="Status"
                htmlFor={`${formId}-status`}
                hint={
                  !canPublish
                    ? "Activate is hidden without catalog.publish."
                    : undefined
                }
                error={fieldErrors.status}
              >
                <Select
                  value={form.status}
                  onValueChange={(value) =>
                    setForm((current) => ({
                      ...current,
                      status: value as AttributeStatus,
                    }))
                  }
                >
                  <SelectTrigger
                    id={`${formId}-status`}
                    aria-label="Status"
                    aria-invalid={Boolean(fieldErrors.status)}
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
                htmlFor={`${formId}-sort`}
                error={fieldErrors.sort_order}
              >
                <Input
                  id={`${formId}-sort`}
                  type="number"
                  min={0}
                  value={form.sort_order}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      sort_order: event.target.value,
                    }))
                  }
                />
              </Field>
              <div className="flex items-end pb-1">
                <div className="flex items-center gap-2">
                  <Switch
                    id={`${formId}-filterable`}
                    checked={form.is_filterable}
                    onCheckedChange={(checked) =>
                      setForm((current) => ({
                        ...current,
                        is_filterable: checked,
                      }))
                    }
                  />
                  <Label htmlFor={`${formId}-filterable`} className="text-sm">
                    Filterable in storefront
                  </Label>
                </div>
              </div>
            </div>
          </AdminPanel>

          <AdminPanel>
            <AdminPanelHeader
              title="Translations"
              description="Georgian (ka) is required. English is optional and kept when switching tabs."
            />
            <Tabs
              value={localeTab}
              onValueChange={(value) => setLocaleTab(value as LocaleTab)}
            >
              <TabsList aria-label="Translation locale">
                <TabsTrigger value="ka">Georgian (ka) *</TabsTrigger>
                <TabsTrigger value="en">English (en)</TabsTrigger>
              </TabsList>
            </Tabs>

            <div className="mt-4 grid gap-4" role="tabpanel">
              <Field
                label="Name"
                htmlFor={`${formId}-name-${localeTab}`}
                error={
                  fieldErrors[`translations.${localeTab}.name`] ??
                  fieldErrors["translations.0.name"] ??
                  null
                }
              >
                <Input
                  id={`${formId}-name-${localeTab}`}
                  value={activeTranslation.name}
                  onChange={(event) =>
                    updateTranslation(localeTab, { name: event.target.value })
                  }
                  aria-invalid={Boolean(
                    fieldErrors[`translations.${localeTab}.name`] ||
                    fieldErrors["translations.0.name"],
                  )}
                />
              </Field>
              <Field
                label="Description"
                htmlFor={`${formId}-desc-${localeTab}`}
              >
                <Textarea
                  id={`${formId}-desc-${localeTab}`}
                  className="min-h-20"
                  value={activeTranslation.description}
                  onChange={(event) =>
                    updateTranslation(localeTab, {
                      description: event.target.value,
                    })
                  }
                />
              </Field>
            </div>
          </AdminPanel>
        </fieldset>

        <div className="sticky bottom-0 z-10 -mx-1 border-t border-border/60 bg-background/90 px-1 py-4 backdrop-blur-md">
          <div className="flex flex-wrap gap-2">
            {canManage ? (
              <Button type="submit" size="sm" disabled={submitting}>
                {submitting
                  ? "Saving…"
                  : mode === "create"
                    ? "Create attribute"
                    : "Save changes"}
              </Button>
            ) : (
              <p className="text-sm text-muted-foreground">
                You can view attributes but need catalog.manage to edit.
              </p>
            )}
            {mode === "edit" && canManage ? (
              <Button
                type="button"
                variant="destructive"
                size="sm"
                disabled={submitting}
                onClick={() => setConfirmArchive(true)}
              >
                Archive
              </Button>
            ) : null}
            <Button asChild variant="outline" size="sm">
              <Link href="/admin/catalog/attributes">Cancel</Link>
            </Button>
          </div>
        </div>
      </form>

      {mode === "edit" && attribute ? (
        <AttributeValuesPanel
          attributeId={attribute.id}
          attributeType={attribute.type}
        />
      ) : (
        <AdminPanel>
          <AdminPanelHeader
            title="Values"
            description="Save the attribute first, then add its values."
          />
        </AdminPanel>
      )}
    </div>
  );
}
