<template>
  <CrmLayout title="Contacts" subtitle="Gérez vos contacts et leur engagement" current-page="contacts">
    <template #header-actions>
      <Link
        href="/import-export"
        class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
      >
        Import / Export
      </Link>
      <button
        v-if="capabilities.canCreate"
        class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
        @click="showCreateModal = true"
      >
        Ajouter un contact
      </button>
      <span
        v-else
        class="cursor-not-allowed rounded-xl bg-slate-100 border border-slate-200 px-5 py-2.5 text-xs font-bold text-slate-300"
        title="Création de contacts non disponible pour ce compte"
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
              <th class="px-6 py-4">LinkedIn</th>
              <th class="px-6 py-4">Téléphone</th>
              <th class="px-6 py-4">Score</th>
              <th class="px-6 py-4">Dernier échange</th>
              <th class="px-6 py-4">Statut</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="contacts.length === 0">
              <td colspan="9" class="px-6 py-16 text-center">
                <p class="text-sm font-medium text-slate-500">Aucun contact dans la base.</p>
                <p class="mt-1 text-xs text-slate-400">Ajoutez votre premier contact pour commencer à suivre vos échanges.</p>
                <button
                  v-if="capabilities.canCreate"
                  class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                  @click="showCreateModal = true"
                >
                  Ajouter un contact
                </button>
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
                <a
                  v-if="contact.linkedinUrl"
                  :href="contact.linkedinUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-xs font-bold text-blue-600 hover:text-blue-800 hover:underline"
                  title="Voir le profil LinkedIn"
                >
                  LinkedIn
                </a>
                <span v-else class="text-xs text-slate-300">—</span>
              </td>
              <td class="px-6 py-4">
                <div class="flex flex-col gap-0.5 text-xs text-slate-600">
                  <span v-if="contact.phoneLandline">{{ contact.phoneLandline }}</span>
                  <span v-if="contact.phoneMobile && contact.phoneMobile !== contact.phoneLandline">{{ contact.phoneMobile }}</span>
                  <span v-if="!contact.phoneLandline && !contact.phoneMobile && contact.phone">{{ contact.phone }}</span>
                  <span v-if="!contact.phoneLandline && !contact.phoneMobile && !contact.phone" class="text-slate-300">—</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <ScoreBadge :level="contact.scoreLevel" :score="contact.score" />
              </td>
              <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ formatDateFR(contact.lastActivityAt) || 'Aucun' }}</td>
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
                <Link class="text-xs font-bold text-blue-600 hover:text-blue-800" :href="`/contacts/${contact.id}`">Fiche</Link>
                <span class="mx-1.5 text-slate-200">·</span>
                <Link class="text-xs font-bold text-slate-600 hover:text-slate-800" :href="`/contacts/${contact.id}#historique`">Historique</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </CrmLayout>

  <!-- Create contact modal -->
  <Teleport to="body">
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm" @click.self="closeCreateModal">
      <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 p-6">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-base font-bold text-slate-900">Nouveau contact</h2>
          <button class="text-slate-400 hover:text-slate-900 font-bold text-lg leading-none" @click="closeCreateModal">✕</button>
        </div>

        <div v-if="createError" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-medium text-red-800">
          {{ createError }}
        </div>

        <div class="space-y-3">
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Adresse e-mail <span class="text-red-500">*</span></label>
            <input
              v-model="createForm.email"
              type="email"
              placeholder="prenom.nom@domaine.fr"
              class="w-full rounded-xl border bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
              :class="createFieldErrors.email ? 'border-red-300' : 'border-slate-200'"
            />
            <p v-if="createFieldErrors.email" class="mt-1 text-xs text-red-600">{{ createFieldErrors.email }}</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="mb-1 block text-xs font-bold text-slate-600">Prénom</label>
              <input v-model="createForm.firstName" type="text" placeholder="Marie"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
            </div>
            <div>
              <label class="mb-1 block text-xs font-bold text-slate-600">Nom</label>
              <input v-model="createForm.lastName" type="text" placeholder="Dupont"
                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
            </div>
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Poste</label>
            <input v-model="createForm.title" type="text" placeholder="Directeur commercial"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Organisation</label>
            <select
              v-model="createForm.organizationId"
              class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/30 outline-none"
            >
              <option :value="null">Aucune organisation</option>
              <option v-for="organization in organizations" :key="organization.id" :value="organization.id">
                {{ organization.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Téléphone</label>
            <input v-model="createForm.phone" type="text" placeholder="+33 6 00 00 00 00"
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
            @click="createContact"
          >
            {{ creating ? 'Enregistrement…' : 'Enregistrer le contact' }}
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
import ScoreBadge from '@/Components/Badges/ScoreBadge.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

const props = defineProps({
  contacts: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  organizations: { type: Array, default: () => [] },
  capabilities: { type: Object, default: () => ({ canCreate: false, createEndpoint: '/api/contacts' }) },
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

// ── Create contact modal ──────────────────────────────────────
const showCreateModal = ref(false);
const creating = ref(false);
const createError = ref(null);
const createFieldErrors = ref({});
const createForm = ref({ email: '', firstName: '', lastName: '', title: '', organizationId: null, phone: '' });

function closeCreateModal() {
  showCreateModal.value = false;
  createError.value = null;
  createFieldErrors.value = {};
  createForm.value = { email: '', firstName: '', lastName: '', title: '', organizationId: null, phone: '' };
}

async function createContact() {
  creating.value = true;
  createError.value = null;
  createFieldErrors.value = {};
  try {
    await axios.post(props.capabilities.createEndpoint, {
      email: createForm.value.email,
      firstName: createForm.value.firstName || undefined,
      lastName: createForm.value.lastName || undefined,
      title: createForm.value.title || undefined,
      organizationId: createForm.value.organizationId || undefined,
      phone: createForm.value.phone || undefined,
    });
    closeCreateModal();
    router.reload({ preserveState: false });
  } catch (e) {
    const errors = e.response?.data?.errors ?? {};
    const mapped = {};
    ['email', 'firstName', 'lastName', 'title', 'phone'].forEach((key) => {
      if (errors[key]) mapped[key] = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
    });
    createFieldErrors.value = mapped;
    createError.value = Object.keys(mapped).length === 0
      ? (e.response?.data?.message ?? 'Impossible de créer le contact. Veuillez réessayer.')
      : 'Veuillez corriger les erreurs ci-dessous.';
  } finally {
    creating.value = false;
  }
}
</script>
