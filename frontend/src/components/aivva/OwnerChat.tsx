"use client";

import { useEffect, useRef, useState } from "react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { aivvas, type ChatMessage } from "@/lib/api";

export function OwnerChat({ aivvaId, name }: { aivvaId: string; name: string }) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [draft, setDraft] = useState("Where are you right now?");
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);
  const bottom = useRef<HTMLDivElement>(null);

  useEffect(() => {
    aivvas.chat(aivvaId).then((res) => setMessages(res.data)).catch((err: Error) => setError(err.message));
  }, [aivvaId]);

  useEffect(() => {
    bottom.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages.length]);

  return (
    <div className="flex h-full min-h-[360px] flex-col">
      <div className="flex-1 space-y-3 overflow-y-auto pr-1">
        {messages.length === 0 && (
          <p className="text-sm text-muted-foreground">
            Talk with {name}. This is not a second control stick — confirm new directions from Home or My AIVVA.
          </p>
        )}
        {messages.map((row) => (
          <div key={row.id} className={row.role === "owner" ? "ml-8" : "mr-8"}>
            <p className="text-[10px] uppercase tracking-[0.16em] text-muted-foreground">
              {row.role === "owner" ? "You" : name}
            </p>
            <p className={`mt-1 rounded-2xl px-3 py-2 text-sm leading-6 ${row.role === "owner" ? "bg-white/10" : "bg-teal/10 text-foreground"}`}>
              {row.body}
            </p>
          </div>
        ))}
        <div ref={bottom} />
      </div>
      <form
        className="mt-4 space-y-2"
        onSubmit={async (event) => {
          event.preventDefault();
          const text = draft.trim();
          if (!text) return;
          setPending(true);
          setError(null);
          try {
            const res = await aivvas.sendChat(aivvaId, text);
            setMessages(res.data);
            setDraft("");
          } catch (err) {
            setError(err instanceof Error ? err.message : "Could not send.");
          } finally {
            setPending(false);
          }
        }}
      >
        <Textarea value={draft} onChange={(e) => setDraft(e.target.value)} rows={3} />
        {error && <p className="text-sm text-destructive">{error}</p>}
        <Button type="submit" disabled={pending}>
          {pending ? "Listening…" : `Talk to ${name}`}
        </Button>
      </form>
    </div>
  );
}
