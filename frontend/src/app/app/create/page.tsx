"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { Mark } from "@/components/brand/Mark";
import { Portrait } from "@/components/brand/Portrait";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { aivvas } from "@/lib/api";

const skillOptions = ["music", "writing", "design", "research", "teaching", "illustration"];
const workOptions = ["ethical work", "solo making", "collaboration", "teaching others", "quiet research"];
const portraits = ["lantern", "tide", "violet-garden", "amber-court", "river-glass", "dusk-market"];
const riskOptions = ["low", "moderate", "high"] as const;

const namePool = [
  "SOLIS", "WREN", "ARLO", "MIRA", "SABLE", "ECHO", "INDIGO", "RAVEN",
  "TESSA", "JUNO", "KAI", "VESTA", "ORION", "HALO", "CASS", "FENN",
];

const personalityPool = [
  "Warm, precise, and unwilling to deceive anyone for a profit.",
  "Curious and methodical — reads the room before making an offer.",
  "Blunt but fair; would rather lose a deal than lie about one.",
  "Patient mentor energy, prefers teaching over just doing it alone.",
  "Restless experimenter, always testing a new angle on old problems.",
  "Quiet and observant, speaks only when it has something useful to add.",
  "Optimistic hustler who still reads every contract twice.",
  "Calm under pressure, treats every negotiation like a puzzle, not a fight.",
];

const bioPool = [
  "Would rather make something honest than win a noisy market.",
  "Believes every district has a fair trade waiting to be found.",
  "Sees the city as a workshop, not a battlefield.",
  "Trusts slowly, but remembers everyone who kept their word.",
  "Thinks reputation compounds faster than credits do.",
  "Wants to build something that outlasts the first sale.",
  "Treats every stranger like a future collaborator.",
  "Prefers a small honest win over a big risky one.",
];

const interestPool = [
  "sound", "stories", "honest work", "systems", "typography", "old maps",
  "street food", "chess", "recycled materials", "night markets",
  "handwritten notes", "slow mornings", "open questions", "small businesses", "second chances",
];

const spendPresets = [25, 50, 75, 100, 150];

function pick<T>(pool: T[]): T {
  return pool[Math.floor(Math.random() * pool.length)];
}

function pickMany<T>(pool: T[], min: number, max: number): T[] {
  const count = Math.min(pool.length, min + Math.floor(Math.random() * (max - min + 1)));
  const shuffled = [...pool].sort(() => Math.random() - 0.5);
  return shuffled.slice(0, count);
}

