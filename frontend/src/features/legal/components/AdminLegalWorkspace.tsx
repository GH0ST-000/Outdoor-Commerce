"use client";

import { FormEvent, useEffect, useState } from "react";
import Link from "next/link";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { LegalWorkspaceNav } from "@/features/legal/components/LegalWorkspaceNav";
import {
  createLegalAuthority,
  createLegalDocument,
  createLegalProvision,
  createLegalRule,
  createLegalSource,
  dismissChangeDetection,
  fetchChangeDetections,
  fetchLegalAuthorities,
  fetchLegalConflicts,
  fetchLegalDashboard,
  fetchLegalDocuments,
  fetchLegalRules,
  fetchLegalSources,
  previewLegalEvaluation,
  resolveLegalConflict,
  transitionLegalRule,
  transitionLegalSource,
  transitionLegalVersion,
  uploadLegalVersion,
} from "@/features/legal/api/admin-legal-api";
import type { LegalDashboard } from "@/features/legal/types/legal-types";
import { ApiClientError } from "@/lib/api-client";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

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

export function AdminLegalDashboardPage() {
  const [data, setData] = useState<LegalDashboard | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchLegalDashboard()
      .then(setData)
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load legal dashboard.",
        ),
      )
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Legal sources"
        description="Official sources, versioned documents, and structured rules. This workspace is informational and is not legal advice."
      />
      <LegalWorkspaceNav />
      {loading ? statusText("Loading legal dashboard…") : null}
      {error ? statusText(error, "error") : null}
      {data ? (
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[
            [
              "Sources awaiting verification",
              data.sources_awaiting_verification,
            ],
            ["Versions in review", data.versions_awaiting_review],
            ["Rules in review", data.rules_awaiting_review],
            ["Open high-severity conflicts", data.open_high_conflicts],
            ["Detected source changes", data.open_change_detections],
            [
              "Documents without a current version",
              data.documents_without_current_version,
            ],
          ].map(([label, value]) => (
            <div
              key={String(label)}
              className="rounded-xl border border-border p-4"
            >
              <dt className="text-sm text-muted-foreground">{label}</dt>
              <dd className="mt-1 text-2xl font-medium">{value}</dd>
            </div>
          ))}
        </dl>
      ) : null}
      {data?.recently_published_rules.length ? (
        <section>
          <h2 className="text-lg font-medium">Recently published</h2>
          <ul className="mt-2 list-disc pl-5">
            {data.recently_published_rules.map((rule) => (
              <li key={rule.id}>{rule.title}</li>
            ))}
          </ul>
        </section>
      ) : null}
      <p className="text-sm text-muted-foreground">
        Absence of a prohibition is not permission. Unpublished drafts never
        appear publicly.
      </p>
    </div>
  );
}

