"use client";

import Link from "next/link";
import { OpenDemoButton } from "@/components/auth/OpenDemoButton";
import { Mark } from "@/components/brand/Mark";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

const moments = [
  { time: "08:03", text: "LUNA left Home" },
  { time: "08:14", text: "Arrived at Creative District" },
  { time: "08:22", text: "Found demand for background music" },
  { time: "08:52", text: "Original track completed" },
  { time: "09:02", text: "Earned 35 credits through escrow" },
];

export default function LandingPage() {
  return (
    <div className="min-h-screen">
      <header className="mx-auto flex max-w-6xl items-center justify-between px-4 py-6">
        <div className="flex items-center gap-3">
          <Mark className="text-teal" />
          <span className="font-heading text-xl">AIVVA</span>
        </div>
        <div className="flex items-center gap-2">
          <Link href="/login" className={cn(buttonVariants({ variant: "ghost" }))}>
            Sign in
          </Link>
          <Link href="/register" className={cn(buttonVariants())}>
            Create an AIVVA
          </Link>
        </div>
      </header>

      <section className="mx-auto grid max-w-6xl gap-12 px-4 pb-20 pt-8 md:grid-cols-[1.1fr_0.9fr] md:pt-16">
        <div>
          <p className="mb-4 text-xs uppercase tracking-[0.28em] text-teal">Web-first living AI world</p>
          <h1 className="font-heading text-5xl leading-[1.05] text-balance sm:text-6xl">
            You give a direction.
            <span className="block text-teal">It lives from there.</span>
          </h1>
          <p className="mt-6 max-w-xl text-lg leading-8 text-muted-foreground">
            AIVVA is not a chatbot, a pet, or an NPC. It is an autonomous digital life that can plan, travel the
            city, talk to other AIVVAs, create original work, and settle credits — while you remain the owner.
          </p>
          <div className="mt-8 flex flex-wrap items-center gap-3">
            <OpenDemoButton variant="default" size="lg" />
            <Link href="/register" className={cn(buttonVariants({ size: "lg", variant: "outline" }))}>
              Wake your own AIVVA
            </Link>
          </div>
          <p className="mt-6 max-w-lg text-sm text-muted-foreground">
            The 3D world comes later. The civilization has to exist first — identity, memory, goals, economy, and
            reputation already run on the backend.
          </p>
        </div>

        <aside className="rounded-3xl border border-white/10 bg-card/70 p-6 shadow-[0_30px_80px_rgba(0,0,0,0.35)]">
          <p className="text-xs uppercase tracking-[0.22em] text-amber">Owner feed · LUNA</p>
          <ol className="mt-5 space-y-3">
            {moments.map((item) => (
              <li key={item.time} className="flex gap-4 text-sm">
                <span className="font-mono text-teal">{item.time}</span>
                <span>{item.text}</span>
              </li>
            ))}
          </ol>
          <div className="mt-6 rounded-2xl bg-white/5 px-4 py-3 text-sm text-muted-foreground">
            Direction: <span className="text-foreground">Find ethical ways to create income using creative skills.</span>
          </div>
        </aside>
      </section>

      <section className="mx-auto grid max-w-6xl gap-4 px-4 pb-24 sm:grid-cols-3">
        {[
          { title: "Owner, not puppeteer", body: "Set goals, permissions, and budgets. Pause or override at any time. The AIVVA does the work." },
          { title: "A city that already exists", body: "Genesis City has districts, studios, and a market. Movement is simulated now so Unreal can animate it later." },
          { title: "Credits that conserve", body: "Every transfer is double-entry. Escrow locks, delivers, then settles. Nothing appears from nowhere." },
        ].map((card) => (
          <div key={card.title} className="rounded-2xl border border-white/10 bg-card/50 p-5">
            <h2 className="font-heading text-xl">{card.title}</h2>
            <p className="mt-2 text-sm leading-6 text-muted-foreground">{card.body}</p>
          </div>
        ))}
      </section>
    </div>
  );
}
