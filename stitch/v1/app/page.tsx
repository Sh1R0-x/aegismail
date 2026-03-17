import { Header } from '@/components/Header';
import { 
  Mail, 
  Eye, 
  MousePointer2, 
  CheckCircle, 
  ArrowUpRight, 
  ArrowDownRight,
  Plus,
  Calendar,
  HelpCircle,
  Check,
  AlertCircle,
  UserPlus
} from 'lucide-react';
import Link from 'next/link';

export default function Dashboard() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Tableau de Bord" 
        subtitle="Vue d'ensemble de votre performance de mailing"
        actions={
          <Link href="/campaigns/create" className="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:opacity-90 transition-all flex items-center gap-2 shadow-lg shadow-blue-500/20">
            <Plus className="w-4 h-4" />
            Nouvelle Campagne
          </Link>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        {/* Key Performance Metrics */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <MetricCard 
            title="Mails Envoyés" 
            value="124,802" 
            trend="+12.5%" 
            trendUp={true} 
            icon={<Mail className="w-5 h-5 text-blue-600" />} 
            iconBg="bg-blue-50" 
          />
          <MetricCard 
            title="Taux d'Ouverture" 
            value="24.8%" 
            trend="+4.2%" 
            trendUp={true} 
            icon={<Eye className="w-5 h-5 text-emerald-600" />} 
            iconBg="bg-emerald-50" 
          />
          <MetricCard 
            title="Taux de Clic" 
            value="3.12%" 
            trend="-0.8%" 
            trendUp={false} 
            icon={<MousePointer2 className="w-5 h-5 text-violet-600" />} 
            iconBg="bg-violet-50" 
          />
          <MetricCard 
            title="Délivrabilité" 
            value="99.4%" 
            trend="+0.1%" 
            trendUp={true} 
            icon={<CheckCircle className="w-5 h-5 text-indigo-600" />} 
            iconBg="bg-indigo-50" 
          />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Main Chart Section & Activity */}
          <div className="lg:col-span-2 space-y-8">
            <section className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
              <div className="flex items-center justify-between mb-8">
                <div>
                  <h3 className="font-bold text-slate-900 text-lg">Volume d&apos;Envois Hebdomadaire</h3>
                  <p className="text-sm text-slate-500 mt-1">Données des 7 derniers jours</p>
                </div>
                <select className="text-sm bg-slate-50 border border-slate-200 rounded-xl focus:ring-blue-500 px-4 py-2 font-medium text-slate-700 outline-none">
                  <option>7 derniers jours</option>
                  <option>30 derniers jours</option>
                </select>
              </div>
              
              {/* Mock Bar Chart */}
              <div className="h-64 relative flex items-end justify-between gap-4 px-2">
                {[40, 65, 55, 85, 45, 75, 60].map((height, i) => (
                  <div key={i} className="w-full relative group flex flex-col justify-end h-full">
                    <div 
                      className={`w-full rounded-t-lg transition-all duration-300 ${i === 3 ? 'bg-blue-600' : 'bg-slate-100 hover:bg-blue-100'}`}
                      style={{ height: `${height}%` }}
                    >
                      <div className="absolute -top-8 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-xs font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                        {Math.round(height * 280)}
                      </div>
                    </div>
                  </div>
                ))}
              </div>
              <div className="flex justify-between mt-4 px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                <span>Lun</span><span>Mar</span><span>Mer</span><span>Jeu</span><span>Ven</span><span>Sam</span><span>Dim</span>
              </div>
            </section>

            <section className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
              <h3 className="font-bold text-slate-900 text-lg mb-6">Activité Récente</h3>
              <div className="space-y-6">
                <ActivityItem 
                  icon={<Check className="w-5 h-5 text-blue-600" />}
                  iconBg="bg-blue-50"
                  title="Campagne &quot;Soldes d'Été&quot; terminée"
                  time="Il y a 2h"
                  desc="45,000 emails envoyés avec un succès de 99.8%."
                  isLast={false}
                />
                <ActivityItem 
                  icon={<AlertCircle className="w-5 h-5 text-violet-600" />}
                  iconBg="bg-violet-50"
                  title="Alerte Serveur SMTP #04"
                  time="Il y a 5h"
                  desc="Latence élevée détectée sur le nœud de Francfort."
                  isLast={false}
                />
                <ActivityItem 
                  icon={<UserPlus className="w-5 h-5 text-emerald-600" />}
                  iconBg="bg-emerald-50"
                  title="Import de contacts réussi"
                  time="Hier, 18:30"
                  desc="1,240 nouveaux contacts ajoutés à la liste &quot;Prospects B2B&quot;."
                  isLast={true}
                />
              </div>
              <button className="w-full mt-6 py-3 text-sm text-slate-600 font-bold hover:text-blue-600 transition-colors border-t border-slate-100 bg-slate-50/50 hover:bg-slate-50 rounded-b-xl -mb-6">
                Voir tout l&apos;historique
              </button>
            </section>
          </div>

          {/* Right Sidebar Widgets */}
          <div className="space-y-8">
            <section className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
              <h3 className="font-bold text-slate-900 text-lg mb-6">Statut des Serveurs</h3>
              <div className="space-y-3">
                <ServerStatus name="SMTP-Node-01" status="Actif" color="emerald" />
                <ServerStatus name="SMTP-Node-02" status="Actif" color="emerald" />
                <ServerStatus name="SMTP-Node-03" status="Lent" color="violet" />
              </div>
              <div className="mt-6 pt-6 border-t border-slate-100">
                <div className="flex justify-between text-xs font-semibold text-slate-500 mb-3">
                  <span>Stockage Contacts</span>
                  <span className="text-slate-900">85% <span className="text-slate-400 font-normal">(850k / 1M)</span></span>
                </div>
                <div className="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                  <div className="bg-blue-600 h-full w-[85%] rounded-full"></div>
                </div>
              </div>
            </section>

            <section className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
              <div className="flex items-center justify-between mb-6">
                <h3 className="font-bold text-slate-900 text-lg">Campagnes Planifiées</h3>
                <Calendar className="w-5 h-5 text-slate-400" />
              </div>
              <div className="space-y-4">
                <ScheduledCampaign 
                  time="Demain, 09:00" 
                  title="Newsletter Hebdomadaire - Juin S3" 
                  list="Tous les abonnés (142k)" 
                />
                <hr className="border-slate-100" />
                <ScheduledCampaign 
                  time="24 Juin, 14:00" 
                  title="Relance Panier Abandonné" 
                  list="Automatisé (Segments Dynamiques)" 
                />
              </div>
              <button className="w-full mt-6 bg-white border border-slate-200 py-3 rounded-xl text-sm font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                Gérer le calendrier
              </button>
            </section>

            <div className="btn-primary-gradient p-8 rounded-2xl shadow-lg text-white relative overflow-hidden">
              <div className="relative z-10">
                <h4 className="font-bold text-xl mb-3">Besoin d&apos;aide ?</h4>
                <p className="text-blue-100 text-sm mb-6 leading-relaxed font-medium">Notre support technique est disponible 24/7 pour optimiser votre délivrabilité et vos performances.</p>
                <button className="bg-white text-blue-600 px-6 py-3 rounded-xl text-sm font-bold shadow-md hover:bg-blue-50 transition-all w-full">
                  Contacter le Support
                </button>
              </div>
              <HelpCircle className="w-32 h-32 absolute -bottom-6 -right-6 text-white/10" />
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}

