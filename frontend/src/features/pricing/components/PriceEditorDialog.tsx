"use client";

import { FormEvent, useEffect, useState } from "react";
import {
  cancelAdminPricePeriod,
  createAdminVariantPrice,
  fetchAdminVariantPrice,
  publishAdminPricePeriod,
  replaceAdminVariantPrice,
  updateAdminPricePeriod,
} from "@/features/pricing/api/prices-api";
import {
  CURRENCIES,
  formatMoneyMinor,
  parseMajorToMinor,
} from "@/features/pricing/lib/money";
import { mapApiFieldErrors } from "@/features/pricing/schemas/pricing-schemas";
import type {
  PricePeriod,
  VariantPriceRow,
} from "@/features/pricing/types/pricing-types";
import { AdminPanel, AdminPanelHeader } from "@/features/admin/ui/AdminPanel";
import { StatusBadge } from "@/features/admin/ui/StatusBadge";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";

function toDatetimeLocalValue(iso: string | null | undefined): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return "";
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function fromDatetimeLocalValue(value: string): string {
  if (!value) return new Date().toISOString();
  return new Date(value).toISOString();
}

function minorToMajorInput(amountMinor: number, currencyCode: string): string {
  const currency = CURRENCIES[currencyCode] ?? CURRENCIES.GEL!;
  const major = amountMinor / 10 ** currency.minor_units;
  return major.toFixed(currency.minor_units);
}

