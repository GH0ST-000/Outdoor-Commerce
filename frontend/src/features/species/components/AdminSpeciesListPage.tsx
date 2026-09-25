"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { AdminPageHeader } from "@/features/admin/ui/AdminPageHeader";
import {
  fetchAdminSpecies,
  type AdminSpeciesListItem,
} from "@/features/species/api/admin-species-api";
import { Button } from "@/components/ui/button";
import { ApiClientError } from "@/lib/api-client";

export function AdminSpeciesListPage() {
  const [items, setItems] = useState<AdminSpeciesListItem[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchAdminSpecies()
      .then((payload) => setItems(payload.data))
      .catch((caught: unknown) => {
        setError(caught instanceof ApiClientError ? caught.code : "ERROR");
      });
  }, []);

  return (
    <div className="space-y-6">
      <AdminPageHeader
        title="Species"
        description="Biological records only. Publishing does not grant hunting permission."
        actions={
          <Button asChild>
            <Link href="/admin/species/new">New species</Link>
          </Button>
        }
      />
      {error ? <p role="alert">{error}</p> : null}
      <table className="w-full text-sm">
        <thead>
          <tr className="text-left">
            <th>Scientific name</th>
            <th>Common name</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {items.map((item) => (
            <tr key={item.id} className="border-t border-border">
              <td className="py-3 italic">{item.scientific_name}</td>
              <td>{item.common_name}</td>
              <td>{item.publication_status}</td>
              <td>
                <Link href={`/admin/species/${item.id}`} className="underline">
                  Edit
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
