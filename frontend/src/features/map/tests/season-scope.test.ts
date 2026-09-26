import { describe, expect, it } from "vitest";
import {
  featureCollectionBbox,
  seasonScopeCollection,
} from "@/features/map/lib/season-scope";
import type { MunicipalityFeature } from "@/features/map/lib/season-scope";

function place(
  nameEn: string,
  kind: "municipality" | "city" = "municipality",
): MunicipalityFeature {
  return {
    type: "Feature",
    properties: { id: nameEn, name: nameEn, name_en: nameEn, kind },
    geometry: {
      type: "Polygon",
      coordinates: [
        [
          [0, 0],
          [1, 0],
          [1, 1],
          [0, 0],
        ],
      ],
    },
  };
}

const places = [
  place("Akhalkalaki"),
  place("Ninotsminda"),
  place("Tsalka"),
  place("Dmanisi"),
  place("Gori"),
  place("Batumi", "city"),
];

describe("season scope", () => {
  it("paints only the four municipalities named for the September window", () => {
    const scope = seasonScopeCollection(places, "conditional", [
      {
        condition_type: "region",
        operator: "equals",
        string_value: "ახალქალაქი",
      },
      {
        condition_type: "region",
        operator: "equals",
        string_value: "ნინოწმინდა",
      },
      { condition_type: "region", operator: "equals", string_value: "წალკა" },
      { condition_type: "region", operator: "equals", string_value: "დმანისი" },
    ]);
    expect(
      scope.features.map((feature) => feature.properties?.name_en),
    ).toEqual(["Akhalkalaki", "Ninotsminda", "Tsalka", "Dmanisi"]);
  });

  it("paints the rest of the municipalities when those four are excluded", () => {
    const scope = seasonScopeCollection(places, "conditional", [
      {
        condition_type: "region",
        operator: "not_equals",
        string_value: "ახალქალაქი",
      },
      {
        condition_type: "region",
        operator: "not_equals",
        string_value: "ნინოწმინდა",
      },
      {
        condition_type: "region",
        operator: "not_equals",
        string_value: "წალკა",
      },
      {
        condition_type: "region",
        operator: "not_equals",
        string_value: "დმანისი",
      },
    ]);
    expect(
      scope.features.map((feature) => feature.properties?.name_en),
    ).toEqual(["Gori"]);
  });

  it("uses the municipality outline as the camera bounds", () => {
    const scope = seasonScopeCollection(places, "conditional", [
      { condition_type: "region", operator: "equals", string_value: "წალკა" },
    ]);
    expect(featureCollectionBbox(scope)).toEqual([0, 0, 1, 1]);
  });

  it("does not paint a closed season", () => {
    const scope = seasonScopeCollection(places, "closed", []);
    expect(scope.features).toEqual([]);
  });
});
