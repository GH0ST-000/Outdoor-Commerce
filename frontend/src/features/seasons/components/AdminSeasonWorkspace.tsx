"use client";

import { FormEvent, useEffect, useState } from "react";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import { LegalWorkspaceNav } from "@/features/legal/components/LegalWorkspaceNav";
import {
  createSeason,
  createSeasonOverride,
  fetchGenerationRuns,
  fetchSeasonCoverage,
  fetchSeasonDashboard,
  fetchSeasonOverrides,
  fetchSeasons,
  transitionSeason,
} from "@/features/seasons/api/admin-seasons-api";
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

export function AdminSeasonDashboardPage() {
  const [data, setData] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSeasonDashboard()
      .then(setData)
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load season dashboard.",
        ),
      );
  }, []);

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Season calendar"
        description="Source-backed hunting and fishing seasons. Occurrences are projections. This is not legal advice."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {data ? (
        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {(
            [
              ["Published hunting", data.published_hunting],
              ["Published fishing", data.published_fishing],
              ["Drafts", data.drafts],
              ["Awaiting review", data.awaiting_review],
              ["Opening soon", data.opening_soon],
              ["Closing soon", data.closing_soon],
              ["Failed generation runs", data.failed_generation_runs],
              ["Open calendar conflicts", data.open_calendar_conflicts],
            ] as [string, unknown][]
          ).map(([label, value]) => (
            <div key={label} className="rounded-xl border border-border p-4">
              <dt className="text-sm text-muted-foreground">{label}</dt>
              <dd className="mt-1 text-2xl font-semibold">
                {String(value ?? 0)}
              </dd>
            </div>
          ))}
        </dl>
      ) : (
        statusText("Loading season dashboard…")
      )}
    </div>
  );
}

