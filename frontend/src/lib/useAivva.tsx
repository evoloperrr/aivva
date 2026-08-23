"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import {
  aivvas,
  world,
  type ActivityItem,
  type Aivva,
  type Marketplace,
  type Notice,
  type WorldMap,
} from "./api";

export type AivvaLiveState = {
  list: Aivva[];
  current: Aivva | null;
  setCurrent: (next: Aivva | null) => void;
  activity: ActivityItem[];
  map: WorldMap | null;
  market: Marketplace | null;
  notices: Notice[];
  loadMarket: () => Promise<void>;
  error: string | null;
  setError: (next: string | null) => void;
  loading: boolean;
  offline: boolean;
  refresh: () => Promise<Aivva | null>;
};

const AivvaLiveContext = createContext<AivvaLiveState | null>(null);

function useAivvaLiveState(): AivvaLiveState {
  const [list, setList] = useState<Aivva[]>([]);
  const [current, setCurrent] = useState<Aivva | null>(null);
  const [activity, setActivity] = useState<ActivityItem[]>([]);
  const [map, setMap] = useState<WorldMap | null>(null);
  const [market, setMarket] = useState<Marketplace | null>(null);
  const [notices, setNotices] = useState<Notice[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [offline, setOffline] = useState(false);

  const refresh = useCallback(async () => {
    const [owned, worldMap, feed] = await Promise.all([
      aivvas.list(),
      world.map(),
      world.notifications().catch(() => ({ data: [] as Notice[] })),
    ]);
    setList(owned.data);
    setMap(worldMap);
    setNotices(feed.data);
    const selected = owned.data[0] ?? null;
    setCurrent(selected);
    if (selected) {
      const events = await aivvas.activity(selected.id);
      setActivity(events.data);
    } else {
      setActivity([]);
    }
    setOffline(false);
    return selected;
  }, []);

  useEffect(() => {
    let cancelled = false;
    queueMicrotask(() => {
      refresh()
        .catch((err: Error) => {
          if (cancelled) return;
          setError(err.message);
          setOffline(err.message.includes("offline"));
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    });
    return () => {
      cancelled = true;
    };
  }, [refresh]);

  const currentId = current?.id;

  useEffect(() => {
    if (!currentId) return;
    const id = window.setInterval(async () => {
      try {
        const live = await aivvas.live(currentId);
        setCurrent(live.data);
        setActivity(live.activity);
        setMap(await world.map());
        setOffline(false);
        setError(null);
      } catch (err) {
        const message = err instanceof Error ? err.message : "Live update failed";
        setError(message);
        setOffline(message.includes("offline"));
      }
    }, 2800);
    return () => window.clearInterval(id);
  }, [currentId]);

  const loadMarket = useCallback(async () => {
    setMarket(await world.marketplace());
  }, []);

  return {
    list,
    current,
    setCurrent,
    activity,
    map,
    market,
    notices,
    loadMarket,
    error,
    setError,
    loading,
    offline,
    refresh,
  };
}

export function AivvaLiveProvider({ children }: { children: React.ReactNode }) {
  const value = useAivvaLiveState();
  const memo = useMemo(() => value, [value]);
  return <AivvaLiveContext.Provider value={memo}>{children}</AivvaLiveContext.Provider>;
}

export function useAivvaLive() {
  const ctx = useContext(AivvaLiveContext);
  if (!ctx) throw new Error("useAivvaLive must be used within AivvaLiveProvider");
  return ctx;
}