export function AdminLegalSourcesPage() {
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [authorities, setAuthorities] = useState<Record<string, unknown>[]>([]);
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState("");

  async function reload() {
    const [sources, authorityRows] = await Promise.all([
      fetchLegalSources(filter ? `?verification_status=${filter}` : ""),
      fetchLegalAuthorities(),
    ]);
    setRows(sources.data);
    setAuthorities(authorityRows);
  }

  useEffect(() => {
    void Promise.all([
      fetchLegalSources(filter ? `?verification_status=${filter}` : ""),
      fetchLegalAuthorities(),
    ])
      .then(([sources, authorityRows]) => {
        setRows(sources.data);
        setAuthorities(authorityRows);
      })
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load sources.",
        ),
      );
  }, [filter]);

  async function onCreateAuthority(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await createLegalAuthority({
        name: form.get("name"),
        authority_type: form.get("authority_type"),
        is_fictional: true,
      });
      setStatus(
        "Fictional authority saved. It is marked as not a real legal source.",
      );
      await reload();
    } catch (cause) {
      setError(
        cause instanceof ApiClientError
          ? cause.message
          : "Unable to create authority.",
      );
    }
  }

  async function onCreateSource(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await createLegalSource({
        authority_id: form.get("authority_id"),
        name: form.get("name"),
        source_type: form.get("source_type"),
        official_base_url: form.get("official_base_url") || null,
        allowed_domain: form.get("allowed_domain") || null,
        monitor_for_changes: form.get("monitor_for_changes") === "on",
      });
      setStatus("Source created as unverified.");
      await reload();
    } catch (cause) {
      setError(
        cause instanceof ApiClientError
          ? cause.message
          : "Unable to create source.",
      );
    }
  }

  return (
    <div className="space-y-8">
      <AdminPageHeader
        title="Legal sources"
        description="Only verified, active sources may support published rules."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {status ? statusText(status) : null}
      <div className="flex flex-wrap gap-2">
        <Label htmlFor="source-filter">Verification</Label>
        <select
          id="source-filter"
          className="rounded-md border border-input bg-background px-2 py-1"
          value={filter}
          onChange={(event) => setFilter(event.target.value)}
        >
          <option value="">All</option>
          <option value="unverified">Unverified</option>
          <option value="pending_review">Pending review</option>
          <option value="verified">Verified</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] text-left text-sm">
          <caption className="sr-only">Legal sources</caption>
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Status</th>
              <th scope="col">Authority</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={String(row.id)} className="border-t border-border">
                <td>{String(row.name)}</td>
                <td>{String(row.verification_status)}</td>
                <td>
                  {String(
                    (row.authority as { name?: string } | undefined)?.name ??
                      "",
                  )}
                </td>
                <td className="space-x-2 py-2">
                  <Button
                    size="sm"
                    variant="outline"
                    onClick={() =>
                      transitionLegalSource(String(row.id), "submit-review")
                        .then(reload)
                        .catch((cause) =>
                          setError(
                            cause instanceof ApiClientError
                              ? cause.message
                              : "Failed",
                          ),
                        )
                    }
                  >
                    Submit review
                  </Button>
                  <Button
                    size="sm"
                    onClick={() =>
                      transitionLegalSource(String(row.id), "verify")
                        .then(reload)
                        .catch((cause) =>
                          setError(
                            cause instanceof ApiClientError
                              ? cause.message
                              : "Failed",
                          ),
                        )
                    }
                  >
                    Verify
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <form className="grid max-w-xl gap-3" onSubmit={onCreateAuthority}>
        <h2 className="text-lg font-medium">
          Register a fictional or verified authority
        </h2>
        <Label htmlFor="auth-name">Name</Label>
        <Input id="auth-name" name="name" required />
        <Label htmlFor="auth-type">Type</Label>
        <select
          id="auth-type"
          name="authority_type"
          className="rounded-md border border-input px-2 py-2"
        >
          <option value="agency">Agency</option>
          <option value="ministry">Ministry</option>
          <option value="legislature">Legislature</option>
        </select>
        <Button type="submit">Save authority</Button>
      </form>
      <form className="grid max-w-xl gap-3" onSubmit={onCreateSource}>
        <h2 className="text-lg font-medium">Register a source</h2>
        <Label htmlFor="source-authority">Authority</Label>
        <select
          id="source-authority"
          name="authority_id"
          required
          className="rounded-md border border-input px-2 py-2"
        >
          {authorities.map((authority) => (
            <option key={String(authority.id)} value={String(authority.id)}>
              {String(authority.name)}
            </option>
          ))}
        </select>
        <Label htmlFor="source-name">Name</Label>
        <Input id="source-name" name="name" required />
        <Label htmlFor="source-type">Type</Label>
        <select
          id="source-type"
          name="source_type"
          className="rounded-md border border-input px-2 py-2"
        >
          <option value="manual_verified_source">Manually verified</option>
          <option value="official_website">Official website</option>
          <option value="legislation_portal">Legislation portal</option>
          <option value="official_gazette">Official gazette</option>
          <option value="official_document">Official document</option>
        </select>
        <Label htmlFor="source-url">Official HTTPS URL</Label>
        <Input id="source-url" name="official_base_url" type="url" />
        <Label htmlFor="source-domain">Allowed domain</Label>
        <Input id="source-domain" name="allowed_domain" />
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" name="monitor_for_changes" />
          Monitor for source changes (never auto-publishes)
        </label>
        <Button type="submit">Create source</Button>
      </form>
    </div>
  );
}

