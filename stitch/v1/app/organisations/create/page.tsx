import { Header } from '@/components/Header';
import { Save, ChevronLeft, Building2, Globe, Mail, MapPin, CreditCard } from 'lucide-react';
import Link from 'next/link';

export default function CreateOrganisation() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Nouvelle Organisation" 
        subtitle="Créez et configurez une nouvelle entité dans votre espace."
        actions={
          <div className="flex items-center gap-3">
            <Link href="/organisations" className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <ChevronLeft className="w-4 h-4" /> Annuler
            </Link>
            <button className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
              <Save className="w-4 h-4" /> Créer l&apos;organisation
            </button>
          </div>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-3xl mx-auto space-y-8">
          
          <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm space-y-8">
            
            {/* Informations Générales */}
            <div>
              <div className="flex items-center gap-2 mb-6">
                <Building2 className="w-5 h-5 text-blue-600" />
                <h3 className="text-lg font-bold text-slate-900">Informations Générales</h3>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nom de l&apos;organisation</label>
                  <input type="text" placeholder="Ex: Nexa Labs SAS" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Secteur d&apos;activité</label>
                  <select className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none">
                    <option>Technologie & Logiciels</option>
                    <option>E-commerce & Retail</option>
                    <option>Services Financiers</option>
                    <option>Santé & Médical</option>
                    <option>Éducation</option>
                    <option>Autre</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Taille de l&apos;entreprise</label>
                  <select className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none">
                    <option>1-10 employés</option>
                    <option>11-50 employés</option>
                    <option>51-200 employés</option>
                    <option>201-500 employés</option>
                    <option>500+ employés</option>
                  </select>
                </div>
              </div>
            </div>

            <hr className="border-slate-100" />

            {/* Coordonnées */}
            <div>
              <div className="flex items-center gap-2 mb-6">
                <Globe className="w-5 h-5 text-violet-600" />
                <h3 className="text-lg font-bold text-slate-900">Coordonnées & Domaine</h3>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nom de domaine principal</label>
                  <div className="relative">
                    <Globe className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input type="text" placeholder="nexalabs.io" className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                  </div>
                  <p className="text-xs text-slate-500 mt-2">Ce domaine sera utilisé pour vérifier l&apos;identité de l&apos;expéditeur.</p>
                </div>
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Adresse postale</label>
                  <div className="relative">
                    <MapPin className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input type="text" placeholder="123 Avenue des Champs-Élysées, 75008 Paris" className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                  </div>
                </div>
              </div>
            </div>

            <hr className="border-slate-100" />

            {/* Limites et Forfait */}
            <div>
              <div className="flex items-center gap-2 mb-6">
                <CreditCard className="w-5 h-5 text-emerald-600" />
                <h3 className="text-lg font-bold text-slate-900">Limites & Forfait</h3>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Forfait assigné</label>
                  <select className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none">
                    <option>Starter (10k emails/mois)</option>
                    <option>Pro (50k emails/mois)</option>
                    <option>Business (200k emails/mois)</option>
                    <option>Enterprise (Sur mesure)</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Limite d&apos;envoi mensuelle</label>
                  <input type="number" defaultValue={50000} className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>
              </div>
            </div>

          </div>

        </div>
      </div>
    </main>
  );
}
