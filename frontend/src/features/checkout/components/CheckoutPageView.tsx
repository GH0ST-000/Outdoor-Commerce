"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { ApiClientError } from "@/lib/api-client";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { EmptyState } from "@/components/ui/empty-state";
import { useLocale } from "@/components/locale-provider";
import { useCart } from "@/features/cart/providers/CartProvider";
import { PriceDisplay } from "@/features/pricing/components/PriceDisplay";
import { useCheckoutCopy } from "@/features/checkout/copy";
import { QuoteSummary } from "@/features/checkout/components/QuoteSummary";
import {
  checkoutAddressSchema,
  checkoutContactSchema,
} from "@/features/checkout/lib/schemas";
import {
  clearRememberedCheckoutSessionId,
  createCheckoutQuote,
  createCheckoutSession,
  getCheckoutSession,
  rememberedCheckoutSessionId,
  updateCheckoutAddress,
  updateCheckoutContact,
  updateCheckoutFulfillment,
} from "@/features/checkout/api/checkout-client";
import {
  clearOrderIdempotencyKey,
  createOrder,
  rememberOrderIdempotencyKey,
} from "@/features/orders/api/order-client";
import { useOrderCopy } from "@/features/orders/copy";
import type {
  CheckoutSession,
  CheckoutStep,
  FulfillmentMethodOption,
} from "@/features/checkout/types";
import { cn } from "@/lib/utils";

function fieldError(
  details: Record<string, string[]> | undefined,
  key: string,
): string | undefined {
  const value = details?.[key];
  return Array.isArray(value) ? value[0] : undefined;
}

function remainingLabel(expiresAt: string, now: number): string {
  const ms = Date.parse(expiresAt) - now;
  if (Number.isNaN(ms) || ms <= 0) {
    return "";
  }
  const total = Math.floor(ms / 1000);
  const minutes = Math.floor(total / 60);
  const seconds = total % 60;
  return `${minutes}:${seconds.toString().padStart(2, "0")}`;
}

