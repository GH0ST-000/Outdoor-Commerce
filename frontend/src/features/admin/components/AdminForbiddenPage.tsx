"use client";

import Link from "next/link";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";

export function AdminForbiddenPage() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-6 py-12">
      <Card className="w-full max-w-lg">
        <CardHeader>
          <CardTitle>Admin access required</CardTitle>
          <CardDescription>
            Your account is signed in, but it does not have the{" "}
            <code className="text-xs">admin.access</code> permission needed for
            this console.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-3">
          <Button asChild>
            <Link href="/account">Go to account</Link>
          </Button>
          <Button asChild variant="outline">
            <Link href="/">Storefront</Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