export function AdminLegalDocumentsPage() {
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [sources, setSources] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<string | null>(null);

  async function reload() {
    const [documents, sourceRows] = await Promise.all([
      fetchLegalDocuments(),
      fetchLegalSources(),
    ]);
    setRows(documents.data);
    setSources(sourceRows.data);
  }

  useEffect(() => {
    void Promise.all([fetchLegalDocuments(), fetchLegalSources()])
      .then(([documents, sourceRows]) => {
        setRows(documents.data);
        setSources(sourceRows.data);
      })
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load documents.",
        ),
      );
  }, []);

  async function onCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await createLegalDocument({
        source_id: form.get("source_id"),
        title: form.get("title"),
        document_type: form.get("document_type"),
        official_identifier: form.get("official_identifier") || null,
      });
      setStatus("Document created.");
      await reload();
    } catch (cause) {
      setError(
        cause instanceof ApiClientError
          ? cause.message
          : "Unable to create document.",
      );
    }
  }

  async function onUpload(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    const documentId = String(data.get("document_id") ?? "");
    data.delete("document_id");
    try {
      await uploadLegalVersion(documentId, data);
      setStatus(
        "Version stored on the private legal disk. Checksums are SHA-256.",
      );
      await reload();
    } catch (cause) {
      setError(
        cause instanceof ApiClientError ? cause.message : "Upload failed.",
      );
    }
  }

  return (
    <div className="space-y-8">
      <AdminPageHeader
        title="Legal documents"
        description="Versions are immutable after approval. Files stay on private local storage."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {status ? statusText(status) : null}
      <ul className="space-y-3">
        {rows.map((row) => (
          <li
            key={String(row.id)}
            className="rounded-xl border border-border p-4"
          >
            <p className="font-medium">{String(row.title)}</p>
            <p className="text-sm text-muted-foreground">
              {String(row.official_identifier ?? "No official identifier")} ·
              current version {String(row.current_version_id ?? "none")}
            </p>
            <div className="mt-2 flex flex-wrap gap-2">
              {(
                row.versions as
                  | {
                      id: string;
                      version_label: string;
                      review_status: string;
                    }[]
                  | undefined
              )?.map((version) => (
                <Button
                  key={version.id}
                  size="sm"
                  variant="outline"
                  onClick={() =>
                    transitionLegalVersion(version.id, "submit-review")
                      .then(() => transitionLegalVersion(version.id, "approve"))
                      .then(reload)
                      .catch((cause) =>
                        setError(
                          cause instanceof ApiClientError
                            ? cause.message
                            : "Version review failed",
                        ),
                      )
                  }
                >
                  Submit & approve {version.version_label} (
                  {version.review_status})
                </Button>
              ))}
            </div>
          </li>
        ))}
      </ul>
      <form className="grid max-w-xl gap-3" onSubmit={onCreate}>
        <h2 className="text-lg font-medium">Create document</h2>
        <Label htmlFor="doc-source">Source</Label>
        <select
          id="doc-source"
          name="source_id"
          required
          className="rounded-md border border-input px-2 py-2"
        >
          {sources.map((source) => (
            <option key={String(source.id)} value={String(source.id)}>
              {String(source.name)}
            </option>
          ))}
        </select>
        <Label htmlFor="doc-title">Title</Label>
        <Input id="doc-title" name="title" required />
        <Label htmlFor="doc-id">Official identifier</Label>
        <Input id="doc-id" name="official_identifier" />
        <Label htmlFor="doc-type">Type</Label>
        <select
          id="doc-type"
          name="document_type"
          className="rounded-md border border-input px-2 py-2"
        >
          <option value="official_notice">Official notice</option>
          <option value="regulation">Regulation</option>
          <option value="law">Law</option>
          <option value="order">Order</option>
        </select>
        <Button type="submit">Create document</Button>
      </form>
      <form className="grid max-w-xl gap-3" onSubmit={onUpload}>
        <h2 className="text-lg font-medium">Upload official file</h2>
        <Label htmlFor="upload-doc">Document</Label>
        <select
          id="upload-doc"
          name="document_id"
          required
          className="rounded-md border border-input px-2 py-2"
        >
          {rows.map((row) => (
            <option key={String(row.id)} value={String(row.id)}>
              {String(row.title)}
            </option>
          ))}
        </select>
        <Label htmlFor="version-label">Version label</Label>
        <Input id="version-label" name="version_label" required />
        <Label htmlFor="legal-file">PDF, TXT, or HTML (max 20MB)</Label>
        <Input
          id="legal-file"
          name="file"
          type="file"
          accept=".pdf,.txt,.html,application/pdf,text/plain,text/html"
          required
        />
        <Button type="submit">Upload version</Button>
      </form>
      <form
        className="grid max-w-xl gap-3"
        onSubmit={async (event) => {
          event.preventDefault();
          const form = new FormData(event.currentTarget);
          try {
            await createLegalProvision(String(form.get("version_id")), {
              provision_type: form.get("provision_type"),
              reference_code: form.get("reference_code"),
              official_text: form.get("official_text"),
              normalized_summary: form.get("normalized_summary") || null,
            });
            setStatus(
              "Provision saved. Official text is stored separately from the editorial summary.",
            );
          } catch (cause) {
            setError(
              cause instanceof ApiClientError
                ? cause.message
                : "Unable to save provision.",
            );
          }
        }}
      >
        <h2 className="text-lg font-medium">Record a provision</h2>
        <Label htmlFor="prov-version">Version ID</Label>
        <Input id="prov-version" name="version_id" required />
        <Label htmlFor="prov-type">Type</Label>
        <select
          id="prov-type"
          name="provision_type"
          className="rounded-md border border-input px-2 py-2"
        >
          <option value="article">Article</option>
          <option value="section">Section</option>
          <option value="clause">Clause</option>
        </select>
        <Label htmlFor="prov-ref">Reference code</Label>
        <Input id="prov-ref" name="reference_code" required />
        <Label htmlFor="prov-official">Official source text</Label>
        <Textarea id="prov-official" name="official_text" required />
        <Label htmlFor="prov-summary">
          Editorial summary (not legally authoritative)
        </Label>
        <Textarea id="prov-summary" name="normalized_summary" />
        <Button type="submit">Save provision</Button>
      </form>
    </div>
  );
}