function MetricCard({ title, value, trend, trendUp, icon, iconBg }: any) {
  return (
    <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col justify-between hover:border-blue-200 transition-colors">
      <div className="flex items-center justify-between mb-4">
        <span className="text-slate-500 text-sm font-bold">{title}</span>
        <div className={`p-2.5 rounded-xl ${iconBg}`}>{icon}</div>
      </div>
      <div>
        <div className="text-3xl font-black text-slate-900 tracking-tight">{value}</div>
        <div className="flex items-center gap-1.5 mt-2">
          <span className={`text-xs font-bold flex items-center ${trendUp ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'} px-2 py-0.5 rounded-md`}>
            {trendUp ? <ArrowUpRight className="w-3 h-3 mr-0.5" /> : <ArrowDownRight className="w-3 h-3 mr-0.5" />}
            {trend}
          </span>
          <span className="text-slate-400 text-xs font-medium">vs mois dernier</span>
        </div>
      </div>
    </div>
  );
}

function ActivityItem({ icon, iconBg, title, time, desc, isLast }: any) {
  return (
    <div className="flex gap-4">
      <div className="relative">
        <div className={`w-10 h-10 rounded-full ${iconBg} flex items-center justify-center z-10 relative ring-4 ring-white`}>
          {icon}
        </div>
        {!isLast && <div className="absolute top-10 bottom-[-24px] left-1/2 -translate-x-1/2 w-0.5 bg-slate-100"></div>}
      </div>
      <div className="flex-1 pb-2">
        <div className="flex justify-between items-start">
          <p className="text-sm font-bold text-slate-900">{title}</p>
          <span className="text-xs font-medium text-slate-400">{time}</span>
        </div>
        <p className="text-sm text-slate-500 mt-1">{desc}</p>
      </div>
    </div>
  );
}

function ServerStatus({ name, status, color }: any) {
  const bgColors: any = { emerald: 'bg-emerald-50', violet: 'bg-violet-50' };
  const textColors: any = { emerald: 'text-emerald-600', violet: 'text-violet-600' };
  const dotColors: any = { emerald: 'bg-emerald-500', violet: 'bg-violet-500' };

  return (
    <div className="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-100">
      <div className="flex items-center gap-3">
        <div className={`w-2 h-2 rounded-full ${dotColors[color]} ${status === 'Actif' ? 'animate-pulse' : ''}`}></div>
        <span className="text-sm font-bold text-slate-700">{name}</span>
      </div>
      <span className={`text-[10px] font-black ${textColors[color]} ${bgColors[color]} px-2.5 py-1 rounded-md uppercase tracking-wider`}>
        {status}
      </span>
    </div>
  );
}

function ScheduledCampaign({ time, title, list }: any) {
  return (
    <div className="group cursor-pointer p-3 -mx-3 rounded-xl hover:bg-slate-50 transition-colors">
      <div className="flex justify-between text-xs font-semibold text-slate-400 mb-1.5">
        <span>{time}</span>
        <span className="group-hover:text-blue-600 font-bold transition-colors">Éditer</span>
      </div>
      <p className="text-sm font-bold text-slate-900 line-clamp-1">{title}</p>
      <p className="text-xs text-slate-500 mt-0.5 font-medium">Liste: {list}</p>
    </div>
  );
}
