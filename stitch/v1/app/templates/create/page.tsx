import { Header } from '@/components/Header';
import { Save, Eye, LayoutTemplate, Type, Image as ImageIcon, Settings, ChevronLeft } from 'lucide-react';
import Link from 'next/link';

export default function CreateTemplate() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Créer un modèle" 
        subtitle="Concevez votre nouveau modèle d'email."
        actions={
          <div className="flex items-center gap-3">
            <Link href="/templates" className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <ChevronLeft className="w-4 h-4" /> Annuler
            </Link>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <Eye className="w-4 h-4" /> Aperçu
            </button>
            <button className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
              <Save className="w-4 h-4" /> Enregistrer le modèle
            </button>
          </div>
        }
      />
      
      <div className="flex-1 overflow-hidden flex flex-col md:flex-row">
        
        {/* Main Editor Area */}
        <div className="flex-1 flex flex-col overflow-y-auto custom-scrollbar p-8">
          <div className="max-w-4xl mx-auto w-full space-y-6">
            
            {/* Editor Toolbar */}
            <div className="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-1 overflow-x-auto custom-scrollbar">
              <button className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"><Type className="w-5 h-5" /></button>
              <div className="w-px h-6 bg-slate-200 mx-1"></div>
              <select className="bg-transparent border-none text-sm font-bold text-slate-700 focus:ring-0 outline-none py-1 px-2 hover:bg-slate-100 rounded-lg cursor-pointer">
                <option>Normal</option>
                <option>Titre 1</option>
                <option>Titre 2</option>
              </select>
              <div className="w-px h-6 bg-slate-200 mx-1"></div>
              <button className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors font-bold">B</button>
              <button className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors italic font-serif">I</button>
              <button className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors underline">U</button>
              <div className="w-px h-6 bg-slate-200 mx-1"></div>
              <button className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"><ImageIcon className="w-5 h-5" /></button>
              <button className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"><LayoutTemplate className="w-5 h-5" /></button>
            </div>

            {/* Editor Content Area */}
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm min-h-[500px] p-8 flex flex-col items-center justify-center border-dashed border-2">
              <div className="text-center">
                <LayoutTemplate className="w-12 h-12 text-slate-300 mx-auto mb-4" />
                <h3 className="text-lg font-bold text-slate-900 mb-2">Commencez à concevoir</h3>
                <p className="text-sm text-slate-500 max-w-sm mx-auto mb-6">Glissez-déposez des éléments ici ou commencez à taper pour créer votre modèle.</p>
                <button className="px-4 py-2 bg-blue-50 text-blue-600 rounded-xl text-sm font-bold hover:bg-blue-100 transition-colors">
                  Ajouter un bloc
                </button>
              </div>
            </div>

          </div>
        </div>

        {/* Right Sidebar (Settings) */}
        <div className="w-full md:w-80 bg-white border-l border-slate-200 p-6 overflow-y-auto custom-scrollbar shrink-0">
          <div className="flex items-center gap-2 mb-6">
            <Settings className="w-5 h-5 text-slate-900" />
            <h3 className="font-bold text-slate-900">Paramètres du modèle</h3>
          </div>
          
          <div className="space-y-6">
            <div>
              <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nom du modèle</label>
              <input type="text" placeholder="Ex: Newsletter Mensuelle" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
            </div>
            
            <div>
              <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Catégorie</label>
              <select className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none">
                <option>Newsletters</option>
                <option>Prospection</option>
                <option>Transactionnel</option>
                <option>Événements</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Objet par défaut</label>
              <input type="text" placeholder="Ex: Découvrez nos nouveautés" className="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium focus:ring-2 focus:ring-blue-500/30 outline-none" />
            </div>

            <div className="pt-6 border-t border-slate-100">
              <h4 className="font-bold text-slate-900 mb-4 text-sm">Variables disponibles</h4>
              <div className="space-y-2">
                <VariableTag name="Prénom" tag="{{firstName}}" />
                <VariableTag name="Nom" tag="{{lastName}}" />
                <VariableTag name="Entreprise" tag="{{companyName}}" />
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  );
}

function VariableTag({ name, tag }: any) {
  return (
    <div className="flex items-center justify-between p-2.5 bg-slate-50 border border-slate-100 rounded-xl hover:border-blue-200 transition-colors cursor-pointer group">
      <span className="text-sm font-bold text-slate-700">{name}</span>
      <span className="text-[10px] font-mono font-bold text-slate-400 bg-white px-2 py-1 rounded border border-slate-200 group-hover:text-blue-600 group-hover:border-blue-200 transition-colors">{tag}</span>
    </div>
  );
}
