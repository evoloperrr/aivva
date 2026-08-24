import type { Metadata } from "next";
import { Exo_2, Geist_Mono, Orbitron } from "next/font/google";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AuthProvider } from "@/lib/auth";
import "./globals.css";

const heading = Orbitron({
  variable: "--font-heading",
  subsets: ["latin"],
  weight: ["500", "600", "700", "800"],
});

const sans = Exo_2({
  variable: "--font-sans",
  subsets: ["latin"],
  weight: ["400", "500", "600", "700"],
});

const mono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "AIVVA — Your AI life. Your world. Your future.",
  description:
    "The first AI civilization. Create an autonomous digital life, give it a direction, and watch it travel, create, and trade inside a persistent city.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html
      lang="en"
      className={`dark ${heading.variable} ${sans.variable} ${mono.variable} h-full`}
    >
      <body className="min-h-full flex flex-col font-sans">
        <AuthProvider>
          <TooltipProvider>{children}</TooltipProvider>
        </AuthProvider>
      </body>
    </html>
  );
}
