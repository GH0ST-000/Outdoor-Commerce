import { Skeleton } from "@/components/ui/skeleton";
import { ProductGridSkeleton } from "@/features/catalog/components/catalog-skeletons";

export function ProductDetailSkeleton() {
  return (
    <div className="sf-band-paper min-h-[70svh] pb-28 lg:pb-16">
      <div className="sf-container pt-6 pb-8">
        <Skeleton className="h-4 w-56 motion-reduce:animate-none" />
      </div>
      <div className="sf-container grid items-start gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(20rem,0.95fr)]">
        <div className="space-y-3">
          <Skeleton className="aspect-[4/5] w-full rounded-[1.5rem] motion-reduce:animate-none" />
          <div className="hidden gap-2 md:flex">
            <Skeleton className="size-16 rounded-lg motion-reduce:animate-none" />
            <Skeleton className="size-16 rounded-lg motion-reduce:animate-none" />
            <Skeleton className="size-16 rounded-lg motion-reduce:animate-none" />
          </div>
        </div>
        <div className="space-y-4">
          <Skeleton className="h-3 w-24 motion-reduce:animate-none" />
          <Skeleton className="h-10 w-4/5 motion-reduce:animate-none" />
          <Skeleton className="h-8 w-32 motion-reduce:animate-none" />
          <Skeleton className="h-16 w-full motion-reduce:animate-none" />
          <Skeleton className="h-11 w-full motion-reduce:animate-none" />
          <Skeleton className="h-12 w-full rounded-full motion-reduce:animate-none" />
        </div>
      </div>
      <div className="sf-container mt-12 space-y-4">
        <Skeleton className="h-8 w-48 motion-reduce:animate-none" />
        <Skeleton className="h-24 w-full max-w-prose motion-reduce:animate-none" />
        <ProductGridSkeleton count={4} />
      </div>
    </div>
  );
}
