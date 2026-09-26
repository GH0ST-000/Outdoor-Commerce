import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { getMapCopy } from "@/features/map/copy/map-copy";
import {
  LayerControl,
  LocationResult,
  MapLegend,
  ResultsList,
  ShareDialog,
} from "@/features/map/components/MapPanels";
import type { SpatialEvaluation, ViewportFeature } from "@/features/spatial/types/spatial-types";

const evaluation: SpatialEvaluation = {
  coordinate: { longitude: 44.5, latitude: 41.5, srid: 4326, coordinate_order: "longitude,latitude" },
  outcome: "unknown",
  boundary_warning: true,
  on_boundary: false,
  near_boundary: true,
  matching_zones: [],
  applied_rules: [],
  conditions: [{ type: "permit", value: "required" }],
  limits: [],
  citations: [{ source_name: "Official gazette", reference_code: "Art. 1", excerpt: "Quoted text" }],
  disclaimer: "Not legal advice",
};

describe("map panels", () => {
  it("renders Georgian outcomes and a boundary warning", () => {
    render(
      <LocationResult
        copy={getMapCopy("ka")}
        evaluation={evaluation}
        loading={false}
        accuracyMeters={400}
      />,
    );
    expect(screen.getByText("უცნობი")).toBeInTheDocument();
    expect(screen.getByText("საზღვრის გაურკვევლობა")).toBeInTheDocument();
    expect(screen.getByText("Official gazette")).toBeInTheDocument();
  });

  it("lists published seasons without treating the point as permitted", () => {
    render(
      <LocationResult
        copy={getMapCopy("ka")}
        evaluation={{
          ...evaluation,
          seasonal_availability: {
            results: [
              {
                species: {
                  slug: "anas-acuta",
                  common_name: "კუდსადგისა იხვი",
                  scientific_name: "Anas acuta",
                },
                overall_state: "conditional",
                conditional_windows: [{ from: "2026-09-26", to: "2026-09-26" }],
                conditions: [
                  { operator: "equals", string_value: "დმანისი" },
                ],
                limits: [{ amount: "2.0000", unit: "ცალი" }],
              },
            ],
          },
        }}
        loading={false}
        accuracyMeters={null}
      />,
    );
    expect(screen.getByText("უცნობი")).toBeInTheDocument();
    expect(screen.getByText(/კუდსადგისა იხვი/)).toBeInTheDocument();
    expect(screen.getByText(/მხოლოდ: დმანისი/)).toBeInTheDocument();
    expect(screen.getByText(/დღიური ლიმიტი: 2 ცალი/)).toBeInTheDocument();
    expect(screen.getByText(/არჩეულ წერტილზე ნებართვა არ არის/)).toBeInTheDocument();
  });

  it("toggles layers and switches list selection", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    const copy = getMapCopy("en");
    render(<LayerControl copy={copy} layers={["protected"]} counts={{ protected: 2 }} onChange={onChange} />);
    await user.click(screen.getByRole("checkbox", { name: "Protected areas" }));
    expect(onChange).toHaveBeenCalledWith([]);
    render(<MapLegend copy={copy} />);
    expect(screen.getByRole("region", { name: "Legend" }) || screen.getByRole("heading", { name: "Legend" })).toBeTruthy();
  });

  it("selects a zone from the list and confirms a point share", async () => {
    const user = userEvent.setup();
    const onSelect = vi.fn();
    const feature = {
      type: "Feature",
      id: "11111111-1111-1111-1111-111111111111",
      properties: {
        id: "11111111-1111-1111-1111-111111111111",
        name: "FICTIONAL Ridge",
        official_name: "FICTIONAL Ridge",
        zone_type: "protected_area",
        legal_state: "prohibited",
        is_fictional: true,
        srid: 4326 as const,
        coordinate_order: "longitude,latitude" as const,
      },
      geometry: null,
      bbox: [44, 41, 45, 42] as [number, number, number, number],
    } satisfies ViewportFeature;
    render(
      <ResultsList
        copy={getMapCopy("en")}
        features={[feature]}
        selectedId={null}
        onSelect={onSelect}
      />,
    );
    await user.click(screen.getByRole("button", { name: /FICTIONAL Ridge/ }));
    expect(onSelect).toHaveBeenCalledWith(feature.properties.id);
    const onConfirm = vi.fn();
    render(
      <ShareDialog
        copy={getMapCopy("en")}
        open
        url="https://hunt.test/map?pin=44.1000,41.2000"
        failed={false}
        onCancel={vi.fn()}
        onConfirm={onConfirm}
      />,
    );
    expect(screen.getByRole("dialog")).toHaveTextContent("selected coordinates");
    await user.click(screen.getByRole("button", { name: "Copy point link" }));
    expect(onConfirm).toHaveBeenCalled();
  });
});
