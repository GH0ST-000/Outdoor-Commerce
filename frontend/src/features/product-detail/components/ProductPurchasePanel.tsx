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
  labels,
}: {
  purchasable: boolean;
  hasPrice: boolean;
  quantity: number;
  onQuantityChange: (value: number) => void;
  onPurchase: () => void;
  labels: {
    quantity: string;
    decrease: string;
    increase: string;
    addToCart: string;
    cartSoon: string;
    unavailable: string;
    hint: string;
    disabledHint: string;
  };
}) {
  const cartReady = isCartEnabled();
  const canSubmit = cartReady && purchasable && hasPrice;
  const actionLabel =
    !hasPrice || !purchasable
      ? labels.unavailable
      : cartReady
        ? labels.addToCart
        : labels.cartSoon;
  const hint = !hasPrice || !purchasable ? labels.disabledHint : labels.hint;

  return (
    <div className="space-y-3">
      <QuantityStepper
        value={quantity}
        onChange={(value) => onQuantityChange(clampPurchaseQuantity(value))}
        min={1}
        max={PRODUCT_QUANTITY_UI_MAX}
        disabled={!purchasable || !hasPrice}
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
        onClick={onPurchase}
        aria-describedby="product-purchase-hint"
        data-testid="product-purchase-action"
      >
        {actionLabel}
      </Button>
      <p id="product-purchase-hint" className="text-sm text-muted-foreground">
        {hint}
      </p>
    </div>
  );
}
