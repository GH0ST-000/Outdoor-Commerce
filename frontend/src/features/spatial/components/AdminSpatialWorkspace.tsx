"use client";

import { FormEvent, useEffect, useState } from "react";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import {
  createSpatialDataset,
  createSpatialSource,
  fetchSpatialCoverage,
  fetchSpatialDashboard,
  fetchSpatialImports,
  fetchSpatialSources,
  fetchSpatialZones,
  importSpatialVersion,
  mapSpatialVersion,
  previewSpatialEvaluation,
  previewSpatialVersion,
  transitionSpatialVersion,
  uploadSpatialVersion,
  verifySpatialSource,
} from "@/features/spatial/api/admin-spatial-api";
import {
  BoundaryWarning,
  LegalSpatialState,
  SourceAttribution,
  ZoneTypeBadge,
} from "@/features/spatial/components/SpatialMapPrimitives";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

function statusText(message: string, kind: "ok" | "error" | "info" = "info") {
  return (
    <p
      role={kind === "error" ? "alert" : "status"}
      className={
        kind === "error"
          ? "text-sm text-destructive"
          : "text-sm text-muted-foreground"
      }
    >
      {message}
    </p>
  );
}

export function AdminSpatialDashboardPage() {
  const [data, setData] = useState<Record<string, number> | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSpatialDashboard()
      .then(setData)
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load spatial dashboard.",
        ),
      );
  }, []);

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Spatial zones"
        description="Versioned, source-backed geometry. Drafts are never public. This is not legal advice."
      />
      <SpatialWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {data ? (
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {(
            [
              [
                "Sources awaiting verification",
                data.sources_awaiting_verification,
              ],
              ["Versions awaiting review", data.versions_awaiting_review],
              ["Failed imports", data.imports_failed],
              ["Zones without assignments", data.zones_without_assignments],
              ["Published versions", data.published_versions],
            ] as [string, number | undefined][]
          ).map(([label, value]) => (
            <div key={label} className="rounded-xl border border-border p-4">
              <dt className="text-sm text-muted-foreground">{label}</dt>
              <dd className="mt-1 text-2xl font-semibold">
                {String(value ?? 0)}
              </dd>
            </div>
          ))}
        </dl>
      ) : null}
    </div>
  );
}

export function SpatialWorkspaceNav() {
  const links = [
    { href: "/admin/spatial", label: "Dashboard" },
    { href: "/admin/spatial/import", label: "Import wizard" },
    { href: "/admin/spatial/zones", label: "Zones" },
    { href: "/admin/spatial/coverage", label: "Coverage" },
  ];
  return (
    <nav aria-label="Spatial workspace" className="flex flex-wrap gap-2">
      {links.map((link) => (
        <a
          key={link.href}
          href={link.href}
          className="rounded-full border border-border px-3 py-1.5 text-sm focus-visible:outline-none focus-visible:ring-2"
        >
          {link.label}
        </a>
      ))}
    </nav>
  );
}

