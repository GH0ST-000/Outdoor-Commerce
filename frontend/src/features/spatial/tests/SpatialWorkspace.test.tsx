import { fireEvent, render, screen } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import {
  AdminSpatialDashboardPage,
  AdminSpatialImportPage,
  AdminSpatialZonesPage,
} from "@/features/spatial/components/AdminSpatialWorkspace";
import {
  BoundaryWarning,
  SourceAttribution,
  ZoneDetailsPanel,
} from "@/features/spatial/components/SpatialMapPrimitives";
import { ApiClientError } from "@/lib/api-client";
import { TestProviders } from "@/test/providers";

const adminApi = vi.hoisted(() => ({
  fetchSpatialDashboard: vi.fn(),
  fetchSpatialCoverage: vi.fn(),
  fetchSpatialImports: vi.fn(),
  fetchSpatialSources: vi.fn(),
  fetchSpatialZones: vi.fn(),
  createSpatialSource: vi.fn(),
  verifySpatialSource: vi.fn(),
  createSpatialDataset: vi.fn(),
  uploadSpatialVersion: vi.fn(),
  mapSpatialVersion: vi.fn(),
  importSpatialVersion: vi.fn(),
  previewSpatialVersion: vi.fn(),
  transitionSpatialVersion: vi.fn(),
  previewSpatialEvaluation: vi.fn(),
}));

vi.mock("@/features/spatial/api/admin-spatial-api", () => adminApi);

describe("spatial admin workspace", () => {
  beforeEach(() => {
    Object.values(adminApi).forEach((fn) => fn.mockReset());
  });

  it("shows dashboard counts and an empty zones table", async () => {
    adminApi.fetchSpatialDashboard.mockResolvedValue({
      sources_awaiting_verification: 2,
      versions_awaiting_review: 1,
      imports_failed: 0,
      zones_without_assignments: 4,
      published_versions: 3,
    });
    adminApi.fetchSpatialZones.mockResolvedValue({ data: [] });
    render(
      <TestProviders>
        <AdminSpatialDashboardPage />
      </TestProviders>,
    );
    expect(
      await screen.findByText(/sources awaiting verification/i),
    ).toBeInTheDocument();
    expect(screen.getByText("2")).toBeInTheDocument();

    render(
      <TestProviders>
        <AdminSpatialZonesPage />
      </TestProviders>,
    );
    expect(
      await screen.findByText(/no spatial zones yet/i),
    ).toBeInTheDocument();
  });

  it("shows import wizard validation and permission errors", async () => {
    adminApi.createSpatialSource.mockRejectedValue(
      new ApiClientError({
        status: 403,
        code: "SPATIAL_PERMISSION_REQUIRED",
        message: "You do not have permission to perform this spatial action.",
      }),
    );
    const { container } = render(
      <TestProviders>
        <AdminSpatialImportPage />
      </TestProviders>,
    );
    expect(
      screen.getByRole("heading", { name: /spatial import wizard/i }),
    ).toBeInTheDocument();
    fireEvent.submit(
      screen
        .getByRole("button", { name: /upload without publishing/i })
        .closest("form")!,
    );
    expect(
      await screen.findByText(/you do not have permission/i),
    ).toBeInTheDocument();
    expect(await axe(container)).toHaveNoViolations();
  });
});

describe("spatial map primitives", () => {
  it("renders attribution, boundary warning, and bilingual labels", () => {
    render(
      <TestProviders>
        <ZoneDetailsPanel
          name="Testus Reserve"
          officialName="FICTIONAL Testus Reserve"
          zoneType="protected_area"
          outcome="unknown"
          warning
          attribution={{
            source_name: "FICTIONAL Agency",
            publisher_name: "Test",
          }}
          verifiedAt="2026-01-01T00:00:00Z"
          locale="en"
        />
        <SourceAttribution attribution={{ source_name: "FICTIONAL Agency" }} />
        <BoundaryWarning visible locale="ka" />
      </TestProviders>,
    );
    expect(screen.getByText(/official name/i)).toBeInTheDocument();
    expect(screen.getAllByText(/fictional agency/i).length).toBeGreaterThan(0);
    expect(screen.getByText(/საზღვართან/i)).toBeInTheDocument();
  });
});