export function AdminSeasonDefinitionsPage() {
  const [items, setItems] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [form, setForm] = useState({
    legal_rule_id: "",
    species_id: "",
    activity_type: "hunting",
    season_type: "opening",
    schedule_type: "annual_recurring",
    start_month: "9",
    start_day: "1",
    end_month: "11",
    end_day: "30",
    start_date: "",
    end_date: "",
    jurisdiction_code: "GE",
  });

  function reload() {
    fetchSeasons()
      .then((response) => setItems(response.data))
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load seasons.",
        ),
      );
  }

  useEffect(() => {
    reload();
  }, []);

  async function onCreate(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      const payload: Record<string, unknown> = {
        legal_rule_id: form.legal_rule_id,
        species_id: form.species_id,
        activity_type: form.activity_type,
        season_type: form.season_type,
        schedule_type: form.schedule_type,
        jurisdiction_code: form.jurisdiction_code,
      };
      if (form.schedule_type === "annual_recurring") {
        payload.start_month = Number(form.start_month);
        payload.start_day = Number(form.start_day);
        payload.end_month = Number(form.end_month);
        payload.end_day = Number(form.end_day);
      } else {
        payload.start_date = form.start_date;
        payload.end_date = form.end_date;
      }
      await createSeason(payload);
      setStatus("Draft season created.");
      reload();
    } catch (cause) {
      setError(
        cause instanceof ApiClientError ? cause.message : "Create failed.",
      );
    }
  }

  async function act(
    id: string,
    action: Parameters<typeof transitionSeason>[1],
  ) {
    try {
      await transitionSeason(id, action, "Reviewed in admin workspace");
      setStatus(`${action} completed.`);
      reload();
    } catch (cause) {
      setError(
        cause instanceof ApiClientError ? cause.message : "Action failed.",
      );
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Season definitions"
        description="Create source-backed season schedules. Publication requires a published legal rule and approved citations."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {status ? statusText(status, "ok") : null}
      <form
        onSubmit={onCreate}
        className="grid gap-3 rounded-xl border border-border p-4 sm:grid-cols-2"
      >
        <div>
          <Label htmlFor="season-rule">Legal rule public ID</Label>
          <Input
            id="season-rule"
            required
            value={form.legal_rule_id}
            onChange={(e) =>
              setForm({ ...form, legal_rule_id: e.target.value })
            }
          />
        </div>
        <div>
          <Label htmlFor="season-species">Species public ID</Label>
          <Input
            id="season-species"
            required
            value={form.species_id}
            onChange={(e) => setForm({ ...form, species_id: e.target.value })}
          />
        </div>
        <div>
          <Label htmlFor="season-activity">Activity</Label>
          <select
            id="season-activity"
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={form.activity_type}
            onChange={(e) =>
              setForm({ ...form, activity_type: e.target.value })
            }
          >
            <option value="hunting">Hunting</option>
            <option value="fishing">Fishing</option>
          </select>
        </div>
        <div>
          <Label htmlFor="season-type">Season type</Label>
          <select
            id="season-type"
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={form.season_type}
            onChange={(e) => setForm({ ...form, season_type: e.target.value })}
          >
            <option value="opening">Opening</option>
            <option value="closure">Closure</option>
            <option value="special_opening">Special opening</option>
            <option value="special_closure">Special closure</option>
            <option value="restriction">Restriction</option>
          </select>
        </div>
        <div>
          <Label htmlFor="season-schedule">Schedule</Label>
          <select
            id="season-schedule"
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={form.schedule_type}
            onChange={(e) =>
              setForm({ ...form, schedule_type: e.target.value })
            }
          >
            <option value="annual_recurring">Annual recurring</option>
            <option value="fixed_range">Fixed range</option>
          </select>
        </div>
        {form.schedule_type === "annual_recurring" ? (
          <>
            <div>
              <Label htmlFor="start-md">Opening month/day</Label>
              <div className="flex gap-2">
                <Input
                  id="start-md"
                  value={form.start_month}
                  onChange={(e) =>
                    setForm({ ...form, start_month: e.target.value })
                  }
                />
                <Input
                  value={form.start_day}
                  onChange={(e) =>
                    setForm({ ...form, start_day: e.target.value })
                  }
                />
              </div>
            </div>
            <div>
              <Label htmlFor="end-md">Closing month/day</Label>
              <div className="flex gap-2">
                <Input
                  id="end-md"
                  value={form.end_month}
                  onChange={(e) =>
                    setForm({ ...form, end_month: e.target.value })
                  }
                />
                <Input
                  value={form.end_day}
                  onChange={(e) =>
                    setForm({ ...form, end_day: e.target.value })
                  }
                />
              </div>
            </div>
          </>
        ) : (
          <>
            <div>
              <Label htmlFor="start-date">Start date</Label>
              <Input
                id="start-date"
                type="date"
                value={form.start_date}
                onChange={(e) =>
                  setForm({ ...form, start_date: e.target.value })
                }
              />
            </div>
            <div>
              <Label htmlFor="end-date">End date</Label>
              <Input
                id="end-date"
                type="date"
                value={form.end_date}
                onChange={(e) => setForm({ ...form, end_date: e.target.value })}
              />
            </div>
          </>
        )}
        <div className="sm:col-span-2">
          <Button type="submit">Create draft</Button>
        </div>
      </form>
      <ul className="space-y-3">
        {items.map((item) => (
          <li
            key={String(item.id)}
            className="rounded-xl border border-border p-4"
          >
            <p className="font-medium">
              {String(
                (item.species as { scientific_name?: string } | undefined)
                  ?.scientific_name ?? item.id,
              )}{" "}
              · {String(item.activity_type)} · {String(item.status)}
            </p>
            <div className="mt-3 flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => act(String(item.id), "preview")}
              >
                Preview
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => act(String(item.id), "submit-review")}
              >
                Submit review
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => act(String(item.id), "approve")}
              >
                Approve
              </Button>
              <Button
                type="button"
                size="sm"
                onClick={() => act(String(item.id), "publish")}
              >
                Publish
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => act(String(item.id), "generate-occurrences")}
              >
                Generate
              </Button>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => act(String(item.id), "supersede")}
              >
                Supersede
              </Button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}

