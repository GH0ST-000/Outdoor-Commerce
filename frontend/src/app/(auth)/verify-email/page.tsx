import { VerifyEmailContent } from "@/features/auth/components/VerifyEmailContent";

type VerifyEmailPageProps = {
  searchParams: Promise<{ status?: string }>;
};

export default async function VerifyEmailPage({
  searchParams,
}: VerifyEmailPageProps) {
  const params = await searchParams;
  const status = params.status ?? "unknown";

  return <VerifyEmailContent status={status} />;
}
