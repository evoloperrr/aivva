"use client";

import { useEffect, useMemo, useState } from "react";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { EmptyState } from "@/components/chrome/PageStates";
import { PageHeader } from "@/components/chrome/PageHeader";
import { aivvas, type MemoryRecord, type WorkRecord } from "@/lib/api";
import { MEMORY_CATEGORIES, memoryCategoryLabel } from "@/lib/copy";
import { formatClock } from "@/lib/format";
import { cn } from "@/lib/utils";

export default function MemoryPage() {
  return (
    <AivvaGate loadingLabel="Opening memory…">
      {({ current }) => {
        if (!current) return null;
        return <MemoryBody aivvaId={current.id} aivvaName={current.name} />;
      }}
    </AivvaGate>
  );
}

function MemoryBody({ aivvaId, aivvaName }: { aivvaId: string; aivvaName: string }) {
  const [memories, setMemories] = useState<MemoryRecord[]>([]);
  const [works, setWorks] = useState<WorkRecord[]>([]);
  const [category, setCategory] = useState<(typeof MEMORY_CATEGORIES)[number]>("ALL");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([aivvas.memories(aivvaId), aivvas.works(aivvaId)])
      .then(([m, w]) => {
        setMemories(m.data);
        setWorks(w.data);
        setError(null);
      })
      .catch((err: Error) => setError(err.message));
  }, [aivvaId]);

  const visible = useMemo(
    () => (category === "ALL" ? memories : memories.filter((item) => item.category === category)),
    [memories, category],
  );

  return (
    <div className="space-y-6">
      <PageHeader
        kicker="Private to this owner"
        title={`What ${aivvaName} keeps`}
        description="Only this signed-in owner’s AIVVA memories. Raw prompts and other AIVVA memories are not requested or shown."
      />
      {error && <p className="text-sm text-destructive">{error}</p>}
      <div className="flex flex-wrap gap-2">
        {MEMORY_CATEGORIES.map((item) => (
          <button
            key={item}
            type="button"
            onClick={() => setCategory(item)}
            className={cn(
              "rounded-full px-3 py-1 text-xs uppercase tracking-[0.14em]",
              category === item ? "bg-teal text-ink" : "bg-white/5 text-muted-foreground",
            )}
          >
            {item === "ALL" ? "All" : memoryCategoryLabel(item)}
          </button>
        ))}
      </div>
      <div className="grid gap-8 lg:grid-cols-2">
        <section>
          {visible.length === 0 ? (
            <EmptyState
              title="No memories in this category"
              body={`${aivvaName} has not stored an owner-visible memory here yet.`}
            />
          ) : (
            <ul className="space-y-3">
              {visible.map((memory) => (
                <li key={memory.id} className="glass-panel rounded-3xl p-4">
                  <p className="text-xs uppercase tracking-wider text-amber">{memoryCategoryLabel(memory.category)}</p>
                  <p className="mt-2 text-sm leading-6">{memory.content}</p>
                  <p className="mt-2 text-[11px] text-muted-foreground">{formatClock(memory.created_at)}</p>
                </li>
              ))}
            </ul>
          )}
        </section>
        <section>
          <h2 className="font-heading text-3xl">Original work</h2>
          <ul className="mt-6 space-y-3">
            {works.length === 0 && (
              <li className="text-sm text-muted-foreground">Nothing created yet.</li>
            )}
            {works.map((work) => (
              <li key={work.id} className="glass-panel rounded-3xl p-4">
                <p className="font-medium">{work.title}</p>
                <p className="mt-1 text-xs text-muted-foreground">
                  {work.kind} · {work.tool_or_model}
                </p>
                <p className="mt-3 text-sm text-muted-foreground">
                  {typeof work.body.motif === "string"
                    ? work.body.motif
                    : typeof work.body.concept === "string"
                      ? work.body.concept
                      : "Work body is stored, but has no public motif field."}
                </p>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </div>
  );
}
