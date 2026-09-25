import { fireEvent, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { SpeciesLegalOverview } from "@/features/legal/components/SpeciesLegalOverview";
import {
  AdminLegalConflictsPage,
  AdminLegalDashboardPage,
  AdminLegalDocumentsPage,
  AdminLegalEvaluatePage,
  AdminLegalRulesPage,
  AdminLegalSourcesPage,
} from "@/features/legal/components/AdminLegalWorkspace";
import { ApiClientError } from "@/lib/api-client";

const api = vi.hoisted(() => ({
  fetchLegalDashboard: vi.fn(),
  fetchLegalSources: vi.fn(),
  fetchLegalAuthorities: vi.fn(),
  createLegalAuthority: vi.fn(),
  createLegalSource: vi.fn(),
  transitionLegalSource: vi.fn(),
  fetchLegalDocuments: vi.fn(),
  createLegalDocument: vi.fn(),
  uploadLegalVersion: vi.fn(),
  transitionLegalVersion: vi.fn(),
  createLegalProvision: vi.fn(),
  fetchLegalRules: vi.fn(),
  createLegalRule: vi.fn(),
  transitionLegalRule: vi.fn(),
  fetchLegalConflicts: vi.fn(),
  resolveLegalConflict: vi.fn(),
  previewLegalEvaluation: vi.fn(),
  fetchChangeDetections: vi.fn(),
  dismissChangeDetection: vi.fn(),
}));

vi.mock("next/navigation", () => ({
  usePathname: () => "/admin/legal",
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), refresh: vi.fn() }),
}));

vi.mock("@/features/legal/api/admin-legal-api", () => api);

describe("public legal overview", () => {
  it("renders unknown and conflict states with text labels, not color alone", async () => {
    const { container, rerender } = render(
      <SpeciesLegalOverview
        locale="en"
        legal={{
          available: false,
          message_key: "species.legal_information_not_yet_available",
          outcome: "unknown",
          summary: "Not enough verified published evidence.",
        }}
      />,
    );
    expect(
      screen.getByRole("heading", { name: "Regulations & Official Sources" }),
    ).toBeInTheDocument();
    expect(screen.getByText("Unknown")).toBeInTheDocument();
    expect(screen.getByText(/not legal advice/i)).toBeInTheDocument();
    expect(await axe(container)).toHaveNoViolations();

    rerender(
      <SpeciesLegalOverview
        locale="en"
        legal={{
          available: true,
          message_key: "legal.informational_not_advice",
          outcome: "conflict",
          summary: "Published rules conflict.",
        }}
      />,
    );
    expect(screen.getByText("Conflict")).toBeInTheDocument();
    expect(screen.getByText("Published rules conflict.")).toBeInTheDocument();
  });

  it("renders allowed, prohibited, and conditional states with icons and copy", () => {
    const { rerender } = render(
      <SpeciesLegalOverview
        locale="en"
        legal={{
          available: true,
          message_key: "legal.informational_not_advice",
          outcome: "allowed",
          summary: "A published permission applies.",
          last_verified_at: "2026-09-01T00:00:00+04:00",
          citations: [
            {
              rule_id: "r1",
              reference_code: "Art. 1",
              excerpt: "FICTIONAL excerpt",
              is_primary: true,
              official_url: "https://example.test/notice",
              source_name: "FICTIONAL portal",
            },
          ],
          limits: [
            {
              rule_id: "r1",
              limit_type: "bag",
              amount: 1,
              unit: "animal",
              period: "day",
              applies_per: "person",
            },
          ],
        }}
      />,
    );
    expect(screen.getByText("Allowed")).toBeInTheDocument();
    expect(
      screen.getByText("Art. 1 — “FICTIONAL excerpt”"),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "FICTIONAL portal" }),
    ).toHaveAttribute("href", "https://example.test/notice");
    expect(screen.getByText(/Last verified/)).toBeInTheDocument();

    rerender(
      <SpeciesLegalOverview
        locale="en"
        legal={{
          available: true,
          message_key: "legal.informational_not_advice",
          outcome: "prohibited",
          summary: "A published prohibition applies.",
        }}
      />,
    );
    expect(screen.getByText("Prohibited")).toBeInTheDocument();

    rerender(
      <SpeciesLegalOverview
        locale="en"
        legal={{
          available: true,
          message_key: "legal.informational_not_advice",
          outcome: "conditional",
          summary: "A permit is required.",
        }}
      />,
    );
    expect(screen.getByText("Conditional")).toBeInTheDocument();
  });
});

