import { Header } from '@/components/Header';
import { Send, Save, ChevronLeft, Users, FileText, Settings, CheckCircle2 } from 'lucide-react';
import Link from 'next/link';

export default function CreateCampaign() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Nouvelle Campagne" 
        subtitle="Configurez et lancez votre campagne d'emailing."
        actions={
          <div className="flex items-center gap-3">
            <Link href="/campaigns" className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <ChevronLeft className="w-4 h-4" /> Annuler
            </Link>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <Save className="w-4 h-4" /> Enregistrer le brouillon
            </button>
            <button className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
              Continuer <Send className="w-4 h-4 ml-1" />
            </button>
          </div>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-4xl mx-auto space-y-8">
          
          {/* Stepper */}
          <div className="flex items-center justify-between relative">
            <div className="absolute left-0 top-1/2 -translate-y-1/2 w-full h-1 bg-slate-200 rounded-full -z-10"></div>
            <div className="absolute left-0 top-1/2 -translate-y-1/2 w-1/4 h-1 bg-blue-600 rounded-full -z-10"></div>
            
            <Step icon={<Settings className="w-5 h-5" />} title="Configuration" active={true} completed={false} />
            <Step icon={<Users className="w-5 h-5" />} title="Audience" active={false} completed={false} />
            <Step icon={<FileText className="w-5 h-5" />} title="Contenu" active={false} completed={false} />
            <Step icon={<CheckCircle2 className="w-5 h-5" />} title="Confirmation" active={false} completed={false} />
          </div>

          {/* Configuration Form */}
          <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm space-y-8">
            <div>
              <h3 className="text-lg font-bold text-slate-900 mb-1">Informations générales</h3>
              <p className="text-sm text-slate-500 mb-6">Définissez les paramètres de base de votre campagne.</p>
              
              <div className="space-y-5">
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nom de la campagne (interne)</label>
                  <input type="text" placeholder="Ex: Promo Été 2024" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nom de l&apos;expéditeur</label>
                    <input type="text" placeholder="Ex: Jean de AEGIS" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email de l&apos;expéditeur</label>
                    <select className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none">
                      <option>jean.d@aegis-mailing.com</option>
                      <option>contact@aegis-mailing.com</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>

            <div className="pt-8 border-t border-slate-100">
              <h3 className="text-lg font-bold text-slate-900 mb-1">Paramètres d&apos;envoi</h3>
              <p className="text-sm text-slate-500 mb-6">Configurez le suivi et l&apos;optimisation.</p>
              
              <div className="space-y-4">
                <label className="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                  <input type="checkbox" defaultChecked className="mt-1 rounded text-blue-600 focus:ring-blue-500 border-slate-300 w-4 h-4" />
                  <div>
                    <span className="block text-sm font-bold text-slate-900">Suivi des ouvertures et des clics</span>
                    <span className="block text-xs text-slate-500 mt-0.5">Insère un pixel de suivi et réécrit les liens pour analyser l&apos;engagement.</span>
                  </div>
                </label>
                
                <label className="flex items-start gap-3 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors">
                  <input type="checkbox" className="mt-1 rounded text-blue-600 focus:ring-blue-500 border-slate-300 w-4 h-4" />
                  <div>
                    <span className="block text-sm font-bold text-slate-900">A/B Testing (Objet)</span>
                    <span className="block text-xs text-slate-500 mt-0.5">Testez deux objets différents sur un échantillon avant l&apos;envoi global.</span>
                  </div>
                </label>
              </div>
            </div>
          </div>

        </div>
      </div>
    </main>
  );
}

function Step({ icon, title, active, completed }: any) {
  return (
    <div className="flex flex-col items-center gap-2 bg-slate-50 px-2">
      <div className={`w-10 h-10 rounded-full flex items-center justify-center border-2 transition-colors ${
        active ? 'bg-blue-600 border-blue-600 text-white shadow-lg shadow-blue-500/30' : 
        completed ? 'bg-emerald-500 border-emerald-500 text-white' : 
        'bg-white border-slate-200 text-slate-400'
      }`}>
        {icon}
      </div>
      <span className={`text-xs font-bold ${active ? 'text-blue-600' : completed ? 'text-emerald-600' : 'text-slate-400'}`}>
        {title}
      </span>
    </div>
  );
}
