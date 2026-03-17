import type {Metadata} from 'next';
import './globals.css'; // Global styles
import { Sidebar } from '@/components/Sidebar';

export const metadata: Metadata = {
  title: 'AEGIS MAILING - Premium B2B Solutions',
  description: 'Plateforme d\'emailing B2B',
};

export default function RootLayout({children}: {children: React.ReactNode}) {
  return (
    <html lang="fr">
      <body className="font-sans antialiased bg-slate-50 text-slate-900 flex h-screen overflow-hidden" suppressHydrationWarning>
        <Sidebar />
        <div className="flex-1 flex flex-col overflow-hidden">
          {children}
        </div>
      </body>
    </html>
  );
}
