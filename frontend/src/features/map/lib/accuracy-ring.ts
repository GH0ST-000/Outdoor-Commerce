/** Display-only ring. It is not a legal zone and is never sent for evaluation. */
export function accuracyRing(
  longitude: number,
  latitude: number,
  radiusMeters: number,
  steps = 48,
): GeoJSON.Polygon {
  const radius = Math.max(radiusMeters, 1);
  const coordinates: [number, number][] = [];
  for (let index = 0; index <= steps; index += 1) {
    const bearing = (index / steps) * Math.PI * 2;
    const latitudeOffset = (radius / 111_320) * Math.cos(bearing);
    const longitudeScale = Math.max(Math.cos((latitude * Math.PI) / 180), 0.01);
    const longitudeOffset =
      (radius / (111_320 * longitudeScale)) * Math.sin(bearing);
    coordinates.push([
      Number((longitude + longitudeOffset).toFixed(5)),
      Number((latitude + latitudeOffset).toFixed(5)),
    ]);
  }
  return { type: "Polygon", coordinates: [coordinates] };
}
