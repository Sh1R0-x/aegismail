<template>
  <CrmLayout
    :title="composerOpen ? (editingDraft ? 'Modifier le brouillon' : 'Nouveau brouillon') : 'Brouillons'"
    :subtitle="composerOpen ? 'Composez et planifiez votre envoi' : 'G\u00e9rez vos brouillons et planifications'"
    current-page="drafts"
  >
    <template #header-actions>
      <template v-if="composerOpen">
        <button
          class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
          @click="closeComposer"
        >
          \u2190 Retour aux brouillons
        </button>
      </template>
      <template v-else>
        <button
          class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
          @click="openNewDraft"
        >
          Nouveau brouillon
        </button>
      </template>
    </template>

    <MailComposer
      v-if="composerOpen"
      :mode="composerMode"
      :draft="editingDraft"
      :templates="templates"
      @close="closeComposer"
      @saved="onSaved"
      @scheduled="onScheduled"
    />

    <div v-else class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-6 py-4">
        <h2 class="text-sm font-bold text-slate-900">Brouillons enregistrés</h2>
        <span class="text-xs font-bold text-slate-400">{{ drafts.length }} brouillon(s)</span>
      </div>

      <div v-if="drafts.length === 0" class="px-6 py-16 text-center">
        <p class="text-sm font-medium text-slate-400">Aucun brouillon.</p>
        <p class="mt-1 text-xs text-slate-400">Les brouillons sauvegardés depuis le compositeur apparaîtront ici.</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
            <th class="px-6 py-4">Sujet</th>
            <th class="px-6 py-4">Destinataire(s)</th>
            <th class="px-6 py-4">Type</th>
            <th class="px-6 py-4">Statut</th>
            <th class="px-6 py-4">Planifié</th>
            <th class="px-6 py-4">Modifié</th>
            <th class="px-6 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="draft in drafts" :key="draft.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 font-bold text-slate-900">{{ draft.subject || '(sans sujet)' }}</td>
            <td class="px-6 py-4 text-slate-600">{{ draft.recipientCount }} dest.</td>
            <td class="px-6 py-4">
              <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-600">
                {{ draft.type === 'multiple' ? 'Multiple' : 'Simple' }}
              </span>
            </td>
            <td class="px-6 py-4">
              <StatusBadge :status="draft.status" />
            </td>
            <td class="px-6 py-4 text-slate-500">{{ draft.scheduledAt ?? '—' }}</td>
            <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ draft.updatedAt }}</td>
            <td class="px-6 py-4 text-right space-x-3">
              <button
                class="text-xs font-bold text-blue-600 hover:text-blue-800"
                :disabled="loadingId === draft.id"
                @click="editDraft(draft.id)"
              >
                Éditer
              </button>
              <span class="text-slate-200">·</span>
              <button
                class="text-xs font-bold text-slate-500 hover:text-slate-700"
                @click="duplicateDraft(draft.id)"
              >
                Dupliquer
              </button>
              <template v-if="draft.status === 'scheduled'">
                <span class="text-slate-200">·</span>
                <button
                  class="text-xs font-bold text-amber-600 hover:text-amber-800"
                  @click="unscheduleDraft(draft.id)"
                >
                  Déprogrammer
                </button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';
import MailComposer from '@/Components/Composer/MailComposer.vue';

const props = defineProps({
  drafts: { type: Array, default: () => [] },
  templates: { type: Array, default: () => [] },
});

const composerOpen = ref(false);
const composerMode = ref('single');
const editingDraft = ref(null);
const loadingId = ref(null);

function openNewDraft() {
  editingDraft.value = null;
  composerMode.value = 'single';
  composerOpen.value = true;
}

async function editDraft(id) {
  loadingId.value = id;
  try {
    const { data } = await axios.get(`/api/drafts/${id}`);
    editingDraft.value = data;
    composerMode.value = data.type === 'multiple' ? 'multiple' : 'single';
    composerOpen.value = true;
  } finally {
    loadingId.value = null;
  }
}

async function duplicateDraft(id) {
  await axios.post(`/api/drafts/${id}/duplicate`);
  router.reload({ preserveState: false });
}

async function unscheduleDraft(id) {
  await axios.post(`/api/drafts/${id}/unschedule`);
  router.reload({ preserveState: false });
}

function closeComposer() {
  composerOpen.value = false;
  editingDraft.value = null;
}

function onSaved() {
  router.reload({ preserveState: false });
}

function onScheduled() {
  composerOpen.value = false;
  editingDraft.value = null;
  router.reload({ preserveState: false });
}
</script>
