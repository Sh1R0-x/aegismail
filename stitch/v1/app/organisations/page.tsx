import { Header } from '@/components/Header';
import { Building2, Mail, Users, Filter, Plus, MoreVertical, Edit2, ChevronLeft, ChevronRight } from 'lucide-react';
import Link from 'next/link';

export default function Organisations() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Gestion des Organisations" 
        actions={
          <Link href="/organisations/create" className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
            <Plus className="w-4 h-4" /> Nouvelle Organisation
          </Link>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-7xl mx-auto space-y-6">
          {/* Stats Cards */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            <StatCard 
              icon={<Building2 className="w-5 h-5 text-blue-600" />}
              iconBg="bg-blue-50"
              title="Organisations Actives"
              value="1,284"
              trend="+12%"
              trendColor="text-emerald-600 bg-emerald-50"
            />
            <StatCard 
              icon={<Mail className="w-5 h-5 text-blue-600" />}
              iconBg="bg-blue-50"
              title="Volume Total Mensuel"
              value="4.2M"
              trend="Stable"
              trendColor="text-slate-600 bg-slate-100"
            />
            <StatCard 
              icon={<Users className="w-5 h-5 text-violet-600" />}
              iconBg="bg-violet-50"
              title="Total Contacts"
              value="892,400"
              trend="+5.4k"
              trendColor="text-violet-600 bg-violet-50"
            />
          </div>

          {/* Filters and Search */}
          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row gap-4">
            <div className="relative flex-1 max-w-md">
              <SearchIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 w-4 h-4" />
              <input 
                type="text" 
                placeholder="Rechercher une organisation, un domaine..." 
                className="w-full pl-10 pr-4 py-2.5 bg-slate-100 border-none rounded-xl text-sm focus:ring-2 focus:ring-blue-500/30 font-medium placeholder:text-slate-400"
              />
            </div>
            <div className="flex items-center gap-3">
              <button className="flex items-center gap-2 px-5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors text-slate-700 shadow-sm">
                <Filter className="w-4 h-4" /> Industrie
              </button>
              <button className="flex items-center gap-2 px-5 py-2.5 border border-slate-200 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors text-slate-700 shadow-sm">
                <Filter className="w-4 h-4" /> Statut
              </button>
            </div>
          </div>

          {/* Table Card */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-200">
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest">Nom de domaine</th>
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest">Industrie</th>
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest">Nb de contacts</th>
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest">Volume d&apos;envoi</th>
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest">Dernière activité</th>
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  <OrgRow 
                    initials="NL"
                    domain="nexalabs.io"
                    name="Nexa Labs SAS"
                    industry="Technologie"
                    industryColor="blue"
                    contacts="45,200"
                    volume="128k"
                    volumeMax="200k"
                    volumePercent={64}
                    activity="Il y a 2h"
                  />
                  <OrgRow 
                    initials="EV"
                    domain="evergreen.fr"
                    name="Evergreen Retail"
                    industry="E-commerce"
                    industryColor="green"
                    contacts="124,800"
                    volume="480k"
                    volumeMax="500k"
                    volumePercent={96}
                    activity="Hier, 16:45"
                  />
                  <OrgRow 
                    initials="AS"
                    domain="astrium.space"
                    name="Astrium Global"
                    industry="Aérospatiale"
                    industryColor="purple"
                    contacts="8,100"
                    volume="5k"
                    volumeMax="50k"
                    volumePercent={10}
                    activity="Il y a 4 jours"
                  />
                </tbody>
              </table>
            </div>
            
            <div className="px-6 py-4 bg-slate-50 flex items-center justify-between border-t border-slate-200">
              <p className="text-xs text-slate-500 font-bold uppercase tracking-widest">Affichage de 1 à 3 sur 1,284 organisations</p>
              <div className="flex gap-2">
                <button className="p-2 border border-slate-200 rounded-xl bg-white text-slate-400 disabled:opacity-50 hover:bg-slate-50 transition-colors shadow-sm" disabled>
                  <ChevronLeft className="w-5 h-5" />
                </button>
                <button className="p-2 border border-slate-200 rounded-xl bg-white text-slate-600 hover:bg-slate-50 transition-colors shadow-sm">
                  <ChevronRight className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}

function StatCard({ icon, iconBg, title, value, trend, trendColor }: any) {
  return (
    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:border-blue-200 transition-colors">
      <div className="flex items-center justify-between mb-4">
        <div className={`p-2.5 rounded-xl ${iconBg}`}>{icon}</div>
        <span className={`text-xs font-black px-2.5 py-1 rounded-md ${trendColor}`}>{trend}</span>
      </div>
      <p className="text-slate-500 text-sm font-bold mb-1">{title}</p>
      <h3 className="text-3xl font-black text-slate-900 tracking-tight">{value}</h3>
    </div>
  );
}

function OrgRow({ initials, domain, name, industry, industryColor, contacts, volume, volumeMax, volumePercent, activity }: any) {
  const colorMap: any = {
    blue: 'bg-blue-50 text-blue-600',
    green: 'bg-emerald-50 text-emerald-600',
    purple: 'bg-violet-50 text-violet-600',
  };

  return (
    <tr className="hover:bg-slate-50 transition-colors">
      <td className="px-6 py-5">
        <div className="flex items-center gap-4">
          <div className="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center font-black text-blue-600 text-sm">
            {initials}
          </div>
          <div>
            <p className="font-bold text-sm text-slate-900">{domain}</p>
            <p className="text-xs text-slate-500 font-medium mt-0.5">{name}</p>
          </div>
        </div>
      </td>
      <td className="px-6 py-5">
        <span className={`px-3 py-1 rounded-md text-[10px] font-black uppercase tracking-wider ${colorMap[industryColor]}`}>
          {industry}
        </span>
      </td>
      <td className="px-6 py-5 text-sm font-bold text-slate-900">{contacts}</td>
      <td className="px-6 py-5">
        <div className="w-32">
          <div className="flex justify-between text-[10px] mb-1.5 font-bold">
            <span className="text-slate-900">{volume}</span>
            <span className="text-slate-400">/ {volumeMax}</span>
          </div>
          <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
            <div className="h-full btn-primary-gradient rounded-full" style={{ width: `${volumePercent}%` }}></div>
          </div>
        </div>
      </td>
      <td className="px-6 py-5 text-sm font-medium text-slate-500">{activity}</td>
      <td className="px-6 py-5 text-right">
        <div className="flex items-center justify-end gap-2">
          <button className="p-2 border border-slate-200 rounded-xl bg-white text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors shadow-sm">
            <Edit2 className="w-4 h-4" />
          </button>
          <button className="p-2 border border-slate-200 rounded-xl bg-white text-slate-400 hover:text-slate-900 hover:bg-slate-50 transition-colors shadow-sm">
            <MoreVertical className="w-4 h-4" />
          </button>
        </div>
      </td>
    </tr>
  );
}

// Simple Search Icon component since it's used inline
function SearchIcon(props: any) {
  return (
    <svg {...props} xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
      <circle cx="11" cy="11" r="8"></circle>
      <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </svg>
  );
}
