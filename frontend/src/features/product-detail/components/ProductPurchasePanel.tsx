"use client";

import { Button } from "@/components/ui/button";
import { QuantityStepper } from "@/components/commerce/quantity-stepper";
import { isCartEnabled } from "@/features/product-detail/cart/cart-gateway";
import {
  PRODUCT_QUANTITY_UI_MAX,
  clampPurchaseQuantity,
} from "@/features/product-detail/cart/purchase-intent";

export function ProductPurchasePanel({
  purchasable,
  hasPrice,
  quantity,
  onQuantityChange,
  onPurchase,
  pending = false,
  error = null,
  labels,
}: {
  purchasable: boolean;
  hasPrice: boolean;
  quantity: number;
  onQuantityChange: (value: number) => void;
  onPurchase: () => void;
  pending?: boolean;
  error?: string | null;
  labels: {
    quantity: string;
    decrease: string;
    increase: string;
    addToCart: string;
    cartSoon: string;
    unavailable: string;
    hint: string;
    disabledHint: string;
    adding: string;
  };
}) {
  const cartReady = isCartEnabled();
  const canSubmit = cartReady && purchasable && hasPrice && !pending;
  const actionLabel =
    !hasPrice || !purchasable
      ? labels.unavailable
      : !cartReady
        ? labels.cartSoon
        : pending
          ? labels.adding
          : labels.addToCart;
  const hint = !hasPrice || !purchasable ? labels.disabledHint : labels.hint;

  return (
    <div className="space-y-3">
      <QuantityStepper
        value={quantity}
        onChange={(value) => onQuantityChange(clampPurchaseQuantity(value))}
        min={1}
        max={PRODUCT_QUANTITY_UI_MAX}
        disabled={!purchasable || !hasPrice || pending}
        label={labels.quantity}
        decrementLabel={labels.decrease}
        incrementLabel={labels.increase}
      />
      <Button
        type="button"
        variant="primary"
        size="lg"
        fullWidth
        disabled={!canSubmit}
        loading={pending}
        onClick={onPurchase}
        aria-describedby={
          error ? "product-purchase-error" : "product-purchase-hint"
        }
        data-testid="product-purchase-action"
      >
        {actionLabel}
      </Button>
      {error ? (
        <p
          id="product-purchase-error"
          className="text-sm text-destructive"
          role="alert"
        >
          {error}
        </p>
      ) : (
        <p id="product-purchase-hint" className="text-sm text-muted-foreground">
          {hint}
        </p>
      )}
    </div>
  );
}