export function AdminSpatialImportPage() {
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [preview, setPreview] = useState<Record<string, unknown> | null>(null);
  const [evaluation, setEvaluation] = useState<Record<string, unknown> | null>(
    null,
  );

  async function onCreateSource(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      const source = await createSpatialSource({
        name: form.get("name"),
        source_type: form.get("source_type"),
        publisher_name: form.get("publisher_name"),
        jurisdiction_code: form.get("jurisdiction_code") || "XX",
        attribution_text: form.get("attribution_text"),
        is_fictional: true,
      });
      await verifySpatialSource(String(source.id));
      const dataset = await createSpatialDataset({
        spatial_source_id: source.id,
        name: form.get("dataset_name"),
        dataset_type: "other",
        jurisdiction_code: form.get("jurisdiction_code") || "XX",
        is_fictional: true,
      });
      const upload = new FormData();
      const file = form.get("file");
      if (file instanceof File) {
        upload.append("file", file);
      }
      upload.append("version_label", String(form.get("version_label") || "v1"));
      upload.append(
        "source_crs",
        String(form.get("source_crs") || "EPSG:4326"),
      );
      const version = await uploadSpatialVersion(String(dataset.id), upload);
      setMessage(
        `Uploaded version ${String(version.id)}. Mapping and import are still required. Publication is a separate step.`,
      );
      setError(null);
      event.currentTarget.dataset.versionId = String(version.id);
    } catch (cause) {
      setError(
        cause instanceof ApiClientError ? cause.message : "Upload failed.",
      );
    }
  }

  async function onMapAndImport(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const versionId = String(form.get("version_id"));
    try {
      await mapSpatialVersion(versionId, {
        external_identifier: String(form.get("external_identifier")),
        name: String(form.get("name_field") || ""),
      });
      const imported = await importSpatialVersion(versionId);
      const mapped = await previewSpatialVersion(versionId);
      setPreview(mapped);
      setMessage(
        `Import status: ${String(imported.status)}. Draft geometry was created and was not published.`,
      );
      setError(null);
    } catch (cause) {
      setError(
        cause instanceof ApiClientError ? cause.message : "Import failed.",
      );
    }
  }

  async function onReview(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const versionId = String(form.get("version_id"));
    const action = String(form.get("action"));
    try {
      await transitionSpatialVersion(
        versionId,
        action as
          "validate" | "submit-review" | "approve" | "publish" | "reject",
        String(form.get("reason") || "Reviewed in admin wizard"),
      );
      setMessage(`Version ${action} completed.`);
      setError(null);
    } catch (cause) {
      setError(
        cause instanceof ApiClientError
          ? cause.message
          : "Review action failed.",
      );
    }
  }

  async function onPreview(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      const result = await previewSpatialEvaluation({
        lng: Number(form.get("lng")),
        lat: Number(form.get("lat")),
        activity: form.get("activity"),
        jurisdiction: form.get("jurisdiction") || "XX",
      });
      setEvaluation(result);
      setError(null);
    } catch (cause) {
      setError(
        cause instanceof ApiClientError ? cause.message : "Evaluation failed.",
      );
    }
  }

  return (
    <div className="space-y-8">
      <AdminPageHeader
        title="Spatial import wizard"
        description="Upload, map, import, then review. Import never publishes."
      />
      <SpatialWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {message ? statusText(message, "ok") : null}

      <form
        onSubmit={onCreateSource}
        className="space-y-4 rounded-xl border border-border p-4"
      >
        <h2 className="text-lg font-semibold">1–4. Source, CRS, and upload</h2>
        <Label htmlFor="name">Source name</Label>
        <Input id="name" name="name" required />
        <Label htmlFor="publisher_name">Publisher</Label>
        <Input id="publisher_name" name="publisher_name" required />
        <Label htmlFor="source_type">Source type</Label>
        <Input
          id="source_type"
          name="source_type"
          defaultValue="manual_verified_dataset"
          required
        />
        <Label htmlFor="dataset_name">Dataset name</Label>
        <Input id="dataset_name" name="dataset_name" required />
        <Label htmlFor="source_crs">Confirmed source CRS</Label>
        <Input
          id="source_crs"
          name="source_crs"
          defaultValue="EPSG:4326"
          required
        />
        <Label htmlFor="file">GeoJSON file</Label>
        <Input
          id="file"
          name="file"
          type="file"
          accept=".geojson,.json,application/geo+json"
          required
        />
        <Label htmlFor="version_label">Version label</Label>
        <Input
          id="version_label"
          name="version_label"
          defaultValue="v1"
          required
        />
        <Label htmlFor="attribution_text">Attribution</Label>
        <Input id="attribution_text" name="attribution_text" />
        <Button type="submit">Upload without publishing</Button>
      </form>

      <form
        onSubmit={onMapAndImport}
        className="space-y-4 rounded-xl border border-border p-4"
      >
        <h2 className="text-lg font-semibold">5–8. Mapping and import</h2>
        <Label htmlFor="version_id">Dataset version ID</Label>
        <Input id="version_id" name="version_id" required />
        <Label htmlFor="external_identifier">
          External identifier property
        </Label>
        <Input
          id="external_identifier"
          name="external_identifier"
          defaultValue="code"
          required
        />
        <Label htmlFor="name_field">Name property</Label>
        <Input id="name_field" name="name_field" defaultValue="title" />
        <Button type="submit">Import drafts</Button>
      </form>

      <form
        onSubmit={onReview}
        className="space-y-4 rounded-xl border border-border p-4"
      >
        <h2 className="text-lg font-semibold">9–10. Review and publication</h2>
        <Label htmlFor="review_version_id">Dataset version ID</Label>
        <Input id="review_version_id" name="version_id" required />
        <Label htmlFor="action">Action</Label>
        <select
          id="action"
          name="action"
          className="w-full rounded-md border border-border p-2"
        >
          <option value="submit-review">Submit for review</option>
          <option value="approve">Approve</option>
          <option value="publish">Publish</option>
          <option value="reject">Reject</option>
        </select>
        <Label htmlFor="reason">Reason</Label>
        <Input id="reason" name="reason" defaultValue="Reviewed" />
        <Button type="submit">Apply review action</Button>
        <p className="text-sm text-muted-foreground">
          Publication is a distinct permission. Completing import does not
          publish.
        </p>
      </form>

      {preview ? (
        <section
          className="rounded-xl border border-border p-4"
          aria-live="polite"
        >
          <h2 className="text-lg font-semibold">Validation preview</h2>
          <p>Features: {String(preview.feature_count ?? 0)}</p>
          <p>Vertices: {String(preview.vertex_count ?? 0)}</p>
        </section>
      ) : null}

      <form
        onSubmit={onPreview}
        className="space-y-4 rounded-xl border border-border p-4"
      >
        <h2 className="text-lg font-semibold">Internal coordinate preview</h2>
        <Label htmlFor="lng">Longitude</Label>
        <Input id="lng" name="lng" required />
        <Label htmlFor="lat">Latitude</Label>
        <Input id="lat" name="lat" required />
        <Label htmlFor="activity">Activity</Label>
        <Input id="activity" name="activity" defaultValue="hunting" required />
        <Button type="submit">Evaluate</Button>
      </form>
      {evaluation ? (
        <div className="space-y-2">
          <LegalSpatialState
            outcome={String(evaluation.outcome ?? "unknown")}
          />
          <BoundaryWarning visible={Boolean(evaluation.boundary_warning)} />
        </div>
      ) : null}
    </div>
  );
}

