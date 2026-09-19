import { ProductDetailPage } from "@/features/storefront/components/ProductDetailPage";

export default async function Page({
  params,
}: {
  params: Promise<{ productSlug: string }>;
}) {
  const { productSlug } = await params;
  return <ProductDetailPage slug={productSlug} />;
}
