import { Header } from '@/components/Header';
import { UserPlus, SlidersHorizontal, Pencil, MoreVertical, ChevronLeft, ChevronRight } from 'lucide-react';
import Link from 'next/link';

export default function Contacts() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header title="Gestion des Contacts" subtitle="Suivez vos relations commerciales et optimisez vos taux de conversion." />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-7xl mx-auto">
          {/* Page Title & Actions */}
          <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
            <div className="space-y-1">
              <h2 className="text-3xl font-extrabold tracking-tight text-slate-900">Gestion des Contacts</h2>
              <p className="text-slate-500 font-medium">Suivez vos relations commerciales et optimisez vos taux de conversion.</p>
            </div>
            <Link href="/contacts/create" className="btn-primary-gradient inline-flex items-center justify-center px-6 py-3 hover:opacity-90 text-white rounded-xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all gap-2">
              <UserPlus className="w-4 h-4" /> Ajouter un contact
            </Link>
          </div>

          {/* Filters */}
          <div className="flex flex-wrap gap-3 mb-6 items-center">
            <button className="px-5 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-bold uppercase tracking-wider">
              Tous les contacts
            </button>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-wider hover:border-blue-500 hover:text-blue-600 transition-all flex items-center gap-2 shadow-sm">
              Prospects chauds <span className="bg-blue-50 text-blue-600 px-2 py-0.5 rounded-md text-[10px]">12</span>
            </button>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-wider hover:border-violet-500 hover:text-violet-600 transition-all flex items-center gap-2 shadow-sm">
              Clients Premium <span className="bg-violet-50 text-violet-600 px-2 py-0.5 rounded-md text-[10px]">48</span>
            </button>
            <div className="flex-1"></div>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <SlidersHorizontal className="w-4 h-4" /> Filtres avancés
            </button>
          </div>

          {/* Table Card */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-200">
                    <th className="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-[0.1em]">Prénom & Nom</th>
                    <th className="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-[0.1em]">Poste & Organisation</th>
                    <th className="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-[0.1em]">Email</th>
                    <th className="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-[0.1em]">Lead Score</th>
                    <th className="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-[0.1em]">Statut</th>
                    <th className="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-[0.1em] text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  <ContactRow 
                    initials="JD"
                    name="Jean Dupont"
                    role="Directeur Commercial"
                    company="TechCorp Solutions"
                    email="jean.d@techcorp.fr"
                    score={85}
                    status="Client Premium"
                    statusColor="violet"
                  />
                  <ContactRow 
                    initials="ML"
                    name="Marie Lefebvre"
                    role="Responsable Marketing"
                    company="Innova Design"
                    email="m.lefebvre@innova.com"
                    score={92}
                    status="Prospect Chaud"
                    statusColor="blue"
                  />
                </tbody>
              </table>
            </div>
            
            {/* Pagination */}
            <div className="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
              <p className="text-xs font-bold text-slate-500 uppercase tracking-widest">Affichage 1-2 sur 248 contacts</p>
              <div className="flex items-center gap-2">
                <button className="p-1.5 text-slate-400 hover:text-slate-900 hover:bg-slate-200 rounded-lg transition-colors"><ChevronLeft className="w-5 h-5" /></button>
                <button className="w-8 h-8 rounded-lg bg-slate-900 text-white text-xs font-bold flex items-center justify-center">1</button>
                <button className="w-8 h-8 rounded-lg text-slate-600 hover:bg-slate-200 text-xs font-bold flex items-center justify-center transition-colors">2</button>
                <button className="w-8 h-8 rounded-lg text-slate-600 hover:bg-slate-200 text-xs font-bold flex items-center justify-center transition-colors">3</button>
                <button className="p-1.5 text-slate-400 hover:text-slate-900 hover:bg-slate-200 rounded-lg transition-colors"><ChevronRight className="w-5 h-5" /></button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}

function ContactRow({ initials, name, role, company, email, score, status, statusColor }: any) {
  const colorMap: any = {
    violet: 'bg-violet-50 text-violet-600 border-violet-200',
    blue: 'bg-blue-50 text-blue-600 border-blue-200',
  };
  const initialBgMap: any = {
    violet: 'bg-violet-100 text-violet-700',
    blue: 'bg-blue-100 text-blue-700',
  };

  return (
    <tr className="hover:bg-slate-50 transition-colors">
      <td className="px-6 py-5">
        <div className="flex items-center gap-4">
          <div className={`h-10 w-10 rounded-full flex items-center justify-center font-bold text-sm ${initialBgMap[statusColor] || 'bg-slate-100 text-slate-700'}`}>
            {initials}
          </div>
          <span className="text-sm font-bold text-slate-900">{name}</span>
        </div>
      </td>
      <td className="px-6 py-5">
        <div className="text-sm font-bold text-slate-900">{role}</div>
        <div className="text-xs text-slate-500 font-medium mt-0.5">{company}</div>
      </td>
      <td className="px-6 py-5 text-sm text-slate-600 font-medium">{email}</td>
      <td className="px-6 py-5">
        <div className="flex items-center gap-3 w-32">
          <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
            <div className="h-full btn-primary-gradient rounded-full" style={{ width: `${score}%` }}></div>
          </div>
          <span className="text-sm font-bold text-slate-900">{score}</span>
        </div>
      </td>
      <td className="px-6 py-5">
        <span className={`px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border ${colorMap[statusColor]}`}>
          {status}
        </span>
      </td>
      <td className="px-6 py-5 text-right">
        <button className="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors mx-0.5"><Pencil className="w-4 h-4" /></button>
        <button className="p-2 text-slate-400 hover:text-slate-900 hover:bg-slate-100 rounded-lg transition-colors mx-0.5"><MoreVertical className="w-4 h-4" /></button>
      </td>
    </tr>
  );
}
