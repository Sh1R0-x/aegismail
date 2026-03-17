<template>
  <CrmLayout title="Utilisateurs" subtitle="Gestion des accès et des rôles" current-page="users">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
        <h2 class="text-sm font-bold text-slate-900">Gestion des utilisateurs</h2>
      </div>

      <div v-if="users.length === 0" class="px-6 py-16 text-center">
        <p class="text-sm font-medium text-slate-400">Aucun utilisateur configuré.</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
            <th class="px-6 py-4">Nom</th>
            <th class="px-6 py-4">E-mail</th>
            <th class="px-6 py-4">Rôle</th>
            <th class="px-6 py-4">Dernière connexion</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-500 text-xs font-bold text-white">
                  {{ (user.name?.[0] || '?').toUpperCase() }}
                </div>
                <span class="font-bold text-slate-900">{{ user.name }}</span>
              </div>
            </td>
            <td class="px-6 py-4 text-slate-600">{{ user.email }}</td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-600">
                {{ user.role }}
              </span>
            </td>
            <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ user.lastLoginAt || 'Jamais' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </CrmLayout>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineProps({
  users: { type: Array, default: () => [] },
});
</script>
