import { Header } from '@/components/Header';
import Image from 'next/image';
import { UserPlus, MoreVertical, Shield, Mail, Edit2 } from 'lucide-react';
import Link from 'next/link';

export default function Users() {
  return (
    <main className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
      <Header 
        title="Gestion des Utilisateurs" 
        subtitle="Contrôlez les accès et les permissions de votre équipe."
        actions={
          <Link href="/users/invite" className="btn-primary-gradient hover:opacity-90 text-white px-5 py-2.5 rounded-xl text-sm font-bold flex items-center gap-2 transition-all shadow-lg shadow-blue-500/20">
            <UserPlus className="w-4 h-4" /> Inviter un membre
          </Link>
        }
      />
      
      <div className="flex-1 overflow-y-auto p-8 custom-scrollbar">
        <div className="max-w-5xl mx-auto space-y-6">
          
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-200">
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest">Utilisateur</th>
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest">Rôle</th>
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest">Dernière connexion</th>
                    <th className="px-6 py-4 text-xs font-black text-slate-500 uppercase tracking-widest text-right">Actions</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  <UserRow 
                    name="Jean Dupont"
                    email="jean.d@aegis.com"
                    role="Administrateur"
                    roleColor="violet"
                    lastLogin="Il y a 2 minutes"
                    avatar="https://api.dicebear.com/7.x/avataaars/svg?seed=Felix"
                  />
                  <UserRow 
                    name="Marie Lefebvre"
                    email="m.lefebvre@aegis.com"
                    role="Éditeur"
                    roleColor="blue"
                    lastLogin="Hier, 14:30"
                    avatar="https://api.dicebear.com/7.x/avataaars/svg?seed=Marie"
                  />
                  <UserRow 
                    name="Thomas Martin"
                    email="t.martin@aegis.com"
                    role="Lecteur"
                    roleColor="slate"
                    lastLogin="Il y a 3 jours"
                    avatar="https://api.dicebear.com/7.x/avataaars/svg?seed=Thomas"
                  />
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </main>
  );
}

function UserRow({ name, email, role, roleColor, lastLogin, avatar }: any) {
  const colorMap: any = {
    violet: 'bg-violet-50 text-violet-600 border-violet-200',
    blue: 'bg-blue-50 text-blue-600 border-blue-200',
    slate: 'bg-slate-100 text-slate-600 border-slate-200',
  };

  return (
    <tr className="hover:bg-slate-50 transition-colors">
      <td className="px-6 py-4">
        <div className="flex items-center gap-4">
          <div className="w-10 h-10 rounded-full overflow-hidden bg-slate-200 border border-slate-300 relative">
            <Image src={avatar} alt={name} fill referrerPolicy="no-referrer" className="object-cover" />
          </div>
          <div>
            <p className="font-bold text-sm text-slate-900">{name}</p>
            <p className="text-xs text-slate-500 font-medium mt-0.5">{email}</p>
          </div>
        </div>
      </td>
      <td className="px-6 py-4">
        <span className={`px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider border ${colorMap[roleColor]}`}>
          {role}
        </span>
      </td>
      <td className="px-6 py-4 text-sm font-medium text-slate-500">{lastLogin}</td>
      <td className="px-6 py-4 text-right">
        <div className="flex items-center justify-end gap-2">
          <button className="p-2 border border-slate-200 rounded-xl bg-white text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors shadow-sm">
            <Edit2 className="w-4 h-4" />
          </button>
          <button className="p-2 border border-slate-200 rounded-xl bg-white text-slate-400 hover:text-slate-900 hover:bg-slate-50 transition-colors shadow-sm">
            <MoreVertical className="w-4 h-4" />
          </button>
        </div>
      </td>
    </tr>
  );
}
