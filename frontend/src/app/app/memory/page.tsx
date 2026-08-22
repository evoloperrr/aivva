"use client";

import { useEffect, useState } from "react";
import { aivvas, type MemoryRecord, type WorkRecord } from "@/lib/api";
import { useAivvaLive } from "@/lib/useAivva";

export default function MemoryPage() {
  const { current } = useAivvaLive();
  const [memories, setMemories] = useState<MemoryRecord[]>([]);
  const [works, setWorks] = useState<WorkRecord[]>([]);

  useEffect(() => {
    if (!current) return;
    Promise.all([aivvas.memories(current.id), aivvas.works(current.id)]).then(([m, w]) => {
      setMemories(m.data);
      setWorks(w.data);
    });
  }, [current?.id]);

  if (!current) return <p className="text-sm text-muted-foreground">No memories until an AIVVA exists.</p>;

  return (
    <div className="grid gap-8 lg:grid-cols-2">
      <section>
        <p className="text-xs uppercase tracking-[0.22em] text-teal">Memory</p>
        <h1 className="font-heading text-4xl">What {current.name} keeps</h1>
        <ul className="mt-6 space-y-3">
          {memories.length === 0 && <li className="text-sm text-muted-foreground">No memories stored yet.</li>}
          {memories.map((memory) => (
            <li key={memory.id} className="rounded-2xl border border-white/10 bg-card/70 p-4">
              <p className="text-xs uppercase tracking-wider text-amber">{memory.category}</p>
              <p className="mt-2 text-sm leading-6">{memory.content}</p>
            </li>
          ))}
        </ul>
      </section>
      <section>
        <p className="text-xs uppercase tracking-[0.22em] text-violet">Creations</p>
        <h2 className="font-heading text-4xl">Original work</h2>
        <ul className="mt-6 space-y-3">
          {works.length === 0 && <li className="text-sm text-muted-foreground">Nothing created yet.</li>}
          {works.map((work) => (
            <li key={work.id} className="rounded-2xl border border-white/10 bg-card/70 p-4">
              <p className="font-medium">{work.title}</p>
              <p className="mt-1 text-xs text-muted-foreground">
                {work.kind} · {work.tool_or_model}
              </p>
              <p className="mt-3 text-sm text-muted-foreground">
                {typeof work.body.motif === "string" ? work.body.motif : JSON.stringify(work.body)}
              </p>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
