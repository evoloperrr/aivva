"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, useMemo } from "react";
import {
  Activity,
  Bell,
  Brain,
  Briefcase,
  Building2,
  FlaskConical,
  Globe,
  HeartHandshake,
  Home,
  Menu,
  MessageCircle,
  Settings,
  Shield,
  Sparkles,
  Store,
  Wallet,
} from "lucide-react";
import { Mark } from "@/components/brand/Mark";
import { Portrait } from "@/components/brand/Portrait";
import { StatusPill } from "@/components/aivva/StatusPill";
import { Button } from "@/components/ui/button";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { ScrollArea } from "@/components/ui/scroll-area";
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { useAuth } from "@/lib/auth";
import { WORLD_NAME } from "@/lib/copy";
import { isNavActive, NAV_GROUPS, visibleNav, type NavItem } from "@/lib/nav";
import { compactStatus } from "@/lib/status";
import { cn } from "@/lib/utils";
import { AivvaLiveProvider, useAivvaLive } from "@/lib/useAivva";
import { formatRelative } from "@/lib/format";

const icons: Record<string, React.ComponentType<{ className?: string }>> = {
  home: Home,
  sparkles: Sparkles,
  globe: Globe,
  activity: Activity,
  messages: MessageCircle,
  relationships: HeartHandshake,
  marketplace: Store,
  jobs: Briefcase,
  business: Building2,
  wallet: Wallet,
  trust: Shield,
  memory: Brain,
  settings: Settings,
  lab: FlaskConical,
};

function NavLink({
  item,
  pathname,
  onNavigate,
}: {
  item: NavItem;
  pathname: string;
  onNavigate?: () => void;
}) {
  const Icon = icons[item.icon] ?? Sparkles;
  const active = isNavActive(pathname, item);
  return (
    <Link
      href={item.href}
      onClick={onNavigate}
      className={cn(
        "flex items-center gap-3 rounded-xl px-3 py-2 text-sm transition-colors",
        active
          ? "bg-white/10 text-foreground shadow-[inset_0_0_0_1px_rgba(34,227,208,0.18)]"
          : "text-muted-foreground hover:bg-white/5 hover:text-foreground",
      )}
    >
      <Icon className={cn("size-4", active ? "text-teal" : "text-muted-foreground")} />
      {item.label}
    </Link>
  );
}

function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const { user } = useAuth();
  const pathname = usePathname();
  const items = useMemo(() => visibleNav(Boolean(user?.is_admin)), [user?.is_admin]);

  return (
    <nav className="space-y-5">
      {NAV_GROUPS.map((group) => {
        const groupItems = items.filter((item) => item.group === group.id);
        if (groupItems.length === 0) return null;
        return (
          <div key={group.id}>
            <p className="px-3 pb-2 text-[10px] uppercase tracking-[0.22em] text-muted-foreground/80">
              {group.label}
            </p>
            <div className="space-y-1">
              {groupItems.map((item) => (
                <NavLink key={item.href} item={item} pathname={pathname} onNavigate={onNavigate} />
              ))}
            </div>
          </div>
        );
      })}
    </nav>
  );
}