export function AdminLegalRulesPage() {
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<string | null>(null);

  async function reload() {
    const response = await fetchLegalRules();
    setRows(response.data);
  }

  useEffect(() => {
    fetchLegalRules()
      .then((response) => setRows(response.data))
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load rules.",
        ),
      );
  }, []);

  async function onCreate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      await createLegalRule({
        title: form.get("title"),
        activity_type: form.get("activity_type"),
        rule_type: form.get("rule_type"),
        effect: form.get("effect"),
        jurisdiction_code: "GE",
        effective_from: form.get("effective_from"),
        interpretation_summary: form.get("interpretation_summary"),
        citations: [
          {
            provision_id: form.get("provision_id"),
            citation_purpose: "authority",
            quoted_excerpt: form.get("quoted_excerpt"),
            is_primary: true,
          },
        ],
      });
      setStatus(
        "Draft rule created. It cannot be published without an approved citation.",
      );
      await reload();
    } catch (cause) {
      setError(
        cause instanceof ApiClientError
          ? cause.message
          : "Unable to create rule.",
      );
    }
  }

  return (
    <div className="space-y-8">
      <AdminPageHeader
        title="Legal rules"
        description="Structured effects, citations, and review. Published rules cannot be freely edited."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {status ? statusText(status) : null}
      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] text-left text-sm">
          <caption className="sr-only">Legal rules</caption>
          <thead>
            <tr>
              <th scope="col">Title</th>
              <th scope="col">Effect</th>
              <th scope="col">Status</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={String(row.id)} className="border-t border-border">
                <td>{String(row.title)}</td>
                <td>{String(row.effect)}</td>
                <td>{String(row.status)}</td>
                <td className="space-x-2 py-2">
                  {["submit-review", "approve", "publish"].map((action) => (
                    <Button
                      key={action}
                      size="sm"
                      variant="outline"
                      onClick={() =>
                        transitionLegalRule(
                          String(row.id),
                          action as "submit-review" | "approve" | "publish",
                        )
                          .then(reload)
                          .catch((cause) =>
                            setError(
                              cause instanceof ApiClientError
                                ? cause.message
                                : "Transition failed",
                            ),
                          )
                      }
                    >
                      {action.replace("-", " ")}
                    </Button>
                  ))}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <form className="grid max-w-xl gap-3" onSubmit={onCreate}>
        <h2 className="text-lg font-medium">Draft a rule</h2>
        <Label htmlFor="rule-title">Title</Label>
        <Input id="rule-title" name="title" required />
        <Label htmlFor="rule-activity">Activity</Label>
        <select
          id="rule-activity"
          name="activity_type"
          className="rounded-md border border-input px-2 py-2"
        >
          <option value="hunting">Hunting</option>
          <option value="fishing">Fishing</option>
        </select>
        <Label htmlFor="rule-type">Rule type</Label>
        <select
          id="rule-type"
          name="rule_type"
          className="rounded-md border border-input px-2 py-2"
        >
          <option value="prohibition">Prohibition</option>
          <option value="permission">Permission</option>
          <option value="permit_requirement">Permit requirement</option>
        </select>
        <Label htmlFor="rule-effect">Effect</Label>
        <select
          id="rule-effect"
          name="effect"
          className="rounded-md border border-input px-2 py-2"
        >
          <option value="prohibit">Prohibit</option>
          <option value="allow">Allow</option>
          <option value="condition">Condition</option>
          <option value="require">Require</option>
          <option value="limit">Limit</option>
        </select>
        <Label htmlFor="rule-from">Effective from</Label>
        <Input
          id="rule-from"
          name="effective_from"
          type="datetime-local"
          required
        />
        <Label htmlFor="rule-summary">Public interpretation summary</Label>
        <Textarea id="rule-summary" name="interpretation_summary" required />
        <Label htmlFor="rule-provision">Primary citation provision ID</Label>
        <Input id="rule-provision" name="provision_id" required />
        <Label htmlFor="rule-excerpt">Short quoted excerpt</Label>
        <Input id="rule-excerpt" name="quoted_excerpt" maxLength={400} />
        <Button type="submit">Create draft</Button>
      </form>
    </div>
  );
}

