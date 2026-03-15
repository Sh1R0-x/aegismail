<template>
  <CrmLayout title="Brouillons" current-page="drafts">
    <div class="rounded-lg border border-gray-200 bg-white">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
        <h2 class="text-sm font-semibold text-gray-900">Brouillons enregistrés</h2>
        <span class="text-xs text-gray-400">{{ drafts.length }} brouillon(s)</span>
      </div>

      <div v-if="drafts.length === 0" class="px-4 py-12 text-center">
        <p class="text-sm text-gray-500">Aucun brouillon.</p>
        <p class="mt-1 text-xs text-gray-400">Les brouillons sauvegardés depuis le compositeur apparaîtront ici.</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
            <th class="px-4 py-2.5">Sujet</th>
            <th class="px-4 py-2.5">Destinataire(s)</th>
            <th class="px-4 py-2.5">Type</th>
            <th class="px-4 py-2.5">Planifié</th>
            <th class="px-4 py-2.5">Modifié</th>
            <th class="px-4 py-2.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="draft in drafts" :key="draft.id" class="hover:bg-gray-50">
            <td class="px-4 py-2.5 font-medium text-gray-900">{{ draft.subject || '(sans sujet)' }}</td>
            <td class="px-4 py-2.5 text-gray-600">{{ draft.recipientCount }} dest.</td>
            <td class="px-4 py-2.5">
              <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
                {{ draft.type === 'multiple' ? 'Multiple' : 'Simple' }}
              </span>
            </td>
            <td class="px-4 py-2.5 text-gray-500">{{ draft.scheduledAt || '—' }}</td>
            <td class="px-4 py-2.5 text-gray-400">{{ draft.updatedAt }}</td>
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
  drafts: { type: Array, default: () => [] },
});
</script>
