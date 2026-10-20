import '@mantine/core/styles.css';
import './globals.css';
import type { Metadata } from 'next';
import localFont from 'next/font/local';
import { theme } from '@/theme';
import { SiteShell } from '@/components/layout/SiteShell';
import { SiteFooter } from '@/components/layout/SiteFooter';
import { ColorSchemeScript, MantineProvider, mantineHtmlProps } from '@mantine/core';

const lato = localFont({
  src: [
    { path: './fonts/lato/Lato-Regular.woff2', weight: '400', style: 'normal' },
    { path: './fonts/lato/Lato-SemiBold.woff2', weight: '600', style: 'normal' },
    { path: './fonts/lato/Lato-Bold.woff2', weight: '700', style: 'normal' },
  ],
  variable: '--font-lato',
  display: 'swap',
  fallback: ['Arial', 'Helvetica', 'sans-serif'],
});

export const metadata: Metadata = {
  title: 'Pots & Planters — Layout preview',
  description: 'Pots & Planters shared layout preview.',
  manifest: '/site.webmanifest',
  icons: {
    icon: [
      { url: '/icon-192.png', type: 'image/png', sizes: '192x192' },
      { url: '/icon-512.png', type: 'image/png', sizes: '512x512' },
    ],
  },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en" {...mantineHtmlProps} className={lato.variable}>
      <head><ColorSchemeScript forceColorScheme="light" /></head>
      <body>
        <MantineProvider forceColorScheme="light" theme={theme}>
          <SiteShell footer={<SiteFooter />}>{children}</SiteShell>
        </MantineProvider>
      </body>
    </html>
  );
}
