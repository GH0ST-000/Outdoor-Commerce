import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { RecommendationSection } from "@/features/recommendations/components/RecommendationSection";
import { AdminRecommendationWorkspace } from "@/features/recommendations/components/AdminRecommendationWorkspace";
import {
  recommendationContextKey,
  recommendationRequestBody,
} from "@/features/recommendations/lib/request";
import { sanitizeRecommendationEvent } from "@/features/recommendations/analytics/events";
import type { RecommendationResponse } from "@/features/recommendations/types/recommendation-types";

const addItemToCart = vi.hoisted(() => vi.fn());
const adminApi = vi.hoisted(() => ({
  fetchRecommendationCoverage: vi.fn(),
  fetchRecommendationProfiles: vi.fn(),
  fetchTaxonomyTerms: vi.fn(),
  createRecommendationProfile: vi.fn(),
  transitionRecommendationProfile: vi.fn(),
  createMerchandisingRule: vi.fn(),
  simulateRecommendations: vi.fn(),
  createTaxonomyTerm: vi.fn(),
  createProductAssignment: vi.fn(),
  bulkAssignProducts: vi.fn(),
}));

vi.mock("@/features/cart/state/cart-actions", () => ({
  addItemToCart,
}));

vi.mock(
  "@/features/recommendations/api/admin-recommendations-api",
  async () => {
    const actual = await vi.importActual<
      typeof import("@/features/recommendations/api/admin-recommendations-api")
    >("@/features/recommendations/api/admin-recommendations-api");
    return { ...actual, ...adminApi };
  },
);

function card(
  overrides: Partial<RecommendationResponse["recommendations"][number]> = {},
) {
  return {
    slug: "optics",
    name: "Field binoculars",
    brand: "Northline",
    primary_image: null,
    category: "Optics",
    price: { amount_minor: 12900, currency: "GEL" },
    sale_price: null,
    currency: "GEL",
    stock_status: "in_stock",
    eligible_variants: [
      {
        variant_id: 9,
        sku: "BIN-1",
        in_stock: true,
        stock_status: "in_stock",
        price: {
          amount_minor: 12900,
          final_amount_minor: 12900,
          currency: "GEL",
        },
      },
    ],
    recommended_variant: "BIN-1",
    confidence: "high" as const,
    primary_reason: "activity_match",
    primary_reason_text: "Matches the selected hunting activity.",
    supporting_reasons: [{ code: "in_stock", text: "Available in stock." }],
    warnings: [],
    is_promoted: false,
    promotion_label: null,
    product_url: "/products/optics",
    add_to_cart_eligible: true,
    ...overrides,
  };
}

function response(
  overrides: Partial<RecommendationResponse> = {},
): RecommendationResponse {
  return {
    context: {
      activity: "hunting",
      species_slug: null,
      species_category_code: null,
      zone_public_ids: [],
      completeness: "high",
      framing: "contextual",
    },
    placement: "map_location_result",
    gate: "recommendations_allowed",
    profile_version: 1,
    recommendations: [card()],
    pagination: { page: 1, per_page: 10, total: 1, has_more: false },
    warnings: [],
    disclaimer: "Recommendations do not decide legality.",
    ...overrides,
  };
}

describe("recommendation request safety", () => {
  it("omits coordinates and a client-supplied legal gate", () => {
    const body = recommendationRequestBody({
      placement: "map_location_result",
      locale: "en",
      contextToken: "signed",
      activity: "hunting",
      regionCode: "not a region!!!",
    });
    expect(body).not.toHaveProperty("longitude");
    expect(body).not.toHaveProperty("latitude");
    expect(body).not.toHaveProperty("gate");
    expect(body).not.toHaveProperty("region_code");
    expect(body.context_token).toBe("signed");
    expect(
      sanitizeRecommendationEvent({
        placement: "map_location_result",
        latitude: 41.7,
        pin: "44.8,41.7",
      }),
    ).toEqual({ placement: "map_location_result" });
    expect(
      recommendationContextKey({
        placement: "species_detail",
        locale: "ka",
        speciesSlug: "testus",
      }),
    ).not.toContain("41.");
  });
});

