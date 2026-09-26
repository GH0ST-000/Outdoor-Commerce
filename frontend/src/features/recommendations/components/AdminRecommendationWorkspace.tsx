"use client";

import { FormEvent, useEffect, useState } from "react";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { ApiClientError } from "@/lib/api-client";
import {
  bulkAssignProducts,
  createMerchandisingRule,
  createProductAssignment,
  createRecommendationProfile,
  createTaxonomyTerm,
  fetchRecommendationCoverage,
  fetchRecommendationProfiles,
  fetchTaxonomyTerms,
  simulateRecommendations,
  transitionRecommendationProfile,
  type CoverageReport,
  type RecommendationProfileRow,
} from "@/features/recommendations/api/admin-recommendations-api";

function message(text: string, kind: "ok" | "error" = "ok") {
  return (
    <p
      role={kind === "error" ? "alert" : "status"}
      className={
        kind === "error"
          ? "text-sm text-destructive"
          : "text-sm text-muted-foreground"
      }
    >
      {text}
    </p>
  );
}

export function AdminRecommendationWorkspace() {
  const [coverage, setCoverage] = useState<CoverageReport | null>(null);
  const [profiles, setProfiles] = useState<RecommendationProfileRow[]>([]);
  const [terms, setTerms] = useState<
    Array<{
      public_id: string;
      dimension: string;
      code: string;
      default_label: string;
    }>
  >([]);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [simulation, setSimulation] = useState<string>("");
  const [productIds, setProductIds] = useState("");
  const [confirmed, setConfirmed] = useState(false);

  async function reload() {
    const [nextCoverage, nextProfiles, nextTerms] = await Promise.all([
      fetchRecommendationCoverage(),
      fetchRecommendationProfiles(),
      fetchTaxonomyTerms(),
    ]);
    setCoverage(nextCoverage);
    setProfiles(nextProfiles);
    setTerms(nextTerms);
  }

  useEffect(() => {
    let cancelled = false;
    Promise.all([
      fetchRecommendationCoverage(),
      fetchRecommendationProfiles(),
      fetchTaxonomyTerms(),
    ])
      .then(([nextCoverage, nextProfiles, nextTerms]) => {
        if (cancelled) return;
        setCoverage(nextCoverage);
        setProfiles(nextProfiles);
        setTerms(nextTerms);
      })
      .catch((cause) => {
        if (!cancelled) {
          setError(
            cause instanceof ApiClientError
              ? cause.message
              : "Unable to load recommendations.",
          );
        }
      });
    return () => {
      cancelled = true;
    };
  }, []);

  function fail(cause: unknown) {
    setNotice(null);
    setError(
      cause instanceof ApiClientError
        ? cause.message
        : "The recommendation action failed.",
    );
  }

  async function onCreateProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await createRecommendationProfile(
        String(form.get("name") ?? ""),
        String(form.get("placement") ?? ""),
      );
      setNotice(
        "Draft profile created. Submit it for review before publication.",
      );
      setError(null);
      await reload();
    } catch (cause) {
      fail(cause);
    }
  }

  async function onMerchandising(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const value = Number(form.get("adjustment_value"));
    if (!Number.isInteger(value) || value < 0 || value > 12) {
      setError("Boosts must stay between 0 and 12.");
      return;
    }
    try {
      await createMerchandisingRule({
        name: String(form.get("name") ?? ""),
        placement: String(form.get("placement") ?? "species_detail"),
        adjustment_type: String(form.get("adjustment_type") ?? "boost"),
        adjustment_value: value,
        reason: String(form.get("reason") ?? ""),
        starts_at: String(form.get("starts_at") ?? ""),
        ends_at: String(form.get("ends_at") ?? ""),
        product_id: form.get("product_id")
          ? Number(form.get("product_id"))
          : null,
      });
      setNotice("Merchandising rule saved. It cannot bypass a hard exclusion.");
      setError(null);
    } catch (cause) {
      fail(cause);
    }
  }

  async function onSimulate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      const result = await simulateRecommendations({
        placement: String(form.get("placement") ?? "species_detail"),
        conclusion: String(form.get("conclusion") ?? "allowed"),
        spatially_verified: form.get("spatially_verified") === "on",
        activity: String(form.get("activity") ?? "hunting"),
        species_slug: String(form.get("species_slug") ?? "") || null,
        completeness: "partial",
        locale: "en",
      });
      setSimulation(JSON.stringify(result, null, 2));
      setNotice("Simulation finished. Diagnostics stay in this admin view.");
      setError(null);
    } catch (cause) {
      fail(cause);
    }
  }

  async function onTerm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await createTaxonomyTerm({
        dimension: String(form.get("dimension") ?? "activity"),
        code: String(form.get("code") ?? ""),
        default_label: String(form.get("default_label") ?? ""),
        labels: {
          ka: String(form.get("label_ka") ?? ""),
          en: String(form.get("label_en") ?? ""),
        },
      });
      setNotice("Taxonomy term saved.");
      setError(null);
      await reload();
    } catch (cause) {
      fail(cause);
    }
  }

  async function onAssignment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const productId = Number(form.get("product_id"));
    try {
      await createProductAssignment(productId, {
        term_id: String(form.get("term_id") ?? ""),
        variant_id: String(form.get("variant_id") ?? "") || null,
        assignment_type: String(form.get("assignment_type") ?? "supported"),
        source_type: String(form.get("source_type") ?? "manual_verified"),
        review_notes: String(form.get("review_notes") ?? ""),
        status: "active",
      });
      setNotice(
        "Assignment saved. An exclusion defeats a positive match for the same term.",
      );
      setError(null);
    } catch (cause) {
      fail(cause);
    }
  }

  async function onBulk(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const ids = productIds
      .split(/[\s,]+/)
      .map((value) => Number(value))
      .filter((value) => Number.isInteger(value) && value > 0);
    if (!confirmed) {
      setError("Confirm the bulk mapping before it runs.");
      return;
    }
    try {
      const result = await bulkAssignProducts({
        product_ids: ids,
        confirm: true,
        term_id: String(form.get("term_id") ?? ""),
        assignment_type: String(form.get("assignment_type") ?? "supported"),
        source_type: "manual_verified",
        status: "active",
      });
      setNotice(
        `Bulk mapping accepted for ${result.affected} products${result.queued ? " and queued" : ""}.`,
      );
      setError(null);
    } catch (cause) {
      fail(cause);
    }
  }

  const previewCount = productIds
    .split(/[\s,]+/)
    .filter((value) => Number(value) > 0).length;

  return (
    <div className="space-y-8">
      <AdminPageHeader
        title="Recommendations"
        description="Map products to outdoor context, publish a ranking profile, and simulate results without using a shopper's location."
      />
      {error ? message(error, "error") : null}
      {notice ? message(notice) : null}
      <section aria-labelledby="coverage-heading" className="space-y-2">
        <h2 id="coverage-heading" className="text-xl font-semibold">
          Coverage
        </h2>
        {coverage ? (
          <ul className="grid gap-2 text-sm sm:grid-cols-2">
            <li>
              Products without activity mappings:{" "}
              {coverage.products_without_activity}
            </li>
            <li>
              Products without assignments:{" "}
              {coverage.products_without_assignments}
            </li>
            <li>
              Contradictory assignments: {coverage.contradictory_assignments}
            </li>
            <li>Low-confidence sources: {coverage.low_confidence_sources}</li>
            <li>
              Scheduled merchandising rules: {coverage.scheduled_merchandising}
            </li>
            <li>Expiring assignments: {coverage.expiring_assignments}</li>
            <li>
              Equipment terms without products:{" "}
              {coverage.equipment_terms_without_products.join(", ") || "None"}
            </li>
            <li>
              Active profiles:{" "}
              {coverage.active_profiles
                .map((profile) => `${profile.placement} v${profile.version}`)
                .join(", ")}
            </li>
          </ul>
        ) : (
          <p role="status">Loading coverage</p>
        )}
      </section>

      <section aria-labelledby="profiles-heading" className="space-y-3">
        <h2 id="profiles-heading" className="text-xl font-semibold">
          Ranking profiles
        </h2>
        <ul className="space-y-2 text-sm">
          {profiles.map((profile) => (
            <li
              key={profile.public_id}
              className="flex flex-wrap items-center gap-2"
            >
              <span>
                {profile.name} · {profile.placement} · v{profile.version} ·{" "}
                {profile.status}
              </span>
              {profile.status === "draft" ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() =>
                    void transitionRecommendationProfile(
                      profile.public_id,
                      "submit-review",
                    )
                      .then(reload)
                      .catch(fail)
                  }
                >
                  Submit for review
                </Button>
              ) : null}
              {profile.status === "in_review" ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() =>
                    void transitionRecommendationProfile(
                      profile.public_id,
                      "approve",
                    )
                      .then(reload)
                      .catch(fail)
                  }
                >
                  Approve
                </Button>
              ) : null}
              {profile.status === "approved" ? (
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  onClick={() =>
                    void transitionRecommendationProfile(
                      profile.public_id,
                      "publish",
                    )
                      .then(reload)
                      .catch(fail)
                  }
                >
                  Publish
                </Button>
              ) : null}
            </li>
          ))}
        </ul>
        <form className="grid max-w-xl gap-3" onSubmit={onCreateProfile}>
          <Label htmlFor="profile-name">Profile name</Label>
          <Input id="profile-name" name="name" required />
          <Label htmlFor="profile-placement">Placement</Label>
          <select
            id="profile-placement"
            name="placement"
            className="h-10 rounded-md border px-3"
            defaultValue="species_detail"
          >
            <option value="outdoor_context_result">
              Outdoor context result
            </option>
            <option value="species_detail">Species detail</option>
            <option value="season_explorer">Season explorer</option>
            <option value="map_location_result">Map location result</option>
          </select>
          <Button type="submit">Create draft profile</Button>
        </form>
      </section>

      <section aria-labelledby="taxonomy-heading" className="space-y-3">
        <h2 id="taxonomy-heading" className="text-xl font-semibold">
          Context taxonomy
        </h2>
        <ul className="text-sm">
          {terms.map((term) => (
            <li key={term.public_id}>
              {term.dimension}:{term.code} — {term.default_label}
            </li>
          ))}
        </ul>
        <form className="grid max-w-xl gap-3" onSubmit={onTerm}>
          <Label htmlFor="term-dimension">Dimension</Label>
          <Input
            id="term-dimension"
            name="dimension"
            defaultValue="equipment"
            required
          />
          <Label htmlFor="term-code">Code</Label>
          <Input id="term-code" name="code" required />
          <Label htmlFor="term-label">Default label</Label>
          <Input id="term-label" name="default_label" required />
          <Label htmlFor="term-ka">Georgian label</Label>
          <Input id="term-ka" name="label_ka" required />
          <Label htmlFor="term-en">English label</Label>
          <Input id="term-en" name="label_en" required />
          <Button type="submit">Save term</Button>
        </form>
      </section>

      <section aria-labelledby="assignment-heading" className="space-y-3">
        <h2 id="assignment-heading" className="text-xl font-semibold">
          Product mapping
        </h2>
        <form className="grid max-w-xl gap-3" onSubmit={onAssignment}>
          <Label htmlFor="assign-product">Product id</Label>
          <Input
            id="assign-product"
            name="product_id"
            inputMode="numeric"
            required
          />
          <Label htmlFor="assign-variant">Variant id</Label>
          <Input id="assign-variant" name="variant_id" />
          <Label htmlFor="assign-term">Taxonomy term</Label>
          <select
            id="assign-term"
            name="term_id"
            className="h-10 rounded-md border px-3"
            required
          >
            {terms.map((term) => (
              <option key={term.public_id} value={term.public_id}>
                {term.dimension}:{term.code}
              </option>
            ))}
          </select>
          <Label htmlFor="assign-type">Assignment type</Label>
          <select
            id="assign-type"
            name="assignment_type"
            className="h-10 rounded-md border px-3"
            defaultValue="supported"
          >
            <option value="required_match">Required match</option>
            <option value="preferred_match">Preferred match</option>
            <option value="supported">Supported</option>
            <option value="neutral">Neutral</option>
            <option value="excluded">Excluded</option>
          </select>
          <Label htmlFor="assign-source">Source</Label>
          <select
            id="assign-source"
            name="source_type"
            className="h-10 rounded-md border px-3"
            defaultValue="manual_verified"
          >
            <option value="manual_verified">Manual verified</option>
            <option value="manufacturer_specification">
              Manufacturer specification
            </option>
            <option value="catalog_attribute">Catalog attribute</option>
            <option value="legal_rule">Legal rule</option>
            <option value="species_knowledge">Species knowledge</option>
          </select>
          <Label htmlFor="assign-notes">Review notes</Label>
          <Input id="assign-notes" name="review_notes" />
          <Button type="submit">Save assignment</Button>
        </form>
        <form className="grid max-w-xl gap-3" onSubmit={onBulk}>
          <Label htmlFor="bulk-products">Product ids</Label>
          <textarea
            id="bulk-products"
            className="min-h-24 rounded-md border p-2"
            value={productIds}
            onChange={(event) => setProductIds(event.target.value)}
          />
          <p className="text-sm">Affected products: {previewCount}</p>
          <Label htmlFor="bulk-term">Taxonomy term</Label>
          <select
            id="bulk-term"
            name="term_id"
            className="h-10 rounded-md border px-3"
          >
            {terms.map((term) => (
              <option key={term.public_id} value={term.public_id}>
                {term.code}
              </option>
            ))}
          </select>
          <Label htmlFor="bulk-type">Assignment type</Label>
          <select
            id="bulk-type"
            name="assignment_type"
            className="h-10 rounded-md border px-3"
            defaultValue="supported"
          >
            <option value="supported">Supported</option>
            <option value="excluded">Excluded</option>
          </select>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={confirmed}
              onChange={(event) => setConfirmed(event.target.checked)}
            />
            I confirm this mapping
          </label>
          <Button type="submit">Run bulk mapping</Button>
        </form>
      </section>

      <section aria-labelledby="merch-heading" className="space-y-3">
        <h2 id="merch-heading" className="text-xl font-semibold">
          Merchandising
        </h2>
        <form className="grid max-w-xl gap-3" onSubmit={onMerchandising}>
          <Label htmlFor="merch-name">Name</Label>
          <Input id="merch-name" name="name" required />
          <Label htmlFor="merch-placement">Placement</Label>
          <Input
            id="merch-placement"
            name="placement"
            defaultValue="species_detail"
            required
          />
          <Label htmlFor="merch-type">Adjustment</Label>
          <select
            id="merch-type"
            name="adjustment_type"
            className="h-10 rounded-md border px-3"
            defaultValue="boost"
          >
            <option value="boost">Boost</option>
            <option value="demote">Demote</option>
            <option value="pin">Pin</option>
            <option value="exclude">Exclude</option>
          </select>
          <Label htmlFor="merch-value">Value (0–12)</Label>
          <Input
            id="merch-value"
            name="adjustment_value"
            inputMode="numeric"
            defaultValue="4"
            required
          />
          <Label htmlFor="merch-product">Product id</Label>
          <Input id="merch-product" name="product_id" inputMode="numeric" />
          <Label htmlFor="merch-reason">Reason</Label>
          <Input id="merch-reason" name="reason" required />
          <Label htmlFor="merch-start">Starts</Label>
          <Input
            id="merch-start"
            name="starts_at"
            type="datetime-local"
            required
          />
          <Label htmlFor="merch-end">Ends</Label>
          <Input id="merch-end" name="ends_at" type="datetime-local" required />
          <Button type="submit">Save merchandising rule</Button>
        </form>
      </section>

      <section aria-labelledby="simulate-heading" className="space-y-3">
        <h2 id="simulate-heading" className="text-xl font-semibold">
          Simulation
        </h2>
        <form className="grid max-w-xl gap-3" onSubmit={onSimulate}>
          <Label htmlFor="sim-placement">Placement</Label>
          <Input
            id="sim-placement"
            name="placement"
            defaultValue="species_detail"
            required
          />
          <Label htmlFor="sim-conclusion">Legal conclusion</Label>
          <select
            id="sim-conclusion"
            name="conclusion"
            className="h-10 rounded-md border px-3"
            defaultValue="allowed"
          >
            <option value="allowed">Allowed</option>
            <option value="conditional">Conditional</option>
            <option value="unknown">Unknown</option>
            <option value="prohibited">Prohibited</option>
            <option value="conflict">Conflict</option>
          </select>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" name="spatially_verified" />
            Spatially verified
          </label>
          <Label htmlFor="sim-activity">Activity</Label>
          <Input id="sim-activity" name="activity" defaultValue="hunting" />
          <Label htmlFor="sim-species">Species slug</Label>
          <Input id="sim-species" name="species_slug" />
          <Button type="submit">Run simulation</Button>
        </form>
        {simulation ? (
          <pre
            className="max-h-96 overflow-auto whitespace-pre-wrap text-xs"
            aria-label="Simulation diagnostics"
          >
            {simulation}
          </pre>
        ) : null}
      </section>
    </div>
  );
}
