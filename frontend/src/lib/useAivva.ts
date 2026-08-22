"use client";

import { useCallback, useEffect, useState } from "react";
import {
  aivvas,
  world,
  type ActivityItem,
  type Aivva,
  type Marketplace,
  type WorldMap,
} from "./api";

export function useAivvaLive() {
  const [list, setList] = useState<Aivva[]>([]);
  const [current, setCurrent] = useState<Aivva | null>(null);
  const [activity, setActivity] = useState<ActivityItem[]>([]);
  const [map, setMap] = useState<WorldMap | null>(null);
  const [market, setMarket] = useState<Marketplace | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    const [owned, worldMap] = await Promise.all([aivvas.list(), world.map()]);
    setList(owned.data);
    setMap(worldMap);
    const selected = owned.data[0] ?? null;
    setCurrent(selected);
    if (selected) {
      const feed = await aivvas.activity(selected.id);
      setActivity(feed.data);
    }
    return selected;
  }, []);

  useEffect(() => {
    refresh()
      .catch((err: Error) => setError(err.message))
      .finally(() => setLoading(false));
  }, [refresh]);

  useEffect(() => {
    if (!current) return;
    const id = window.setInterval(async () => {
      try {
        const due =
          current.status !== "PAUSED" &&
          current.status !== "DORMANT" &&
          current.goal &&
          (!current.next_scheduled_at || new Date(current.next_scheduled_at).getTime() <= Date.now());
        if (due) {
          const ticked = await aivvas.tick(current.id);
          setCurrent(ticked.data);
        } else {
          const fresh = await aivvas.get(current.id);
          setCurrent(fresh.data);
        }
        const [feed, worldMap] = await Promise.all([aivvas.activity(current.id), world.map()]);
        setActivity(feed.data);
        setMap(worldMap);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Live update failed");
      }
    }, 2500);
    return () => window.clearInterval(id);
  }, [current?.id, current?.status, current?.next_scheduled_at, current?.goal]);

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
    loadMarket,
    error,
    setError,
    loading,
    refresh,
  };
}
