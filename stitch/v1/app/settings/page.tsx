import { Header } from '@/components/Header';
import { Save, Server, Shield, Globe, Bell, CreditCard } from 'lucide-react';

export default function Settings() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Paramètres Système" 
        subtitle="Configuration globale de votre instance AEGIS MAILING."
        actions={
          <button className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
            <Save className="w-4 h-4" /> Enregistrer
          </button>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-5xl mx-auto flex flex-col md:flex-row gap-8">
          
          {/* Settings Navigation */}
          <div className="w-full md:w-64 shrink-0 space-y-1">
            <SettingsNav icon={<Server className="w-5 h-5" />} title="Serveurs SMTP" active={true} />
            <SettingsNav icon={<Shield className="w-5 h-5" />} title="Sécurité & API" active={false} />
            <SettingsNav icon={<Globe className="w-5 h-5" />} title="Domaines d'envoi" active={false} />
            <SettingsNav icon={<Bell className="w-5 h-5" />} title="Notifications" active={false} />
            <SettingsNav icon={<CreditCard className="w-5 h-5" />} title="Facturation" active={false} />
          </div>

          {/* Settings Content */}
          <div className="flex-1 space-y-6">
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <h3 className="text-lg font-bold text-slate-900 mb-6">Configuration SMTP Principale</h3>
              
              <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Hôte SMTP</label>
                    <input type="text" defaultValue="smtp.aegis-mailing.com" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                  </div>
                  <div>
                    <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Port</label>
                    <input type="text" defaultValue="587" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                  </div>
                </div>
                
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nom d&apos;utilisateur</label>
                  <input type="text" defaultValue="api_user_7892" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>
                
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Mot de passe</label>
                  <input type="password" defaultValue="••••••••••••••••" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>

                <div className="pt-4 flex items-center gap-3">
                  <input type="checkbox" id="tls" defaultChecked className="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500" />
                  <label htmlFor="tls" className="text-sm font-bold text-slate-700">Forcer le chiffrement TLS</label>
                </div>
              </div>
            </div>

            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
              <h3 className="text-lg font-bold text-slate-900 mb-6">Limites d&apos;envoi (Throttling)</h3>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Emails par heure (Max)</label>
                  <input type="number" defaultValue="50000" className="w-full md:w-1/2 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                  <p className="text-xs text-slate-400 mt-2 font-medium">Laissez vide pour utiliser la limite de votre forfait (100k/h).</p>
                </div>
              </div>
            </div>
            
          </div>

        </div>
      </div>
    </main>
  );
}

function SettingsNav({ icon, title, active }: any) {
  return (
    <button className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all ${
      active 
        ? 'bg-blue-50 text-blue-600' 
        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
    }`}>
      {icon}
      {title}
    </button>
  );
}
