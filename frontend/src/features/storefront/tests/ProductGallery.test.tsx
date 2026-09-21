import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { ProductGallery } from "@/features/storefront/components/commerce/ProductGallery";
import { TestProviders } from "@/test/providers";
import type { ResponsiveMedia } from "@/features/storefront/media/types";

vi.mock("next/image", () => ({
  default: (props: { alt: string; src: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img alt={props.alt} src={props.src} />
  ),
}));

const items: ResponsiveMedia[] = [
  {
    alt: { en: "Front", ka: "წინა" },
    fallbackSrc: "/media/front.jpg",
    width: 800,
    height: 1000,
  },
  {
    alt: { en: "Side", ka: "გვერდი" },
    fallbackSrc: "/media/side.jpg",
    width: 800,
    height: 1000,
  },
];

describe("ProductGallery", () => {
  it("slides to the next and previous image", async () => {
    const user = userEvent.setup();
    render(
      <TestProviders>
        <ProductGallery
          items={items}
          alt={{ en: "Product", ka: "პროდუქტი" }}
          locale="en"
          ariaLabel="Product images"
        />
      </TestProviders>,
    );

    expect(screen.getByText("1 / 2")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Next image" }));
    expect(screen.getByText("2 / 2")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Previous image" }));
    expect(screen.getByText("1 / 2")).toBeInTheDocument();
  });
});
