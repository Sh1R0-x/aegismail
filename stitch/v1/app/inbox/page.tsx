import { Header } from '@/components/Header';
import Image from 'next/image';
import { Search, Star, ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';

export default function Inbox() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header title="Boîte de réception" />
      
      {/* Segmentation Bar */}
      <div className="bg-white px-8 py-0 border-b border-slate-200 flex items-center gap-8 shrink-0 overflow-x-auto custom-scrollbar">
        <button className="text-sm font-bold text-blue-600 border-b-2 border-blue-600 py-4 whitespace-nowrap">Tous les messages</button>
        <button className="text-sm font-semibold text-slate-500 hover:text-slate-800 py-4 whitespace-nowrap transition-colors">Non lus</button>
        <button className="text-sm font-semibold text-slate-500 hover:text-slate-800 py-4 whitespace-nowrap transition-colors">Prioritaires</button>
        <button className="text-sm font-semibold text-slate-500 hover:text-slate-800 py-4 whitespace-nowrap transition-colors">Réseaux Sociaux</button>
        <button className="text-sm font-semibold text-slate-500 hover:text-slate-800 py-4 whitespace-nowrap transition-colors">Promotions</button>
        <div className="ml-auto flex items-center gap-2 py-4">
          <button className="text-xs font-bold text-slate-400 flex items-center gap-1 hover:text-blue-600 transition-colors uppercase tracking-wider">
            Trier par <ChevronDown className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Email List */}
      <div className="flex-1 overflow-y-auto p-8 space-y-3 custom-scrollbar">
        <div className="max-w-5xl mx-auto space-y-3">
          {/* Date Separator */}
          <div className="flex items-center gap-4 py-2">
            <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Aujourd&apos;hui</span>
            <div className="flex-1 h-px bg-slate-200"></div>
          </div>

          {/* Email Card 1 (Unread/Urgent) */}
          <div className="bg-white p-4 rounded-2xl border border-slate-200 flex items-center gap-6 shadow-sm hover:shadow-md transition-all group cursor-pointer relative overflow-hidden">
            <div className="absolute left-0 top-0 bottom-0 w-1.5 bg-blue-600"></div>
            <div className="flex items-center gap-4 min-w-[200px]">
              <input type="checkbox" className="rounded text-blue-600 focus:ring-blue-500 border-slate-300 w-4 h-4" />
              <div className="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center font-bold text-blue-600 text-sm">JD</div>
              <p className="text-sm font-bold text-slate-900 truncate">Jean Dupont</p>
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 mb-1">
                <span className="bg-violet-100 text-violet-700 text-[10px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider">Urgent</span>
                <h4 className="text-sm font-bold text-slate-900 truncate">Révision du contrat trimestriel - Phase finale</h4>
              </div>
              <p className="text-xs text-slate-500 font-medium truncate">Bonjour Marc, veuillez trouver ci-joint la version mise à jour du contrat après nos derniers échanges...</p>
            </div>
            <div className="flex items-center gap-4 shrink-0">
              <Star className="w-5 h-5 text-slate-300 group-hover:text-amber-400 transition-colors" />
              <p className="text-[11px] font-bold text-slate-400 w-12 text-right">10:45</p>
            </div>
          </div>

          {/* Email Card 2 */}
          <div className="bg-white/60 hover:bg-white p-4 rounded-2xl border border-slate-100 hover:border-slate-200 flex items-center gap-6 shadow-sm hover:shadow-md transition-all group cursor-pointer">
            <div className="flex items-center gap-4 min-w-[200px]">
              <input type="checkbox" className="rounded text-blue-600 focus:ring-blue-500 border-slate-300 w-4 h-4" />
              <div className="w-10 h-10 rounded-full overflow-hidden bg-slate-200 relative">
                <Image src="https://api.dicebear.com/7.x/avataaars/svg?seed=Sophie" alt="Sophie" fill referrerPolicy="no-referrer" className="object-cover" />
              </div>
              <p className="text-sm font-semibold text-slate-700 truncate">Sophie Martin</p>
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 mb-1">
                <h4 className="text-sm font-bold text-slate-700 truncate">Réunion marketing - Rappel des rapports de performance</h4>
              </div>
              <p className="text-xs text-slate-500 font-medium truncate">N&apos;oubliez pas d&apos;apporter les rapports de performance pour la réunion de demain matin à 9h...</p>
            </div>
            <div className="flex items-center gap-4 shrink-0">
              <Star className="w-5 h-5 text-blue-500 fill-blue-500" />
              <p className="text-[11px] font-bold text-slate-400 w-12 text-right">09:12</p>
            </div>
          </div>

          {/* Date Separator */}
          <div className="flex items-center gap-4 py-4">
            <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Hier</span>
            <div className="flex-1 h-px bg-slate-200"></div>
          </div>

          {/* Email Card 3 */}
          <div className="bg-white/60 hover:bg-white p-4 rounded-2xl border border-slate-100 hover:border-slate-200 flex items-center gap-6 shadow-sm hover:shadow-md transition-all group cursor-pointer">
            <div className="flex items-center gap-4 min-w-[200px]">
              <input type="checkbox" className="rounded text-blue-600 focus:ring-blue-500 border-slate-300 w-4 h-4" />
              <div className="w-10 h-10 rounded-full overflow-hidden bg-slate-200 relative">
                <Image src="https://api.dicebear.com/7.x/avataaars/svg?seed=Sophie" alt="Sophie" fill referrerPolicy="no-referrer" className="object-cover" />
              </div>
              <p className="text-sm font-semibold text-slate-700 truncate">Sophie Martin</p>
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 mb-1">
                <h4 className="text-sm font-bold text-slate-700 truncate">Réunion marketing - Rappel des rapports de performance</h4>
              </div>
              <p className="text-xs text-slate-500 font-medium truncate">N&apos;oubliez pas d&apos;apporter les rapports de performance pour la réunion de demain matin à 9h...</p>
            </div>
            <div className="flex items-center gap-4 shrink-0">
              <Star className="w-5 h-5 text-blue-500 fill-blue-500" />
              <p className="text-[11px] font-bold text-slate-400 w-12 text-right">09:12</p>
            </div>
          </div>
        </div>
      </div>

      {/* Footer Pagination */}
      <footer className="h-16 bg-white border-t border-slate-200 flex items-center justify-between px-8 shrink-0">
        <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Affichage 1-50 sur 1,240 messages</p>
        <div className="flex items-center gap-4">
          <button className="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
            <ChevronLeft className="w-5 h-5" />
          </button>
          <span className="text-xs font-bold text-slate-700">Page 1 / 25</span>
          <button className="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
            <ChevronRight className="w-5 h-5" />
          </button>
        </div>
      </footer>
    </main>
  );
}
