import { Suspense } from "react";
import { AuthGuard } from "@/features/auth/components/AuthGuard";
import { LoginForm } from "@/features/auth/components/LoginForm";

export default function LoginPage() {
  return (
    <AuthGuard mode="guest-only">
      <Suspense fallback={<p role="status">Loading…</p>}>
        <LoginForm />
      </Suspense>
    </AuthGuard>
  );
}
