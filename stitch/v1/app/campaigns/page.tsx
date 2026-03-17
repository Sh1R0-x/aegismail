import { Header } from '@/components/Header';
import { Send, Plus, Filter, MoreVertical, Play, Pause, BarChart2 } from 'lucide-react';
import Link from 'next/link';

export default function Campaigns() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Campagnes" 
        subtitle="Gérez et analysez vos envois d'emails en masse."
        actions={
          <Link href="/campaigns/create" className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
            <Plus className="w-4 h-4" /> Nouvelle Campagne
          </Link>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-7xl mx-auto space-y-6">
          
          {/* Filters */}
          <div className="flex flex-wrap gap-3 items-center">
            <button className="px-5 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-bold uppercase tracking-wider">
              Toutes
            </button>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-wider hover:border-emerald-500 hover:text-emerald-600 transition-all shadow-sm">
              En cours
            </button>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-wider hover:border-blue-500 hover:text-blue-600 transition-all shadow-sm">
              Planifiées
            </button>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-wider hover:border-slate-500 hover:text-slate-900 transition-all shadow-sm">
              Terminées
            </button>
            <div className="flex-1"></div>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-xs font-bold uppercase tracking-wider hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <Filter className="w-4 h-4" /> Filtres
            </button>
          </div>

          {/* Campaigns List */}
          <div className="space-y-4">
            <CampaignCard 
              status="En cours"
              statusColor="emerald"
              title="Newsletter Mensuelle - Juin 2024"
              date="Aujourd'hui, 09:00"
              sent="45,200"
              total="124,000"
              openRate="18.5%"
              clickRate="2.1%"
            />
            <CampaignCard 
              status="Planifiée"
              statusColor="blue"
              title="Lancement Produit V2 - Invitation Webinaire"
              date="Demain, 14:30"
              sent="0"
              total="85,500"
              openRate="-"
              clickRate="-"
            />
            <CampaignCard 
              status="Terminée"
              statusColor="slate"
              title="Relance Inactifs Q1"
              date="12 Juin 2024, 10:00"
              sent="32,100"
              total="32,100"
              openRate="24.8%"
              clickRate="4.2%"
            />
          </div>

        </div>
      </div>
    </main>
  );
}

function CampaignCard({ status, statusColor, title, date, sent, total, openRate, clickRate }: any) {
  const colorMap: any = {
    emerald: 'bg-emerald-50 text-emerald-600 border-emerald-200',
    blue: 'bg-blue-50 text-blue-600 border-blue-200',
    slate: 'bg-slate-100 text-slate-600 border-slate-200',
  };

  const isRunning = status === 'En cours';
  const progress = isRunning ? (parseInt(sent.replace(/,/g, '')) / parseInt(total.replace(/,/g, ''))) * 100 : (status === 'Terminée' ? 100 : 0);

  return (
    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
        
        <div className="flex-1">
          <div className="flex items-center gap-3 mb-2">
            <span className={`px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border ${colorMap[statusColor]}`}>
              {status}
            </span>
            <span className="text-xs font-bold text-slate-400">{date}</span>
          </div>
          <h3 className="text-lg font-bold text-slate-900 group-hover:text-blue-600 transition-colors">{title}</h3>
          
          {isRunning && (
            <div className="mt-4 max-w-md">
              <div className="flex justify-between text-xs font-bold mb-1.5">
                <span className="text-blue-600">Envoi en cours...</span>
                <span className="text-slate-500">{sent} / {total}</span>
              </div>
              <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                <div className="h-full bg-blue-600 rounded-full relative overflow-hidden" style={{ width: `${progress}%` }}>
                  <div className="absolute inset-0 bg-white/20 animate-[shimmer_1s_infinite] -skew-x-12"></div>
                </div>
              </div>
            </div>
          )}
          {!isRunning && (
             <p className="text-sm text-slate-500 font-medium mt-1">Destinataires: {total}</p>
          )}
        </div>

        <div className="flex items-center gap-8 md:border-l border-slate-100 md:pl-8">
          <div className="text-center">
            <p className="text-2xl font-black text-slate-900">{openRate}</p>
            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Ouverture</p>
          </div>
          <div className="text-center">
            <p className="text-2xl font-black text-slate-900">{clickRate}</p>
            <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Clics</p>
          </div>
          
          <div className="flex items-center gap-2 ml-4">
            {isRunning ? (
              <button className="p-2.5 bg-amber-50 text-amber-600 hover:bg-amber-100 rounded-xl transition-colors">
                <Pause className="w-5 h-5" />
              </button>
            ) : status === 'Planifiée' ? (
              <button className="p-2.5 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-xl transition-colors">
                <Play className="w-5 h-5" />
              </button>
            ) : (
              <button className="p-2.5 bg-slate-50 text-slate-600 hover:bg-slate-100 hover:text-blue-600 rounded-xl transition-colors">
                <BarChart2 className="w-5 h-5" />
              </button>
            )}
            <button className="p-2.5 bg-slate-50 text-slate-400 hover:text-slate-900 hover:bg-slate-100 rounded-xl transition-colors">
              <MoreVertical className="w-5 h-5" />
            </button>
          </div>
        </div>

      </div>
    </div>
  );
}
