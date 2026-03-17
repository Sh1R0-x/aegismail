import { Header } from '@/components/Header';
import { Activity, ArrowUpRight, ArrowDownRight, Users, Mail, MousePointer2, AlertTriangle } from 'lucide-react';

export default function ActivityFeed() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header title="Flux d'activité & Analytiques" subtitle="Surveillez les performances et les événements en temps réel." />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-7xl mx-auto space-y-8">
          
          {/* Top Stats */}
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <p className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Score de Santé</p>
              <div className="flex items-end gap-3">
                <span className="text-4xl font-black text-emerald-600">98<span className="text-2xl text-emerald-400">/100</span></span>
              </div>
              <div className="mt-4 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                <div className="h-full bg-emerald-500 w-[98%] rounded-full"></div>
              </div>
            </div>
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <p className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Taux de Rebond</p>
              <div className="flex items-end gap-3">
                <span className="text-3xl font-black text-slate-900">0.4%</span>
                <span className="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md mb-1 flex items-center"><ArrowDownRight className="w-3 h-3 mr-0.5"/> 0.1%</span>
              </div>
            </div>
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <p className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Plaintes Spam</p>
              <div className="flex items-end gap-3">
                <span className="text-3xl font-black text-slate-900">0.01%</span>
                <span className="text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded-md mb-1 flex items-center">Stable</span>
              </div>
            </div>
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <p className="text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Désabonnements</p>
              <div className="flex items-end gap-3">
                <span className="text-3xl font-black text-slate-900">1.2%</span>
                <span className="text-xs font-bold text-rose-600 bg-rose-50 px-2 py-1 rounded-md mb-1 flex items-center"><ArrowUpRight className="w-3 h-3 mr-0.5"/> 0.3%</span>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {/* Activity Timeline */}
            <div className="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
              <h3 className="text-lg font-bold text-slate-900 mb-6">Journal des événements</h3>
              
              <div className="space-y-0">
                <TimelineItem 
                  icon={<Mail className="w-4 h-4 text-blue-600" />}
                  iconBg="bg-blue-100"
                  title="Campagne &quot;Promo Été&quot; démarrée"
                  desc="Envoi en cours à 45,000 destinataires."
                  time="Aujourd'hui, 09:00"
                />
                <TimelineItem 
                  icon={<MousePointer2 className="w-4 h-4 text-violet-600" />}
                  iconBg="bg-violet-100"
                  title="Pic de clics détecté"
                  desc="Taux de clic anormalement élevé sur le lien #3 de la campagne Q2."
                  time="Aujourd'hui, 08:15"
                />
                <TimelineItem 
                  icon={<AlertTriangle className="w-4 h-4 text-amber-600" />}
                  iconBg="bg-amber-100"
                  title="Avertissement de délivrabilité"
                  desc="Léger retard détecté sur les serveurs de réception Microsoft (Outlook/Hotmail)."
                  time="Hier, 14:30"
                />
                <TimelineItem 
                  icon={<Users className="w-4 h-4 text-emerald-600" />}
                  iconBg="bg-emerald-100"
                  title="Import de liste terminé"
                  desc="Liste &quot;Salon Tech 2024&quot; importée avec succès (1,200 contacts)."
                  time="Hier, 11:00"
                  isLast={true}
                />
              </div>
            </div>

            {/* Top Links / Domains */}
            <div className="space-y-8">
              <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h3 className="text-lg font-bold text-slate-900 mb-6">Domaines de réception</h3>
                <div className="space-y-4">
                  <DomainStat domain="gmail.com" percent={45} />
                  <DomainStat domain="outlook.com" percent={25} />
                  <DomainStat domain="yahoo.com" percent={15} />
                  <DomainStat domain="orange.fr" percent={10} />
                  <DomainStat domain="Autres" percent={5} />
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </main>
  );
}

function TimelineItem({ icon, iconBg, title, desc, time, isLast }: any) {
  return (
    <div className="flex gap-4 relative">
      {!isLast && <div className="absolute top-8 bottom-[-16px] left-[15px] w-0.5 bg-slate-100"></div>}
      <div className={`w-8 h-8 rounded-full ${iconBg} flex items-center justify-center shrink-0 z-10 ring-4 ring-white mt-1`}>
        {icon}
      </div>
      <div className="pb-8">
        <div className="flex items-center gap-3 mb-1">
          <h4 className="text-sm font-bold text-slate-900">{title}</h4>
          <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{time}</span>
        </div>
        <p className="text-sm text-slate-500 font-medium">{desc}</p>
      </div>
    </div>
  );
}

function DomainStat({ domain, percent }: any) {
  return (
    <div>
      <div className="flex justify-between text-sm font-bold mb-1.5">
        <span className="text-slate-700">{domain}</span>
        <span className="text-slate-900">{percent}%</span>
      </div>
      <div className="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
        <div className="h-full bg-slate-400 rounded-full" style={{ width: `${percent}%` }}></div>
      </div>
    </div>
  );
}
