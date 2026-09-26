import {
  LAYER_ORDER,
  LAYER_ZONE_TYPES,
  type LayerId,
} from "@/features/map/lib/cartography";

export const LEGAL_SOURCE_ID = "legal-zones";

export type MapLayerSpec = {
  id: string;
  kind: "fill" | "line";
  layer: LayerId;
  filter: unknown[];
};

export function categoryLayerSpecs(): MapLayerSpec[] {
  const specs: MapLayerSpec[] = [];
  for (const layer of LAYER_ORDER) {
    const filter = [
      "in",
      ["get", "zone_type"],
      ["literal", [...LAYER_ZONE_TYPES[layer]]],
    ];
    specs.push({ id: `zone-fill-${layer}`, kind: "fill", layer, filter });
    specs.push({ id: `zone-line-${layer}`, kind: "line", layer, filter });
  }
  return specs;
}

export function orderedLayerIds(): string[] {
  const ids = categoryLayerSpecs().map((spec) => spec.id);
  return [
    ...ids,
    "zone-line-hover",
    "zone-line-selected",
    "boundary-warning",
    "user-accuracy",
    "user-location",
    "selected-point",
  ];
}