export function AdminSeasonCoveragePage() {
  const [data, setData] = useState<Record<string, unknown> | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSeasonCoverage()
      .then(setData)
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load coverage.",
        ),
      );
  }, []);

  const rows = (data?.rows as Record<string, unknown>[] | undefined) ?? [];

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Season coverage"
        description="Missing season records mean unknown, not closed. Do not treat empty cells as prohibition."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      {data ? (
        <p className="text-sm text-muted-foreground">
          With seasons: {String(data.species_with_seasons)} · Without seasons:{" "}
          {String(data.species_without_seasons)}
        </p>
      ) : null}
      <div className="overflow-x-auto">
        <table className="w-full min-w-[40rem] text-left text-sm">
          <thead>
            <tr>
              <th className="border-b p-2">Species</th>
              <th className="border-b p-2">Activity</th>
              <th className="border-b p-2">Verified season</th>
              <th className="border-b p-2">Missing data</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={String(row.species_id)}>
                <td className="border-b p-2">{String(row.scientific_name)}</td>
                <td className="border-b p-2">{String(row.activity_type)}</td>
                <td className="border-b p-2">
                  {row.has_verified_season ? "Yes" : "No"}
                </td>
                <td className="border-b p-2">
                  {row.missing_data ? "Unknown — not closed" : "Recorded"}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export function AdminSeasonOverridesPage() {
  const [items, setItems] = useState<Record<string, unknown>[]>([]);
  const [runs, setRuns] = useState<Record<string, unknown>[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [form, setForm] = useState({
    base_season_definition_id: "",
    legal_rule_id: "",
    override_type: "closure",
    start_date: "",
    end_date: "",
    reason: "",
    precedence: "100",
  });

  useEffect(() => {
    fetchSeasonOverrides()
      .then((response) => setItems(response.data))
      .catch((cause) =>
        setError(
          cause instanceof ApiClientError
            ? cause.message
            : "Unable to load overrides.",
        ),
      );
    fetchGenerationRuns()
      .then((response) => setRuns(response.data))
      .catch(() => undefined);
  }, []);

  async function onCreate(event: FormEvent) {
    event.preventDefault();
    try {
      await createSeasonOverride({
        ...form,
        precedence: Number(form.precedence),
      });
      const refreshed = await fetchSeasonOverrides();
      setItems(refreshed.data);
    } catch (cause) {
      setError(
        cause instanceof ApiClientError ? cause.message : "Create failed.",
      );
    }
  }

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Overrides and generation"
        description="Temporary closures and special openings require a source-backed rule and review. Occurrences are read-only projections."
      />
      <LegalWorkspaceNav />
      {error ? statusText(error, "error") : null}
      <form
        onSubmit={onCreate}
        className="grid gap-3 rounded-xl border border-border p-4 sm:grid-cols-2"
      >
        <div>
          <Label htmlFor="ov-base">Base season ID</Label>
          <Input
            id="ov-base"
            required
            value={form.base_season_definition_id}
            onChange={(e) =>
              setForm({ ...form, base_season_definition_id: e.target.value })
            }
          />
        </div>
        <div>
          <Label htmlFor="ov-rule">Legal rule ID</Label>
          <Input
            id="ov-rule"
            required
            value={form.legal_rule_id}
            onChange={(e) =>
              setForm({ ...form, legal_rule_id: e.target.value })
            }
          />
        </div>
        <div>
          <Label htmlFor="ov-type">Override type</Label>
          <select
            id="ov-type"
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
            value={form.override_type}
            onChange={(e) =>
              setForm({ ...form, override_type: e.target.value })
            }
          >
            <option value="closure">Closure</option>
            <option value="special_opening">Special opening</option>
            <option value="date_adjustment">Date adjustment</option>
          </select>
        </div>
        <div>
          <Label htmlFor="ov-reason">Reason</Label>
          <Input
            id="ov-reason"
            required
            value={form.reason}
            onChange={(e) => setForm({ ...form, reason: e.target.value })}
          />
        </div>
        <div>
          <Label htmlFor="ov-start">Start date</Label>
          <Input
            id="ov-start"
            type="date"
            required
            value={form.start_date}
            onChange={(e) => setForm({ ...form, start_date: e.target.value })}
          />
        </div>
        <div>
          <Label htmlFor="ov-end">End date</Label>
          <Input
            id="ov-end"
            type="date"
            required
            value={form.end_date}
            onChange={(e) => setForm({ ...form, end_date: e.target.value })}
          />
        </div>
        <div className="sm:col-span-2">
          <Button type="submit">Create override draft</Button>
        </div>
      </form>
      <ul className="space-y-2">
        {items.map((item) => (
          <li
            key={String(item.id)}
            className="rounded-md border border-border px-3 py-2 text-sm"
          >
            {String(item.override_type)} · {String(item.status)} ·{" "}
            {String(item.reason)}
          </li>
        ))}
      </ul>
      <section>
        <h2 className="text-lg font-semibold">Generation runs</h2>
        <ul className="mt-2 space-y-2">
          {runs.map((run) => (
            <li
              key={String(run.id)}
              className="rounded-md border border-border px-3 py-2 text-sm"
            >
              {String(run.status)} · {String(run.from_year)}–
              {String(run.through_year)} · failures {String(run.failures)}
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
