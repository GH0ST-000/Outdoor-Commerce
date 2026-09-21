import Link from "next/link";
import { EmptyState } from "@/components/ui/empty-state";
import { Button } from "@/components/ui/button";
import { storefrontCopy } from "@/features/storefront/fixtures/demo-catalog";

export default function NotFound() {
  const copy = storefrontCopy.ka;
  return (
    <div className="sf-container sf-section space-y-4">
      <EmptyState
        title={copy.product.notFound}
        description={copy.catalog.emptyCategoryDescription}
      />
      <div className="text-center">
        <Button asChild variant="outline">
          <Link href="/catalog">{copy.catalog.allCategories}</Link>
        </Button>
      </div>
    </div>
  );
}
