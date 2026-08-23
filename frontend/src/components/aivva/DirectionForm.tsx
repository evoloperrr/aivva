"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { aivvas, type Aivva, type Interpretation } from "@/lib/api";

export function DirectionForm({
  aivva,
  onChanged,
}: {
  aivva: Aivva;
  onChanged: () => Promise<unknown> | unknown;
}) {
  const [direction, setDirection] = useState(
    aivva.goal?.raw_direction ?? "Find ethical ways to create income using creative skills.",
  );
  const [interpretation, setInterpretation] = useState<Interpretation | null>(null);
  const [goalId, setGoalId] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  return (
    <div>
      <p className="text-[11px] uppercase tracking-[0.22em] text-violet">Direction</p>
      <h2 className="mt-1 font-heading text-2xl">Give your AIVVA direction</h2>
      <p className="mt-1 text-sm text-muted-foreground">
        The city interprets first. You confirm before it becomes the active goal. Pause is available if you need the
        life to wait.
      </p>
      <Textarea
        className="mt-4"
        value={direction}
        onChange={(event) => setDirection(event.target.value)}
        rows={4}
      />
      <div className="mt-3 flex flex-wrap gap-2">
        <Button
          type="button"
          onClick={async () => {
            setBusy("interpret");
            setError(null);
            try {
              const res = await aivvas.interpret(aivva.id, direction);
              setInterpretation(res.interpretation);
              setGoalId(res.goal_id);
            } catch (err) {
              setError(err instanceof Error ? err.message : "Could not interpret.");
            } finally {
              setBusy(null);
            }
          }}
          disabled={busy === "interpret"}
        >
          {busy === "interpret" ? "Reading…" : "Interpret"}
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={!goalId || !interpretation?.allowed || busy === "confirm"}
          onClick={async () => {
            if (!goalId) return;
            setBusy("confirm");
            try {
              await aivvas.confirm(aivva.id, goalId);
              setInterpretation(null);
              setGoalId(null);
              await onChanged();
            } catch (err) {
              setError(err instanceof Error ? err.message : "Could not confirm.");
            } finally {
              setBusy(null);
            }
          }}
        >
          Confirm
        </Button>
        {aivva.status === "DORMANT" || aivva.status === "PAUSED" ? (
          <Button
            type="button"
            variant="outline"
            onClick={async () => {
              setBusy("activate");
              await aivvas.activate(aivva.id);
              await onChanged();
              setBusy(null);
            }}
          >
            Resume
          </Button>
        ) : (
          <Button
            type="button"
            variant="outline"
            onClick={async () => {
              setBusy("pause");
              await aivvas.pause(aivva.id);
              await onChanged();
              setBusy(null);
            }}
          >
            Pause
          </Button>
        )}
        <Button
          type="button"
          variant="ghost"
          onClick={() => {
            setInterpretation(null);
            setGoalId(null);
          }}
        >
          Clear reading
        </Button>
      </div>
      {error && <p className="mt-3 text-sm text-destructive">{error}</p>}
      {interpretation && (
        <div className="mt-4 rounded-2xl bg-white/5 p-4 text-sm">
          {!interpretation.allowed ? (
            <p className="text-destructive">{interpretation.reason}</p>
          ) : (
            <div className="space-y-2">
              <p>
                <span className="text-muted-foreground">Type · </span>
                {interpretation.goal.goal_type}
              </p>
              <p>
                <span className="text-muted-foreground">Constraints · </span>
                {interpretation.goal.ethical_constraint.join(", ")}
              </p>
              <p>
                <span className="text-muted-foreground">Risk · </span>
                {interpretation.goal.risk_level}
              </p>
              <p>
                <span className="text-muted-foreground">Estimated spend · </span>
                {interpretation.estimated_cost} credits
              </p>
              <p>
                <span className="text-muted-foreground">Permissions · </span>
                {interpretation.permissions_needed.join(", ")}
              </p>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
