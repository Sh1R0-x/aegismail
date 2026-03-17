import { Header } from '@/components/Header';
import { Send, ChevronLeft, Mail, Shield, AlertCircle } from 'lucide-react';
import Link from 'next/link';

export default function InviteUser() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Inviter un membre" 
        subtitle="Ajoutez un nouveau collaborateur à votre espace de travail."
        actions={
          <div className="flex items-center gap-3">
            <Link href="/users" className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <ChevronLeft className="w-4 h-4" /> Annuler
            </Link>
            <button className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
              <Send className="w-4 h-4" /> Envoyer l&apos;invitation
            </button>
          </div>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-2xl mx-auto space-y-8">
          
          <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm space-y-8">
            
            {/* Email */}
            <div>
              <div className="flex items-center gap-2 mb-4">
                <Mail className="w-5 h-5 text-blue-600" />
                <h3 className="text-lg font-bold text-slate-900">Adresse Email</h3>
              </div>
              <p className="text-sm text-slate-500 mb-4">L&apos;invitation sera envoyée à cette adresse email. Le destinataire devra créer un compte ou se connecter.</p>
              
              <input type="email" placeholder="collaborateur@entreprise.com" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
            </div>

            <hr className="border-slate-100" />

            {/* Role Selection */}
            <div>
              <div className="flex items-center gap-2 mb-4">
                <Shield className="w-5 h-5 text-violet-600" />
                <h3 className="text-lg font-bold text-slate-900">Rôle et Permissions</h3>
              </div>
              <p className="text-sm text-slate-500 mb-6">Définissez le niveau d&apos;accès de ce nouvel utilisateur.</p>
              
              <div className="space-y-3">
                <label className="flex items-start gap-4 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors relative overflow-hidden group">
                  <input type="radio" name="role" value="admin" className="mt-1 text-blue-600 focus:ring-blue-500 border-slate-300" />
                  <div className="flex-1">
                    <span className="block text-sm font-bold text-slate-900 mb-1">Administrateur</span>
                    <span className="block text-xs text-slate-500 leading-relaxed">Accès total à toutes les fonctionnalités, y compris la facturation, la gestion des utilisateurs et les paramètres serveurs.</span>
                  </div>
                </label>

                <label className="flex items-start gap-4 p-4 border border-blue-200 bg-blue-50/50 rounded-xl cursor-pointer transition-colors relative overflow-hidden group">
                  <input type="radio" name="role" value="editor" defaultChecked className="mt-1 text-blue-600 focus:ring-blue-500 border-slate-300" />
                  <div className="flex-1">
                    <span className="block text-sm font-bold text-slate-900 mb-1">Éditeur (Recommandé)</span>
                    <span className="block text-xs text-slate-500 leading-relaxed">Peut créer, modifier et envoyer des campagnes. Peut gérer les contacts et les modèles. Pas d&apos;accès à la facturation.</span>
                  </div>
                  <div className="absolute right-0 top-0 bottom-0 w-1 bg-blue-600"></div>
                </label>

                <label className="flex items-start gap-4 p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors relative overflow-hidden group">
                  <input type="radio" name="role" value="viewer" className="mt-1 text-blue-600 focus:ring-blue-500 border-slate-300" />
                  <div className="flex-1">
                    <span className="block text-sm font-bold text-slate-900 mb-1">Lecteur</span>
                    <span className="block text-xs text-slate-500 leading-relaxed">Accès en lecture seule aux statistiques, campagnes et contacts. Ne peut rien modifier ni envoyer.</span>
                  </div>
                </label>
              </div>
            </div>

            <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 flex gap-3 items-start mt-6">
              <AlertCircle className="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
              <div>
                <h4 className="text-sm font-bold text-amber-800 mb-1">Information sur la facturation</h4>
                <p className="text-xs text-amber-700 font-medium leading-relaxed">L&apos;ajout d&apos;un nouveau membre peut impacter votre facturation selon votre forfait actuel. L&apos;invitation expirera dans 7 jours si elle n&apos;est pas acceptée.</p>
              </div>
            </div>

          </div>

        </div>
      </div>
    </main>
  );
}
