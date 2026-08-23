"use client";

import { useAivvaLive, type AivvaLiveState } from "@/lib/useAivva";
import { ErrorState, LoadingState, MissingAivva } from "./PageStates";

export function AivvaGate({
  children,
  allowEmpty = false,
  loadingLabel,
}: {
  children: (live: AivvaLiveState) => React.ReactNode;
  allowEmpty?: boolean;
  loadingLabel?: string;
}) {
  const live = useAivvaLive();

  if (live.loading) return <LoadingState label={loadingLabel} />;
  if (live.offline && !live.current) {
    return <ErrorState message={live.error ?? "The AIVVA backend is offline."} />;
  }
  if (live.error && !live.current && !live.map) {
    return <ErrorState message={live.error} />;
  }
  if (!live.current && !allowEmpty) return <MissingAivva />;

  return (
    <>
      {live.error && live.current && (
        <p className="mb-4 rounded-2xl border border-amber/30 bg-amber/10 px-4 py-2 text-sm text-amber">
          {live.error}
        </p>
      )}
      {children(live)}
    </>
  );
}
