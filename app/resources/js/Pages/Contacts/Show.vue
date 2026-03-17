<template>
  <CrmLayout :title="pageTitle" subtitle="Fiche contact et historique d’échanges" current-page="contacts">
    <template #header-actions>
      <Link href="/contacts" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
        ← Retour aux contacts
      </Link>
    </template>

    <div class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Contact</p>
            <h2 class="mt-1 text-lg font-bold text-slate-900">{{ pageTitle }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ form.email }}</p>
          </div>
          <div class="flex gap-3">
            <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm" @click="resetForm">
              Annuler
            </button>
            <button class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-xs font-bold text-red-700 hover:bg-red-100 shadow-sm" @click="removeContact">
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
            <label class="mb-1 block text-xs font-bold text-slate-600">Prénom</label>
            <input v-model="form.firstName" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Nom</label>
            <input v-model="form.lastName" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">E-mail principal</label>
            <input v-model="form.email" type="email" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
            <p v-if="fieldErrors.email" class="mt-1 text-xs text-red-600">{{ fieldErrors.email }}</p>
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Organisation</label>
            <select v-model="form.organizationId" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 outline-none focus:ring-2 focus:ring-blue-500/30">
              <option :value="null">Aucune organisation</option>
              <option v-for="organization in organizations" :key="organization.id" :value="organization.id">{{ organization.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Poste</label>
            <input v-model="form.title" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold text-slate-600">Téléphone</label>
            <input v-model="form.phone" type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
          </div>
        </div>

        <div class="mt-4">
          <label class="mb-1 block text-xs font-bold text-slate-600">Notes</label>
          <textarea v-model="form.notes" rows="4" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
        </div>
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.3fr_1fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Adresses liées</p>
              <h3 class="mt-1 text-sm font-bold text-slate-900">E-mails du contact</h3>
            </div>
            <button class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm" @click="showEmailForm = !showEmailForm">
              {{ showEmailForm ? 'Fermer' : 'Ajouter un e-mail' }}
            </button>
          </div>

          <div v-if="showEmailForm" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto]">
              <input v-model="newEmail.email" type="email" placeholder="nouvelle.adresse@domaine.fr" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium outline-none focus:ring-2 focus:ring-blue-500/30" />
              <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600">
                <input v-model="newEmail.isPrimary" type="checkbox" class="rounded border-slate-300" />
                Principale
              </label>
              <button class="btn-primary-gradient rounded-xl px-4 py-2 text-xs font-bold text-white disabled:opacity-40" :disabled="savingEmail" @click="addEmail">
                {{ savingEmail ? 'Ajout…' : 'Ajouter' }}
              </button>
            </div>
            <p v-if="fieldErrors.newEmail" class="mt-2 text-xs text-red-600">{{ fieldErrors.newEmail }}</p>
          </div>

          <div class="mt-4 space-y-3">
            <div v-for="email in contact.emails" :key="email.id" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3">
              <div>
                <p class="text-sm font-bold text-slate-900">{{ email.email }}</p>
                <p class="mt-1 text-xs text-slate-500">
                  {{ email.isPrimary ? 'Adresse principale' : 'Adresse secondaire' }}
                  <span v-if="email.bounceStatus"> · {{ email.bounceStatus }}</span>
                  <span v-if="email.lastSeenAt"> · vue le {{ email.lastSeenAt }}</span>
                </p>
              </div>
              <button
                v-if="email.canDelete"
                class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 hover:bg-red-100"
                @click="removeEmail(email.id)"
              >
                Supprimer
              </button>
            </div>
          </div>
        </section>

        <section id="historique" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Historique</p>
          <h3 class="mt-1 text-sm font-bold text-slate-900">Derniers fils liés</h3>
          <div class="mt-4 space-y-3">
            <div v-if="contact.recentThreads.length === 0" class="rounded-xl border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-400">
              Aucun échange historisé pour ce contact.
            </div>
            <div v-for="thread in contact.recentThreads" :key="thread.id" class="rounded-xl border border-slate-200 px-4 py-3">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold text-slate-900">{{ thread.subject || '(Sans objet)' }}</p>
                <span class="text-xs font-medium text-slate-400">{{ thread.lastActivityAt || '—' }}</span>
              </div>
              <p class="mt-1 text-xs text-slate-500">
                {{ thread.lastDirection === 'in' ? 'Dernier message entrant' : 'Dernier message sortant' }}
                <span v-if="thread.replyReceived"> · réponse reçue</span>
                <span v-if="thread.autoReplyReceived"> · auto-réponse reçue</span>
              </p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';

const props = defineProps({
  contact: { type: Object, required: true },
  organizations: { type: Array, default: () => [] },
});

const form = ref(snapshot());
const saving = ref(false);
const savingEmail = ref(false);
const fieldErrors = ref({});
const banner = ref(null);
const showEmailForm = ref(false);
const newEmail = ref({ email: '', isPrimary: false });

const pageTitle = computed(() => {
  const name = `${form.value.firstName || ''} ${form.value.lastName || ''}`.trim();
  return name !== '' ? name : form.value.email;
});

function snapshot() {
  return {
    firstName: props.contact.firstName ?? '',
    lastName: props.contact.lastName ?? '',
    email: props.contact.emails?.find((email) => email.isPrimary)?.email ?? '',
    organizationId: props.contact.organizationId ?? null,
    title: props.contact.title ?? '',
    phone: props.contact.phone ?? '',
    notes: props.contact.notes ?? '',
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
    const { data } = await axios.put(`/api/contacts/${props.contact.id}`, form.value);
    banner.value = { type: 'success', message: data.message };
    router.reload({ preserveState: false });
  } catch (error) {
    fieldErrors.value = mapErrors(error.response?.data?.errors ?? {});
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de mettre à jour le contact.' };
  } finally {
    saving.value = false;
  }
}

async function removeContact() {
  if (!window.confirm('Supprimer définitivement ce contact ?')) return;

  try {
    await axios.delete(`/api/contacts/${props.contact.id}`);
    router.visit('/contacts');
  } catch (error) {
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de supprimer ce contact.' };
  }
}

async function addEmail() {
  savingEmail.value = true;
  fieldErrors.value = {};
  try {
    await axios.post(`/api/contacts/${props.contact.id}/emails`, newEmail.value);
    newEmail.value = { email: '', isPrimary: false };
    showEmailForm.value = false;
    router.reload({ preserveState: false });
  } catch (error) {
    const errors = error.response?.data?.errors ?? {};
    fieldErrors.value = { ...fieldErrors.value, newEmail: errors.email?.[0] ?? error.response?.data?.message };
  } finally {
    savingEmail.value = false;
  }
}

async function removeEmail(emailId) {
  if (!window.confirm('Supprimer cette adresse e-mail liée ?')) return;

  try {
    await axios.delete(`/api/contacts/${props.contact.id}/emails/${emailId}`);
    router.reload({ preserveState: false });
  } catch (error) {
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de supprimer cette adresse e-mail.' };
  }
}

function mapErrors(errors) {
  return {
    email: errors.email?.[0],
  };
}
</script>
