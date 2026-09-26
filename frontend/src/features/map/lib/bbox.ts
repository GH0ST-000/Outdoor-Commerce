export type BBox = [number, number, number, number];

export function roundCoordinate(value: number, decimals: number): number {
  const factor = 10 ** decimals;
  return Math.round(value * factor) / factor;
}

export function normalizeBbox(
  west: number,
  south: number,
  east: number,
  north: number,
): BBox | null {
  if (![west, south, east, north].every((value) => Number.isFinite(value))) {
    return null;
  }
  if (south < -90 || north > 90 || south > north) return null;
  if (west < -180 || east > 180 || west >= east) return null;
  return [
    roundCoordinate(west, 3),
    roundCoordinate(south, 3),
    roundCoordinate(east, 3),
    roundCoordinate(north, 3),
  ];
}

export function spanDegrees(bbox: BBox): number {
  return Math.max(bbox[2] - bbox[0], bbox[3] - bbox[1]);
}
