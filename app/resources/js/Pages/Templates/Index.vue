<template>
  <CrmLayout title="Modèles" current-page="templates">
    <div class="rounded-lg border border-gray-200 bg-white">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
        <h2 class="text-sm font-semibold text-gray-900">Modèles d'e-mail</h2>
        <button class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">
          Nouveau modèle
        </button>
      </div>

      <div v-if="templates.length === 0" class="px-4 py-12 text-center">
        <p class="text-sm text-gray-500">Aucun modèle créé.</p>
        <p class="mt-1 text-xs text-gray-400">Créez un modèle pour l'utiliser dans vos envois simples ou multiples.</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
            <th class="px-4 py-2.5">Nom</th>
            <th class="px-4 py-2.5">Sujet</th>
            <th class="px-4 py-2.5">Statut</th>
            <th class="px-4 py-2.5">Utilisé</th>
            <th class="px-4 py-2.5">Modifié</th>
            <th class="px-4 py-2.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="tpl in templates" :key="tpl.id" class="hover:bg-gray-50">
            <td class="px-4 py-2.5 font-medium text-gray-900">{{ tpl.name }}</td>
            <td class="px-4 py-2.5 text-gray-600 truncate max-w-xs">{{ tpl.subject }}</td>
            <td class="px-4 py-2.5">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                  tpl.active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500',
                ]"
              >
                {{ tpl.active ? 'Actif' : 'Archivé' }}
              </span>
            </td>
            <td class="px-4 py-2.5 text-gray-500">{{ tpl.usageCount }} fois</td>
            <td class="px-4 py-2.5 text-gray-400">{{ tpl.updatedAt }}</td>
            <td class="px-4 py-2.5 text-right">
              <button class="text-xs font-medium text-blue-600 hover:text-blue-800">Éditer</button>
              <span class="mx-1 text-gray-300">·</span>
              <button class="text-xs font-medium text-gray-500 hover:text-gray-700">Dupliquer</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </CrmLayout>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';

defineProps({
  templates: { type: Array, default: () => [] },
});
</script>
