<template>
  <CrmLayout title="Contacts" subtitle="Gérez vos contacts et leur engagement" current-page="contacts">
    <template #header-actions>
      <span
        class="cursor-not-allowed rounded-xl bg-slate-100 border border-slate-200 px-5 py-2.5 text-xs font-bold text-slate-300"
        title="Ajout de contact — disponible dans une prochaine version"
      >
        Ajouter un contact
      </span>
    </template>

    <div class="space-y-6">
      <!-- Filters -->
      <div class="flex items-center gap-3">
        <select v-model="statusFilter" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/30 outline-none">
          <option value="all">Tous les statuts</option>
          <option value="active">Actifs</option>
          <option value="bounced">Adresse invalide</option>
          <option value="unsubscribed">Désinscrits</option>
        </select>
        <select v-model="scoreFilter" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/30 outline-none">
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
          class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
        />
      </div>

      <!-- Contacts table -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
              <th class="px-6 py-4">Contact</th>
              <th class="px-6 py-4">Organisation</th>
              <th class="px-6 py-4">E-mail</th>
              <th class="px-6 py-4">Score</th>
              <th class="px-6 py-4">Dernier échange</th>
              <th class="px-6 py-4">Statut</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="contacts.length === 0">
              <td colspan="7" class="px-6 py-16 text-center text-sm font-medium text-slate-400">
                Aucun contact. Ajoutez votre premier contact pour commencer.
              </td>
            </tr>
            <tr v-for="contact in contacts" :key="contact.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-500 text-xs font-bold text-white">
                    {{ (contact.firstName?.[0] || '').toUpperCase() }}{{ (contact.lastName?.[0] || '').toUpperCase() }}
                  </div>
                  <div>
                    <p class="font-bold text-slate-900">{{ contact.firstName }} {{ contact.lastName }}</p>
                    <p v-if="contact.title" class="text-xs text-slate-400">{{ contact.title }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 text-slate-600">{{ contact.organization || '—' }}</td>
              <td class="px-6 py-4 text-slate-600">{{ contact.email }}</td>
              <td class="px-6 py-4">
                <ScoreBadge :level="contact.scoreLevel" :score="contact.score" />
              </td>
              <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ contact.lastActivityAt || 'Aucun' }}</td>
              <td class="px-6 py-4">
                <span
                  v-if="contact.excluded"
                  class="inline-flex items-center rounded-md border border-red-200 bg-red-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-red-700"
                >
                  Exclu
                </span>
                <span
                  v-else-if="contact.unsubscribed"
                  class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-600"
                >
                  Désinscrit
                </span>
                <span
                  v-else
                  class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-emerald-700"
                >
                  Actif
                </span>
              </td>
              <td class="px-6 py-4 text-right">
                <span class="cursor-not-allowed text-xs font-bold text-slate-300" title="Fiche contact — disponible dans une prochaine version">Fiche</span>
                <span class="mx-1.5 text-slate-200">·</span>
                <span class="cursor-not-allowed text-xs font-bold text-slate-300" title="Historique e-mails — disponible dans une prochaine version">E-mails</span>
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
