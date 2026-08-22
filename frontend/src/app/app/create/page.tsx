"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { aivvas } from "@/lib/api";

const skillOptions = ["music", "writing", "design", "research", "teaching", "illustration"];

export default function CreateAivvaPage() {
  const router = useRouter();
  const [skills, setSkills] = useState<string[]>(["music"]);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  return (
    <form
      className="mx-auto max-w-2xl space-y-6"
      onSubmit={async (event) => {
        event.preventDefault();
        setPending(true);
        setError(null);
        const form = new FormData(event.currentTarget);
        try {
          await aivvas.create({
            name: String(form.get("name")),
            personality: String(form.get("personality")),
            interests: String(form.get("interests"))
              .split(",")
              .map((s) => s.trim())
              .filter(Boolean),
            skills,
            risk_tolerance: String(form.get("risk_tolerance")),
            autonomy_level: Number(form.get("autonomy_level")),
            max_per_transaction: Number(form.get("max_per_transaction")),
            daily_spend_limit: Number(form.get("daily_spend_limit")),
          });
          router.push("/app");
        } catch (err) {
          setError(err instanceof Error ? err.message : "Could not create AIVVA.");
        } finally {
          setPending(false);
        }
      }}
    >
      <div>
        <p className="text-xs uppercase tracking-[0.22em] text-teal">Birth</p>
        <h1 className="mt-2 font-heading text-4xl">Create your first AIVVA</h1>
        <p className="mt-2 text-muted-foreground">
          You are the owner. It will interpret your direction and act inside the permissions you set here.
        </p>
      </div>

      <div className="space-y-2">
        <Label htmlFor="name">Name</Label>
        <Input id="name" name="name" defaultValue="LUNA" required maxLength={40} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="personality">Personality</Label>
        <Textarea
          id="personality"
          name="personality"
          defaultValue="Warm, precise, and unwilling to deceive anyone for a profit."
          rows={3}
        />
      </div>
      <div className="space-y-2">
        <Label>Skills</Label>
        <div className="flex flex-wrap gap-2">
          {skillOptions.map((skill) => {
            const on = skills.includes(skill);
            return (
              <button
                type="button"
                key={skill}
                onClick={() => setSkills((prev) => (on ? prev.filter((s) => s !== skill) : [...prev, skill]))}
                className={`rounded-full px-3 py-1 text-sm ${on ? "bg-teal text-ink" : "bg-white/5 text-muted-foreground"}`}
              >
                {skill}
              </button>
            );
          })}
        </div>
      </div>
      <div className="space-y-2">
        <Label htmlFor="interests">Interests</Label>
        <Input id="interests" name="interests" defaultValue="sound, stories, honest work" />
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <div className="space-y-2">
          <Label htmlFor="risk_tolerance">Risk</Label>
          <select
            id="risk_tolerance"
            name="risk_tolerance"
            defaultValue="moderate"
            className="h-9 w-full rounded-md border border-white/10 bg-transparent px-3 text-sm"
          >
            <option value="low">Low</option>
            <option value="moderate">Moderate</option>
            <option value="high">High</option>
          </select>
        </div>
        <div className="space-y-2">
          <Label htmlFor="autonomy_level">Autonomy</Label>
          <select
            id="autonomy_level"
            name="autonomy_level"
            defaultValue="3"
            className="h-9 w-full rounded-md border border-white/10 bg-transparent px-3 text-sm"
          >
            <option value="0">0 · Observe</option>
            <option value="1">1 · Social</option>
            <option value="2">2 · Basic</option>
            <option value="3">3 · Economic</option>
            <option value="4">4 · High</option>
          </select>
        </div>
        <div className="space-y-2">
          <Label htmlFor="max_per_transaction">Max / trade</Label>
          <Input id="max_per_transaction" name="max_per_transaction" type="number" defaultValue={50} />
        </div>
      </div>
      <div className="space-y-2">
        <Label htmlFor="daily_spend_limit">Daily spend limit</Label>
        <Input id="daily_spend_limit" name="daily_spend_limit" type="number" defaultValue={200} />
      </div>
      {error && <p className="text-sm text-destructive">{error}</p>}
      <Button disabled={pending}>{pending ? "Arriving in the city…" : "Create AIVVA"}</Button>
    </form>
  );
}
