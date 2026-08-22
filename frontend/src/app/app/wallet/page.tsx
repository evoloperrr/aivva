"use client";

import { useEffect, useState } from "react";
import { aivvas, type OrderRecord, type WalletRecord } from "@/lib/api";
import { useAivvaLive } from "@/lib/useAivva";

export default function WalletPage() {
  const { current } = useAivvaLive();
  const [wallet, setWallet] = useState<WalletRecord | null>(null);
  const [orders, setOrders] = useState<OrderRecord[]>([]);

  useEffect(() => {
    if (!current) return;
    aivvas.wallet(current.id).then((res) => {
      setWallet(res.wallet);
      setOrders(res.orders);
    });
  }, [current?.id]);

  if (!current) return <p className="text-sm text-muted-foreground">Create an AIVVA to open a wallet.</p>;

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs uppercase tracking-[0.22em] text-teal">Ledger</p>
        <h1 className="font-heading text-4xl">Wallet</h1>
        <p className="mt-2 text-muted-foreground">
          AIVVA Credits are internal platform units. They are not cryptocurrency and cannot be withdrawn.
        </p>
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <div className="rounded-2xl border border-white/10 bg-card/70 p-5">
          <p className="text-xs uppercase tracking-wider text-muted-foreground">Available</p>
          <p className="mt-2 font-heading text-4xl text-teal">{wallet?.available_balance ?? current.wallet.available}</p>
        </div>
        <div className="rounded-2xl border border-white/10 bg-card/70 p-5">
          <p className="text-xs uppercase tracking-wider text-muted-foreground">Held in escrow</p>
          <p className="mt-2 font-heading text-4xl text-amber">{wallet?.held_balance ?? current.wallet.held}</p>
        </div>
      </div>
      <section className="rounded-2xl border border-white/10 bg-card/70 p-5">
        <h2 className="font-heading text-2xl">Orders</h2>
        {orders.length === 0 ? (
          <p className="mt-3 text-sm text-muted-foreground">No marketplace orders yet.</p>
        ) : (
          <ul className="mt-4 divide-y divide-white/5">
            {orders.map((order) => (
              <li key={order.id} className="flex items-center justify-between py-3 text-sm">
                <span>{order.status}</span>
                <span className="font-mono text-teal">{order.amount} cr</span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}
