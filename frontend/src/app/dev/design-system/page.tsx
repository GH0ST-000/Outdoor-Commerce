import { notFound } from "next/navigation";

export const metadata = {
  title: "Storefront design system",
  robots: { index: false, follow: false },
};

export default async function DesignSystemPage() {
  if (process.env.NODE_ENV === "production") {
    notFound();
  }

  const { DesignSystemGallery } = await import("./gallery");
  return <DesignSystemGallery />;
}
