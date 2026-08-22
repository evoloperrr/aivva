"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Mark } from "@/components/brand/Mark";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { auth } from "@/lib/api";
import { useAuth } from "@/lib/auth";

export default function RegisterPage() {
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
            const res = await auth.register({
              name: String(form.get("name")),
              email: String(form.get("email")),
              password: String(form.get("password")),
              password_confirmation: String(form.get("password_confirmation")),
            });
            setSession(res.token, res.user);
            router.push("/app/create");
          } catch (err) {
            setError(err instanceof Error ? err.message : "Could not create account.");
          } finally {
            setPending(false);
          }
        }}
      >
        <Mark className="text-sm text-teal" />
        <h1 className="mt-3 font-heading text-3xl">Begin as an owner</h1>
        <p className="mt-2 text-sm text-muted-foreground">Then you will create the life that acts for you.</p>
        <div className="mt-6 space-y-4">
          <div className="space-y-2">
            <Label htmlFor="name">Your name</Label>
            <Input id="name" name="name" required autoComplete="name" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="email">Email</Label>
            <Input id="email" name="email" type="email" required autoComplete="email" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="password">Password</Label>
            <Input id="password" name="password" type="password" required minLength={8} autoComplete="new-password" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="password_confirmation">Confirm password</Label>
            <Input id="password_confirmation" name="password_confirmation" type="password" required minLength={8} />
          </div>
        </div>
        {error && <p className="mt-4 text-sm text-destructive">{error}</p>}
        <Button type="submit" className="mt-6 w-full" disabled={pending}>
          {pending ? "Creating…" : "Create account"}
        </Button>
        <p className="mt-4 text-center text-sm text-muted-foreground">
          Already an owner?{" "}
          <Link href="/login" className="text-teal">
            Sign in
          </Link>
        </p>
      </form>
    </div>
  );
}
