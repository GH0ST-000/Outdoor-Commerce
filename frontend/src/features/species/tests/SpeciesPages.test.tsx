import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { axe } from "jest-axe";
import { SpeciesDirectoryView } from "@/features/species/components/SpeciesDirectoryView";
import { SpeciesDetailView } from "@/features/species/components/SpeciesDetailView";
import { emptySpeciesQuery } from "@/features/species/query-state/species-search-params";
import type {
  SpeciesCard,
  SpeciesDetail,
} from "@/features/species/types/species-types";

const { push } = vi.hoisted(() => ({ push: vi.fn() }));

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push, replace: vi.fn(), refresh: vi.fn() }),
}));

vi.mock("next/image", () => ({
  default: (props: { alt: string; src: string }) => (
    // eslint-disable-next-line @next/next/no-img-element
    <img alt={props.alt} src={props.src} />
  ),
}));

const legal = {
  available: false,
  message_key: "species.legal_information_not_yet_available",
};

const card: SpeciesCard = {
  id: "abc",
  slug: "testus-fixtureus",
  locale: "ka",
  common_name: "სატესტო სახეობა",
  scientific_name: "Testus fixtureus",
  summary: "შეჯამება",
  activity_type: "wildlife",
  domain_type: "terrestrial",
  habitats: [{ code: "forest", name: "ტყე" }],
  verified: true,
  media: null,
  legal_information: legal,
};

const detail: SpeciesDetail = {
  id: "abc",
  slug: "testus-fixtureus",
  locale: "ka",
  requested_locale: "en",
  translation_fallback: true,
  common_name: "სატესტო სახეობა",
  short_name: "სატესტო",
  scientific_name: "Testus fixtureus",
  scientific_name_authorship: null,
  summary: "შეჯამება",
  activity_type: "wildlife",
  domain_type: "terrestrial",
  native_status: null,
  taxonomy: {
    kingdom: { code: "Animalia", scientific_name: "Animalia" },
    class: { code: "Mammalia", scientific_name: "Mammalia" },
    order: null,
    family: { code: "Testidae", scientific_name: "Testidae" },
    genus: { code: "Testus", scientific_name: "Testus" },
  },
  aliases: [],
  identification: {
    overview: "<p>ნიშნები</p>",
    appearance: null,
    traits: [{ category: "color", label: "ფერი", description: "მუქი" }],
    similar_species: [],
  },
  habitats: [{ code: "forest", name: "ტყე", importance: "primary" }],
  behavior: {
    overview: null,
    diet: null,
    breeding_notes: null,
    seasonal_behavior: null,
    field_notes: null,
    safety_notes: null,
    habitat_description: null,
  },
  characteristics: null,
  conservation: [],
  media: [],
  sources: [
    {
      id: "src",
      title: "Test source",
      publisher: "Publisher",
      source_type: "scientific_database",
      url: "https://example.org",
      language: "en",
      published_at: "2020-01-01",
      retrieved_at: "2026-01-01",
      is_official: false,
      verification_status: "verified",
    },
  ],
  legal_information: legal,
  seo: { title: "სატესტო", description: "შეჯამება" },
  meta: {
    verified: true,
    verification_status: "verified",
    content_version: 1,
    published_at: "2026-01-01T00:00:00Z",
    updated_at: "2026-01-01T00:00:00Z",
  },
};

describe("species directory", () => {
  it("keeps filter state in the URL and shows the legal boundary", async () => {
    const user = userEvent.setup();
    render(
      <SpeciesDirectoryView
        locale="ka"
        query={emptySpeciesQuery()}
        cards={[card]}
        total={1}
        lastPage={1}
        filters={{
          activity_types: ["wildlife"],
          domain_types: ["terrestrial"],
          habitats: [{ code: "forest", name: "ტყე" }],
          taxonomy: { classes: [], orders: [], families: ["Testidae"] },
          conservation: [],
          sorts: ["name"],
        }}
        error={null}
      />,
    );

    expect(screen.getByRole("note").textContent).toMatch(/ბიოლოგიურ/);
    expect(screen.getByText("სატესტო სახეობა")).toBeInTheDocument();
    expect(screen.getByText("Testus fixtureus")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "ველური ბუნება" }));
    expect(push).toHaveBeenCalled();
  });

  it("has no axe violations on the directory", async () => {
    const { container } = render(
      <SpeciesDirectoryView
        locale="en"
        query={emptySpeciesQuery()}
        cards={[]}
        total={0}
        lastPage={1}
        filters={null}
        error={null}
      />,
    );
    expect(screen.getByRole("status").textContent).toMatch(
      /No species matched/i,
    );
    expect(await axe(container)).toHaveNoViolations();
  });

  it("shows an error state", () => {
    render(
      <SpeciesDirectoryView
        locale="en"
        query={emptySpeciesQuery()}
        cards={[]}
        total={0}
        lastPage={1}
        filters={null}
        error="SPECIES_NOT_FOUND"
      />,
    );
    expect(screen.getByRole("alert").textContent).toMatch(/unavailable/i);
  });
});

describe("species detail", () => {
  it("renders taxonomy, sources, fallback, and the legal overview without hunting claims", async () => {
    const { container } = render(
      <SpeciesDetailView locale="en" species={detail} />,
    );
    expect(
      screen.getByRole("heading", { name: /Regulations & Official Sources/ }),
    ).toBeInTheDocument();
    expect(screen.getByText(/not legal advice/i)).toBeInTheDocument();
    expect(screen.getByText(/Unknown/)).toBeInTheDocument();
    expect(screen.queryByText(/hunt now/i)).not.toBeInTheDocument();
    expect(screen.getByText("Test source")).toBeInTheDocument();
    expect(
      screen.getByText(/English translation is not published/),
    ).toBeInTheDocument();
    expect(screen.getByText("Animalia")).toBeInTheDocument();
    expect(screen.getByText("ფერი")).toBeInTheDocument();
    expect(await axe(container)).toHaveNoViolations();
  });
});
