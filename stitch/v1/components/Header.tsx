import { Bell, Search, Settings } from 'lucide-react';

interface HeaderProps {
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
}

export function Header({ title, subtitle, actions }: HeaderProps) {
  return (
    <header className="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-10 h-20 shrink-0">
      <div className="flex items-center gap-6 flex-1">
        <div>
          <h1 className="text-xl font-bold text-slate-900 leading-tight">{title}</h1>
          {subtitle && <p className="text-xs text-slate-500 font-medium mt-0.5">{subtitle}</p>}
        </div>
        
        <div className="max-w-md flex-1 relative hidden md:block ml-8">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Rechercher..."
            className="w-full bg-slate-100 border-none rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/30 transition-all placeholder:text-slate-400"
          />
        </div>
      </div>

      <div className="flex items-center gap-4">
        {actions}
        <div className="flex items-center gap-2">
          <button className="p-2.5 bg-slate-100 rounded-xl text-slate-600 hover:text-blue-600 transition-colors relative">
            <Bell className="w-5 h-5" />
            <span className="absolute top-2 right-2.5 block h-2 w-2 rounded-full bg-blue-600 ring-2 ring-white"></span>
          </button>
          <button className="p-2.5 bg-slate-100 rounded-xl text-slate-600 hover:text-blue-600 transition-colors">
            <Settings className="w-5 h-5" />
          </button>
        </div>
      </div>
    </header>
  );
}
