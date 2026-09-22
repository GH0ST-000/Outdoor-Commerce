import { describe, expect, it } from "vitest";
import { highlightSegments } from "@/features/search/lib/highlight";

describe("safe search highlighting", () => {
  it("marks matching text without interpreting HTML", () => {
    const segments = highlightSegments(
      "<script>alert(1)</script> Scope",
      "<script>",
    );
    expect(segments[0]).toEqual({ text: "<script>", match: true });
    expect(segments.map((item) => item.text).join("")).toBe(
      "<script>alert(1)</script> Scope",
    );
  });

  it("is case-insensitive and leaves unmatched text plain", () => {
    expect(highlightSegments("Alpine Jacket", "alp")).toEqual([
      { text: "Alp", match: true },
      { text: "ine Jacket", match: false },
    ]);
  });
});
