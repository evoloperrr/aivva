"use client";

import { useEffect, useState } from "react";
import { AivvaGate } from "@/components/chrome/AivvaGate";
import { GlassPanel } from "@/components/chrome/GlassPanel";
import { PageHeader } from "@/components/chrome/PageHeader";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { aivvas } from "@/lib/api";
import { formatCredits } from "@/lib/format";
import { cn } from "@/lib/utils";

function isCoffee(text: string) {
  return /coffee|cafe|café/i.test(text);
}

export default function MarketplacePage() {
  return (
    <AivvaGate loadingLabel="Reading the market…" allowEmpty>
      {({ current, market, loadMarket, loading }) => (
        <MarketplaceBody
          aivvaId={current?.id ?? null}
          market={market}
          loadMarket={loadMarket}
          loading={loading && !market}
        />
      )}
    </AivvaGate>
  );
}

function MarketplaceBody({
  aivvaId,
  market,
  loadMarket,
  loading,
}: {
  aivvaId: string | null;
  market: ReturnType<typeof import("@/lib/useAivva").useAivvaLive>["market"];
  loadMarket: () => Promise<void>;
  loading: boolean;
}) {
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState<string | null>(null);

  useEffect(() => {
    loadMarket().catch((err: Error) => setError(err.message));
  }, [loadMarket]);

  if (loading) return <p className="text-sm text-muted-foreground">Reading the market…</p>;

  const requests = market?.requests ?? [];
  const listings = market?.listings ?? [];

  return (
    <div className="space-y-8">
      <PageHeader
        kicker="Economy"
        title="Marketplace"
        description="Requests, offers, prices, and states come from the live ledger world. Completed transactions are shown only when the backend has them."
      />

      {aivvaId && (
        <div className="grid gap-4 lg:grid-cols-2">
          <form
            className="glass-panel space-y-3 rounded-3xl p-5"
            onSubmit={async (event) => {
              event.preventDefault();
              const formEl = event.currentTarget;
              const form = new FormData(formEl);
              setPending("request");
              setError(null);
              try {
                await aivvas.createRequest(aivvaId, {
                  title: String(form.get("title")),
                  category: String(form.get("category")),
                  budget_min: Number(form.get("budget_min")),
                  budget_max: Number(form.get("budget_max")),
                  description: String(form.get("description")),
                });
                formEl.reset();
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
            className="glass-panel space-y-3 rounded-3xl p-5"
            onSubmit={async (event) => {
              event.preventDefault();
              const formEl = event.currentTarget;
              const form = new FormData(formEl);
              setPending("listing");
              setError(null);
              try {
                await aivvas.createListing(aivvaId, {
                  title: String(form.get("title")),
                  category: String(form.get("category")),
                  price: Number(form.get("price")),
                  description: String(form.get("description")),
                });
                formEl.reset();
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
          {requests.length === 0 && <p className="text-sm text-muted-foreground">No open requests right now.</p>}
          {requests.map((item) => {
            const coffee = isCoffee(`${item.title} ${item.description}`);
            return (
              <article
                key={item.id}
                className={cn("glass-panel rounded-3xl p-4", coffee && "ring-1 ring-orange/40")}
              >
                <div className="flex items-center justify-between gap-3">
                  <h3 className="font-medium">{item.title}</h3>
                  <Badge variant="outline">{item.status}</Badge>
                </div>
                {coffee && (
                  <p className="mt-2 text-[11px] uppercase tracking-[0.16em] text-orange">
                    Genesis coffee-shop request
                  </p>
                )}
                <p className="mt-2 text-sm text-muted-foreground">{item.description}</p>
                <p className="mt-3 text-sm text-teal">
                  {formatCredits(item.budget_min)}–{formatCredits(item.budget_max)} credits ·{" "}
                  {item.buyer?.name ?? "Anonymous"}
                </p>
              </article>
            );
          })}
        </div>
      </section>
      <section>
        <h2 className="font-heading text-2xl">Listings</h2>
        <div className="mt-4 grid gap-3 md:grid-cols-2">
          {listings.length === 0 && <p className="text-sm text-muted-foreground">No listings yet.</p>}
          {listings.map((item) => (
            <GlassPanel key={item.id} className="p-4">
              <div className="flex items-center justify-between gap-3">
                <h3 className="font-medium">{item.title}</h3>
                <Badge variant="outline">{item.status}</Badge>
              </div>
              <p className="mt-2 text-sm text-muted-foreground">{item.description}</p>
              <p className="mt-3 text-sm text-amber">
                {formatCredits(item.price)} credits · {item.seller?.name ?? "Anonymous"}
              </p>
            </GlassPanel>
          ))}
        </div>
      </section>
    </div>
  );
}
