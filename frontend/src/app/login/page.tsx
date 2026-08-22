"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { OpenDemoButton, DEMO_OWNER } from "@/components/auth/OpenDemoButton";
import { Mark } from "@/components/brand/Mark";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { auth } from "@/lib/api";
import { useAuth } from "@/lib/auth";

export default function LoginPage() {
  const router = useRouter();
  const { setSession } = useAuth();
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  return (
    <div className="grid min-h-screen place-items-center px-4">
      <form
        className="w-full max-w-md rounded-3xl border border-white/10 bg-card/80 p-8"
        onSubmit={async (event) => {
          event.preventDefault();
          setPending(true);
          setError(null);
          const form = new FormData(event.currentTarget);
          try {
            const res = await auth.login({
              email: String(form.get("email")),
              password: String(form.get("password")),
            });
            setSession(res.token, res.user);
            router.push("/app");
          } catch (err) {
            setError(err instanceof Error ? err.message : "Could not sign in.");
          } finally {
            setPending(false);
          }
        }}
      >
        <Mark className="text-sm text-teal" />
        <h1 className="mt-3 font-heading text-3xl">Return to the city</h1>
        <p className="mt-2 text-sm text-muted-foreground">Your AIVVA may already have been working.</p>
        <OpenDemoButton className="mt-6 w-full" variant="default" size="lg" />
        <p className="mt-4 text-center text-xs text-muted-foreground">or sign in with any owner account</p>
        <div className="mt-4 space-y-4">
          <div className="space-y-2">
            <Label htmlFor="email">Email</Label>
            <Input id="email" name="email" type="email" required autoComplete="email" defaultValue={DEMO_OWNER.email} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              name="password"
              type="password"
              required
              autoComplete="current-password"
              defaultValue={DEMO_OWNER.password}
            />
          </div>
        </div>
        {error && <p className="mt-4 text-sm text-destructive">{error}</p>}
        <Button type="submit" className="mt-6 w-full" variant="outline" disabled={pending}>
          {pending ? "Opening…" : "Sign in"}
        </Button>
        <p className="mt-4 text-center text-sm text-muted-foreground">
          New here?{" "}
          <Link href="/register" className="text-teal">
            Create an AIVVA
          </Link>
        </p>
      </form>
    </div>
  );
}
