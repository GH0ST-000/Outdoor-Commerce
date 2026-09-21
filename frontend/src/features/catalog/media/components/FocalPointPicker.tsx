"use client";

import { useCallback, useRef } from "react";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { cn } from "@/lib/utils";

export function FocalPointPicker({
  previewUrl,
  width,
  height,
  focalPoint,
  disabled,
  onChange,
}: {
  previewUrl: string | null;
  width: number | null;
  height: number | null;
  focalPoint: { x: number; y: number } | null;
  disabled?: boolean;
  onChange: (point: { x: number; y: number } | null) => void;
}) {
  const boxRef = useRef<HTMLDivElement>(null);

  const point = focalPoint ?? { x: 0.5, y: 0.5 };

  const setFromClient = useCallback(
    (clientX: number, clientY: number) => {
      const box = boxRef.current;
      if (!box || disabled) return;
      const rect = box.getBoundingClientRect();
      const x = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
      const y = Math.min(1, Math.max(0, (clientY - rect.top) / rect.height));
      onChange({ x: Number(x.toFixed(4)), y: Number(y.toFixed(4)) });
    },
    [disabled, onChange],
  );

  return (
    <div className="space-y-3">
      <div
        ref={boxRef}
        className={cn(
          "relative aspect-[4/3] overflow-hidden rounded-lg border border-border/70 bg-muted",
          !disabled && previewUrl && "cursor-crosshair",
        )}
        onClick={(event) => setFromClient(event.clientX, event.clientY)}
        onKeyDown={() => undefined}
        role="img"
        aria-label="Click to set focal point"
      >
        {previewUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={previewUrl}
            alt=""
            className="size-full object-cover"
            style={{ objectPosition: `${point.x * 100}% ${point.y * 100}%` }}
          />
        ) : (
          <div className="flex size-full items-center justify-center text-xs text-muted-foreground">
            Preview unavailable
          </div>
        )}
        <span
          className="pointer-events-none absolute size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-primary shadow"
          style={{ left: `${point.x * 100}%`, top: `${point.y * 100}%` }}
          aria-hidden
        />
      </div>
      <div className="flex flex-wrap items-end gap-3">
        <Field label="Focal X" htmlFor="focal-x" className="w-28">
          <Input
            id="focal-x"
            type="number"
            min={0}
            max={1}
            step={0.01}
            disabled={disabled}
            value={point.x}
            onChange={(event) =>
              onChange({
                x: Number(event.target.value),
                y: point.y,
              })
            }
          />
        </Field>
        <Field label="Focal Y" htmlFor="focal-y" className="w-28">
          <Input
            id="focal-y"
            type="number"
            min={0}
            max={1}
            step={0.01}
            disabled={disabled}
            value={point.y}
            onChange={(event) =>
              onChange({
                x: point.x,
                y: Number(event.target.value),
              })
            }
          />
        </Field>
        <Button
          type="button"
          size="sm"
          variant="outline"
          disabled={disabled}
          onClick={() => onChange({ x: 0.5, y: 0.5 })}
        >
          Center
        </Button>
        <Button
          type="button"
          size="sm"
          variant="ghost"
          disabled={disabled}
          onClick={() => onChange(null)}
        >
          Reset
        </Button>
      </div>
      {width && height ? (
        <p className="text-xs text-muted-foreground">
          Original {width}×{height}px
        </p>
      ) : null}
    </div>
  );
}
