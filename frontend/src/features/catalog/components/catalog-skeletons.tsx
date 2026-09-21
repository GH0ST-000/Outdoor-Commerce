import { Skeleton } from "@/components/ui/skeleton";

export function ProductGridSkeleton({ count = 10 }: { count?: number }) {
  return (
    <div className="grid grid-cols-1 gap-4 min-[420px]:grid-cols-2 md:grid-cols-3 md:gap-5">
      {Array.from({ length: count }, (_, index) => (
        <div
          key={index}
          className="overflow-hidden rounded-[var(--radius-2xl)] border border-border/60 bg-card"
        >
          <Skeleton className="aspect-[4/5] w-full rounded-none motion-reduce:animate-none" />
          <div className="space-y-2 p-4">
            <Skeleton className="h-3 w-20 motion-reduce:animate-none" />
            <Skeleton className="h-5 w-4/5 motion-reduce:animate-none" />
            <Skeleton className="h-4 w-28 motion-reduce:animate-none" />
          </div>
        </div>
      ))}
    </div>
  );
}

export function CatalogPageSkeleton() {
  return (
    <div className="sf-band-paper min-h-[70svh] pb-16">
      <div className="sf-container py-10">
        <Skeleton className="h-4 w-40 motion-reduce:animate-none" />
        <Skeleton className="mt-4 h-14 w-2/3 max-w-lg motion-reduce:animate-none" />
        <Skeleton className="mt-3 h-4 w-full max-w-xl motion-reduce:animate-none" />
      </div>
      <div className="sf-container grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
        <ProductGridSkeleton />
        <div className="hidden space-y-3 lg:block">
          <Skeleton className="h-72 w-full rounded-2xl motion-reduce:animate-none" />
        </div>
      </div>
    </div>
  );
}

export function HomePageSkeleton() {
  return (
    <div>
      <div className="min-h-[100svh] bg-[#1a1613]" />
      <div className="sf-container sf-section space-y-6">
        <Skeleton className="h-10 w-64 motion-reduce:animate-none" />
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
          <Skeleton className="col-span-2 aspect-[16/10] rounded-[1.5rem] motion-reduce:animate-none" />
          <Skeleton className="aspect-[4/5] rounded-[1.5rem] motion-reduce:animate-none" />
          <Skeleton className="aspect-[4/5] rounded-[1.5rem] motion-reduce:animate-none" />
        </div>
      </div>
    </div>
  );
}