function TopBar() {
  const { user, logout } = useAuth();
  const router = useRouter();
  const { current, list, setCurrent, notices } = useAivvaLive();
  const unread = notices.filter((notice) => !notice.read_at).length;

  return (
    <header className="sticky top-0 z-20 border-b border-white/10 bg-[#05070f]/80 backdrop-blur-xl">
      <div className="flex h-16 items-center gap-3 px-4 lg:px-6">
        <Sheet>
          <SheetTrigger
            render={
              <Button type="button" variant="ghost" size="icon" className="lg:hidden" />
            }
          >
            <Menu className="size-4" />
            <span className="sr-only">Open navigation</span>
          </SheetTrigger>
          <SheetContent side="left" className="w-72 bg-[#080b16] p-0">
            <SheetHeader className="border-b border-white/10">
              <SheetTitle className="flex items-center gap-3">
                <Mark className="text-xs text-teal" />
                <span>AIVVA</span>
              </SheetTitle>
            </SheetHeader>
            <div className="p-3">
              <SidebarNav />
            </div>
          </SheetContent>
        </Sheet>

        <div className="hidden items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[11px] uppercase tracking-[0.18em] text-muted-foreground sm:flex">
          <span className="size-1.5 rounded-full bg-blue shadow-[0_0_8px_rgba(77,143,255,0.8)]" />
          {WORLD_NAME}
        </div>

        <div className="ml-auto flex items-center gap-2">
          {current && list.length > 1 ? (
            <DropdownMenu>
              <DropdownMenuTrigger
                render={<button type="button" className="hidden md:flex" />}
              >
                <StatusPill status={current.status} label={`${current.name} • ${compactStatus(current.status_label)}`} />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel>Your AIVVAs</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {list.map((aivva) => (
                  <DropdownMenuItem
                    key={aivva.id}
                    onClick={() => setCurrent(aivva)}
                    className={aivva.id === current.id ? "bg-white/5" : undefined}
                  >
                    <span className="flex-1">{aivva.name}</span>
                    <StatusPill status={aivva.status} label={compactStatus(aivva.status_label)} />
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          ) : current ? (
            <div className="hidden items-center gap-2 md:flex">
              <StatusPill status={current.status} label={`${current.name} • ${compactStatus(current.status_label)}`} />
            </div>
          ) : null}

          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <Button type="button" variant="ghost" size="icon" className="relative" />
              }
            >
              <Bell className="size-4" />
              {unread > 0 && (
                <span className="absolute right-1.5 top-1.5 size-1.5 rounded-full bg-orange" />
              )}
              <span className="sr-only">Notifications</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
              <DropdownMenuLabel>While you were away</DropdownMenuLabel>
              <DropdownMenuSeparator />
              {notices.length === 0 ? (
                <p className="px-2 py-3 text-sm text-muted-foreground">No owner notices yet.</p>
              ) : (
                notices.slice(0, 8).map((notice) => (
                  <div key={notice.id} className="px-2 py-2">
                    <p className="text-sm">{notice.title}</p>
                    {notice.body && <p className="text-xs text-muted-foreground">{notice.body}</p>}
                    <p className="mt-1 text-[10px] uppercase tracking-[0.14em] text-muted-foreground">
                      {formatRelative(notice.created_at)}
                    </p>
                  </div>
                ))
              )}
            </DropdownMenuContent>
          </DropdownMenu>

          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <Button type="button" variant="ghost" className="h-10 gap-2 px-2" />
              }
            >
              <Portrait name={user?.name ?? "Owner"} size={28} />
              <span className="hidden text-sm sm:inline">{user?.name}</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel>
                <p>{user?.name}</p>
                <p className="text-xs font-normal text-muted-foreground">{user?.email}</p>
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => router.push("/app/settings")}>Settings</DropdownMenuItem>
              <DropdownMenuItem
                variant="destructive"
                onClick={async () => {
                  await logout();
                  router.push("/");
                }}
              >
                Sign out
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  );
}

function AuthenticatedChrome({ children }: { children: React.ReactNode }) {
  return (
    <AivvaLiveProvider>
      <div className="flex min-h-screen">
        <aside className="sticky top-0 hidden h-screen w-[248px] shrink-0 border-r border-white/10 bg-[#080b16]/90 lg:block">
          <div className="flex h-16 items-center gap-3 px-5">
            <Link href="/app" className="flex items-center gap-3">
              <Mark className="text-xs text-teal" />
              <span className="font-heading text-xl">AIVVA</span>
            </Link>
          </div>
          <ScrollArea className="h-[calc(100vh-4rem)] px-3 pb-6">
            <SidebarNav />
          </ScrollArea>
        </aside>
        <div className="flex min-w-0 flex-1 flex-col">
          <TopBar />
          <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-6 lg:px-8">{children}</main>
        </div>
      </div>
    </AivvaLiveProvider>
  );
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!loading && !user) router.replace("/login");
  }, [loading, user, router]);

  if (loading || !user) {
    return (
      <div className="grid min-h-screen place-items-center text-sm text-muted-foreground">
        Opening the city…
      </div>
    );
  }

  return <AuthenticatedChrome>{children}</AuthenticatedChrome>;
}
