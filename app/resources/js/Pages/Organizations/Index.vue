<template>
  <CrmLayout title="Organisations" subtitle="Entreprises et domaines associés" current-page="organizations">
    <template #header-actions>
      <button
        v-if="capabilities.canCreate"
        class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
        @click="showCreateModal = true"
      >
        Ajouter une organisation
      </button>
      <span
        v-else
        class="cursor-not-allowed rounded-xl bg-slate-100 border border-slate-200 px-5 py-2.5 text-xs font-bold text-slate-300"
        title="Création d'organisations non disponible pour ce compte"
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
              <td colspan="6" class="px-6 py-16 text-center">
                <p class="text-sm font-medium text-slate-500">Aucune organisation enregistrée.</p>
                <p class="mt-1 text-xs text-slate-400">Créez votre première organisation pour regrouper vos contacts.</p>
                <button
                  v-if="capabilities.canCreate"
                  class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                  @click="showCreateModal = true"
                >
                  Ajouter une organisation
                </button>
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
              <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ formatDateFR(org.lastActivityAt) || 'Aucun' }}</td>
              <td class="px-6 py-4 text-right">
                <Link class="text-xs font-bold text-blue-600 hover:text-blue-800" :href="`/organizations/${org.id}`">Fiche</Link>
                <span class="mx-1.5 text-slate-200">·</span>
                <Link class="text-xs font-bold text-slate-600 hover:text-slate-800" :href="`/organizations/${org.id}#historique`">Historique</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </CrmLayout>

  <!-- Create organization modal -->
  <Teleport to="body">
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm" @click.self="closeCreateModal">
      <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-base font-bold text-slate-900">Nouvelle organisation</h2>
          <button class="text-slate-400 hover:text-slate-900 font-bold text-lg leading-none" @click="closeCreateModal">✕</button>
        </div>

        <div v-if="createError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-medium text-red-800">
          {{ createError }}
        </div>

        <div class="space-y-3">
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Nom de l'organisation <span class="text-red-500">*</span></label>
            <input
              v-model="createForm.name"
              type="text"
              placeholder="Acme Corp"
              class="w-full rounded-xl border bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
              :class="createFieldErrors.name ? 'border-red-300' : 'border-slate-200'"
            />
            <p v-if="createFieldErrors.name" class="mt-1 text-xs text-red-600">{{ createFieldErrors.name }}</p>
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Domaine</label>
            <input v-model="createForm.domain" type="text" placeholder="acme.com"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Site web</label>
            <input v-model="createForm.website" type="text" placeholder="https://acme.com"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-3">
          <button
            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm"
            @click="closeCreateModal"
          >
            Annuler
          </button>
          <button
            :disabled="creating"
            class="btn-primary-gradient text-white px-5 py-2 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all disabled:opacity-40"
            @click="createOrganization"
          >
            {{ creating ? 'Enregistrement…' : 'Enregistrer l\'organisation' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

const props = defineProps({
  organizations: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  capabilities: { type: Object, default: () => ({ canCreate: false, createEndpoint: '/api/organizations' }) },
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

// ── Create organization modal ─────────────────────────────────
const showCreateModal = ref(false);
const creating = ref(false);
const createError = ref(null);
const createFieldErrors = ref({});
const createForm = ref({ name: '', domain: '', website: '' });

function closeCreateModal() {
  showCreateModal.value = false;
  createError.value = null;
  createFieldErrors.value = {};
  createForm.value = { name: '', domain: '', website: '' };
}

async function createOrganization() {
  creating.value = true;
  createError.value = null;
  createFieldErrors.value = {};
  try {
    await axios.post(props.capabilities.createEndpoint, {
      name: createForm.value.name,
      domain: createForm.value.domain || undefined,
      website: createForm.value.website || undefined,
    });
    closeCreateModal();
    router.reload({ preserveState: false });
  } catch (e) {
    const errors = e.response?.data?.errors ?? {};
    const mapped = {};
    ['name', 'domain', 'website'].forEach((key) => {
      if (errors[key]) mapped[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
    });
    createFieldErrors.value = mapped;
    createError.value = Object.keys(mapped).length === 0
      ? (e.response?.data?.message ?? 'Impossible de créer l\'organisation. Veuillez réessayer.')
      : 'Veuillez corriger les erreurs ci-dessous.';
  } finally {
    creating.value = false;
  }
}
</script>
