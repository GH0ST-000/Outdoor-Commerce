import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { SafeJson } from "@/features/admin/components/SafeJson";

describe("SafeJson", () => {
  it("renders JSON as text without interpreting HTML", () => {
    const { container } = render(
      <SafeJson
        label="Payload"
        value={{ note: "<img src=x onerror=alert(1)>", ok: true }}
      />,
    );

    expect(screen.getByText("Payload")).toBeInTheDocument();
    const pre = container.querySelector("pre");
    expect(pre).not.toBeNull();
    expect(pre?.innerHTML).toContain("&lt;img src=x onerror=alert(1)&gt;");
    expect(container.querySelector("img")).toBeNull();
    expect(pre?.textContent).toContain("<img src=x onerror=alert(1)>");
  });

  it("shows an empty label for nullish or empty objects", () => {
    const { rerender } = render(<SafeJson value={null} emptyLabel="None" />);
    expect(screen.getByText("None")).toBeInTheDocument();

    rerender(<SafeJson value={{}} emptyLabel="None" />);
    expect(screen.getByText("None")).toBeInTheDocument();
  });
});
