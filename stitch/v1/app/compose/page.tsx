import { Header } from '@/components/Header';
import { Send, Save, Eye, Paperclip, Type, Image as ImageIcon, LayoutTemplate, X } from 'lucide-react';

export default function Compose() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Rédaction de message" 
        subtitle="Créez votre prochaine campagne d'emailing."
        actions={
          <div className="flex items-center gap-3">
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <Save className="w-4 h-4" /> Brouillon
            </button>
            <button className="px-5 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-all flex items-center gap-2 shadow-sm">
              <Eye className="w-4 h-4" /> Aperçu
            </button>
            <button className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
              <Send className="w-4 h-4" /> Envoyer
            </button>
          </div>
        }
      />
      
      <div className="flex-1 overflow-hidden flex flex-col md:flex-row">
        
        {/* Main Editor Area */}
        <div className="flex-1 flex flex-col overflow-y-auto custom-scrollbar p-8">
          <div className="max-w-4xl mx-auto w-full space-y-6">
            
            {/* Metadata Fields */}
            <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
              <div className="flex items-start border-b border-slate-100 pb-4">
                <label className="w-24 text-sm font-bold text-slate-500 mt-2">À :</label>
                <div className="flex-1">
                  <div className="flex flex-wrap items-center gap-2 mb-2">
                    <span className="bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg text-sm font-bold flex items-center gap-2 border border-blue-100">
                      Liste: Tous les abonnés (142k) <X className="w-3 h-3 cursor-pointer hover:text-blue-800" />
                    </span>
                    <span className="bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-bold flex items-center gap-2 border border-slate-200">
                      client1@exemple.com <X className="w-3 h-3 cursor-pointer hover:text-slate-900" />
                    </span>
                    <span className="bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-bold flex items-center gap-2 border border-slate-200">
                      partenaire@entreprise.fr <X className="w-3 h-3 cursor-pointer hover:text-slate-900" />
                    </span>
                  </div>
                  <textarea 
                    placeholder="Ajouter des emails (séparés par des virgules)..." 
                    className="w-full bg-transparent border-none text-sm focus:ring-0 outline-none placeholder:text-slate-300 resize-none h-10"
                  />
                </div>
              </div>
              <div className="flex items-center border-b border-slate-100 pb-4">
                <label className="w-24 text-sm font-bold text-slate-500">De :</label>
                <select className="flex-1 bg-transparent border-none text-sm font-bold text-slate-900 focus:ring-0 outline-none">
                  <option>Jean Dupont &lt;jean.d@aegis-mailing.com&gt;</option>
                  <option>Support &lt;support@aegis-mailing.com&gt;</option>
                </select>
              </div>
              <div className="flex items-center">
                <label className="w-24 text-sm font-bold text-slate-500">Sujet :</label>
                <input type="text" placeholder="Saisissez l'objet de votre email..." className="flex-1 bg-transparent border-none text-lg font-bold text-slate-900 focus:ring-0 outline-none placeholder:text-slate-300" />
              </div>
            </div>

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
              <button className="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors"><Paperclip className="w-5 h-5" /></button>
              <div className="flex-1"></div>
              <button className="px-3 py-1.5 text-sm font-bold text-blue-600 hover:bg-blue-50 rounded-lg transition-colors flex items-center gap-2">
                <LayoutTemplate className="w-4 h-4" /> Modèles
              </button>
            </div>

            {/* Editor Content Area */}
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm min-h-[400px] p-8">
              <div className="prose prose-slate max-w-none">
                <p className="text-slate-500">Bonjour {'{{firstName}}'},</p>
                <p className="text-slate-500 mt-4">Commencez à rédiger votre message ici...</p>
              </div>
            </div>

          </div>
        </div>

        {/* Right Sidebar (Optional/Tools) */}
        <div className="w-full md:w-80 bg-white border-l border-slate-200 p-6 overflow-y-auto custom-scrollbar shrink-0">
          <h3 className="font-bold text-slate-900 mb-6">Variables dynamiques</h3>
          <div className="space-y-2">
            <VariableTag name="Prénom" tag="{{firstName}}" />
            <VariableTag name="Nom" tag="{{lastName}}" />
            <VariableTag name="Entreprise" tag="{{companyName}}" />
            <VariableTag name="Email" tag="{{email}}" />
          </div>

          <h3 className="font-bold text-slate-900 mb-4 mt-8">Score Anti-Spam</h3>
          <div className="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
            <div className="flex items-end gap-2 mb-2">
              <span className="text-2xl font-black text-emerald-600">9.8</span>
              <span className="text-sm font-bold text-emerald-600 mb-1">/ 10</span>
            </div>
            <p className="text-xs font-medium text-emerald-700">Excellent. Votre message a très peu de chances d&apos;arriver en spam.</p>
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
