<template>
  <CrmLayout title="Organisations" current-page="organizations">
    <template #header-actions>
      <button class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">
        Ajouter une organisation
      </button>
    </template>

    <div class="space-y-4">
      <!-- Filters -->
      <div class="flex items-center gap-3">
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par nom, domaine…"
          class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400"
        />
      </div>

      <!-- Organisations table -->
      <div class="rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
              <th class="px-4 py-2.5">Organisation</th>
              <th class="px-4 py-2.5">Domaine</th>
              <th class="px-4 py-2.5">Contacts</th>
              <th class="px-4 py-2.5">Mails envoyés</th>
              <th class="px-4 py-2.5">Dernier échange</th>
              <th class="px-4 py-2.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="organizations.length === 0">
              <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                Aucune organisation enregistrée.
              </td>
            </tr>
            <tr v-for="org in organizations" :key="org.id" class="hover:bg-gray-50">
              <td class="px-4 py-2.5 font-medium text-gray-900">{{ org.name }}</td>
              <td class="px-4 py-2.5 text-gray-600">{{ org.domain || '—' }}</td>
              <td class="px-4 py-2.5 text-gray-600">{{ org.contactCount }}</td>
              <td class="px-4 py-2.5 text-gray-600">{{ org.sentCount }}</td>
              <td class="px-4 py-2.5 text-xs text-gray-400">{{ org.lastActivityAt || 'Aucun' }}</td>
              <td class="px-4 py-2.5 text-right">
                <button class="text-xs font-medium text-blue-600 hover:text-blue-800">Fiche</button>
                <span class="mx-1 text-gray-300">·</span>
                <button class="text-xs font-medium text-gray-500 hover:text-gray-700">Historique</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
  organizations: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search || '');

let searchTimeout = null;
watch(search, () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get('/organizations', {
      ...(search.value ? { search: search.value } : {}),
    }, { preserveState: true, replace: true });
  }, 300);
});
</script>
