import type {
  PublicAvailability,
  PublicBrandDetail,
  PublicProductDetail,
  PublicVariantAxis,
  PublicVariantCombination,
  PublicVariantPrice,
} from "@/features/catalog/types/public-catalog";

export type VariantSelection = Record<string, string>;

export type VariantValueAvailability = "available" | "out_of_stock" | "invalid";

export type VariantIndex = {
  combinations: PublicVariantCombination[];
  variantById: Map<number, PublicVariantCombination>;
  variantByKey: Map<string, PublicVariantCombination>;
  valuesByAttribute: Map<string, Set<string>>;
};

export type VariantSelectionState = {
  selectedVariant: PublicVariantCombination | null;
  selection: VariantSelection;
  usedUrlFallback: boolean;
};

export type ProductPurchaseIntent = {
  product_id: number;
  product_variant_id: number;
  quantity: number;
  pricing_signature: string | null;
};

export type AddToCartCommand = ProductPurchaseIntent;

export type CartGateway = {
  addToCart: (command: AddToCartCommand) => Promise<void>;
};

export type ProductDetailExperienceProps = {
  detail: PublicProductDetail;
  related: Array<
    import("@/features/storefront/types/storefront-types").ProductCardData
  >;
  brandDetail?: PublicBrandDetail | null;
  initialVariantId?: number | null;
};

export type {
  PublicVariantAxis,
  PublicVariantCombination,
  PublicVariantPrice,
  PublicAvailability,
};
