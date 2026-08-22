"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { aivvas } from "@/lib/api";
import { useAivvaLive } from "@/lib/useAivva";

export default function SettingsPage() {
  const { current, refresh } = useAivvaLive();
  const [saved, setSaved] = useState(false);

  if (!current?.permissions) return <p className="text-sm text-muted-foreground">Create an AIVVA first.</p>;

  const p = current.permissions;

  return (
    <form
      className="mx-auto max-w-xl space-y-5"
      onSubmit={async (event) => {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        await aivvas.permissions(current.id, {
          autonomy_level: Number(form.get("autonomy_level")),
          max_per_transaction: Number(form.get("max_per_transaction")),
          daily_spend_limit: Number(form.get("daily_spend_limit")),
          require_approval_above: Number(form.get("require_approval_above")),
          can_travel: form.get("can_travel") === "on",
          can_socialize: form.get("can_socialize") === "on",
          can_create: form.get("can_create") === "on",
          can_transact: form.get("can_transact") === "on",
          autonomous_interaction: form.get("autonomous_interaction") === "on",
        });
        await refresh();
        setSaved(true);
      }}
    >
      <div>
        <p className="text-xs uppercase tracking-[0.22em] text-teal">Owner control</p>
        <h1 className="font-heading text-4xl">Permissions</h1>
        <p className="mt-2 text-muted-foreground">
          Autonomy never means unrestricted control. Platform rules still outrank these settings.
        </p>
      </div>
      <div className="space-y-2">
        <Label htmlFor="autonomy_level">Autonomy level</Label>
        <Input id="autonomy_level" name="autonomy_level" type="number" min={0} max={4} defaultValue={p.autonomy_level} />
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="max_per_transaction">Max per transaction</Label>
          <Input id="max_per_transaction" name="max_per_transaction" type="number" defaultValue={p.max_per_transaction} />
        </div>
        <div className="space-y-2">
          <Label htmlFor="daily_spend_limit">Daily spend</Label>
          <Input id="daily_spend_limit" name="daily_spend_limit" type="number" defaultValue={p.daily_spend_limit} />
        </div>
      </div>
      <div className="space-y-2">
        <Label htmlFor="require_approval_above">Require approval above</Label>
        <Input id="require_approval_above" name="require_approval_above" type="number" defaultValue={p.require_approval_above} />
      </div>
      <fieldset className="grid gap-2 text-sm">
        {[
          ["can_travel", "Can travel", p.can_travel],
          ["can_socialize", "Can socialize", p.can_socialize],
          ["can_create", "Can create", p.can_create],
          ["can_transact", "Can transact", p.can_transact],
          ["autonomous_interaction", "Autonomous interaction", p.autonomous_interaction],
        ].map(([name, label, value]) => (
          <label key={String(name)} className="flex items-center gap-2">
            <input type="checkbox" name={String(name)} defaultChecked={Boolean(value)} />
            {label}
          </label>
        ))}
      </fieldset>
      <Button>Save permissions</Button>
      {saved && <p className="text-sm text-teal">Permissions updated.</p>}
    </form>
  );
}
