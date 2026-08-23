"use client";

import { OwnerChat } from "@/components/aivva/OwnerChat";
import { CharacterCard } from "@/components/aivva/CharacterCard";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";

export default function ChatPage() {
  return (
    <AivvaGate loadingLabel="Opening a line…">
      {({ current }) => {
        if (!current) return null;
        return (
          <div className="mx-auto max-w-3xl space-y-4">
            <CharacterCard aivva={current} compact />
            <GlassPanel className="p-5">
              <OwnerChat aivvaId={current.id} name={current.name} />
            </GlassPanel>
          </div>
        );
      }}
    </AivvaGate>
  );
}
