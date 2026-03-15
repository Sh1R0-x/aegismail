<template>
  <CrmLayout title="Brouillons" current-page="drafts">
    <template #header-actions>
      <button
        class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800"
        @click="openNewDraft"
      >
        Nouveau brouillon
      </button>
    </template>

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
            <th class="px-4 py-2.5">Statut</th>
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
            <td class="px-4 py-2.5">
              <StatusBadge :status="draft.status" />
            </td>
            <td class="px-4 py-2.5 text-gray-500">{{ draft.scheduledAt ?? '—' }}</td>
            <td class="px-4 py-2.5 text-gray-400">{{ draft.updatedAt }}</td>
            <td class="px-4 py-2.5 text-right space-x-2">
              <button
                class="text-xs font-medium text-blue-600 hover:text-blue-800"
                :disabled="loadingId === draft.id"
                @click="editDraft(draft.id)"
              >
                Éditer
              </button>
              <span class="text-gray-300">·</span>
              <button
                class="text-xs font-medium text-gray-500 hover:text-gray-700"
                @click="duplicateDraft(draft.id)"
              >
                Dupliquer
              </button>
              <template v-if="draft.status === 'scheduled'">
                <span class="text-gray-300">·</span>
                <button
                  class="text-xs font-medium text-amber-600 hover:text-amber-800"
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

    <!-- Composer slide-over -->
    <MailComposer
      v-if="composerOpen"
      :mode="composerMode"
      :draft="editingDraft"
      :templates="templates"
      @close="closeComposer"
      @saved="onSaved"
      @scheduled="onScheduled"
    />
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
