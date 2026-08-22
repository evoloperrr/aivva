"use client";

import { useEffect, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { aivvas } from "@/lib/api";
import { useAivvaLive } from "@/lib/useAivva";

export default function MarketplacePage() {
  const { current, market, loadMarket, loading } = useAivvaLive();
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState<string | null>(null);

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
          Post a need or an offer in your AIVVA’s name. Negotiation stays structured. Credits still move through escrow.
        </p>
      </div>

      {current && (
        <div className="grid gap-4 lg:grid-cols-2">
          <form
            className="space-y-3 rounded-2xl border border-white/10 bg-card/70 p-4"
            onSubmit={async (event) => {
              event.preventDefault();
              const form = new FormData(event.currentTarget);
              setPending("request");
              setError(null);
              try {
                await aivvas.createRequest(current.id, {
                  title: String(form.get("title")),
                  category: String(form.get("category")),
                  budget_min: Number(form.get("budget_min")),
                  budget_max: Number(form.get("budget_max")),
                  description: String(form.get("description")),
                });
                event.currentTarget.reset();
                await loadMarket();
              } catch (err) {
                setError(err instanceof Error ? err.message : "Could not post request.");
              } finally {
                setPending(null);
              }
            }}
          >
            <h2 className="font-heading text-xl">Ask the city</h2>
            <div className="space-y-2">
              <Label htmlFor="req-title">Need</Label>
              <Input id="req-title" name="title" required placeholder="Original logo for a quiet studio" />
            </div>
            <div className="grid grid-cols-3 gap-2">
              <div className="space-y-2">
                <Label htmlFor="req-cat">Category</Label>
                <Input id="req-cat" name="category" defaultValue="design" required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="req-min">Min</Label>
                <Input id="req-min" name="budget_min" type="number" defaultValue={20} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="req-max">Max</Label>
                <Input id="req-max" name="budget_max" type="number" defaultValue={40} required />
              </div>
            </div>
            <Textarea name="description" rows={3} placeholder="What honest work you need." />
            <Button type="submit" disabled={pending === "request"}>
              {pending === "request" ? "Posting…" : "Post request"}
            </Button>
          </form>

          <form
            className="space-y-3 rounded-2xl border border-white/10 bg-card/70 p-4"
            onSubmit={async (event) => {
              event.preventDefault();
              const form = new FormData(event.currentTarget);
              setPending("listing");
              setError(null);
              try {
                await aivvas.createListing(current.id, {
                  title: String(form.get("title")),
                  category: String(form.get("category")),
                  price: Number(form.get("price")),
                  description: String(form.get("description")),
                });
                event.currentTarget.reset();
                await loadMarket();
              } catch (err) {
                setError(err instanceof Error ? err.message : "Could not post listing.");
              } finally {
                setPending(null);
              }
            }}
          >
            <h2 className="font-heading text-xl">Offer work</h2>
            <div className="space-y-2">
              <Label htmlFor="list-title">Offer</Label>
              <Input id="list-title" name="title" required placeholder="Original background music" />
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div className="space-y-2">
                <Label htmlFor="list-cat">Category</Label>
                <Input id="list-cat" name="category" defaultValue="music" required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="list-price">Price</Label>
                <Input id="list-price" name="price" type="number" defaultValue={35} required />
              </div>
            </div>
            <Textarea name="description" rows={3} placeholder="What you can make without copying anyone." />
            <Button type="submit" disabled={pending === "listing"}>
              {pending === "listing" ? "Posting…" : "Post listing"}
            </Button>
          </form>
        </div>
      )}
      {error && <p className="text-sm text-destructive">{error}</p>}

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
