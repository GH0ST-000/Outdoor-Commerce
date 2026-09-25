import { describe, expect, it } from "vitest";
import {
  parseSpeciesSearchParams,
  speciesQueryToSearchParams,
} from "@/features/species/query-state/species-search-params";

describe("species URL state", () => {
  it("round-trips allowlisted filters and pagination", () => {
    const query = parseSpeciesSearchParams({
      q: "ირემი",
      activity_type: "hunting",
      habitat: "forest",
      sort: "newest",
      page: "2",
    });
    expect(query).toEqual({
      q: "ირემი",
      activity_type: "hunting",
      domain_type: undefined,
      taxonomy: undefined,
      habitat: "forest",
      conservation_status: undefined,
      sort: "newest",
      page: 2,
    });
    expect(speciesQueryToSearchParams(query)).toContain(
      "q=%E1%83%98%E1%83%A0%E1%83%94%E1%83%9B%E1%83%98",
    );
  });
});
