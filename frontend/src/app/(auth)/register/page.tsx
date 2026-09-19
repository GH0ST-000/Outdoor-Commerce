import { AuthGuard } from "@/features/auth/components/AuthGuard";
import { RegisterForm } from "@/features/auth/components/RegisterForm";

export default function RegisterPage() {
  return (
    <AuthGuard mode="guest-only">
      <RegisterForm />
    </AuthGuard>
  );
}
