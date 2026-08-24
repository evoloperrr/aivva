"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Mark } from "@/components/brand/Mark";
import { Portrait } from "@/components/brand/Portrait";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { aivvas, world, type MapPlace } from "@/lib/api";

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

const STEP_LABELS = ["Generate", "Location", "Character", "Skills & Work", "Permissions", "Review"];

export default function CreateAivvaPage() {
  const router = useRouter();
  const [step, setStep] = useState(0);
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
  const [locations, setLocations] = useState<MapPlace[]>([]);
  const [locationsLoading, setLocationsLoading] = useState(true);
  const [homeLocationId, setHomeLocationId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    world
      .locations()
      .then((res) => setLocations(res.data))
      .catch(() => setLocations([]))
      .finally(() => setLocationsLoading(false));
  }, []);

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
    if (locations.length > 0) {
      setHomeLocationId(pick(locations).id);
    }
  }

  async function handleCreate() {
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
        home_location_id: homeLocationId ?? undefined,
      });
      router.push("/app");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not create AIVVA.");
    } finally {
      setPending(false);
    }
  }

  const selectedLocation = locations.find((l) => l.id === homeLocationId) ?? null;

  // Step 0 — Generate
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

  const totalSteps = STEP_LABELS.length - 1; // excludes the generate step
  const stepIndex = step; // 1..5

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="space-y-2">
        <p className="text-xs uppercase tracking-[0.22em] text-teal">
          Step {stepIndex} of {totalSteps} · {STEP_LABELS[step]}
        </p>
        <div className="flex gap-1.5">
          {STEP_LABELS.slice(1).map((label, i) => (
            <div
              key={label}
              className={`h-1.5 flex-1 rounded-full ${i + 1 <= stepIndex ? "bg-teal" : "bg-white/10"}`}
            />
          ))}
        </div>
      </div>

      <div className="holo-frame flex items-center gap-4 rounded-2xl p-4">
        <Portrait name={name || "A"} seed={portrait} size={72} />
        <div className="min-w-0">
          <p className="font-heading text-2xl">{name || "Unnamed"}</p>
          <p className="text-sm text-muted-foreground">{skills.join(" · ") || "no skills yet"}</p>
        </div>
      </div>

      {step === 1 && (
        <div className="space-y-4">
          <div>
            <h2 className="font-heading text-2xl">Where does {name || "your AIVVA"} start?</h2>
            <p className="mt-1 text-sm text-muted-foreground">Its home in Genesis City — it can always travel from here.</p>
          </div>
          {locationsLoading ? (
            <p className="text-sm text-muted-foreground">Loading Genesis City locations…</p>
          ) : locations.length === 0 ? (
            <p className="text-sm text-muted-foreground">Could not load locations. A default home will be used.</p>
          ) : (
            <div className="grid gap-3 sm:grid-cols-2">
              {locations.map((loc) => {
                const on = homeLocationId === loc.id;
                return (
                  <button
                    type="button"
                    key={loc.id}
                    onClick={() => setHomeLocationId(loc.id)}
                    className={`rounded-xl border p-3 text-left transition-colors ${
                      on ? "border-teal bg-teal/10" : "border-white/10 bg-white/5 hover:border-white/20"
                    }`}
                  >
                    <p className="text-xs uppercase tracking-wide" style={{ color: loc.district.color }}>
                      {loc.district.name}
                    </p>
                    <p className="font-heading text-lg">{loc.name}</p>
                    {loc.description && <p className="mt-1 text-xs text-muted-foreground">{loc.description}</p>}
                  </button>
                );
              })}
            </div>
          )}
        </div>
      )}

      {step === 2 && (
        <div className="space-y-4">
          <h2 className="font-heading text-2xl">Who are they?</h2>
          <div className="space-y-2">
            <Label htmlFor="name">Name</Label>
            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required maxLength={40} />
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
            <Textarea id="personality" value={personality} onChange={(e) => setPersonality(e.target.value)} rows={3} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="bio">How they see the city</Label>
            <Textarea id="bio" value={bio} onChange={(e) => setBio(e.target.value)} rows={2} />
          </div>
          <div className="space-y-2">
            <Label htmlFor="interests">Interests</Label>
            <Input id="interests" value={interests} onChange={(e) => setInterests(e.target.value)} />
          </div>
        </div>
      )}

      {step === 3 && (
        <div className="space-y-4">
          <h2 className="font-heading text-2xl">What can they do?</h2>
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
        </div>
      )}

      {step === 4 && (
        <div className="space-y-4">
          <h2 className="font-heading text-2xl">What can they risk?</h2>
          <div className="grid gap-4 sm:grid-cols-3">
            <div className="space-y-2">
              <Label htmlFor="risk_tolerance">Risk</Label>
              <select
                id="risk_tolerance"
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
              type="number"
              value={dailySpendLimit}
              onChange={(e) => setDailySpendLimit(Number(e.target.value))}
            />
          </div>
        </div>
      )}

      {step === 5 && (
        <div className="space-y-4">
          <h2 className="font-heading text-2xl">Ready to arrive?</h2>
          <div className="space-y-2 rounded-xl border border-white/10 bg-white/5 p-4 text-sm">
            <p><span className="text-muted-foreground">Home:</span> {selectedLocation ? `${selectedLocation.name} (${selectedLocation.district.name})` : "Default birthplace"}</p>
            <p><span className="text-muted-foreground">Personality:</span> {personality}</p>
            <p><span className="text-muted-foreground">Skills:</span> {skills.join(", ") || "none"}</p>
            <p><span className="text-muted-foreground">Work preferences:</span> {work.join(", ") || "none"}</p>
            <p><span className="text-muted-foreground">Interests:</span> {interests}</p>
            <p><span className="text-muted-foreground">Risk / Autonomy:</span> {riskTolerance} / level {autonomyLevel}</p>
            <p><span className="text-muted-foreground">Budget:</span> {maxPerTransaction} per trade, {dailySpendLimit} daily</p>
          </div>
          {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
      )}

      <div className="flex items-center justify-between pt-2">
        <Button type="button" variant="ghost" onClick={() => setStep((s) => Math.max(0, s - 1))}>
          ← Back
        </Button>
        {step < 5 ? (
          <Button type="button" onClick={() => setStep((s) => s + 1)}>
            Next →
          </Button>
        ) : (
          <Button type="button" onClick={handleCreate} disabled={pending}>
            {pending ? "Arriving in the city…" : "Create AIVVA"}
          </Button>
        )}
      </div>
    </div>
  );
}