export function CheckoutPageView() {
  const copy = useCheckoutCopy();
  const orderCopy = useOrderCopy();
  const cart = useCart();
  const router = useRouter();
  const { locale } = useLocale();
  const localeTag = locale === "ka" ? "ka-GE" : "en";
  const headingRef = useRef<HTMLHeadingElement>(null);
  const firstErrorRef = useRef<HTMLInputElement>(null);
  const [session, setSession] = useState<CheckoutSession | null>(null);
  const [step, setStep] = useState<CheckoutStep>("contact");
  const [status, setStatus] = useState<"loading" | "ready" | "empty" | "error">(
    "loading",
  );
  const [pending, setPending] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [now, setNow] = useState(() => Date.now());
  const [contact, setContact] = useState({
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    customer_note: "",
  });
  const [address, setAddress] = useState({
    recipient_first_name: "",
    recipient_last_name: "",
    phone: "",
    country_code: "GE",
    municipality_or_city: "",
    street: "",
    house_number: "",
    apartment: "",
    postal_code: "",
    delivery_instructions: "",
  });
  const [methodCode, setMethodCode] = useState<string>("");

  const quote = session?.quote ?? null;
  const expired = quote !== null && Date.parse(quote.expires_at) <= now;
  const selectedMethod = session?.available_fulfillment_methods.find(
    (method) => method.code === methodCode,
  );
  const requiresAddress = selectedMethod?.requires_address === true;
  const cartEmpty = cart.status === "ready" && cart.cart.item_count === 0;
  const cartHasItems = cart.status === "ready" && cart.cart.item_count > 0;

  const load = useCallback(async () => {
    try {
      const remembered = rememberedCheckoutSessionId();
      let next: CheckoutSession;
      if (remembered) {
        try {
          next = await getCheckoutSession(remembered);
        } catch (caught) {
          if (caught instanceof ApiClientError && caught.status === 404) {
            next = await createCheckoutSession();
          } else {
            throw caught;
          }
        }
      } else {
        next = await createCheckoutSession();
      }
      setSession(next);
      setContact({
        first_name: next.contact.first_name ?? "",
        last_name: next.contact.last_name ?? "",
        email: next.contact.email ?? "",
        phone: next.contact.phone ?? "",
        customer_note: next.contact.customer_note ?? "",
      });
      setAddress((current) => ({
        ...current,
        recipient_first_name:
          next.address?.recipient_first_name ??
          next.contact.first_name ??
          current.recipient_first_name,
        recipient_last_name:
          next.address?.recipient_last_name ??
          next.contact.last_name ??
          current.recipient_last_name,
        phone: next.address?.phone ?? next.contact.phone ?? current.phone,
        country_code: next.address?.country_code ?? "GE",
        municipality_or_city: next.address?.municipality_or_city ?? "",
        street: next.address?.street ?? "",
        house_number: next.address?.house_number ?? "",
        apartment: next.address?.apartment ?? "",
        postal_code: next.address?.postal_code ?? "",
        delivery_instructions: next.address?.delivery_instructions ?? "",
      }));
      setMethodCode(next.fulfillment.method_code ?? "");
      setError(null);
      setStatus("ready");
      if (next.quote) {
        setStep("review");
      } else if (next.contact.complete) {
        setStep("delivery");
      }
    } catch (caught) {
      if (
        caught instanceof ApiClientError &&
        caught.code === "CHECKOUT_CART_EMPTY"
      ) {
        clearRememberedCheckoutSessionId();
        setStatus("empty");
        return;
      }
      setError(
        caught instanceof ApiClientError ? caught.message : copy.loadError,
      );
      setStatus("error");
    }
  }, [copy.loadError]);

  useEffect(() => {
    if (!cartHasItems) {
      if (cartEmpty) {
        clearRememberedCheckoutSessionId();
      }
      return;
    }
    const timer = window.setTimeout(() => {
      void load();
    }, 0);
    return () => window.clearTimeout(timer);
  }, [cartHasItems, cartEmpty, load]);

  useEffect(() => {
    headingRef.current?.focus();
  }, [step]);

  useEffect(() => {
    if (!quote || expired) {
      return;
    }
    const timer = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(timer);
  }, [quote, expired]);

  async function run(label: string, task: () => Promise<CheckoutSession>) {
    setPending(label);
    setError(null);
    setFieldErrors({});
    try {
      const next = await task();
      setSession(next);
      if (next.fulfillment.method_code) {
        setMethodCode(next.fulfillment.method_code);
      }
      return next;
    } catch (caught) {
      if (caught instanceof ApiClientError) {
        setError(
          caught.code === "CHECKOUT_VERSION_CONFLICT"
            ? copy.versionConflict
            : caught.code === "CHECKOUT_INSUFFICIENT_STOCK"
              ? copy.stockChanged
              : caught.message,
        );
        if (caught.details) {
          setFieldErrors(caught.details);
          window.setTimeout(() => firstErrorRef.current?.focus(), 0);
        }
        if (caught.code === "CHECKOUT_VERSION_CONFLICT") {
          void load();
        }
      } else {
        setError(copy.loadError);
      }
      return null;
    } finally {
      setPending(null);
    }
  }

  async function saveContact() {
    if (!session) {
      return;
    }
    const parsed = checkoutContactSchema.safeParse(contact);
    if (!parsed.success) {
      setError(parsed.error.issues[0]?.message ?? copy.loadError);
      firstErrorRef.current?.focus();
      return;
    }
    const next = await run("contact", () =>
      updateCheckoutContact(session.id, {
        ...parsed.data,
        checkout_version: session.version,
      }),
    );
    if (next) {
      setStep("delivery");
    }
  }

  async function saveDelivery() {
    if (!session) {
      return;
    }
    if (!methodCode) {
      setError(copy.noMethods);
      return;
    }
    const method = session.available_fulfillment_methods.find(
      (item) => item.code === methodCode,
    );
    const pickup = method?.type === "store_pickup";
    if (!pickup) {
      const parsed = checkoutAddressSchema.safeParse(address);
      if (!parsed.success) {
        setError(parsed.error.issues[0]?.message ?? copy.loadError);
        firstErrorRef.current?.focus();
        return;
      }
      const afterAddress = await run("address", () =>
        updateCheckoutAddress(session.id, {
          ...parsed.data,
          checkout_version: session.version,
        }),
      );
      if (!afterAddress) {
        return;
      }
      const afterMethod = await run("fulfillment", () =>
        updateCheckoutFulfillment(afterAddress.id, {
          method_code: methodCode,
          checkout_version: afterAddress.version,
        }),
      );
      if (afterMethod) {
        setStep("review");
      }
      return;
    }
    const next = await run("fulfillment", () =>
      updateCheckoutFulfillment(session.id, {
        method_code: methodCode,
        checkout_version: session.version,
        pickup_location_id:
          method?.pickup_locations?.find((item) => item.selected)?.id ??
          method?.pickup_locations?.[0]?.id,
      }),
    );
    if (next) {
      setStep("review");
    }
  }

  async function quoteTotals() {
    if (!session) {
      return;
    }
    await run("quote", () =>
      createCheckoutQuote(session.id, session.version, cart.cart.version),
    );
  }

  async function confirmOrder() {
    if (!session?.quote || expired || pending !== null) {
      return;
    }
    const quoteId = session.quote.id;
    const key = rememberOrderIdempotencyKey(quoteId);
    setPending("order");
    setError(null);
    try {
      const order = await createOrder({
        checkoutSessionId: session.id,
        quoteId,
        checkoutVersion: session.version,
        idempotencyKey: key,
      });
      clearOrderIdempotencyKey(quoteId);
      clearRememberedCheckoutSessionId();
      await cart.refresh();
      router.push(`/order-confirmation/${order.id}`);
    } catch (caught) {
      if (caught instanceof ApiClientError) {
        if (
          caught.code === "ORDER_QUOTE_EXPIRED" ||
          caught.code === "CHECKOUT_QUOTE_EXPIRED"
        ) {
          setError(orderCopy.quoteExpired);
          await quoteTotals();
        } else if (
          caught.code === "ORDER_RESERVATION_EXPIRED" ||
          caught.code === "ORDER_RESERVATION_MISSING"
        ) {
          setError(orderCopy.reservationExpired);
        } else if (
          caught.code === "ORDER_VERSION_CONFLICT" ||
          caught.code === "CHECKOUT_VERSION_CONFLICT"
        ) {
          setError(orderCopy.versionConflict);
          void load();
        } else {
          setError(caught.message);
        }
      } else {
        setError(orderCopy.confirming);
      }
    } finally {
      setPending(null);
    }
  }

  const steps = useMemo(
    () => [
      { id: "contact" as const, label: copy.steps.contact },
      { id: "delivery" as const, label: copy.steps.delivery },
      { id: "review" as const, label: copy.steps.review },
    ],
    [copy.steps],
  );

  if (cartEmpty || status === "empty") {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <EmptyState title={copy.emptyTitle} description={copy.emptyHint} />
        <div className="mt-4 text-center">
          <Button asChild>
            <Link href="/cart">{copy.shopCart}</Link>
          </Button>
        </div>
      </div>
    );
  }

  if (status === "loading") {
    return (
      <div className="mx-auto max-w-5xl px-4 py-16" aria-busy="true">
        <p>{copy.loading}</p>
      </div>
    );
  }

  if (status === "error" || !session) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16">
        <Alert tone="danger" title={copy.loadError}>
          <p>{error}</p>
          <Button className="mt-3" type="button" onClick={() => void load()}>
            {copy.retry}
          </Button>
        </Alert>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 lg:py-14">
      <h1
        ref={headingRef}
        tabIndex={-1}
        className="text-3xl font-semibold tracking-tight outline-none"
      >
        {copy.title}
      </h1>
      <ol className="mt-6 flex gap-4 text-sm" aria-label={copy.title}>
        {steps.map((item) => (
          <li key={item.id}>
            <span
              className={cn(
                "rounded-full px-3 py-1",
                step === item.id
                  ? "bg-primary text-primary-foreground"
                  : "bg-muted text-muted-foreground",
              )}
            >
              {item.label}
            </span>
          </li>
        ))}
      </ol>
      {error ? (
        <Alert tone="danger" className="mt-6">
          {error}
        </Alert>
      ) : null}
      <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div>
          {step === "contact" ? (
            <form
              className="grid gap-4 sm:grid-cols-2"
              onSubmit={(event) => {
                event.preventDefault();
                void saveContact();
              }}
            >
              <Field
                label={copy.firstName}
                htmlFor="checkout-first-name"
                required
                error={fieldError(fieldErrors, "first_name")}
              >
                <Input
                  ref={firstErrorRef}
                  value={contact.first_name}
                  onChange={(event) =>
                    setContact((current) => ({
                      ...current,
                      first_name: event.target.value,
                    }))
                  }
                  autoComplete="given-name"
                />
              </Field>
              <Field
                label={copy.lastName}
                htmlFor="checkout-last-name"
                required
                error={fieldError(fieldErrors, "last_name")}
              >
                <Input
                  value={contact.last_name}
                  onChange={(event) =>
                    setContact((current) => ({
                      ...current,
                      last_name: event.target.value,
                    }))
                  }
                  autoComplete="family-name"
                />
              </Field>
              <Field
                label={copy.email}
                htmlFor="checkout-email"
                required
                error={fieldError(fieldErrors, "email")}
              >
                <Input
                  type="email"
                  value={contact.email}
                  onChange={(event) =>
                    setContact((current) => ({
                      ...current,
                      email: event.target.value,
                    }))
                  }
                  autoComplete="email"
                />
              </Field>
              <Field
                label={copy.phone}
                htmlFor="checkout-phone"
                required
                error={fieldError(fieldErrors, "phone")}
              >
                <Input
                  type="tel"
                  value={contact.phone}
                  onChange={(event) =>
                    setContact((current) => ({
                      ...current,
                      phone: event.target.value,
                    }))
                  }
                  autoComplete="tel"
                />
              </Field>
              <div className="sm:col-span-2">
                <Field label={copy.note} htmlFor="checkout-note">
                  <Textarea
                    value={contact.customer_note}
                    onChange={(event) =>
                      setContact((current) => ({
                        ...current,
                        customer_note: event.target.value,
                      }))
                    }
                  />
                </Field>
              </div>
              <div className="sm:col-span-2">
                <Button type="submit" disabled={pending === "contact"}>
                  {copy.saveContact}
                </Button>
              </div>
            </form>
          ) : null}

          {step === "delivery" ? (
            <form
              className="space-y-6"
              onSubmit={(event) => {
                event.preventDefault();
                void saveDelivery();
              }}
            >
              <RadioGroup
                value={methodCode}
                onValueChange={setMethodCode}
                aria-label={copy.delivery}
                className="gap-3"
              >
                {session.available_fulfillment_methods.map(
                  (method: FulfillmentMethodOption) => (
                    <label
                      key={method.id}
                      className={cn(
                        "flex cursor-pointer gap-3 rounded-[var(--radius-xl)] border p-4",
                        method.eligible
                          ? "border-border/70"
                          : "border-border/40 opacity-70",
                      )}
                    >
                      <RadioGroupItem
                        value={method.code}
                        disabled={!method.eligible}
                        aria-label={method.name}
                      />
                      <span>
                        <span className="block font-medium">{method.name}</span>
                        <span className="block text-sm text-muted-foreground">
                          {method.description}
                        </span>
                        {method.eligible && method.amount_minor !== null ? (
                          <span className="mt-1 block text-sm">
                            <PriceDisplay
                              currency={method.currency}
                              locale={localeTag}
                              amountMinor={method.amount_minor}
                            />
                          </span>
                        ) : null}
                        {!method.eligible ? (
                          <span className="mt-1 block text-sm text-destructive">
                            {method.unavailable_reason ?? copy.unavailable}
                          </span>
                        ) : null}
                      </span>
                    </label>
                  ),
                )}
              </RadioGroup>
              {selectedMethod?.type === "store_pickup" &&
              selectedMethod.pickup_locations?.length ? (
                <p className="text-sm text-muted-foreground">
                  {selectedMethod.pickup_locations.find((item) => item.selected)
                    ?.name ?? selectedMethod.pickup_locations[0]?.name}
                </p>
              ) : null}
              {requiresAddress ? (
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label={copy.city} htmlFor="checkout-city" required>
                    <Input
                      value={address.municipality_or_city}
                      onChange={(event) =>
                        setAddress((current) => ({
                          ...current,
                          municipality_or_city: event.target.value,
                        }))
                      }
                    />
                  </Field>
                  <Field label={copy.street} htmlFor="checkout-street">
                    <Input
                      value={address.street}
                      onChange={(event) =>
                        setAddress((current) => ({
                          ...current,
                          street: event.target.value,
                        }))
                      }
                    />
                  </Field>
                  <Field label={copy.house} htmlFor="checkout-house">
                    <Input
                      value={address.house_number}
                      onChange={(event) =>
                        setAddress((current) => ({
                          ...current,
                          house_number: event.target.value,
                        }))
                      }
                    />
                  </Field>
                  <Field label={copy.apartment} htmlFor="checkout-apt">
                    <Input
                      value={address.apartment}
                      onChange={(event) =>
                        setAddress((current) => ({
                          ...current,
                          apartment: event.target.value,
                        }))
                      }
                    />
                  </Field>
                </div>
              ) : null}
              <div className="flex gap-3">
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => setStep("contact")}
                >
                  {copy.back}
                </Button>
                <Button type="submit" disabled={pending !== null}>
                  {copy.saveDelivery}
                </Button>
              </div>
            </form>
          ) : null}

          {step === "review" ? (
            <div className="space-y-6">
              <p>
                <button
                  type="button"
                  className="text-sm underline"
                  onClick={() => setStep("contact")}
                >
                  {copy.editContact}
                </button>
                {" · "}
                <button
                  type="button"
                  className="text-sm underline"
                  onClick={() => setStep("delivery")}
                >
                  {copy.editDelivery}
                </button>
              </p>
              {pending === "quote" ? <p>{copy.quoteLoading}</p> : null}
              {pending === "order" ? (
                <p role="status">{orderCopy.confirming}</p>
              ) : null}
              {quote && !expired ? (
                <Alert tone="info">{orderCopy.confirmHint}</Alert>
              ) : null}
              <div className="flex flex-col gap-3 sm:flex-row">
                <Button
                  type="button"
                  onClick={() => void quoteTotals()}
                  disabled={pending !== null}
                >
                  {quote ? copy.refreshQuote : copy.createQuote}
                </Button>
                <Button
                  type="button"
                  variant="secondary"
                  disabled={pending !== null || !quote || expired}
                  onClick={() => void confirmOrder()}
                >
                  {orderCopy.confirmOrder}
                </Button>
              </div>
            </div>
          ) : null}
        </div>
        <div>
          {quote ? (
            <QuoteSummary
              quote={quote}
              expired={expired}
              remainingLabel={remainingLabel(quote.expires_at, now)}
            />
          ) : (
            <aside className="rounded-[var(--radius-2xl)] border border-border/60 bg-card p-5 text-sm text-muted-foreground">
              {copy.quoteLoading}
            </aside>
          )}
        </div>
      </div>
    </div>
  );
}
