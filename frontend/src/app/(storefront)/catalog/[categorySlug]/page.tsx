import { CatalogPage } from "@/features/storefront/components/CatalogPage";

export default async function Page({
  params,
}: {
  params: Promise<{ categorySlug: string }>;
}) {
  const { categorySlug } = await params;
  return <CatalogPage categorySlug={categorySlug} />;
}
