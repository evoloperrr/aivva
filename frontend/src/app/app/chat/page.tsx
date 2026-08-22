"use client";

import { OwnerChat } from "@/components/aivva/OwnerChat";
import { Portrait } from "@/components/brand/Portrait";
import { useAivvaLive } from "@/lib/useAivva";

export default function ChatPage() {
  const { current, loading } = useAivvaLive();

  if (loading) return <p className="text-sm text-muted-foreground">Opening a line…</p>;
  if (!current) return <p className="text-sm text-muted-foreground">Create an AIVVA before you talk.</p>;

  return (
    <div className="mx-auto max-w-2xl space-y-4">
      <div className="flex items-center gap-3">
        <Portrait name={current.name} seed={current.profile?.portrait_seed} size={52} />
        <div>
          <p className="text-xs uppercase tracking-[0.22em] text-teal">Owner line</p>
          <h1 className="font-heading text-4xl">{current.name}</h1>
          <p className="text-sm text-muted-foreground">
            {current.status_label} · {current.location?.name ?? "somewhere in the city"}
          </p>
        </div>
      </div>
      <div className="rounded-3xl border border-white/10 bg-card/70 p-5">
        <OwnerChat aivvaId={current.id} name={current.name} />
      </div>
    </div>
  );
}
