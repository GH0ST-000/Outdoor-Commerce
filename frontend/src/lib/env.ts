import { z } from "zod";

const serverSchema = z.object({
  NODE_ENV: z
    .enum(["development", "test", "production"])
    .default("development"),
  BACKEND_INTERNAL_URL: z.string().url().optional(),
});

const publicSchema = z.object({
  NEXT_PUBLIC_APP_URL: z.string().url().optional(),
  NEXT_PUBLIC_API_URL: z.string().url().optional(),
});

export type ServerEnv = z.infer<typeof serverSchema>;
export type PublicEnv = z.infer<typeof publicSchema>;

export function getServerEnv(): ServerEnv {
  return serverSchema.parse({
    NODE_ENV: process.env.NODE_ENV,
    BACKEND_INTERNAL_URL: process.env.BACKEND_INTERNAL_URL,
  });
}

export function getPublicEnv(): PublicEnv {
  return publicSchema.parse({
    NEXT_PUBLIC_APP_URL: process.env.NEXT_PUBLIC_APP_URL,
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
  });
}
