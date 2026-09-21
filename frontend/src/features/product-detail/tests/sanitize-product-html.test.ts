import { describe, expect, it } from "vitest";
import { sanitizeProductHtml } from "@/features/product-detail/utils/sanitize-product-html";

describe("sanitizeProductHtml", () => {
  it("strips script tags and keeps allowed markup", () => {
    const html = sanitizeProductHtml(
      '<p>Safe</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>',
    );
    expect(html).toContain("<p>Safe</p>");
    expect(html).not.toContain("script");
    expect(html).not.toContain("javascript:");
  });

  it("returns empty for missing descriptions", () => {
    expect(sanitizeProductHtml(null)).toBe("");
    expect(sanitizeProductHtml("   ")).toBe("");
  });
});