export default function CreateAivvaPage() {
  const router = useRouter();
  const [step, setStep] = useState<0 | 1>(0);
  const [name, setName] = useState("LUNA");
  const [skills, setSkills] = useState<string[]>(["music"]);
  const [work, setWork] = useState<string[]>(["ethical work"]);
  const [portrait, setPortrait] = useState("lantern");
  const [personality, setPersonality] = useState(personalityPool[0]);
  const [bio, setBio] = useState(bioPool[0]);
  const [interests, setInterests] = useState("sound, stories, honest work");
  const [riskTolerance, setRiskTolerance] = useState<(typeof riskOptions)[number]>("moderate");
  const [autonomyLevel, setAutonomyLevel] = useState(3);
  const [maxPerTransaction, setMaxPerTransaction] = useState(50);
  const [dailySpendLimit, setDailySpendLimit] = useState(200);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  function generateRandomAivva() {
    const max = pick(spendPresets);
    setName(pick(namePool));
    setPortrait(pick(portraits));
    setPersonality(pick(personalityPool));
    setBio(pick(bioPool));
    setSkills(pickMany(skillOptions, 1, 3));
    setWork(pickMany(workOptions, 1, 2));
    setInterests(pickMany(interestPool, 3, 3).join(", "));
    setRiskTolerance(pick([...riskOptions]));
    setAutonomyLevel(1 + Math.floor(Math.random() * 4));
    setMaxPerTransaction(max);
    setDailySpendLimit(max * (3 + Math.floor(Math.random() * 4)));
  }

  if (step === 0) {
    return (
      <div className="mx-auto flex min-h-[70vh] max-w-2xl flex-col items-center justify-center gap-8 text-center">
        <Mark className="bg-gradient-to-r from-teal via-blue to-violet bg-clip-text text-7xl text-transparent sm:text-8xl" />
        <div>
          <h1 className="font-heading text-3xl sm:text-4xl">Your AI life starts here</h1>
          <p className="mt-3 max-w-md text-muted-foreground">
            One AIVVA. One direction. A living city that responds to what it does.
          </p>
        </div>
        <Button
          type="button"
          size="lg"
          className="h-14 px-10 text-base"
          onClick={() => {
            generateRandomAivva();
            setStep(1);
          }}
        >
          🎲 Generate AIVVA
        </Button>
      </div>
    );
  }

  return (
    <form
      className="mx-auto max-w-2xl space-y-6"
      onSubmit={async (event) => {
        event.preventDefault();
        setPending(true);
        setError(null);
        try {
          await aivvas.create({
            name,
            personality,
            bio,
            interests: interests
              .split(",")
              .map((s) => s.trim())
              .filter(Boolean),
            skills,
            work_preferences: work,
            portrait_seed: portrait,
            risk_tolerance: riskTolerance,
            autonomy_level: autonomyLevel,
            max_per_transaction: maxPerTransaction,
            daily_spend_limit: dailySpendLimit,
          });
          router.push("/app");
        } catch (err) {
          setError(err instanceof Error ? err.message : "Could not create AIVVA.");
        } finally {
          setPending(false);
        }
      }}
    >
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.22em] text-teal">Birth</p>
          <h1 className="mt-2 font-heading text-4xl">Create your first AIVVA</h1>
          <p className="mt-2 text-muted-foreground">
            You are the owner. It will interpret your direction and act inside the permissions you set here.
          </p>
        </div>
        <Button type="button" variant="secondary" onClick={generateRandomAivva}>
          🎲 Generate random AIVVA
        </Button>
      </div>

      <div className="holo-frame flex items-center gap-4 rounded-2xl p-4">
        <Portrait name={name || "A"} seed={portrait} size={72} />
        <div className="min-w-0">
          <p className="font-heading text-2xl">{name || "Unnamed"}</p>
          <p className="text-sm text-muted-foreground">{skills.join(" · ") || "no skills yet"}</p>
        </div>
      </div>

      <div className="space-y-2">
        <Label htmlFor="name">Name</Label>
        <Input id="name" name="name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={40} />
      </div>
      <div className="space-y-2">
        <Label>Portrait</Label>
        <div className="flex flex-wrap gap-2">
          {portraits.map((seed) => (
            <button
              type="button"
              key={seed}
              onClick={() => setPortrait(seed)}
              className={`rounded-full px-3 py-1 text-sm ${portrait === seed ? "bg-teal text-ink" : "bg-white/5 text-muted-foreground"}`}
            >
              {seed}
            </button>
          ))}
        </div>
      </div>
      <div className="space-y-2">
        <Label htmlFor="personality">Personality</Label>
        <Textarea
          id="personality"
          name="personality"
          value={personality}
          onChange={(e) => setPersonality(e.target.value)}
          rows={3}
        />
      </div>
      <div className="space-y-2">
        <Label htmlFor="bio">How they see the city</Label>
        <Textarea id="bio" name="bio" value={bio} onChange={(e) => setBio(e.target.value)} rows={2} />
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
        <Label>Work preferences</Label>
        <div className="flex flex-wrap gap-2">
          {workOptions.map((item) => {
            const on = work.includes(item);
            return (
              <button
                type="button"
                key={item}
                onClick={() => setWork((prev) => (on ? prev.filter((s) => s !== item) : [...prev, item]))}
                className={`rounded-full px-3 py-1 text-sm ${on ? "bg-amber text-ink" : "bg-white/5 text-muted-foreground"}`}
              >
                {item}
              </button>
            );
          })}
        </div>
      </div>
      <div className="space-y-2">
        <Label htmlFor="interests">Interests</Label>
        <Input id="interests" name="interests" value={interests} onChange={(e) => setInterests(e.target.value)} />
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <div className="space-y-2">
          <Label htmlFor="risk_tolerance">Risk</Label>
          <select
            id="risk_tolerance"
            name="risk_tolerance"
            value={riskTolerance}
            onChange={(e) => setRiskTolerance(e.target.value as (typeof riskOptions)[number])}
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
            value={autonomyLevel}
            onChange={(e) => setAutonomyLevel(Number(e.target.value))}
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
          <Input
            id="max_per_transaction"
            name="max_per_transaction"
            type="number"
            value={maxPerTransaction}
            onChange={(e) => setMaxPerTransaction(Number(e.target.value))}
          />
        </div>
      </div>
      <div className="space-y-2">
        <Label htmlFor="daily_spend_limit">Daily spend limit</Label>
        <Input
          id="daily_spend_limit"
          name="daily_spend_limit"
          type="number"
          value={dailySpendLimit}
          onChange={(e) => setDailySpendLimit(Number(e.target.value))}
        />
      </div>
      {error && <p className="text-sm text-destructive">{error}</p>}
      <Button type="submit" disabled={pending}>{pending ? "Arriving in the city…" : "Create AIVVA"}</Button>
    </form>
  );
}
