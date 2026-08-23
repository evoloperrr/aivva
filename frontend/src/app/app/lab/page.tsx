"use client";

import { useEffect, useState } from "react";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { StatusCard } from "@/components/chrome/StatusCard";
import { ErrorState } from "@/components/chrome/PageStates";
import { admin, type AdminHealth } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { formatClock } from "@/lib/format";

export default function GenesisLabPage() {
  const { user } = useAuth();
  const [health, setHealth] = useState<AdminHealth | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!user?.is_admin) return;
    admin
      .health()
      .then(setHealth)
      .catch((err: Error) => setError(err.message));
  }, [user?.is_admin]);

  if (!user?.is_admin) {
    return (
      <ErrorState
        title="Genesis Lab is restricted"
        message="This console is available only to authorized operators. No experiment controls or secrets are shown here."
      />
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        kicker="Operator"
        title="Genesis Lab"
        description="Read-only city health. Peer conversation, isolation, and economy experiments stay on their existing artisan commands. Secrets and raw prompts are not displayed."
      />
      {error && <p className="text-sm text-destructive">{error}</p>}
      {health && (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatusCard label="Active AIVVAs" value={health.active_aivvas} tone="teal" />
            <StatusCard label="Paused" value={health.paused} tone="amber" />
            <StatusCard label="Open requests" value={health.open_requests} tone="orange" />
            <StatusCard label="Settled orders" value={health.settled_orders} tone="violet" />
          </div>
          <GlassPanel className="p-5">
            <h2 className="font-heading text-2xl">Ledger integrity</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              {health.ledger.balanced === undefined
                ? "Integrity payload received. Balanced flag was not included."
                : health.ledger.balanced
                  ? "Ledger reports balanced."
                  : "Ledger reports an integrity issue."}
            </p>
          </GlassPanel>
          <div className="grid gap-5 lg:grid-cols-2">
            <GlassPanel className="p-5">
              <h2 className="font-heading text-2xl">Recent model calls</h2>
              <p className="mt-1 text-xs text-muted-foreground">Provider, purpose, and latency only.</p>
              <ul className="mt-4 space-y-3 text-sm">
                {health.recent_ai.length === 0 && (
                  <li className="text-muted-foreground">No recent provider calls.</li>
                )}
                {health.recent_ai.map((row) => (
                  <li key={row.id} className="border-b border-white/5 pb-3 last:border-0">
                    <p>
                      {row.provider ?? "unknown provider"} · {row.model ?? "unknown model"}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {row.purpose ?? "unspecified"} · {row.status ?? "unknown"}
                      {row.latency_ms != null ? ` · ${row.latency_ms}ms` : ""}
                      {row.created_at ? ` · ${formatClock(row.created_at)}` : ""}
                    </p>
                  </li>
                ))}
              </ul>
            </GlassPanel>
            <GlassPanel className="p-5">
              <h2 className="font-heading text-2xl">Recent ledger</h2>
              <ul className="mt-4 space-y-3 text-sm">
                {health.recent_ledger.length === 0 && (
                  <li className="text-muted-foreground">No recent ledger rows.</li>
                )}
                {health.recent_ledger.map((row) => (
                  <li key={row.id} className="border-b border-white/5 pb-3 last:border-0">
                    <p>{row.type ?? "transaction"}</p>
                    <p className="text-xs text-muted-foreground">
                      {row.description ?? "No public description"}
                      {row.reversed ? " · reversed" : ""}
                      {row.created_at ? ` · ${formatClock(row.created_at)}` : ""}
                    </p>
                  </li>
                ))}
              </ul>
            </GlassPanel>
          </div>
          <p className="text-xs text-muted-foreground">
            Isolation, injection refusal, and live LLM settlement are still run from the existing Genesis and peer
            conversation artisan commands. This page does not start those experiments.
          </p>
        </>
      )}
    </div>
  );
}
