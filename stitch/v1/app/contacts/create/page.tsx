import { Header } from '@/components/Header';
import { Save, ChevronLeft, User, Building2, Mail, Phone, MapPin, Tag } from 'lucide-react';
import Link from 'next/link';

export default function CreateContact() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Ajouter un contact" 
        subtitle="Créez une nouvelle fiche contact dans votre base de données."
        actions={
          <div className="flex items-center gap-3">
            <Link href="/contacts" className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <ChevronLeft className="w-4 h-4" /> Annuler
            </Link>
            <button className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
              <Save className="w-4 h-4" /> Enregistrer le contact
            </button>
          </div>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-3xl mx-auto space-y-8">
          
          {/* Form Sections */}
          <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm space-y-8">
            
            {/* Informations Personnelles */}
            <div>
              <div className="flex items-center gap-2 mb-6">
                <User className="w-5 h-5 text-blue-600" />
                <h3 className="text-lg font-bold text-slate-900">Informations Personnelles</h3>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Prénom</label>
                  <input type="text" placeholder="Ex: Jean" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nom</label>
                  <input type="text" placeholder="Ex: Dupont" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Email Principal</label>
                  <div className="relative">
                    <Mail className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input type="email" placeholder="jean.dupont@exemple.com" className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Téléphone</label>
                  <div className="relative">
                    <Phone className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input type="tel" placeholder="+33 6 12 34 56 78" className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                  </div>
                </div>
              </div>
            </div>

            <hr className="border-slate-100" />

            {/* Informations Professionnelles */}
            <div>
              <div className="flex items-center gap-2 mb-6">
                <Building2 className="w-5 h-5 text-violet-600" />
                <h3 className="text-lg font-bold text-slate-900">Informations Professionnelles</h3>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="md:col-span-2">
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Organisation / Entreprise</label>
                  <input type="text" placeholder="Rechercher ou créer une organisation..." className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Poste occupé</label>
                  <input type="text" placeholder="Ex: Directeur Marketing" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                </div>
                <div>
                  <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Statut du contact</label>
                  <select className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none">
                    <option>Prospect froid</option>
                    <option>Prospect chaud</option>
                    <option>Client régulier</option>
                    <option>Client Premium</option>
                  </select>
                </div>
              </div>
            </div>

            <hr className="border-slate-100" />

            {/* Segmentation */}
            <div>
              <div className="flex items-center gap-2 mb-6">
                <Tag className="w-5 h-5 text-emerald-600" />
                <h3 className="text-lg font-bold text-slate-900">Segmentation & Tags</h3>
              </div>
              
              <div>
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Tags (séparés par des virgules)</label>
                <input type="text" placeholder="Ex: VIP, Événement 2024, B2B" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
                <div className="flex flex-wrap gap-2 mt-3">
                  <span className="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-bold cursor-pointer hover:bg-slate-200 transition-colors">+ B2B</span>
                  <span className="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-bold cursor-pointer hover:bg-slate-200 transition-colors">+ E-commerce</span>
                  <span className="bg-slate-100 text-slate-600 px-3 py-1 rounded-lg text-xs font-bold cursor-pointer hover:bg-slate-200 transition-colors">+ SaaS</span>
                </div>
              </div>
            </div>

          </div>

        </div>
      </div>
    </main>
  );
}