describe("recommendation section", () => {
  beforeEach(() => {
    addItemToCart.mockReset();
    addItemToCart.mockResolvedValue({});
  });

  it("shows ranked products, reasons, confidence, and adds the eligible variant", async () => {
    const user = userEvent.setup();
    const load = vi.fn().mockResolvedValue(response());
    render(
      <RecommendationSection
        request={{
          placement: "map_location_result",
          locale: "en",
          contextToken: "token-a",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByRole("link", { name: "Field binoculars" }),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Matches the selected hunting activity."),
    ).toBeInTheDocument();
    expect(screen.getByText(/Match confidence: High/)).toBeInTheDocument();
    expect(screen.getByText(/BIN-1/)).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Why this product?" }));
    expect(screen.getByText("Available in stock.")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Add to cart" }));
    expect(addItemToCart).toHaveBeenCalledWith({ variant_id: 9, quantity: 1 });
    expect(await screen.findByText("Added to cart")).toBeInTheDocument();
  });

  it("discloses a promotion and a required-equipment badge", async () => {
    const load = vi.fn().mockResolvedValue(
      response({
        recommendations: [
          card({
            is_promoted: true,
            promotion_label: "Promoted",
            primary_reason: "required_equipment_match",
            primary_reason_text:
              "Matches a verified required equipment category.",
          }),
        ],
      }),
    );
    render(
      <RecommendationSection
        request={{
          placement: "outdoor_context_result",
          locale: "en",
          contextToken: "token-a",
        }}
        load={load}
      />,
    );
    expect(await screen.findByText("Promoted")).toBeInTheDocument();
    expect(screen.getByText("Required equipment category")).toBeInTheDocument();
  });

  it("shows a condition notice before informational products", async () => {
    const load = vi.fn().mockResolvedValue(
      response({
        gate: "recommendations_information_only",
        context: { ...response().context, framing: "conditional" },
        warnings: [
          {
            code: "conditional_restriction",
            text: "Confirm the listed requirements before proceeding.",
          },
        ],
      }),
    );
    render(
      <RecommendationSection
        request={{
          placement: "season_explorer",
          locale: "en",
          activity: "hunting",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByText(/Confirm the listed requirements/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Field binoculars" }),
    ).toBeInTheDocument();
  });

  it("blocks activity products for a prohibited context", async () => {
    const load = vi.fn().mockResolvedValue(
      response({
        gate: "recommendations_blocked",
        recommendations: [],
        context: { ...response().context, framing: "blocked" },
        warnings: [
          {
            code: "gate_blocked",
            text: "Activity equipment is not shown for this context.",
          },
        ],
      }),
    );
    render(
      <RecommendationSection
        request={{
          placement: "map_location_result",
          locale: "en",
          contextToken: "blocked",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByText(/Activity equipment is not shown/),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("link", { name: "Field binoculars" }),
    ).not.toBeInTheDocument();
  });

  it("suppresses conflict recommendations", async () => {
    const load = vi.fn().mockResolvedValue(
      response({
        gate: "recommendations_blocked",
        recommendations: [],
        context: { ...response().context, framing: "conflict" },
        warnings: [
          {
            code: "gate_conflict",
            text: "The legal context needs clarification.",
          },
        ],
      }),
    );
    render(
      <RecommendationSection
        request={{
          placement: "map_location_result",
          locale: "ka",
          contextToken: "conflict",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByText("The legal context needs clarification."),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", {
        name: "ამ კონტექსტისთვის შერჩეული აღჭურვილობა",
      }),
    ).toBeInTheDocument();
  });

  it("labels unknown map results as general discovery", async () => {
    const load = vi.fn().mockResolvedValue(
      response({
        gate: "recommendations_unknown",
        recommendations: [],
        context: { ...response().context, framing: "suppressed" },
      }),
    );
    render(
      <RecommendationSection
        request={{
          placement: "map_location_result",
          locale: "en",
          contextToken: "unknown",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByText(/not products approved for this place/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "General catalog" }),
    ).toHaveAttribute("href", "/products");
  });

  it("labels species gear without a location claim and localizes Georgian", async () => {
    const load = vi.fn().mockResolvedValue(
      response({
        placement: "species_detail",
        gate: "recommendations_unknown",
        context: { ...response().context, framing: "species_related" },
        recommendations: [
          card({
            primary_reason_text:
              "Species-related gear. Location is not verified.",
          }),
        ],
      }),
    );
    const { rerender } = render(
      <RecommendationSection
        request={{
          placement: "species_detail",
          locale: "en",
          speciesSlug: "testus",
          activity: "hunting",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByRole("heading", { name: "Species-related gear" }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "These are species-related gear suggestions. Location is not verified.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Species-related gear. Location is not verified."),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", {
        name: "Open the map for more accurate suggestions",
      }),
    ).toHaveAttribute("href", "/map");
    rerender(
      <RecommendationSection
        request={{
          placement: "species_detail",
          locale: "ka",
          speciesSlug: "testus",
          activity: "hunting",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByRole("heading", {
        name: "სახეობასთან დაკავშირებული აღჭურვილობა",
      }),
    ).toBeInTheDocument();
  });

  it("shows empty and error states", async () => {
    const load = vi
      .fn()
      .mockResolvedValue(
        response({
          recommendations: [],
          context: { ...response().context, framing: "contextual" },
        }),
      );
    const { rerender } = render(
      <RecommendationSection
        request={{
          placement: "season_explorer",
          locale: "en",
          activity: "fishing",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByText("No verified products match this context."),
    ).toBeInTheDocument();
    const failing = vi.fn().mockRejectedValue(new Error("down"));
    rerender(
      <RecommendationSection
        request={{
          placement: "season_explorer",
          locale: "en",
          activity: "hunting",
        }}
        load={failing}
      />,
    );
    expect(await screen.findByRole("alert")).toHaveTextContent(
      /could not be loaded/,
    );
  });

  it("paginates with the keyboard-reachable next control", async () => {
    const user = userEvent.setup();
    const load = vi
      .fn()
      .mockImplementation(async (request: { page?: number }) =>
        response({
          pagination: {
            page: request.page ?? 1,
            per_page: 1,
            total: 2,
            has_more: (request.page ?? 1) < 2,
          },
        }),
      );
    render(
      <RecommendationSection
        request={{
          placement: "map_location_result",
          locale: "en",
          contextToken: "one",
        }}
        load={load}
      />,
    );
    const next = await screen.findByRole("button", { name: "Next" });
    next.focus();
    expect(next).toHaveFocus();
    await user.keyboard("{Enter}");
    expect(load).toHaveBeenCalledWith(expect.objectContaining({ page: 2 }));
    expect(await screen.findByText("Page 2")).toBeInTheDocument();
  });

  it("marks a changed context as stale until the next result arrives", async () => {
    let release: (value: RecommendationResponse) => void = () => undefined;
    const load = vi
      .fn()
      .mockImplementation((request: { contextToken?: string | null }) => {
        if (request.contextToken === "two") {
          return new Promise<RecommendationResponse>((resolve) => {
            release = resolve;
          });
        }
        return Promise.resolve(response());
      });
    const { rerender } = render(
      <RecommendationSection
        request={{
          placement: "map_location_result",
          locale: "en",
          contextToken: "one",
        }}
        load={load}
      />,
    );
    expect(
      await screen.findByRole("link", { name: "Field binoculars" }),
    ).toBeInTheDocument();
    rerender(
      <RecommendationSection
        request={{
          placement: "map_location_result",
          locale: "en",
          contextToken: "two",
        }}
        load={load}
      />,
    );
    expect(await screen.findByText(/no longer current/)).toBeInTheDocument();
    release(
      response({
        recommendations: [card({ name: "Refreshed scope", slug: "scope" })],
      }),
    );
    expect(
      await screen.findByRole("link", { name: "Refreshed scope" }),
    ).toBeInTheDocument();
  });

  it("does not offer an out-of-stock variant as immediately purchasable", async () => {
    const load = vi.fn().mockResolvedValue(
      response({
        recommendations: [
          card({
            stock_status: "out_of_stock",
            add_to_cart_eligible: false,
            recommended_variant: null,
            eligible_variants: [
              {
                variant_id: 3,
                sku: "BIN-OOS",
                in_stock: false,
                stock_status: "out_of_stock",
                price: null,
              },
            ],
          }),
        ],
      }),
    );
    render(
      <RecommendationSection
        request={{
          placement: "map_location_result",
          locale: "en",
          contextToken: "stock",
        }}
        load={load}
      />,
    );
    expect(await screen.findByText("Out of stock")).toBeInTheDocument();
    expect(
      screen.getByText("Choose a variant on the product page."),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("button", { name: "Add to cart" }),
    ).not.toBeInTheDocument();
  });
});

describe("recommendation admin workspace", () => {
  beforeEach(() => {
    adminApi.fetchRecommendationCoverage.mockResolvedValue({
      products_without_activity: 4,
      products_without_assignments: 6,
      contradictory_assignments: 1,
      low_confidence_sources: 2,
      equipment_terms_without_products: ["hunting_license"],
      active_profiles: [
        { placement: "species_detail", version: 1, slug: "species-detail-v1" },
      ],
      scheduled_merchandising: 0,
      expiring_assignments: 0,
    });
    adminApi.fetchRecommendationProfiles.mockResolvedValue([
      {
        public_id: "profile-1",
        name: "Species default",
        placement: "species_detail",
        version: 1,
        status: "published",
      },
    ]);
    adminApi.fetchTaxonomyTerms.mockResolvedValue([
      {
        public_id: "term-1",
        dimension: "activity",
        code: "hunting",
        default_label: "Hunting",
      },
    ]);
  });

  it("shows coverage gaps and the active profile", async () => {
    render(<AdminRecommendationWorkspace />);
    expect(
      await screen.findByText(/Products without activity mappings: 4/),
    ).toBeInTheDocument();
    expect(screen.getByText(/hunting_license/)).toBeInTheDocument();
    expect(screen.getByText(/Species default/)).toBeInTheDocument();
  });
});
