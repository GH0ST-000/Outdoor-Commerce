import { ProductGrid } from "@/features/storefront/components/commerce/ProductCard";
import type { ProductCardData } from "@/features/storefront/types/storefront-types";

export function RelatedProducts({
  products,
  heading,
}: {
  products: ProductCardData[];
  heading: string;
}) {
  if (products.length === 0) {
    return null;
  }

  return (
    <section className="space-y-4" aria-labelledby="related-products-heading">
      <h2 id="related-products-heading" className="font-display text-2xl">
        {heading}
      </h2>
      <ProductGrid products={products} />
    </section>
  );
}
