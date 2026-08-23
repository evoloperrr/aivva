import Link from "next/link";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export function LoadingState({ label = "Opening the city…" }: { label?: string }) {
  return (
    <div className="grid min-h-[40vh] place-items-center">
      <div className="text-center">
        <div className="mx-auto h-10 w-10 rounded-full border border-teal/30 border-t-teal motion-safe:animate-spin" />
        <p className="mt-4 text-sm text-muted-foreground">{label}</p>
      </div>
    </div>
  );
}

export function ErrorState({
  title = "The city could not be reached",
  message,
}: {
  title?: string;
  message: string;
}) {
  return (
    <div className="glass-panel rounded-3xl px-6 py-12 text-center">
      <p className="text-[11px] uppercase tracking-[0.22em] text-coral">Signal lost</p>
      <h2 className="mt-2 font-heading text-3xl">{title}</h2>
      <p className="mx-auto mt-3 max-w-lg text-sm leading-6 text-muted-foreground">{message}</p>
    </div>
  );
}

export function EmptyState({
  kicker,
  title,
  body,
  action,
}: {
  kicker?: string;
  title: string;
  body: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="rounded-3xl border border-dashed border-white/15 px-6 py-16 text-center">
      {kicker && <p className="text-[11px] uppercase tracking-[0.22em] text-teal">{kicker}</p>}
      <h2 className="mt-2 font-heading text-3xl">{title}</h2>
      <p className="mx-auto mt-3 max-w-lg text-sm leading-6 text-muted-foreground">{body}</p>
      {action && <div className="mt-6">{action}</div>}
    </div>
  );
}

export function MissingAivva() {
  return (
    <EmptyState
      kicker="Birth"
      title="No AIVVA yet"
      body="Create one to give this owner a life in Genesis City. Until then there is no location, goal, wallet, or memory to show."
      action={
        <Link href="/app/create" className={cn(buttonVariants(), "px-4")}>
          Create AIVVA
        </Link>
      }
    />
  );
}
