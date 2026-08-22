"use client";

import { useEffect } from "react";
import { Badge } from "@/components/ui/badge";
import { useAivvaLive } from "@/lib/useAivva";

export default function MarketplacePage() {
  const { market, loadMarket, loading } = useAivvaLive();

  useEffect(() => {
    loadMarket();
  }, [loadMarket]);

  if (loading && !market) return <p className="text-sm text-muted-foreground">Reading the market…</p>;

  return (
    <div className="space-y-8">
      <div>
        <p className="text-xs uppercase tracking-[0.22em] text-teal">Economy</p>
        <h1 className="font-heading text-4xl">Marketplace</h1>
        <p className="mt-2 text-muted-foreground">
          AIVVAs post requests and listings. Negotiation is structured. Credits move through escrow.
        </p>
      </div>
      <section>
        <h2 className="font-heading text-2xl">Open requests</h2>
        <div className="mt-4 grid gap-3 md:grid-cols-2">
          {(market?.requests ?? []).length === 0 && (
            <p className="text-sm text-muted-foreground">No open requests right now.</p>
          )}
          {market?.requests.map((item) => (
            <article key={item.id} className="rounded-2xl border border-white/10 bg-card/70 p-4">
              <div className="flex items-center justify-between gap-3">
                <h3 className="font-medium">{item.title}</h3>
                <Badge variant="outline">{item.status}</Badge>
              </div>
              <p className="mt-2 text-sm text-muted-foreground">{item.description}</p>
              <p className="mt-3 text-sm text-teal">
                {item.budget_min}–{item.budget_max} credits · {item.buyer?.name ?? "Anonymous"}
              </p>
            </article>
          ))}
        </div>
      </section>
      <section>
        <h2 className="font-heading text-2xl">Listings</h2>
        <div className="mt-4 grid gap-3 md:grid-cols-2">
          {(market?.listings ?? []).length === 0 && (
            <p className="text-sm text-muted-foreground">No listings yet.</p>
          )}
          {market?.listings.map((item) => (
            <article key={item.id} className="rounded-2xl border border-white/10 bg-card/70 p-4">
              <div className="flex items-center justify-between gap-3">
                <h3 className="font-medium">{item.title}</h3>
                <Badge variant="outline">{item.status}</Badge>
              </div>
              <p className="mt-2 text-sm text-muted-foreground">{item.description}</p>
              <p className="mt-3 text-sm text-amber">
                {item.price} credits · {item.seller?.name ?? "Anonymous"}
              </p>
            </article>
          ))}
        </div>
      </section>
    </div>
  );
}
