export function ActiveNotPurchasableNotice({
  className,
}: {
  className?: string;
}) {
  return (
    <p
      className={
        className ??
        "rounded-md border border-border/70 bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
      }
      role="note"
    >
      Active means catalog content is ready to publish — not that the product is
      purchasable. SKU, price, inventory, and media come in later days.
    </p>
  );
}
