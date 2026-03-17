import { Header } from '@/components/Header';
import { FileEdit, Plus, MoreVertical, Clock, Trash2 } from 'lucide-react';
import Link from 'next/link';

export default function Drafts() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Brouillons" 
        subtitle="Reprenez la rédaction de vos campagnes non terminées."
        actions={
          <Link href="/compose" className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
            <Plus className="w-4 h-4" /> Nouveau Message
          </Link>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-5xl mx-auto">
          
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <DraftCard 
              title="Invitation Événement Paris 2024"
              lastEdited="Modifié il y a 2 heures"
              preview="Bonjour {{firstName}}, nous avons le plaisir de vous inviter à notre événement exclusif..."
            />
            <DraftCard 
              title="Mise à jour des CGV - Q3"
              lastEdited="Modifié hier"
              preview="Chère cliente, cher client, nous vous informons d'une mise à jour de nos conditions..."
            />
            <DraftCard 
              title="Promo Flash - Black Friday B2B"
              lastEdited="Modifié le 12 Juin"
              preview="Profitez de nos offres exceptionnelles sur l'ensemble de notre catalogue..."
            />
            <DraftCard 
              title="Newsletter Interne - Juillet"
              lastEdited="Modifié le 10 Juin"
              preview="Voici les dernières actualités de l'entreprise pour ce mois de juillet..."
            />
          </div>

        </div>
      </div>
    </main>
  );
}

function DraftCard({ title, lastEdited, preview }: any) {
  return (
    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all group flex flex-col h-full cursor-pointer relative overflow-hidden">
      <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-slate-200 to-slate-100 group-hover:from-blue-500 group-hover:to-violet-500 transition-all"></div>
      
      <div className="flex items-start justify-between mb-4">
        <div className="p-3 bg-slate-50 text-slate-400 rounded-xl group-hover:bg-blue-50 group-hover:text-blue-600 transition-colors">
          <FileEdit className="w-6 h-6" />
        </div>
        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button className="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
            <Trash2 className="w-4 h-4" />
          </button>
        </div>
      </div>
      
      <h3 className="text-lg font-bold text-slate-900 mb-2 line-clamp-2">{title}</h3>
      <p className="text-sm text-slate-500 font-medium line-clamp-3 mb-6 flex-1">{preview}</p>
      
      <div className="flex items-center gap-2 text-xs font-bold text-slate-400 mt-auto pt-4 border-t border-slate-100">
        <Clock className="w-3.5 h-3.5" />
        {lastEdited}
      </div>
    </div>
  );
}
