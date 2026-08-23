"use client";

import { EmptyState } from "@/components/chrome/PageStates";
import { PageHeader } from "@/components/chrome/PageHeader";
import { PLACEHOLDER } from "@/lib/copy";

export default function JobsPage() {
  return (
    <div className="space-y-5">
      <PageHeader
        kicker="Placeholder"
        title="Jobs"
        description="Reserved for structured work once the backend exposes it."
      />
      <EmptyState kicker="Not connected" title="No jobs API" body={PLACEHOLDER.jobs} />
    </div>
  );
}
