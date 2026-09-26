import { render, cleanup } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { resolveMapConfig } from "@/features/map/lib/map-config";
import { orderedLayerIds } from "@/features/map/lib/layers";

const created: MockMap[] = [];

class MockMap {
  handlers: Record<
    string,
    (event?: {
      point: { x: number; y: number };
      lngLat: { lng: number; lat: number };
    }) => void
  > = {};
  sources: Record<
    string,
    { data?: unknown; setData: (data: unknown) => void }
  > = {};
  layers: string[] = [];
  removed = false;
  constructor() {
    created.push(this);
  }
  on(event: string, handler: (event?: never) => void) {
    this.handlers[event] = handler as never;
    if (event === "load") handler();
  }
  addControl() {}
  addSource(id: string, spec: { data?: unknown }) {
    this.sources[id] = {
      data: spec.data,
      setData: (data) => {
        this.sources[id].data = data;
      },
    };
  }
  getSource(id: string) {
    return this.sources[id];
  }
  getStyle() {
    return { layers: [{ id: "place-label", type: "symbol" }] };
  }
  addLayer(layer: { id: string }) {
    this.layers.push(layer.id);
  }
  getLayer(id: string) {
    return this.layers.includes(id) ? { id } : undefined;
  }
  setFilter() {}
  setLayoutProperty() {}
  queryRenderedFeatures() {
    return [{ properties: { id: "zone-a" } }, { properties: { id: "zone-b" } }];
  }
  getBounds() {
    return {
      getWest: () => 43,
      getSouth: () => 41,
      getEast: () => 45,
      getNorth: () => 43,
    };
  }
  getCenter() {
    return { lng: 44, lat: 42 };
  }
  getZoom() {
    return 7;
  }
  resize() {}
  remove() {
    this.removed = true;
  }
  isStyleLoaded() {
    return true;
  }
}

vi.mock("maplibre-gl", () => ({
  Map: MockMap,
  NavigationControl: class {},
  setWorkerUrl: () => {},
}));
vi.mock("maplibre-gl/dist/maplibre-gl.css", () => ({}));

describe("map canvas", () => {
  afterEach(() => {
    cleanup();
    created.length = 0;
  });

  it("initializes one map, orders layers, and removes it on unmount", async () => {
    const { MapCanvas } = await import("@/features/map/components/MapCanvas");
    const config = resolveMapConfig("test");
    const onClick = vi.fn();
    const view = render(
      <MapCanvas
        config={config}
        data={{ type: "FeatureCollection", features: [] }}
        selectedId={null}
        hoverId={null}
        point={null}
        user={null}
        boundary={null}
        hiddenLayers={[]}
        onClick={onClick}
      />,
    );
    await vi.waitFor(() => expect(created).toHaveLength(1));
    const map = created[0];
    expect(map.layers.slice(0, 2)).toEqual([
      "zone-fill-admin",
      "zone-line-admin",
    ]);
    expect(map.layers).toEqual(
      expect.arrayContaining(
        orderedLayerIds().filter((id) => id.startsWith("zone-")),
      ),
    );
    map.handlers.click?.({
      point: { x: 1, y: 1 },
      lngLat: { lng: 44.2, lat: 41.7 },
    });
    expect(onClick).toHaveBeenCalledWith({
      ids: ["zone-a", "zone-b"],
      longitude: 44.2,
      latitude: 41.7,
    });
    view.unmount();
    expect(map.removed).toBe(true);
    expect(created).toHaveLength(1);
  });
});
