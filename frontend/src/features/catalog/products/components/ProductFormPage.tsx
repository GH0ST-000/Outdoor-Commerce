"use client";

import { FormEvent, useEffect, useId, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import {
  archiveAdminProduct,
  createAdminProduct,
  fetchAdminProduct,
  fetchCatalogBrandOptions,
  fetchCatalogCategoryOptions,
  updateAdminProduct,
} from "@/features/catalog/products/api/products-api";
import { ActiveNotPurchasableNotice } from "@/features/catalog/products/components/ActiveNotPurchasableNotice";
import { ArchiveConfirmDialog } from "@/features/catalog/products/components/ArchiveConfirmDialog";
import { ProductReadinessPanel } from "@/features/catalog/products/components/ProductReadinessPanel";
import { productFormSchema } from "@/features/catalog/products/schemas/product-schemas";
import type {
  CatalogOption,
  ProductDetail,
  ProductStatus,
  ProductTranslation,
  ProductWritePayload,
} from "@/features/catalog/products/types/product-types";
import { slugFromName } from "@/features/catalog/products/utils/slug";
import { ProductVariantsSection } from "@/features/catalog/variants/components/ProductVariantsSection";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";

type LocaleTab = "ka" | "en";
type WizardStep = "basics" | "catalog" | "variants" | "content" | "publish";

type TranslationDraft = {
  name: string;
  slug: string;
  short_description: string;
  description: string;
  seo_title: string;
  seo_description: string;
  slugManual: boolean;
};

type FormState = {
  brand_id: string;
  primary_category_id: string;
  category_ids: number[];
  status: ProductStatus;
  model_number: string;
  manufacturer_part_number: string;
  is_featured: boolean;
  sort_order: string;
  translations: Record<LocaleTab, TranslationDraft>;
};

const emptyTranslation = (): TranslationDraft => ({
  name: "",
  slug: "",
  short_description: "",
  description: "",
  seo_title: "",
  seo_description: "",
  slugManual: false,
});

function translationFromProduct(
  translations: ProductTranslation[],
  locale: LocaleTab,
): TranslationDraft {
  const row = translations.find((item) => item.locale === locale);
  if (!row) {
    return emptyTranslation();
  }
  return {
    name: row.name ?? "",
    slug: row.slug ?? "",
    short_description: row.short_description ?? "",
    description: row.description ?? "",
    seo_title: row.seo_title ?? "",
    seo_description: row.seo_description ?? "",
    slugManual: true,
  };
}

function emptyForm(): FormState {
  return {
    brand_id: "",
    primary_category_id: "",
    category_ids: [],
    status: "draft",
    model_number: "",
    manufacturer_part_number: "",
    is_featured: false,
    sort_order: "0",
    translations: {
      ka: emptyTranslation(),
      en: emptyTranslation(),
    },
  };
}

function formFromProduct(product: ProductDetail): FormState {
  return {
    brand_id: product.brand_id != null ? String(product.brand_id) : "",
    primary_category_id:
      product.primary_category_id != null
        ? String(product.primary_category_id)
        : "",
    category_ids: product.categories.map((category) => category.id),
    status: product.status,
    model_number: product.model_number ?? "",
    manufacturer_part_number: product.manufacturer_part_number ?? "",
    is_featured: product.is_featured,
    sort_order: String(product.sort_order ?? 0),
    translations: {
      ka: translationFromProduct(product.translations, "ka"),
      en: translationFromProduct(product.translations, "en"),
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

function buildPayload(
  form: FormState,
  options: { removeEnglish: boolean },
): ProductWritePayload {
  const brandId = form.brand_id === "" ? null : Number(form.brand_id);
  const primaryCategoryId =
    form.primary_category_id === "" ? null : Number(form.primary_category_id);

  const translations: ProductWritePayload["translations"] = [
    {
      locale: "ka",
      name: form.translations.ka.name.trim(),
      slug: form.translations.ka.slug.trim(),
      short_description: form.translations.ka.short_description.trim() || null,
      description: form.translations.ka.description.trim() || null,
      seo_title: form.translations.ka.seo_title.trim() || null,
      seo_description: form.translations.ka.seo_description.trim() || null,
    },
  ];

  const en = form.translations.en;
  const enHasContent =
    en.name.trim() !== "" ||
    en.slug.trim() !== "" ||
    en.short_description.trim() !== "" ||
    en.description.trim() !== "" ||
    en.seo_title.trim() !== "" ||
    en.seo_description.trim() !== "";

  if (enHasContent) {
    translations.push({
      locale: "en",
      name: en.name.trim(),
      slug: en.slug.trim(),
      short_description: en.short_description.trim() || null,
      description: en.description.trim() || null,
      seo_title: en.seo_title.trim() || null,
      seo_description: en.seo_description.trim() || null,
    });
  }

  return {
    brand_id: brandId,
    primary_category_id: primaryCategoryId,
    category_ids: form.category_ids,
    status: form.status,
    model_number: form.model_number.trim() || null,
    manufacturer_part_number: form.manufacturer_part_number.trim() || null,
    is_featured: form.is_featured,
    sort_order: Number(form.sort_order) || 0,
    translations,
    sync_translations: true,
    remove_english: options.removeEnglish && !enHasContent,
  };
}

function wizardStepForErrors(
  errors: Record<string, string>,
): WizardStep | null {
  const keys = Object.keys(errors);
  if (
    keys.some(
      (key) =>
        key.startsWith("translations") ||
        key.includes(".name") ||
        key.includes(".slug"),
    )
  ) {
    return "content";
  }
  if (
    keys.some(
      (key) =>
        key === "brand_id" ||
        key === "primary_category_id" ||
        key === "category_ids",
    )
  ) {
    return "catalog";
  }
  if (keys.some((key) => key === "status")) {
    return "publish";
  }
  if (
    keys.some(
      (key) =>
        key === "model_number" ||
        key === "manufacturer_part_number" ||
        key === "sort_order" ||
        key === "is_featured",
    )
  ) {
    return "basics";
  }
  return null;
}

export function ProductFormPage({
  mode,
  productId,
}: {
  mode: "create" | "edit";
  productId?: string;
}) {
  const router = useRouter();
  const formId = useId();
  const { permissions } = useAdminContext();
  const canManage = hasPermission(permissions, PERMISSIONS.CATALOG_MANAGE);
  const canPublish = hasPermission(permissions, PERMISSIONS.CATALOG_PUBLISH);

  const [wizardStep, setWizardStep] = useState<WizardStep>("basics");
  const [localeTab, setLocaleTab] = useState<LocaleTab>("ka");
  const [form, setForm] = useState<FormState>(emptyForm);
  const [loadResult, setLoadResult] = useState<{
    key: string;
    product: ProductDetail | null;
    error: string | null;
  } | null>(
    mode === "create" ? { key: "create", product: null, error: null } : null,
  );
  const [brands, setBrands] = useState<CatalogOption[]>([]);
  const [categories, setCategories] = useState<CatalogOption[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [hadEnglish, setHadEnglish] = useState(false);

  useEffect(() => {
    let cancelled = false;
    void Promise.all([
      fetchCatalogBrandOptions(),
      fetchCatalogCategoryOptions(),
    ])
      .then(([nextBrands, nextCategories]) => {
        if (cancelled) return;
        setBrands(nextBrands);
        setCategories(nextCategories);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setFormError(
          err instanceof ApiClientError
            ? err.message
            : "Unable to load catalog options.",
        );
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (mode !== "edit" || !productId) {
      return;
    }
    let cancelled = false;
    void fetchAdminProduct(productId)
      .then((next) => {
        if (cancelled) return;
        setLoadResult({ key: productId, product: next, error: null });
        setForm(formFromProduct(next));
        setHadEnglish(next.translations.some((row) => row.locale === "en"));
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setLoadResult({
          key: productId,
          product: null,
          error:
            err instanceof ApiClientError
              ? err.message
              : "Unable to load product.",
        });
      });
    return () => {
      cancelled = true;
    };
  }, [mode, productId]);

  const loading = mode === "edit" && loadResult?.key !== productId;
  const loaded =
    mode === "edit" && productId && loadResult?.key === productId
      ? loadResult
      : null;
  const product = loaded?.product ?? null;
  const loadError = loaded?.error ?? null;
  const readiness = product?.readiness ?? null;

  const statusOptions = useMemo(() => {
    const options: ProductStatus[] = ["draft"];
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
        [locale]: {
          ...current.translations[locale],
          ...patch,
        },
      },
    }));
  }

  function onNameChange(locale: LocaleTab, name: string) {
    setForm((current) => {
      const existing = current.translations[locale];
      const nextSlug = existing.slugManual ? existing.slug : slugFromName(name);
      return {
        ...current,
        translations: {
          ...current.translations,
          [locale]: {
            ...existing,
            name,
            slug: nextSlug,
          },
        },
      };
    });
  }

  function onPrimaryCategoryChange(value: string) {
    setForm((current) => {
      const primaryId = value === "" ? null : Number(value);
      let categoryIds = [...current.category_ids];
      if (primaryId !== null && !categoryIds.includes(primaryId)) {
        categoryIds = [...categoryIds, primaryId];
      }
      return {
        ...current,
        primary_category_id: value,
        category_ids: categoryIds,
      };
    });
  }

  function toggleCategory(categoryId: number) {
    setForm((current) => {
      const primaryId =
        current.primary_category_id === ""
          ? null
          : Number(current.primary_category_id);
      if (primaryId === categoryId) {
        return current;
      }
      const selected = current.category_ids.includes(categoryId)
        ? current.category_ids.filter((id) => id !== categoryId)
        : [...current.category_ids, categoryId];
      return { ...current, category_ids: selected };
    });
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (submitting || !canManage) {
      return;
    }

    setFormError(null);
    setNotice(null);

    const parsed = productFormSchema.safeParse({
      brand_id: form.brand_id === "" ? null : Number(form.brand_id),
      primary_category_id:
        form.primary_category_id === ""
          ? null
          : Number(form.primary_category_id),
      category_ids: form.category_ids,
      status: form.status,
      model_number: form.model_number,
      manufacturer_part_number: form.manufacturer_part_number,
      is_featured: form.is_featured,
      sort_order: Number(form.sort_order) || 0,
      translations: {
        ka: {
          locale: "ka" as const,
          name: form.translations.ka.name,
          slug: form.translations.ka.slug,
          short_description: form.translations.ka.short_description,
          description: form.translations.ka.description,
          seo_title: form.translations.ka.seo_title,
          seo_description: form.translations.ka.seo_description,
        },
        en: {
          locale: "en" as const,
          name: form.translations.en.name,
          slug: form.translations.en.slug,
          short_description: form.translations.en.short_description,
          description: form.translations.en.description,
          seo_title: form.translations.en.seo_title,
          seo_description: form.translations.en.seo_description,
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
      const step = wizardStepForErrors(next);
      if (step) {
        setWizardStep(step);
      }
      if (next["translations.ka.name"] || next["translations.ka.slug"]) {
        setLocaleTab("ka");
      } else if (next["translations.en.name"] || next["translations.en.slug"]) {
        setLocaleTab("en");
      }
      return;
    }

    if (form.status === "active" && !canPublish) {
      setFormError("Publishing permission is required to activate a product.");
      setWizardStep("publish");
      return;
    }

    setFieldErrors({});
    setSubmitting(true);

    const payload = buildPayload(form, { removeEnglish: hadEnglish });

    try {
      if (mode === "create") {
        const created = await createAdminProduct(payload);
        setNotice("Product created.");
        router.replace(`/admin/catalog/products/${created.id}/edit`);
        return;
      }

      if (!productId) {
        setFormError("Missing product id.");
        return;
      }

      const updated = await updateAdminProduct(productId, payload);
      setLoadResult({ key: productId, product: updated, error: null });
      setForm(formFromProduct(updated));
      setHadEnglish(updated.translations.some((row) => row.locale === "en"));
      setNotice("Product saved.");
    } catch (error) {
      if (error instanceof ApiClientError) {
        const mapped = mapFieldErrors(error.details);
        setFieldErrors(mapped);
        setFormError(error.message);
        const step = wizardStepForErrors(mapped);
        if (step) {
          setWizardStep(step);
        }
      } else {
        setFormError("Unable to save product.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function onArchiveConfirm() {
    if (!productId || submitting || !canManage) return;
    setSubmitting(true);
    setFormError(null);
    try {
      await archiveAdminProduct(productId);
      router.replace("/admin/catalog/products?include_deleted=1");
    } catch (error) {
      setFormError(
        error instanceof ApiClientError
          ? error.message
          : "Unable to archive product.",
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
        Loading product…
      </p>
    );
  }

  if (mode === "edit" && !product) {
    return (
      <div className="space-y-3">
        <p className="text-sm text-destructive" role="alert">
          {loadError ?? "Product not found."}
        </p>
        <Link
          href="/admin/catalog/products"
          className="text-sm font-medium text-primary underline-offset-4 hover:underline"
        >
          Back to products
        </Link>
      </div>
    );
  }

  const activeTranslation = form.translations[localeTab];
  const title =
    mode === "create"
      ? "New product"
      : form.translations.ka.name || `Product #${productId}`;

  return (
    <div className="space-y-6 pb-24">
      <div>
        <Link
          href="/admin/catalog/products"
          className="text-xs font-semibold uppercase tracking-[0.08em] text-muted-foreground no-underline hover:text-foreground"
        >
          ← Products
        </Link>
        <AdminPageHeader
          className="mt-3"
          title={title}
          description={
            mode === "create"
              ? "Create a draft product with Georgian content."
              : "Update product details, translations, and publishing state."
          }
        />
      </div>

      <ActiveNotPurchasableNotice />

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
        <ArchiveConfirmDialog
          productLabel={
            form.translations.ka.name
              ? `"${form.translations.ka.name}"`
              : `product #${productId}`
          }
          pending={submitting}
          onConfirm={() => void onArchiveConfirm()}
          onCancel={() => setConfirmArchive(false)}
        />
      ) : null}

      <Tabs
        value={wizardStep}
        onValueChange={(value) => setWizardStep(value as WizardStep)}
      >
        <TabsList aria-label="Product form steps">
          <TabsTrigger value="basics">Basics</TabsTrigger>
          <TabsTrigger value="catalog">Catalog</TabsTrigger>
          <TabsTrigger value="variants">Variants</TabsTrigger>
          <TabsTrigger value="content">Content</TabsTrigger>
          <TabsTrigger value="publish">Publish</TabsTrigger>
        </TabsList>

        <form id={formId} className="space-y-6" onSubmit={onSubmit} noValidate>
          <fieldset disabled={!canManage || submitting} className="space-y-6">
            <TabsContent value="basics">
              <AdminPanel>
                <AdminPanelHeader
                  title="Basics"
                  description="Identifiers and merchandising flags."
                />
                <div className="grid gap-4 md:grid-cols-2">
                  <Field
                    label="Model number"
                    htmlFor={`${formId}-model`}
                    error={fieldErrors.model_number}
                  >
                    <Input
                      id={`${formId}-model`}
                      value={form.model_number}
                      onChange={(event) =>
                        setForm((current) => ({
                          ...current,
                          model_number: event.target.value,
                        }))
                      }
                      aria-invalid={Boolean(fieldErrors.model_number)}
                    />
                  </Field>
                  <Field
                    label="Manufacturer part number"
                    htmlFor={`${formId}-mpn`}
                    error={fieldErrors.manufacturer_part_number}
                  >
                    <Input
                      id={`${formId}-mpn`}
                      value={form.manufacturer_part_number}
                      onChange={(event) =>
                        setForm((current) => ({
                          ...current,
                          manufacturer_part_number: event.target.value,
                        }))
                      }
                      aria-invalid={Boolean(
                        fieldErrors.manufacturer_part_number,
                      )}
                    />
                  </Field>
                  <Field label="Sort order" htmlFor={`${formId}-sort`}>
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
                        id={`${formId}-featured`}
                        checked={form.is_featured}
                        onCheckedChange={(checked) =>
                          setForm((current) => ({
                            ...current,
                            is_featured: checked,
                          }))
                        }
                      />
                      <Label htmlFor={`${formId}-featured`} className="text-sm">
                        Featured product
                      </Label>
                    </div>
                  </div>
                </div>
              </AdminPanel>
            </TabsContent>

            <TabsContent value="catalog">
              <AdminPanel>
                <AdminPanelHeader
                  title="Catalog"
                  description="Brand is optional. Primary category stays in the assigned set."
                />
                <div className="grid gap-4 md:grid-cols-2">
                  <Field
                    label="Brand"
                    htmlFor={`${formId}-brand`}
                    error={fieldErrors.brand_id}
                  >
                    <Select
                      value={form.brand_id || "none"}
                      onValueChange={(value) =>
                        setForm((current) => ({
                          ...current,
                          brand_id: value === "none" ? "" : value,
                        }))
                      }
                    >
                      <SelectTrigger
                        id={`${formId}-brand`}
                        aria-label="Brand"
                        aria-invalid={Boolean(fieldErrors.brand_id)}
                      >
                        <SelectValue placeholder="No brand" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">No brand</SelectItem>
                        {brands.map((brand) => (
                          <SelectItem key={brand.id} value={String(brand.id)}>
                            {brand.name ?? `Brand #${brand.id}`} ({brand.status}
                            )
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                  <Field
                    label="Primary category"
                    htmlFor={`${formId}-primary`}
                    error={fieldErrors.primary_category_id}
                  >
                    <Select
                      value={form.primary_category_id || "none"}
                      onValueChange={(value) =>
                        onPrimaryCategoryChange(value === "none" ? "" : value)
                      }
                    >
                      <SelectTrigger
                        id={`${formId}-primary`}
                        aria-label="Primary category"
                        aria-invalid={Boolean(fieldErrors.primary_category_id)}
                      >
                        <SelectValue placeholder="None" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="none">None</SelectItem>
                        {categories.map((category) => (
                          <SelectItem
                            key={category.id}
                            value={String(category.id)}
                          >
                            {category.name ?? `Category #${category.id}`} (
                            {category.status})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </Field>
                  <div className="space-y-2 md:col-span-2">
                    <p className="text-sm font-medium">Categories</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                      {categories.map((category) => {
                        const primaryId =
                          form.primary_category_id === ""
                            ? null
                            : Number(form.primary_category_id);
                        const locked = primaryId === category.id;
                        return (
                          <label
                            key={category.id}
                            className="flex items-center gap-2 text-sm"
                          >
                            <input
                              type="checkbox"
                              checked={form.category_ids.includes(category.id)}
                              disabled={locked}
                              onChange={() => toggleCategory(category.id)}
                            />
                            <span>
                              {category.name ?? `Category #${category.id}`}
                              {locked ? " (primary)" : ""}
                            </span>
                          </label>
                        );
                      })}
                    </div>
                    {fieldErrors.category_ids ? (
                      <p className="text-xs text-destructive" role="alert">
                        {fieldErrors.category_ids}
                      </p>
                    ) : null}
                  </div>
                </div>
              </AdminPanel>
            </TabsContent>

            <TabsContent value="content">
              <AdminPanel>
                <AdminPanelHeader
                  title="Content"
                  description="Georgian (ka) is required. English is optional. Values are kept when switching tabs."
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

                <div className="mt-4 grid gap-4 md:grid-cols-2" role="tabpanel">
                  <Field
                    label="Name"
                    htmlFor={`${formId}-name-${localeTab}`}
                    className="md:col-span-2"
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
                        onNameChange(localeTab, event.target.value)
                      }
                      aria-invalid={Boolean(
                        fieldErrors[`translations.${localeTab}.name`] ||
                        fieldErrors["translations.0.name"] ||
                        fieldErrors["translations.1.name"],
                      )}
                    />
                  </Field>
                  <Field
                    label="Slug"
                    htmlFor={`${formId}-slug-${localeTab}`}
                    className="md:col-span-2"
                    hint="Auto-fills from the name until you edit it manually."
                    error={
                      fieldErrors[`translations.${localeTab}.slug`] ??
                      fieldErrors["translations.0.slug"] ??
                      null
                    }
                  >
                    <Input
                      id={`${formId}-slug-${localeTab}`}
                      value={activeTranslation.slug}
                      onChange={(event) =>
                        updateTranslation(localeTab, {
                          slug: event.target.value,
                          slugManual: true,
                        })
                      }
                      aria-invalid={Boolean(
                        fieldErrors[`translations.${localeTab}.slug`] ||
                        fieldErrors["translations.0.slug"],
                      )}
                    />
                  </Field>
                  <Field
                    label="Short description"
                    htmlFor={`${formId}-short-${localeTab}`}
                    className="md:col-span-2"
                  >
                    <Textarea
                      id={`${formId}-short-${localeTab}`}
                      className="min-h-20"
                      value={activeTranslation.short_description}
                      onChange={(event) =>
                        updateTranslation(localeTab, {
                          short_description: event.target.value,
                        })
                      }
                    />
                  </Field>
                  <Field
                    label="Description"
                    htmlFor={`${formId}-desc-${localeTab}`}
                    className="md:col-span-2"
                  >
                    <Textarea
                      id={`${formId}-desc-${localeTab}`}
                      className="min-h-28"
                      value={activeTranslation.description}
                      onChange={(event) =>
                        updateTranslation(localeTab, {
                          description: event.target.value,
                        })
                      }
                    />
                  </Field>
                  <Field
                    label="SEO title"
                    htmlFor={`${formId}-seo-title-${localeTab}`}
                  >
                    <Input
                      id={`${formId}-seo-title-${localeTab}`}
                      value={activeTranslation.seo_title}
                      onChange={(event) =>
                        updateTranslation(localeTab, {
                          seo_title: event.target.value,
                        })
                      }
                    />
                  </Field>
                  <Field
                    label="SEO description"
                    htmlFor={`${formId}-seo-desc-${localeTab}`}
                  >
                    <Input
                      id={`${formId}-seo-desc-${localeTab}`}
                      value={activeTranslation.seo_description}
                      onChange={(event) =>
                        updateTranslation(localeTab, {
                          seo_description: event.target.value,
                        })
                      }
                    />
                  </Field>
                </div>
                {fieldErrors.translations ? (
                  <p className="mt-3 text-xs text-destructive" role="alert">
                    {fieldErrors.translations}
                  </p>
                ) : null}
              </AdminPanel>
            </TabsContent>

            <TabsContent value="publish">
              <AdminPanel>
                <AdminPanelHeader
                  title="Publish"
                  description="Activation requires catalog.publish and readiness checks."
                />
                <div className="space-y-4">
                  <Field
                    label="Status"
                    htmlFor={`${formId}-status`}
                    className="max-w-xs"
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
                          status: value as ProductStatus,
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
                  <ProductReadinessPanel readiness={readiness} />
                </div>
              </AdminPanel>
            </TabsContent>
          </fieldset>

          <div className="sticky bottom-0 z-10 -mx-1 border-t border-border/60 bg-background/90 px-1 py-4 backdrop-blur-md">
            <div className="flex flex-wrap gap-2">
              {canManage ? (
                <Button type="submit" size="sm" disabled={submitting}>
                  {submitting
                    ? "Saving…"
                    : mode === "create"
                      ? "Create product"
                      : "Save changes"}
                </Button>
              ) : (
                <p className="text-sm text-muted-foreground">
                  You can view products but need catalog.manage to edit.
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
                <Link href="/admin/catalog/products">Cancel</Link>
              </Button>
            </div>
          </div>
        </form>

        <TabsContent value="variants">
          {mode === "edit" && productId ? (
            <ProductVariantsSection
              productId={productId}
              canManage={canManage}
              canPublish={canPublish}
            />
          ) : (
            <AdminPanel>
              <AdminPanelHeader
                title="Variants"
                description="Save the product first, then configure variants."
              />
            </AdminPanel>
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