export function AdminSpatialZonesPage() {
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSpatialZones()
      .then((response) => setRows(response.data))
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load zones.",
        ),
      );
  }, []);

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Spatial zones"
        description="Canonical identities. Legal meaning comes from published rule assignments."
      />
      <SpatialWorkspaceNav />
      {error ? statusText(error, "error") : null}
      <div className="overflow-x-auto">
        <table className="w-full min-w-[40rem] text-left text-sm">
          <caption className="sr-only">Spatial zones</caption>
          <thead>
            <tr>
              <th className="border-b p-2">Name</th>
              <th className="border-b p-2">Type</th>
              <th className="border-b p-2">Status</th>
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr>
                <td className="p-2" colSpan={3}>
                  No spatial zones yet.
                </td>
              </tr>
            ) : (
              rows.map((row) => (
                <tr key={String(row.id)}>
                  <td className="border-b p-2">{String(row.default_name)}</td>
                  <td className="border-b p-2">
                    <ZoneTypeBadge zoneType={String(row.zone_type)} />
                  </td>
                  <td className="border-b p-2">{String(row.status)}</td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export function AdminSpatialCoveragePage() {
  const [data, setData] = useState<Record<string, number> | null>(null);
  const [imports, setImports] = useState<Record<string, unknown>[]>([]);
  const [sources, setSources] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([
      fetchSpatialCoverage(),
      fetchSpatialImports(),
      fetchSpatialSources(),
    ])
      .then(([coverage, importPage, sourcePage]) => {
        setData(coverage);
        setImports(importPage.data);
        setSources(sourcePage.data);
      })
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load coverage.",
        ),
      );
  }, []);

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Spatial coverage"
        description="Verified, missing, draft, and fictional geometry are counted separately."
      />
      <SpatialWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {data ? (
        <ul className="grid gap-3 sm:grid-cols-2">
          {Object.entries(data).map(([key, value]) => (
            <li key={key} className="rounded-xl border border-border p-4">
              <p className="text-sm text-muted-foreground">
                {key.replaceAll("_", " ")}
              </p>
              <p className="text-2xl font-semibold">{value}</p>
            </li>
          ))}
        </ul>
      ) : null}
      <section>
        <h2 className="mb-2 text-lg font-semibold">Sources</h2>
        {sources.map((source) => (
          <SourceAttribution
            key={String(source.id)}
            attribution={{
              source_name: String(source.name ?? ""),
              publisher_name: String(source.publisher_name ?? ""),
              attribution_text: source.attribution_text
                ? String(source.attribution_text)
                : null,
              verified_at: source.verified_at
                ? String(source.verified_at)
                : null,
            }}
          />
        ))}
      </section>
      <section>
        <h2 className="mb-2 text-lg font-semibold">Imports</h2>
        <ul>
          {imports.map((row) => (
            <li key={String(row.id)}>
              {String(row.status)} — imported{" "}
              {String(row.features_imported ?? 0)}, rejected{" "}
              {String(row.features_rejected ?? 0)}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
