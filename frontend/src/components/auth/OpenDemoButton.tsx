"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { auth } from "@/lib/api";
import { useAuth } from "@/lib/auth";

export const DEMO_OWNER = {
  email: "kael@example.com",
  password: "password123",
};

export function OpenDemoButton({
  variant = "outline",
  size = "lg",
  className,
}: {
  variant?: "outline" | "default" | "secondary";
  size?: "lg" | "default";
  className?: string;
}) {
  const router = useRouter();
  const { setSession } = useAuth();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <div>
      <Button
        type="button"
        variant={variant}
        size={size}
        className={className}
        disabled={pending}
        onClick={async () => {
          setPending(true);
          setError(null);
          try {
            const res = await auth.login(DEMO_OWNER);
            setSession(res.token, res.user);
            router.push("/app");
          } catch (err) {
            setError(err instanceof Error ? err.message : "Could not open the demo.");
          } finally {
            setPending(false);
          }
        }}
      >
        {pending ? "Opening…" : "See LUNA in the city"}
      </Button>
      {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
    </div>
  );
}
