import { Header } from '@/components/Header';
import Image from 'next/image';
import { FileText, Plus, Copy, Eye, MoreVertical, Search } from 'lucide-react';
import Link from 'next/link';

export default function Templates() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Mes Modèles" 
        subtitle="Gérez vos templates d'emails réutilisables."
        actions={
          <Link href="/templates/create" className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
            <Plus className="w-4 h-4" /> Créer un modèle
          </Link>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-7xl mx-auto">
          
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8 border-b border-slate-200 pb-4">
            <div className="flex items-center gap-6">
              <button className="text-sm font-bold text-blue-600 border-b-2 border-blue-600 pb-4 -mb-[18px]">Tous les modèles</button>
              <button className="text-sm font-semibold text-slate-500 hover:text-slate-800 pb-4 -mb-[18px] transition-colors">Newsletters</button>
              <button className="text-sm font-semibold text-slate-500 hover:text-slate-800 pb-4 -mb-[18px] transition-colors">Prospection</button>
              <button className="text-sm font-semibold text-slate-500 hover:text-slate-800 pb-4 -mb-[18px] transition-colors">Transactionnel</button>
            </div>
            <div className="relative w-full md:w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
              <input 
                type="text" 
                placeholder="Rechercher un modèle..." 
                className="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/30 outline-none shadow-sm"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <TemplateCard 
              title="Newsletter Standard V2"
              category="Newsletters"
              image="https://images.unsplash.com/photo-1586281380349-632531db7ed4?q=80&w=400&auto=format&fit=crop"
            />
            <TemplateCard 
              title="Prospection Cold Email - A"
              category="Prospection"
              image="https://images.unsplash.com/photo-1555421689-491a97ff2040?q=80&w=400&auto=format&fit=crop"
            />
            <TemplateCard 
              title="Invitation Webinaire"
              category="Événements"
              image="https://images.unsplash.com/photo-1540317580384-e5d43867caa6?q=80&w=400&auto=format&fit=crop"
            />
            <TemplateCard 
              title="Bienvenue Nouveau Client"
              category="Transactionnel"
              image="https://images.unsplash.com/photo-1557200134-90327ee9fafa?q=80&w=400&auto=format&fit=crop"
            />
          </div>

        </div>
      </div>
    </main>
  );
}

function TemplateCard({ title, category, image }: any) {
  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden flex flex-col">
      <div className="h-40 bg-slate-100 relative overflow-hidden">
        <Image src={image} alt={title} fill referrerPolicy="no-referrer" className="object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500" />
        <div className="absolute inset-0 bg-slate-900/0 group-hover:bg-slate-900/40 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
          <button className="bg-white text-slate-900 px-4 py-2 rounded-lg text-sm font-bold shadow-lg hover:bg-blue-50 hover:text-blue-600 transition-colors flex items-center gap-2">
            <Eye className="w-4 h-4" /> Aperçu
          </button>
        </div>
      </div>
      <div className="p-5 flex-1 flex flex-col">
        <span className="text-[10px] font-black text-blue-600 uppercase tracking-wider mb-2">{category}</span>
        <h3 className="text-base font-bold text-slate-900 mb-4 line-clamp-2">{title}</h3>
        
        <div className="mt-auto flex items-center justify-between pt-4 border-t border-slate-100">
          <button className="text-xs font-bold text-slate-500 hover:text-blue-600 flex items-center gap-1.5 transition-colors">
            <Copy className="w-3.5 h-3.5" /> Dupliquer
          </button>
          <button className="p-1.5 text-slate-400 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors">
            <MoreVertical className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
