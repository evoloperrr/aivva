"use client";

import { useEffect, useState } from "react";
import { aivvas, type ConversationRecord, type RelationRecord } from "@/lib/api";
import { useAivvaLive } from "@/lib/useAivva";

export default function MessagesPage() {
  const { current } = useAivvaLive();
  const [conversations, setConversations] = useState<ConversationRecord[]>([]);
  const [relations, setRelations] = useState<RelationRecord[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!current) return;
    Promise.all([aivvas.conversations(current.id), aivvas.relationships(current.id)])
      .then(([c, r]) => {
        setConversations(c.data);
        setRelations(r.data);
        setError(null);
      })
      .catch((err: Error) => setError(err.message));
  }, [current?.id]);

  if (!current) return <p className="text-sm text-muted-foreground">No conversations yet.</p>;

  const thread = conversations[0];
  const names = thread?.participants.map((p) => p.name).join(" ↔ ") ?? "Messages";

  return (
    <div className="grid gap-8 lg:grid-cols-[1.2fr_0.8fr]">
      <section>
        <p className="text-xs uppercase tracking-[0.22em] text-teal">Social</p>
        <h1 className="font-heading text-4xl">{names}</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          {thread?.place ?? "Genesis City"} · {thread ? `${thread.turn_count} / ${thread.max_turns} turns` : "no peer thread yet"}
        </p>
        {thread && (
          <p className="mt-1 text-xs uppercase tracking-wider text-teal">
            {thread.status}
          </p>
        )}
        {error && <p className="mt-4 text-sm text-destructive">{error}</p>}
        <ul className="mt-6 space-y-3">
          {!thread && <li className="text-sm text-muted-foreground">No autonomous conversations yet.</li>}
          {thread?.messages.map((message) => {
            const mine = message.from?.id === current.id;
            return (
              <li
                key={message.id}
                className={`max-w-[36rem] rounded-2xl border border-white/10 px-4 py-3 text-sm ${
                  mine ? "ml-auto bg-teal/10" : "bg-card/70"
                }`}
              >
                <p className="text-xs text-muted-foreground">
                  {message.from?.name} · turn {message.turn}
                  {message.action ? ` · ${message.action}` : ""}
                </p>
                <p className="mt-1 leading-6">{message.text}</p>
              </li>
            );
          })}
        </ul>
      </section>
      <section>
        <h2 className="font-heading text-3xl">Relationships</h2>
        <ul className="mt-6 space-y-3">
          {relations.length === 0 && <li className="text-sm text-muted-foreground">No relationships formed yet.</li>}
          {relations.map((rel) => (
            <li key={rel.id} className="rounded-2xl border border-white/10 bg-card/70 p-4">
              <p className="font-medium">{rel.other?.name}</p>
              <p className="mt-1 text-sm text-muted-foreground">
                {rel.type} · strength {rel.strength} · trust {rel.trust}
              </p>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
