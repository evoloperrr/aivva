"use client";

import { createContext, useContext, useEffect, useMemo, useState } from "react";
import { auth, type User } from "./api";

type AuthContextValue = {
  user: User | null;
  loading: boolean;
  setSession: (token: string, user: User) => void;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem("aivva_token");
    if (!token) {
      queueMicrotask(() => setLoading(false));
      return;
    }
    auth
      .me()
      .then((res) => setUser(res.user))
      .catch(() => {
        localStorage.removeItem("aivva_token");
        setUser(null);
      })
      .finally(() => setLoading(false));
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      setSession: (token, next) => {
        localStorage.setItem("aivva_token", token);
        setUser(next);
      },
      logout: async () => {
        try {
          await auth.logout();
        } catch {
          // token is discarded locally either way
        }
        localStorage.removeItem("aivva_token");
        setUser(null);
      },
    }),
    [user, loading],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
