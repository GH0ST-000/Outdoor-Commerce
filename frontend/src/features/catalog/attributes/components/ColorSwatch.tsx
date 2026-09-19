export function ColorSwatch({ hex }: { hex: string | null }) {
  if (!hex) {
    return <span className="text-xs text-muted-foreground">—</span>;
  }

  return (
    <span className="inline-flex items-center gap-2">
      <span
        role="img"
        aria-label={`Color ${hex}`}
        title={hex}
        className="inline-block size-4 rounded-full border border-border/80"
        style={{ backgroundColor: hex }}
        data-testid="color-swatch"
      />
      <span className="font-mono text-xs">{hex}</span>
    </span>
  );
}
