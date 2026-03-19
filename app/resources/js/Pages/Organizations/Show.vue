<template>
  <CrmLayout :title="organization.name" subtitle="Fiche organisation et historique agrégé" current-page="organizations">
    <template #header-actions>
      <Link href="/organizations" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
        ← Retour aux organisations
      </Link>
    </template>

    <div class="space-y-6">
      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Organisation</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ form.name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ form.domain || 'Aucun domaine' }}</p>
          </div>
          <div class="flex gap-3">
            <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm" @click="resetForm">
              Annuler
            </button>
            <button class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-100 shadow-sm" @click="removeOrganization">
              Supprimer
            </button>
            <button :disabled="saving" class="btn-primary-gradient rounded-xl px-5 py-2 text-xs font-bold text-white shadow-lg shadow-blue-500/20 disabled:opacity-40" @click="save">
              {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </div>
        </div>

        <div v-if="banner" class="mt-4 rounded-xl border px-4 py-3 text-sm font-medium" :class="banner.type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'">
          {{ banner.message }}
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Nom</label>
            <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
            <p v-if="fieldErrors.name" class="mt-1 text-xs text-red-600">{{ fieldErrors.name }}</p>
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Domaine</label>
            <input v-model="form.domain" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
          </div>
          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-bold text-slate-600">Site web</label>
            <input v-model="form.website" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
          </div>
          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-bold text-slate-600">Notes</label>
            <textarea v-model="form.notes" rows="4" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
          </div>
        </div>
      </section>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_1fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Contacts liés</p>
              <h3 class="mt-1 text-sm font-bold text-slate-900">{{ organization.contacts.length }} contact(s)</h3>
            </div>
            <Link :href="`/contacts?search=${encodeURIComponent(organization.name)}`" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm">
              Voir dans Contacts
            </Link>
          </div>

          <div class="mt-4 space-y-3">
            <div v-if="organization.contacts.length === 0" class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-400">
              Aucun contact rattaché pour le moment.
            </div>
            <Link v-for="contact in organization.contacts" :key="contact.id" :href="`/contacts/${contact.id}`" class="block rounded-xl border border-slate-200 px-4 py-3 hover:bg-slate-50 transition-colors">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="text-sm font-bold text-slate-900">{{ contact.name || 'Contact sans nom' }}</p>
                  <p class="mt-1 text-xs text-slate-500">{{ contact.email || 'Aucun e-mail principal' }}</p>
                </div>
                <span class="text-xs font-medium text-slate-400">{{ contact.title || '—' }}</span>
              </div>
            </Link>
          </div>
        </section>

        <section id="historique" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Historique</p>
          <h3 class="mt-1 text-sm font-bold text-slate-900">Derniers fils liés</h3>
          <div class="mt-4 space-y-3">
            <div v-if="organization.recentThreads.length === 0" class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-400">
              Aucun échange historisé pour cette organisation.
            </div>
            <div v-for="thread in organization.recentThreads" :key="thread.id" class="rounded-xl border border-slate-200 px-4 py-3">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-slate-900">{{ thread.subject || '(Sans objet)' }}</p>
                <span class="text-xs font-medium text-slate-400">{{ formatDateFR(thread.lastActivityAt) }}</span>
              </div>
              <p class="mt-1 text-xs text-slate-500">
                {{ thread.contactName || 'Contact non résolu' }}
                <span v-if="thread.lastDirection === 'in'"> · dernier message entrant</span>
                <span v-else> · dernier message sortant</span>
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

const props = defineProps({
  organization: { type: Object, required: true },
});

const form = ref(snapshot());
const saving = ref(false);
const fieldErrors = ref({});
const banner = ref(null);

function snapshot() {
  return {
    name: props.organization.name ?? '',
    domain: props.organization.domain ?? '',
    website: props.organization.website ?? '',
    notes: props.organization.notes ?? '',
  };
}

function resetForm() {
  form.value = snapshot();
  fieldErrors.value = {};
  banner.value = null;
}

async function save() {
  saving.value = true;
  fieldErrors.value = {};
  banner.value = null;
  try {
    const { data } = await axios.put(`/api/organizations/${props.organization.id}`, form.value);
    banner.value = { type: 'success', message: data.message };
    router.reload({ preserveState: false });
  } catch (error) {
    fieldErrors.value = { name: error.response?.data?.errors?.name?.[0] };
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de mettre à jour l’organisation.' };
  } finally {
    saving.value = false;
  }
}

async function removeOrganization() {
  if (!window.confirm('Supprimer définitivement cette organisation ? Les contacts resteront conservés mais non rattachés.')) return;

  try {
    await axios.delete(`/api/organizations/${props.organization.id}`);
    router.visit('/organizations');
  } catch (error) {
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de supprimer cette organisation.' };
  }
}
</script>
