import { FieldGuideArticle } from "@/features/storefront/components/FieldGuideArticle";

export default async function Page({
  params,
}: {
  params: Promise<{ articleSlug: string }>;
}) {
  const { articleSlug } = await params;
  return <FieldGuideArticle slug={articleSlug} />;
}
