<template>
  <CrmLayout title="Organisations" subtitle="Entreprises et domaines associés" current-page="organizations">
    <template #header-actions>
      <span
        class="cursor-not-allowed rounded-xl bg-slate-100 border border-slate-200 px-5 py-2.5 text-xs font-bold text-slate-300"
        title="Ajout d'organisation — disponible dans une prochaine version"
      >
        Ajouter une organisation
      </span>
    </template>

    <div class="space-y-6">
      <!-- Filters -->
      <div class="flex items-center gap-3">
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par nom, domaine…"
          class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
        />
      </div>

      <!-- Organisations table -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
              <th class="px-6 py-4">Organisation</th>
              <th class="px-6 py-4">Domaine</th>
              <th class="px-6 py-4">Contacts</th>
              <th class="px-6 py-4">Mails envoyés</th>
              <th class="px-6 py-4">Dernier échange</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="organizations.length === 0">
              <td colspan="6" class="px-6 py-16 text-center text-sm font-medium text-slate-400">
                Aucune organisation enregistrée.
              </td>
            </tr>
            <tr v-for="org in organizations" :key="org.id" class="hover:bg-slate-50 transition-colors">
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-600">
                    {{ (org.name?.[0] || '?').toUpperCase() }}
                  </div>
                  <span class="font-bold text-slate-900">{{ org.name }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-slate-600">{{ org.domain || '—' }}</td>
              <td class="px-6 py-4">
                <span class="font-bold text-slate-900">{{ org.contactCount }}</span>
              </td>
              <td class="px-6 py-4">
                <span class="font-bold text-slate-900">{{ org.sentCount }}</span>
              </td>
              <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ org.lastActivityAt || 'Aucun' }}</td>
              <td class="px-6 py-4 text-right">
                <span class="cursor-not-allowed text-xs font-bold text-slate-300" title="Fiche organisation — disponible dans une prochaine version">Fiche</span>
                <span class="mx-1.5 text-slate-200">·</span>
                <span class="cursor-not-allowed text-xs font-bold text-slate-300" title="Historique — disponible dans une prochaine version">Historique</span>
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
