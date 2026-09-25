import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { SeasonExplorer } from "@/features/seasons/components/SeasonExplorer";
import { AdminSeasonDashboardPage } from "@/features/seasons/components/AdminSeasonWorkspace";
import { ApiClientError } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const publicApi = vi.hoisted(() => ({
  fetchAvailability: vi.fn(),
}));

const adminApi = vi.hoisted(() => ({
  fetchSeasonDashboard: vi.fn(),
}));

vi.mock("@/features/seasons/api/public-seasons-api", () => publicApi);
vi.mock("@/features/seasons/api/admin-seasons-api", () => adminApi);
vi.mock("next/navigation", () => ({
  usePathname: () => "/admin/legal/seasons",
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
    refresh: vi.fn(),
    prefetch: vi.fn(),
  }),
  useSearchParams: () => new URLSearchParams(),
}));
vi.mock("next/image", () => ({
  default: (props: { alt: string }) => <span>{props.alt}</span>,
}));

describe("season explorer", () => {
  beforeEach(() => {
    publicApi.fetchAvailability.mockReset();
  });

  it("searches with activity, dates, mode, and shows unknown empty state", async () => {
    publicApi.fetchAvailability.mockResolvedValue({
      query: {
        activity: "hunting",
        from: "2026-09-01",
        to: "2026-09-07",
        timezone: "Asia/Tbilisi",
        mode: "any_date",
        region_code: null,
      },
      results: [],
      pagination: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
      disclaimer: "legal.informational_not_advice",
    });
    const { container } = render(
      <TestProviders>
        <SeasonExplorer />
      </TestProviders>,
    );
    expect(
      await screen.findByRole("heading", {
        name: /hunting and fishing seasons/i,
      }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText(/start date/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/end date/i)).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: /fishing/i }));
    fireEvent.click(screen.getByRole("button", { name: /show seasons/i }));
    expect(
      await screen.findByText(/no verified season records/i),
    ).toBeInTheDocument();
    expect(await axe(container)).toHaveNoViolations();
  });

  it("renders partial availability without calling it fully open", async () => {
    publicApi.fetchAvailability.mockResolvedValue({
      query: {
        activity: "hunting",
        from: "2026-09-01",
        to: "2026-09-30",
        timezone: "Asia/Tbilisi",
        mode: "any_date",
        region_code: null,
      },
      results: [
        {
          species: {
            id: "11111111-1111-1111-1111-111111111111",
            slug: "testus-calendarus",
            common_name: "Fictional deer",
            scientific_name: "Testus calendarus",
            media: null,
          },
          overall_state: "partially_open",
          mode: "any_date",
          available_windows: [
            { from: "2026-09-10", to: "2026-09-20", state: "open" },
          ],
          closed_windows: [],
          conditional_windows: [],
          unknown_windows: [
            { from: "2026-09-01", to: "2026-09-09", state: "unknown" },
          ],
          conflicting_windows: [],
          timeline: [],
          next_opening: null,
          next_closing: null,
          limits: [],
          conditions: [],
          citations: [],
          last_verified_at: "2026-01-01T00:00:00+04:00",
          region_code: null,
          disclaimer: "legal.informational_not_advice",
        },
      ],
      pagination: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
      disclaimer: "legal.informational_not_advice",
    });
    render(
      <TestProviders>
        <SeasonExplorer />
      </TestProviders>,
    );
    expect(await screen.findByText("Fictional deer")).toBeInTheDocument();
    expect(screen.getAllByText(/partially open/i).length).toBeGreaterThan(0);
    expect(screen.queryByText(/^open$/i)).not.toBeInTheDocument();
  });

  it("shows API errors without implying a legal closure", async () => {
    publicApi.fetchAvailability.mockRejectedValue(
      new ApiClientError({
        status: 500,
        code: "SERVER",
        message: "Season request failed",
      }),
    );
    render(
      <TestProviders>
        <SeasonExplorer />
      </TestProviders>,
    );
    expect(await screen.findByRole("alert")).toHaveTextContent(
      /season request failed/i,
    );
  });
});

describe("admin season dashboard", () => {
  it("renders coverage counts", async () => {
    adminApi.fetchSeasonDashboard.mockResolvedValue({
      published_hunting: 1,
      published_fishing: 0,
      drafts: 2,
      awaiting_review: 0,
      opening_soon: 0,
      closing_soon: 0,
      failed_generation_runs: 0,
      open_calendar_conflicts: 0,
    });
    render(
      <TestProviders>
        <AdminSeasonDashboardPage />
      </TestProviders>,
    );
    expect(await screen.findByText("Published hunting")).toBeInTheDocument();
    expect(screen.getByText("1")).toBeInTheDocument();
  });
});
