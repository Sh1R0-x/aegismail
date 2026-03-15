<template>
  <CrmLayout title="Utilisateurs" current-page="users">
    <div class="rounded-lg border border-gray-200 bg-white">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
        <h2 class="text-sm font-semibold text-gray-900">Gestion des utilisateurs</h2>
      </div>

      <div v-if="users.length === 0" class="px-4 py-12 text-center">
        <p class="text-sm text-gray-500">Aucun utilisateur configuré.</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
            <th class="px-4 py-2.5">Nom</th>
            <th class="px-4 py-2.5">E-mail</th>
            <th class="px-4 py-2.5">Rôle</th>
            <th class="px-4 py-2.5">Dernière connexion</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
            <td class="px-4 py-2.5 font-medium text-gray-900">{{ user.name }}</td>
            <td class="px-4 py-2.5 text-gray-600">{{ user.email }}</td>
            <td class="px-4 py-2.5">
              <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
                {{ user.role }}
              </span>
            </td>
            <td class="px-4 py-2.5 text-gray-400">{{ user.lastLoginAt || 'Jamais' }}</td>
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
