/**
 * Address geocoding is intentionally unavailable.
 * A future provider must be reviewed for terms, privacy, attribution, and rate limits
 * before this interface is implemented.
 */
export type PlaceGeocodeHit = {
  label: string;
  longitude: number;
  latitude: number;
  attribution: string;
};

export interface PlaceGeocoder {
  search(query: string, signal?: AbortSignal): Promise<PlaceGeocodeHit[]>;
}

export const unavailablePlaceGeocoder: PlaceGeocoder = {
  async search() {
    return [];
  },
};
