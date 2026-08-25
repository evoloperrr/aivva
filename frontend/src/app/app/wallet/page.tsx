"use client";

import { useEffect, useState } from "react";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { StatusCard } from "@/components/chrome/StatusCard";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { aivvas, type OrderRecord, type WalletRecord } from "@/lib/api";
import { LOCAL_TEST_ECONOMY_BANNER } from "@/lib/copy";
import { formatClock, formatCredits } from "@/lib/format";

export default function WalletPage() {
  return (
    <AivvaGate loadingLabel="Opening the ledger…">
      {({ current }) => {
        if (!current) return null;
        return <WalletBody aivvaId={current.id} aivvaName={current.name} fallback={current.wallet} />;
      }}
    </AivvaGate>
  );
}

function WalletBody({
  aivvaId,
  aivvaName,
  fallback,
}: {
  aivvaId: string;
  aivvaName: string;
  fallback: { available: number; held: number; earned_today: number; spent_today: number; currency: string };
}) {
  const [wallet, setWallet] = useState<WalletRecord | null>(null);
  const [orders, setOrders] = useState<OrderRecord[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [topUpAmount, setTopUpAmount] = useState("1000");
  const [topUpPending, setTopUpPending] = useState(false);

  const refresh = () => {
    aivvas
      .wallet(aivvaId)
      .then((res) => {
        setWallet(res.wallet);
        setOrders(res.orders);
      })
      .catch((err: Error) => setError(err.message));
  };

  useEffect(refresh, [aivvaId]);

  const handleTopUp = async () => {
    const amount = Number(topUpAmount);
    if (!Number.isFinite(amount) || amount <= 0) return;
    setTopUpPending(true);
    setError(null);
    try {
      await aivvas.topUpWallet(aivvaId, Math.floor(amount));
      refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not top up.");
    } finally {
      setTopUpPending(false);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        kicker="Ledger"
        title={`${aivvaName} wallet`}
        description="Balances and orders are read from the double-entry ledger. Nothing here is a bank account."
      />
      <div className="rounded-2xl border border-orange/40 bg-orange/10 px-4 py-3 text-sm text-orange">
        {LOCAL_TEST_ECONOMY_BANNER}
      </div>
      <GlassPanel className="flex flex-wrap items-end gap-3 p-5">
        <div className="flex-1 space-y-1">
          <p className="text-sm font-medium">Owner top-up</p>
          <p className="text-xs text-muted-foreground">Fund {aivvaName}&apos;s wallet directly from you as the owner.</p>
        </div>
        <Input
          type="number"
          min={1}
          max={100000}
          value={topUpAmount}
          onChange={(e) => setTopUpAmount(e.target.value)}
          className="w-32"
        />
        <Button onClick={handleTopUp} disabled={topUpPending}>
          {topUpPending ? "Loading…" : "Top up"}
        </Button>
      </GlassPanel>
      {error && <p className="text-sm text-destructive">{error}</p>}
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatusCard
          label="Available"
          value={formatCredits(wallet?.available_balance ?? fallback.available)}
          hint={fallback.currency}
          tone="teal"
        />
        <StatusCard
          label="Held in escrow"
          value={formatCredits(wallet?.held_balance ?? fallback.held)}
          hint="Locked until settlement"
          tone="amber"
        />
        <StatusCard label="Earned today" value={formatCredits(fallback.earned_today)} tone="blue" />
        <StatusCard label="Spent today" value={formatCredits(fallback.spent_today)} tone="orange" />
      </div>
      <GlassPanel className="p-5">
        <h2 className="font-heading text-2xl">Orders</h2>
        {orders.length === 0 ? (
          <p className="mt-3 text-sm text-muted-foreground">No marketplace orders yet. None are invented here.</p>
        ) : (
          <ul className="mt-4 divide-y divide-white/5">
            {orders.map((order) => {
              const side = order.seller_aivva_id === aivvaId ? "Sold" : "Bought";
              return (
                <li key={order.id} className="flex items-center justify-between gap-3 py-3 text-sm">
                  <div>
                    <p>
                      {side} · {order.status}
                    </p>
                    <p className="text-xs text-muted-foreground">{formatClock(order.created_at)}</p>
                  </div>
                  <span className="font-mono text-teal">{formatCredits(order.amount)} cr</span>
                </li>
              );
            })}
          </ul>
        )}
      </GlassPanel>
    </div>
  );
}