export function AdminLegalConflictsPage() {
  const [rows, setRows] = useState<Record<string, unknown>[]>([]);
  const [detections, setDetections] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);

  async function reload() {
    const [conflicts, changes] = await Promise.all([
      fetchLegalConflicts(),
      fetchChangeDetections(),
    ]);
    setRows(conflicts.data);
    setDetections(changes.data);
  }

  useEffect(() => {
    void Promise.all([fetchLegalConflicts(), fetchChangeDetections()])
      .then(([conflicts, changes]) => {
        setRows(conflicts.data);
        setDetections(changes.data);
      })
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load conflicts.",
        ),
      );
  }, []);

  return (
    <div className="space-y-8">
      <AdminPageHeader
        title="Conflicts and source changes"
        description="Unresolved high-severity conflicts block unsafe publication. Source changes never auto-publish."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      <section>
        <h2 className="text-lg font-medium">Rule conflicts</h2>
        {rows.length === 0 ? <p>No open conflicts.</p> : null}
        <ul className="mt-3 space-y-3">
          {rows.map((row) => (
            <li
              key={String(row.id)}
              className="rounded-xl border border-border p-4"
            >
              <p>
                {String(row.type)} · {String(row.severity)} ·{" "}
                {String(row.status)}
              </p>
              <Button
                className="mt-2"
                size="sm"
                onClick={() =>
                  resolveLegalConflict(
                    String(row.id),
                    "Reviewed and resolved in admin workspace.",
                  )
                    .then(reload)
                    .catch((cause) =>
                      setError(
                        cause instanceof ApiClientError
                          ? cause.message
                          : "Resolve failed",
                      ),
                    )
                }
              >
                Resolve
              </Button>
            </li>
          ))}
        </ul>
      </section>
      <section>
        <h2 className="text-lg font-medium">Detected source changes</h2>
        {detections.length === 0 ? <p>No detections.</p> : null}
        <ul className="mt-3 space-y-3">
          {detections.map((row) => (
            <li
              key={String(row.id)}
              className="rounded-xl border border-border p-4"
            >
              <p>
                {String(row.signal)} · {String(row.status)}
              </p>
              <Button
                className="mt-2"
                size="sm"
                variant="outline"
                onClick={() =>
                  dismissChangeDetection(
                    String(row.id),
                    "Marked as false positive. Published rules unchanged.",
                  )
                    .then(reload)
                    .catch((cause) =>
                      setError(
                        cause instanceof ApiClientError
                          ? cause.message
                          : "Dismiss failed",
                      ),
                    )
                }
              >
                Dismiss as false positive
              </Button>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}

export function AdminLegalEvaluatePage() {
  const [result, setResult] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      const data = await previewLegalEvaluation({
        activity_type: form.get("activity_type"),
        jurisdiction_code: "GE",
        occurred_at: form.get("occurred_at"),
        species_id: form.get("species_id") || null,
      });
      setResult(data);
      setError(null);
    } catch (cause) {
      setError(
        cause instanceof ApiClientError ? cause.message : "Evaluation failed.",
      );
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Evaluation preview"
        description="Preview only. This is not legal advice. Only published, effective rules are evaluated."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      <form className="grid max-w-xl gap-3" onSubmit={onSubmit}>
        <Label htmlFor="eval-activity">Activity</Label>
        <select
          id="eval-activity"
          name="activity_type"
          className="rounded-md border border-input px-2 py-2"
        >
          <option value="hunting">Hunting</option>
          <option value="fishing">Fishing</option>
        </select>
        <Label htmlFor="eval-at">Occurred at</Label>
        <Input id="eval-at" name="occurred_at" type="datetime-local" required />
        <Label htmlFor="eval-species">Species public ID (optional)</Label>
        <Input id="eval-species" name="species_id" />
        <Button type="submit">Evaluate</Button>
      </form>
      {result ? (
        <section
          className="rounded-xl border border-border p-4"
          aria-live="polite"
        >
          <p className="font-medium">Outcome: {String(result.outcome)}</p>
          <p className="mt-2 text-sm">{String(result.summary)}</p>
          <p className="mt-2 text-xs text-muted-foreground">
            {String(result.disclaimer)}
          </p>
        </section>
      ) : null}
      <p>
        <Link className="underline" href="/admin/legal">
          Back to dashboard
        </Link>
      </p>
    </div>
  );
}
