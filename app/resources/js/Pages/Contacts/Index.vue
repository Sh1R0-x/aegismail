<template>
  <CrmLayout title="Contacts" current-page="contacts">
    <template #header-actions>
      <button class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">
        Ajouter un contact
      </button>
    </template>

    <div class="space-y-4">
      <!-- Filters -->
      <div class="flex items-center gap-3">
        <select v-model="statusFilter" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700">
          <option value="all">Tous les statuts</option>
          <option value="active">Actifs</option>
          <option value="bounced">Adresse invalide</option>
          <option value="unsubscribed">Désinscrits</option>
        </select>
        <select v-model="scoreFilter" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700">
          <option value="all">Tous les scores</option>
          <option value="engaged">Engagé</option>
          <option value="interested">Intéressé</option>
          <option value="warm">Tiède</option>
          <option value="cold">Froid</option>
          <option value="excluded">À exclure</option>
        </select>
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par nom, e-mail, organisation…"
          class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400"
        />
      </div>

      <!-- Contacts table -->
      <div class="rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
              <th class="px-4 py-2.5">Contact</th>
              <th class="px-4 py-2.5">Organisation</th>
              <th class="px-4 py-2.5">E-mail</th>
              <th class="px-4 py-2.5">Score</th>
              <th class="px-4 py-2.5">Dernier échange</th>
              <th class="px-4 py-2.5">Statut</th>
              <th class="px-4 py-2.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="contacts.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                Aucun contact. Ajoutez votre premier contact pour commencer.
              </td>
            </tr>
            <tr v-for="contact in contacts" :key="contact.id" class="hover:bg-gray-50">
              <td class="px-4 py-2.5">
                <p class="font-medium text-gray-900">{{ contact.firstName }} {{ contact.lastName }}</p>
                <p v-if="contact.title" class="text-xs text-gray-400">{{ contact.title }}</p>
              </td>
              <td class="px-4 py-2.5 text-gray-600">{{ contact.organization || '—' }}</td>
              <td class="px-4 py-2.5 text-gray-600">{{ contact.email }}</td>
              <td class="px-4 py-2.5">
                <ScoreBadge :level="contact.scoreLevel" :score="contact.score" />
              </td>
              <td class="px-4 py-2.5 text-xs text-gray-400">{{ contact.lastActivityAt || 'Aucun' }}</td>
              <td class="px-4 py-2.5">
                <span
                  v-if="contact.excluded"
                  class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700"
                >
                  Exclu
                </span>
                <span
                  v-else-if="contact.unsubscribed"
                  class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                >
                  Désinscrit
                </span>
                <span
                  v-else
                  class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700"
                >
                  Actif
                </span>
              </td>
              <td class="px-4 py-2.5 text-right">
                <button class="text-xs font-medium text-blue-600 hover:text-blue-800">Fiche</button>
                <span class="mx-1 text-gray-300">·</span>
                <button class="text-xs font-medium text-gray-500 hover:text-gray-700">E-mails</button>
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
import ScoreBadge from '@/Components/Badges/ScoreBadge.vue';

const props = defineProps({
  contacts: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
});

const statusFilter = ref(props.filters?.status || 'all');
const scoreFilter = ref(props.filters?.score || 'all');
const search = ref(props.filters?.search || '');

function navigate() {
  router.get('/contacts', {
    ...(search.value ? { search: search.value } : {}),
    ...(statusFilter.value !== 'all' ? { status: statusFilter.value } : {}),
    ...(scoreFilter.value !== 'all' ? { score: scoreFilter.value } : {}),
  }, { preserveState: true, replace: true });
}

watch([statusFilter, scoreFilter], () => navigate());

let searchTimeout = null;
watch(search, () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => navigate(), 300);
});
</script>
