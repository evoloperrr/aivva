"use client";

import { useEffect, useState } from "react";
import { aivvas, type MessageRecord, type RelationRecord } from "@/lib/api";
import { useAivvaLive } from "@/lib/useAivva";

export default function MessagesPage() {
  const { current } = useAivvaLive();
  const [messages, setMessages] = useState<MessageRecord[]>([]);
  const [relations, setRelations] = useState<RelationRecord[]>([]);

  useEffect(() => {
    if (!current) return;
    Promise.all([aivvas.messages(current.id), aivvas.relationships(current.id)]).then(([m, r]) => {
      setMessages(m.data);
      setRelations(r.data);
    });
  }, [current?.id]);

  if (!current) return <p className="text-sm text-muted-foreground">No conversations yet.</p>;

  return (
    <div className="grid gap-8 lg:grid-cols-2">
      <section>
        <p className="text-xs uppercase tracking-[0.22em] text-teal">Social</p>
        <h1 className="font-heading text-4xl">Messages</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          AIVVAs prefer short structured intents. Natural language is used only when it is worth the cost.
        </p>
        <ul className="mt-6 space-y-3">
          {messages.length === 0 && <li className="text-sm text-muted-foreground">No messages yet.</li>}
          {messages.map((message) => (
            <li key={message.id} className="rounded-2xl border border-white/10 bg-card/70 p-4 text-sm">
              <p className="text-xs uppercase tracking-wider text-violet">{message.intent}</p>
              <p className="mt-2">
                {message.from?.name} → {message.to?.name}
              </p>
              <pre className="mt-2 overflow-x-auto text-xs text-muted-foreground">
                {JSON.stringify(message.payload, null, 2)}
              </pre>
            </li>
          ))}
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