describe("admin legal workspace", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.fetchLegalDashboard.mockResolvedValue({
      sources_awaiting_verification: 1,
      versions_awaiting_review: 0,
      rules_awaiting_review: 2,
      open_high_conflicts: 0,
      open_change_detections: 1,
      recently_published_rules: [
        { id: "r1", title: "FICTIONAL published rule" },
      ],
      documents_without_current_version: 0,
      disclaimer: "legal.informational_not_advice",
    });
    api.fetchLegalSources.mockResolvedValue({
      data: [
        {
          id: "src-1",
          name: "FICTIONAL portal",
          verification_status: "unverified",
          authority: { name: "FICTIONAL agency" },
        },
      ],
    });
    api.fetchLegalAuthorities.mockResolvedValue([
      { id: "auth-1", name: "FICTIONAL agency" },
    ]);
    api.fetchLegalDocuments.mockResolvedValue({
      data: [
        {
          id: "doc-1",
          title: "FICTIONAL Hunting Notice",
          official_identifier: "FICT-1",
          current_version_id: "ver-1",
          versions: [
            { id: "ver-1", version_label: "v1", review_status: "draft" },
          ],
        },
      ],
    });
    api.fetchLegalRules.mockResolvedValue({
      data: [
        {
          id: "rule-1",
          title: "FICTIONAL prohibition",
          effect: "prohibit",
          status: "draft",
        },
      ],
    });
    api.fetchLegalConflicts.mockResolvedValue({
      data: [
        {
          id: "c1",
          type: "contradictory_effect",
          severity: "high",
          status: "open",
        },
      ],
    });
    api.fetchChangeDetections.mockResolvedValue({
      data: [{ id: "d1", signal: "etag", status: "open" }],
    });
  });

  it("renders review counts, loading, and recently published rules", async () => {
    const { container } = render(<AdminLegalDashboardPage />);
    expect(screen.getByText("Loading legal dashboard…")).toBeInTheDocument();
    expect(
      await screen.findByText("Sources awaiting verification"),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: "Legal sources" }),
    ).toBeInTheDocument();
    expect(screen.getByText("FICTIONAL published rule")).toBeInTheDocument();
    expect(screen.getByText(/not legal advice/i)).toBeInTheDocument();
    expect(await axe(container)).toHaveNoViolations();
  });

  it("shows a permission-denied error on the dashboard", async () => {
    api.fetchLegalDashboard.mockRejectedValue(
      new ApiClientError({
        status: 403,
        code: "PERMISSION_DENIED",
        message: "You do not have permission to perform this action.",
      }),
    );
    render(<AdminLegalDashboardPage />);
    expect(await screen.findByRole("alert")).toHaveTextContent(
      "You do not have permission to perform this action.",
    );
  });

  it("filters sources by verification status", async () => {
    const user = userEvent.setup();
    render(<AdminLegalSourcesPage />);
    expect(await screen.findByText("FICTIONAL portal")).toBeInTheDocument();
    expect(screen.getByRole("table")).toBeInTheDocument();
    await user.selectOptions(screen.getByLabelText("Verification"), "verified");
    expect(api.fetchLegalSources).toHaveBeenCalledWith(
      "?verification_status=verified",
    );
  });

  it("renders version history metadata without filesystem paths", async () => {
    render(<AdminLegalDocumentsPage />);
    expect(
      await screen.findByRole("heading", { name: "Legal documents" }),
    ).toBeInTheDocument();
    expect(
      screen.getAllByText("FICTIONAL Hunting Notice").length,
    ).toBeGreaterThan(0);
    expect(screen.getByText(/current version ver-1/)).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /Submit & approve v1 \(draft\)/ }),
    ).toBeInTheDocument();
    expect(screen.queryByText(/storage\/app/)).not.toBeInTheDocument();
    expect(
      screen.getByText("Editorial summary (not legally authoritative)"),
    ).toBeInTheDocument();
  });

  it("surfaces upload errors", async () => {
    const user = userEvent.setup();
    api.uploadLegalVersion.mockRejectedValue(
      new ApiClientError({
        status: 422,
        code: "LEGAL_FILE_REJECTED",
        message: "This file type is not allowed.",
      }),
    );
    render(<AdminLegalDocumentsPage />);
    expect(
      await screen.findByRole("heading", { name: "Legal documents" }),
    ).toBeInTheDocument();
    await user.type(screen.getByLabelText("Version label"), "v2");
    const file = new File(["%PDF-1.4"], "notice.pdf", {
      type: "application/pdf",
    });
    await user.upload(screen.getByLabelText(/PDF, TXT, or HTML/), file);
    fireEvent.submit(
      screen.getByRole("button", { name: "Upload version" }).closest("form")!,
    );
    expect(await screen.findByRole("alert")).toHaveTextContent(
      "This file type is not allowed.",
    );
  });

  it("requires citation fields on the rule form", async () => {
    const user = userEvent.setup();
    render(<AdminLegalRulesPage />);
    expect(
      await screen.findByText("FICTIONAL prohibition"),
    ).toBeInTheDocument();
    expect(
      screen.getByLabelText("Primary citation provision ID"),
    ).toBeRequired();
    expect(
      screen.getByLabelText("Public interpretation summary"),
    ).toBeRequired();
    await user.click(screen.getByRole("button", { name: "Create draft" }));
    expect(api.createLegalRule).not.toHaveBeenCalled();
  });

  it("creates a draft rule with a primary citation", async () => {
    const user = userEvent.setup();
    api.createLegalRule.mockResolvedValue({ id: "rule-2" });
    render(<AdminLegalRulesPage />);
    await screen.findByText("FICTIONAL prohibition");
    await user.type(
      screen.getByLabelText("Title"),
      "FICTIONAL hunting prohibition",
    );
    await user.type(
      screen.getByLabelText("Effective from"),
      "2026-01-01T00:00",
    );
    await user.type(
      screen.getByLabelText("Public interpretation summary"),
      "Fictional test only.",
    );
    await user.type(
      screen.getByLabelText("Primary citation provision ID"),
      "prov-1",
    );
    await user.type(
      screen.getByLabelText("Short quoted excerpt"),
      "Hunting is prohibited",
    );
    await user.click(screen.getByRole("button", { name: "Create draft" }));
    expect(api.createLegalRule).toHaveBeenCalledWith(
      expect.objectContaining({
        title: "FICTIONAL hunting prohibition",
        citations: [
          expect.objectContaining({
            provision_id: "prov-1",
            citation_purpose: "authority",
            is_primary: true,
          }),
        ],
      }),
    );
  });

  it("renders the conflict queue and source-change detections", async () => {
    const user = userEvent.setup();
    api.resolveLegalConflict.mockResolvedValue({});
    api.dismissChangeDetection.mockResolvedValue({});
    const { container } = render(<AdminLegalConflictsPage />);
    expect(await screen.findByText(/contradictory_effect/)).toBeInTheDocument();
    expect(screen.getByText(/etag/)).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Resolve" }));
    expect(api.resolveLegalConflict).toHaveBeenCalled();
    await user.click(
      screen.getByRole("button", { name: "Dismiss as false positive" }),
    );
    expect(api.dismissChangeDetection).toHaveBeenCalled();
    expect(await axe(container)).toHaveNoViolations();
  });

  it("shows an evaluation preview labelled as not legal advice", async () => {
    const user = userEvent.setup();
    api.previewLegalEvaluation.mockResolvedValue({
      outcome: "unknown",
      summary: "Not enough verified published evidence.",
      disclaimer: "This is informational and is not legal advice.",
    });
    render(<AdminLegalEvaluatePage />);
    expect(
      screen.getByText(/Preview only. This is not legal advice/i),
    ).toBeInTheDocument();
    await user.type(screen.getByLabelText("Occurred at"), "2026-09-24T12:00");
    await user.click(screen.getByRole("button", { name: "Evaluate" }));
    expect(await screen.findByText("Outcome: unknown")).toBeInTheDocument();
    expect(screen.getAllByText(/not legal advice/i).length).toBeGreaterThan(1);
  });
});