export function PriceEditorDialog({
  row,
  canManage,
  canPublish,
  onClose,
  onSaved,
}: {
  row: VariantPriceRow;
  canManage: boolean;
  canPublish: boolean;
  onClose: () => void;
  onSaved: (message: string) => void;
}) {
  const currency = row.currency_code;
  const draftPeriod =
    row.scheduled_period?.status === "draft"
      ? row.scheduled_period
      : row.current_period?.status === "draft"
        ? row.current_period
        : null;

  const [amountInput, setAmountInput] = useState("");
  const [startsAt, setStartsAt] = useState(
    toDatetimeLocalValue(new Date().toISOString()),
  );
  const [endsAt, setEndsAt] = useState("");
  const [confirmReplace, setConfirmReplace] = useState(false);
  const [history, setHistory] = useState<PricePeriod[]>([]);
  const [expectedVersion, setExpectedVersion] = useState(row.version);
  const [loadingDetail, setLoadingDetail] = useState(true);
  const [pending, setPending] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    void fetchAdminVariantPrice(row.price_list_id, row.product_variant_id)
      .then((detail) => {
        if (cancelled) return;
        setExpectedVersion(detail.version);
        setHistory(detail.periods ?? []);
        const draft =
          detail.scheduled_period?.status === "draft"
            ? detail.scheduled_period
            : detail.current_period?.status === "draft"
              ? detail.current_period
              : null;
        if (draft) {
          setAmountInput(minorToMajorInput(draft.amount_minor, currency));
          setStartsAt(toDatetimeLocalValue(draft.starts_at));
          setEndsAt(toDatetimeLocalValue(draft.ends_at));
        } else if (detail.effective_amount_minor != null) {
          setAmountInput(
            minorToMajorInput(detail.effective_amount_minor, currency),
          );
        }
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setFormError(
          err instanceof ApiClientError
            ? err.message
            : "Unable to load price detail.",
        );
      })
      .finally(() => {
        if (!cancelled) setLoadingDetail(false);
      });
    return () => {
      cancelled = true;
    };
  }, [row.price_list_id, row.product_variant_id]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!canManage || pending) return;

    const parsed = parseMajorToMinor(amountInput, currency);
    if (!parsed.ok) {
      setErrors({ amount: "Enter a valid price amount." });
      return;
    }
    setErrors({});

    setPending(true);
    setFormError(null);
    try {
      const starts_at = fromDatetimeLocalValue(startsAt);
      const ends_at = endsAt ? fromDatetimeLocalValue(endsAt) : null;

      if (draftPeriod) {
        await updateAdminPricePeriod(draftPeriod.id, {
          amount_minor: parsed.amount_minor,
          starts_at,
          ends_at,
        });
        onSaved("Draft price updated.");
        onClose();
        return;
      }

      if (row.current_period && !confirmReplace) {
        setFormError(
          "Published prices require replace confirmation. Enable confirm replace to schedule a new period.",
        );
        return;
      }

      if (row.current_period) {
        await replaceAdminVariantPrice(
          row.price_list_id,
          row.product_variant_id,
          {
            amount_minor: parsed.amount_minor,
            starts_at,
            ends_at,
            expected_version: expectedVersion,
            confirm_replace: true,
          },
        );
        onSaved("Price replacement scheduled.");
      } else {
        await createAdminVariantPrice(
          row.price_list_id,
          row.product_variant_id,
          {
            amount_minor: parsed.amount_minor,
            starts_at,
            ends_at,
          },
        );
        onSaved("Draft price created.");
      }
      onClose();
    } catch (err) {
      if (err instanceof ApiClientError) {
        setFormError(err.message);
        setErrors(mapApiFieldErrors(err.details));
      } else {
        setFormError("Unable to save price.");
      }
    } finally {
      setPending(false);
    }
  }

  async function runPeriodAction(
    task: () => Promise<PricePeriod>,
    message: string,
  ) {
    if (pending) return;
    setPending(true);
    setFormError(null);
    try {
      await task();
      onSaved(message);
      onClose();
    } catch (err) {
      setFormError(
        err instanceof ApiClientError
          ? err.message
          : "Unable to complete action.",
      );
    } finally {
      setPending(false);
    }
  }

  const readOnlyAmount =
    Boolean(row.current_period?.status === "published") &&
    !draftPeriod &&
    !confirmReplace;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-background/80 p-4 backdrop-blur-sm">
      <AdminPanel className="relative w-full max-w-2xl">
        <AdminPanelHeader
          title={`Edit price — ${row.product_name ?? "Variant"} ${row.sku ? `(${row.sku})` : ""}`}
        />
        <div className="space-y-4 p-4 pt-0">
          {loadingDetail ? (
            <p className="text-sm text-muted-foreground" role="status">
              Loading price detail…
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

          {!loadingDetail && canManage ? (
            <form
              className="space-y-4"
              onSubmit={(event) => void onSubmit(event)}
            >
              <p className="text-xs text-muted-foreground">
                Version {expectedVersion}. Draft periods allow amount edits;
                published prices require replace with confirmation.
              </p>
              <Field
                label="Amount"
                htmlFor="price-amount"
                error={errors.amount}
              >
                <Input
                  id="price-amount"
                  value={amountInput}
                  onChange={(event) => setAmountInput(event.target.value)}
                  placeholder="0.00"
                  readOnly={readOnlyAmount && !confirmReplace}
                />
              </Field>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Starts at" htmlFor="price-starts">
                  <Input
                    id="price-starts"
                    type="datetime-local"
                    value={startsAt}
                    onChange={(event) => setStartsAt(event.target.value)}
                  />
                </Field>
                <Field label="Ends at (optional)" htmlFor="price-ends">
                  <Input
                    id="price-ends"
                    type="datetime-local"
                    value={endsAt}
                    onChange={(event) => setEndsAt(event.target.value)}
                  />
                </Field>
              </div>
              {row.current_period && !draftPeriod ? (
                <div className="flex items-center gap-2">
                  <Switch
                    id="price-confirm-replace"
                    checked={confirmReplace}
                    onCheckedChange={setConfirmReplace}
                  />
                  <label htmlFor="price-confirm-replace" className="text-sm">
                    Confirm replace published price
                  </label>
                </div>
              ) : null}
              <div className="flex flex-wrap gap-2">
                <Button type="submit" size="sm" disabled={pending}>
                  {pending ? "Saving…" : "Save"}
                </Button>
                {canPublish && draftPeriod ? (
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={pending}
                    onClick={() =>
                      void runPeriodAction(
                        () => publishAdminPricePeriod(draftPeriod.id),
                        "Price published.",
                      )
                    }
                  >
                    Publish draft
                  </Button>
                ) : null}
                {canManage && draftPeriod ? (
                  <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={pending}
                    onClick={() =>
                      void runPeriodAction(
                        () => cancelAdminPricePeriod(draftPeriod.id),
                        "Draft cancelled.",
                      )
                    }
                  >
                    Cancel draft
                  </Button>
                ) : null}
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  disabled={pending}
                  onClick={onClose}
                >
                  Close
                </Button>
              </div>
            </form>
          ) : null}

          {!canManage ? (
            <p className="text-sm text-muted-foreground">
              You do not have permission to edit prices.
            </p>
          ) : null}

          {history.length > 0 ? (
            <div className="space-y-2 border-t border-border/60 pt-4">
              <h3 className="text-sm font-medium">History</h3>
              <ul className="space-y-2 text-sm">
                {history.map((period) => (
                  <li
                    key={period.id}
                    className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/60 px-3 py-2"
                  >
                    <span>
                      {formatMoneyMinor(period.amount_minor, currency)} ·{" "}
                      {new Date(period.starts_at).toLocaleString()}
                    </span>
                    <StatusBadge status={period.status} />
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </div>
      </AdminPanel>
    </div>
  );
}
