import type { BBox } from "@/features/map/lib/bbox";
import type { MapDetailLevel } from "@/features/map/lib/detail-level";

export function viewportRequestKey(input: {
  bbox: BBox;
  detail: MapDetailLevel;
  activity: string;
  types: string[];
  at: string | null;
  page: number;
  locale: string;
}): string {
  return [
    input.bbox.join(","),
    input.detail,
    input.activity,
    [...input.types].sort().join(","),
    input.at ?? "",
    input.page,
    input.locale,
  ].join("|");
}

export function createViewportSession() {
  let controller: AbortController | null = null;
  let sequence = 0;
  let lastKey = "";

  return {
    begin(key: string): { signal: AbortSignal; seq: number; skip: boolean } {
      if (key === lastKey && controller && !controller.signal.aborted) {
        return { signal: controller.signal, seq: sequence, skip: true };
      }
      controller?.abort();
      controller = new AbortController();
      sequence += 1;
      lastKey = key;
      return { signal: controller.signal, seq: sequence, skip: false };
    },
    isCurrent(seq: number): boolean {
      return seq === sequence;
    },
    abort(): void {
      controller?.abort();
    },
  };
}
