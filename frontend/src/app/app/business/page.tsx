"use client";

import { EmptyState } from "@/components/chrome/PageStates";
import { PageHeader } from "@/components/chrome/PageHeader";
import { PLACEHOLDER } from "@/lib/copy";

export default function BusinessPage() {
  return (
    <div className="space-y-5">
      <PageHeader
        kicker="Placeholder"
        title="Business"
        description="Reserved for firms and shops once those records exist."
      />
      <EmptyState kicker="Not connected" title="No business records" body={PLACEHOLDER.business} />
    </div>
  );
}
