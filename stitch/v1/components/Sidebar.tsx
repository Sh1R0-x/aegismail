'use client';

import Link from 'next/link';
import Image from 'next/image';
import { usePathname } from 'next/navigation';
import {
  LayoutDashboard,
  Send,
  Users,
  Building2,
  Inbox,
  FileText,
  Activity,
  Settings,
  UserCircle,
  Shield,
  LogOut,
  Mail,
  FileEdit
} from 'lucide-react';

const navigation = [
  { name: 'Tableau de bord', href: '/', icon: LayoutDashboard },
  { name: 'Campagnes', href: '/campaigns', icon: Send },
  { name: 'Contacts', href: '/contacts', icon: Users },
  { name: 'Organisations', href: '/organisations', icon: Building2 },
  { name: 'Boîte de réception', href: '/inbox', icon: Inbox },
  { name: 'Brouillons', href: '/drafts', icon: FileEdit },
  { name: 'Modèles', href: '/templates', icon: FileText },
  { name: 'Flux d\'activité', href: '/activity', icon: Activity },
];

const bottomNavigation = [
  { name: 'Paramètres', href: '/settings', icon: Settings },
  { name: 'Utilisateurs', href: '/users', icon: UserCircle },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="w-64 bg-[#0F172A] text-white flex flex-col flex-shrink-0 border-r border-slate-800 h-screen sticky top-0">
      <div className="p-6 flex items-center gap-3">
        <div className="w-8 h-8 bg-gradient-to-br from-blue-600 to-violet-600 rounded-lg flex items-center justify-center shadow-lg shadow-blue-500/20">
          <Shield className="w-5 h-5 text-white" />
        </div>
        <div className="flex flex-col">
          <span className="font-bold text-base tracking-tight leading-none">AEGIS MAILING</span>
          <span className="text-[10px] text-slate-400 font-medium uppercase tracking-widest mt-1">Premium B2B</span>
        </div>
      </div>

      <nav className="flex-1 px-4 py-4 space-y-1 overflow-y-auto custom-scrollbar">
        <div className="text-xs font-semibold text-slate-500 uppercase px-3 py-2 tracking-wider">Menu Principal</div>
        {navigation.map((item) => {
          const isActive = pathname === item.href;
          return (
            <Link
              key={item.name}
              href={item.href}
              className={`flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all ${
                isActive
                  ? 'bg-blue-600/10 text-blue-500 border border-blue-500/20 font-bold'
                  : 'text-slate-400 hover:text-white hover:bg-slate-800/50 font-medium'
              }`}
            >
              <item.icon className="w-5 h-5" />
              <span className="text-sm">{item.name}</span>
            </Link>
          );
        })}

        <div className="pt-6 text-xs font-semibold text-slate-500 uppercase px-3 py-2 tracking-wider">Configuration</div>
        {bottomNavigation.map((item) => {
          const isActive = pathname === item.href;
          return (
            <Link
              key={item.name}
              href={item.href}
              className={`flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all ${
                isActive
                  ? 'bg-blue-600/10 text-blue-500 border border-blue-500/20 font-bold'
                  : 'text-slate-400 hover:text-white hover:bg-slate-800/50 font-medium'
              }`}
            >
              <item.icon className="w-5 h-5" />
              <span className="text-sm">{item.name}</span>
            </Link>
          );
        })}
      </nav>

      <div className="p-4 border-t border-slate-800 bg-slate-900/50">
        <div className="flex items-center gap-3 p-2 rounded-xl bg-slate-800/50 border border-slate-700/50">
          <div className="w-10 h-10 rounded-full bg-slate-700 overflow-hidden border-2 border-slate-600 shrink-0 relative">
            <Image
              src="https://api.dicebear.com/7.x/avataaars/svg?seed=Felix"
              alt="Avatar Utilisateur"
              fill
              referrerPolicy="no-referrer"
              className="object-cover"
            />
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-bold truncate text-white">Jean Dupont</p>
            <p className="text-[10px] text-slate-400 truncate uppercase tracking-wider">Admin Compte</p>
          </div>
          <button className="text-slate-500 hover:text-white transition-colors p-1">
            <LogOut className="w-4 h-4" />
          </button>
        </div>
      </div>
    </aside>
  );
}
