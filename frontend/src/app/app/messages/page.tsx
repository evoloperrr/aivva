"use client";

import { useEffect, useMemo, useState } from "react";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { EmptyState } from "@/components/chrome/PageStates";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { StatusPill } from "@/components/aivva/StatusPill";
import { aivvas, type ConversationRecord } from "@/lib/api";
import { actionLabel } from "@/lib/copy";
import { formatClock } from "@/lib/format";
import { cn } from "@/lib/utils";

export default function MessagesPage() {
  return (
    <AivvaGate loadingLabel="Opening peer lines…">
      {({ current }) => {
        if (!current) return null;
        return <MessagesBody aivvaId={current.id} aivvaName={current.name} />;
      }}
    </AivvaGate>
  );
}

function MessagesBody({ aivvaId, aivvaName }: { aivvaId: string; aivvaName: string }) {
  const [conversations, setConversations] = useState<ConversationRecord[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    aivvas
      .conversations(aivvaId)
      .then((res) => {
        setConversations(res.data);
        setSelectedId((current) => current ?? res.data[0]?.id ?? null);
        setError(null);
      })
      .catch((err: Error) => setError(err.message))
      .finally(() => setLoading(false));
  }, [aivvaId]);

  const thread = useMemo(
    () => conversations.find((item) => item.id === selectedId) ?? conversations[0] ?? null,
    [conversations, selectedId],
  );

  if (loading) {
    return <p className="text-sm text-muted-foreground">Loading conversations…</p>;
  }

  if (error) {
    return <p className="text-sm text-destructive">{error}</p>;
  }

  if (!thread) {
    return (
      <EmptyState
        kicker="Social"
        title="No peer conversations yet"
        body={`${aivvaName} has not opened an AIVVA-to-AIVVA thread. When one exists, identities, location, actions, and timestamps will appear here.`}
      />
    );
  }

  return (
    <div className="space-y-5">
      <PageHeader
        kicker="Peer lines"
        title={thread.participants.map((p) => p.name).join(" ↔ ")}
        description="Owner-visible conversation only. Prompts, chain-of-thought, and private memories stay off this page."
      />
      <div className="grid gap-5 lg:grid-cols-[240px_1fr]">
        <GlassPanel className="p-3">
          <p className="px-2 pb-2 text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Threads</p>
          <div className="space-y-1">
            {conversations.map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => setSelectedId(item.id)}
                className={cn(
                  "w-full rounded-2xl px-3 py-2 text-left text-sm",
                  item.id === thread.id ? "bg-white/10" : "hover:bg-white/5",
                )}
              >
                <p className="truncate">{item.participants.map((p) => p.name).join(" ↔ ")}</p>
                <p className="mt-1 text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                  {item.status} · {item.turn_count}/{item.max_turns}
                </p>
              </button>
            ))}
          </div>
        </GlassPanel>
        <GlassPanel className="p-5">
          <div className="flex flex-wrap items-center gap-3">
            <StatusPill status={thread.status} label={thread.status} />
            <p className="text-sm text-muted-foreground">
              {thread.place ?? "Genesis City"}
              {thread.seed_event ? ` · ${thread.seed_event}` : ""}
            </p>
            <p className="text-xs text-muted-foreground">
              {thread.turn_count} / {thread.max_turns} turns
            </p>
          </div>
          <ul className="mt-6 space-y-3">
            {thread.messages.length === 0 && (
              <li className="text-sm text-muted-foreground">This thread has no spoken turns yet.</li>
            )}
            {thread.messages.map((message) => {
              const mine = message.from?.id === aivvaId;
              return (
                <li
                  key={message.id}
                  className={cn(
                    "max-w-[40rem] rounded-2xl border border-white/10 px-4 py-3 text-sm",
                    mine ? "ml-auto bg-teal/10" : "bg-white/5",
                  )}
                >
                  <p className="text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                    {message.from?.name ?? "Unknown"}
                    {message.turn != null ? ` · turn ${message.turn}` : ""}
                    {message.action ? ` · ${actionLabel(message.action)}` : ""}
                    {message.created_at ? ` · ${formatClock(message.created_at)}` : ""}
                  </p>
                  <p className="mt-2 leading-6">{message.text}</p>
                </li>
              );
            })}
          </ul>
        </GlassPanel>
      </div>
    </div>
  );
}
