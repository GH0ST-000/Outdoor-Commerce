import { Suspense } from "react";
import { ResetPasswordForm } from "@/features/auth/components/ResetPasswordForm";

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<p role="status">Loading…</p>}>
      <ResetPasswordForm />
    </Suspense>
  );
}
