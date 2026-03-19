<template>
  <CrmLayout
    :title="composerOpen ? composerTitle : 'Mails'"
    :subtitle="composerOpen ? 'Composez et planifiez votre envoi' : 'Centre opérationnel : envois, brouillons et programmés'"
    current-page="mails"
  >
    <template #header-actions>
      <template v-if="composerOpen">
        <button
          class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
          @click="closeComposer"
        >
          ← Retour aux mails
        </button>
      </template>
      <template v-else>
        <div class="flex items-center gap-3">
          <button
            class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
            @click="openComposer('single')"
          >
            Mail simple
          </button>
          <button
            class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
            @click="openComposer('multiple')"
          >
            Envoi multiple
          </button>
        </div>
      </template>
    </template>

    <MailComposer
      v-if="composerOpen"
      :mode="composerMode"
      :draft="editingDraft"
      :templates="templates"
      @close="closeComposer"
      @saved="onDraftSaved"
      @scheduled="onDraftScheduled"
    />

    <div v-else class="space-y-6">

      <!-- Quota bar -->
      <div class="flex items-center gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-4 shadow-sm">
        <div class="flex items-center gap-3 text-sm">
          <span
            class="h-2.5 w-2.5 rounded-full"
            :class="quotaPercent > 90 ? 'bg-red-500' : quotaPercent > 70 ? 'bg-amber-500' : 'bg-emerald-500'"
          />
          <span class="font-bold text-slate-900">{{ stats.sentToday }}</span>
          <span class="text-slate-500">/ {{ stats.dailyLimit }} envoyés aujourd'hui</span>
        </div>
        <div class="flex-1">
          <div class="h-2 w-full rounded-full bg-slate-100">
            <div
              class="h-2 rounded-full transition-all"
              :class="quotaPercent > 90 ? 'bg-red-500' : quotaPercent > 70 ? 'bg-amber-500' : 'bg-emerald-500'"
              :style="{ width: Math.min(quotaPercent, 100) + '%' }"
            />
          </div>
        </div>
        <span class="text-xs font-bold text-slate-400">{{ quotaPercent }}%</span>
      </div>

      <!-- Tabs -->
      <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          :class="[
            'rounded-lg px-4 py-2 text-xs font-bold transition-all',
            activeTab === tab.id
              ? 'bg-white text-slate-900 shadow-sm'
              : 'text-slate-500 hover:text-slate-700',
          ]"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
          <span
            v-if="tab.count > 0"
            class="ml-1.5 inline-flex items-center justify-center rounded-full px-2 py-0.5 text-[10px] font-black"
            :class="activeTab === tab.id ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-600'"
          >
            {{ tab.count }}
          </span>
        </button>
      </div>

      <!-- ═══ TAB: Envoyés ═══ -->
      <template v-if="activeTab === 'sent'">

      <!-- Error banner -->
      <div v-if="deleteError" class="flex items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-6 py-4">
        <p class="text-sm font-semibold text-red-700">{{ deleteError }}</p>
        <button class="shrink-0 text-xs font-bold text-red-500 hover:text-red-800" @click="deleteError = null">✕</button>
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-3">
        <select
          v-model="statusFilter"
          class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-blue-500/30 outline-none"
        >
          <option value="all">Tous les statuts</option>
          <option value="sent">Envoyés</option>
          <option value="delivered_if_known">Délivrés</option>
          <option value="opened">Ouverts</option>
          <option value="clicked">Cliqués</option>
          <option value="replied">Répondus</option>
          <option value="auto_replied">Réponses auto</option>
          <option value="soft_bounced">Rebond temporaire</option>
          <option value="hard_bounced">Rebond permanent</option>
          <option value="unsubscribed">Désinscrits</option>
          <option value="failed">Échec</option>
        </select>
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par adresse, sujet…"
          class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
        />
      </div>

      <!-- Recipients table -->
      <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
              <th class="px-6 py-4">Destinataire</th>
              <th class="px-6 py-4">Sujet</th>
              <th class="px-6 py-4">Statut</th>
              <th class="px-6 py-4">Type</th>
              <th class="px-6 py-4">Envoyé</th>
              <th class="px-6 py-4 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="filteredRecipients.length === 0">
              <td colspan="6" class="px-6 py-16 text-center text-sm font-medium text-slate-400">
                <template v-if="recipients.length === 0">
                  Aucun envoi. Utilisez <strong>Mail simple</strong> ou <strong>Envoi multiple</strong> pour démarrer.
                </template>
                <template v-else>Aucun résultat pour ces filtres.</template>
              </td>
            </tr>
            <tr
              v-for="r in filteredRecipients"
              :key="r.id"
              :class="[
                'hover:bg-slate-50 transition-colors',
                r.status === 'hard_bounced' ? 'bg-red-50/40' : '',
                r.status === 'auto_replied' ? 'bg-amber-50/30' : '',
              ]"
            >
              <td class="px-6 py-4">
                  <p class="max-w-[200px] truncate font-bold text-slate-900">{{ r.contactName || r.email }}</p>
                  <p v-if="r.contactName" class="max-w-[200px] truncate text-xs text-slate-400">{{ r.email }}</p>
              </td>
              <td class="px-6 py-4 max-w-xs truncate text-slate-600">{{ r.subject }}</td>
              <td class="px-6 py-4">
                <StatusBadge :status="r.status" />
              </td>
              <td class="px-6 py-4">
                <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-600">
                  {{ r.type === 'multiple' ? 'Multiple' : 'Simple' }}
                </span>
              </td>
              <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ formatDateFR(r.sentAt) }}</td>
              <td class="px-6 py-4 text-right">
                <Link
                  v-if="r.threadId"
                  :href="`/threads/${r.threadId}`"
                  class="text-xs font-bold text-blue-600 hover:text-blue-800"
                >
                  Voir
                </Link>
                <span
                  v-else
                  class="cursor-not-allowed text-xs font-bold text-slate-300"
                  title="Aucun fil historisé n’est encore lié à cet envoi."
                >
                  Voir
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      </template>

      <!-- ═══ TAB: Brouillons ═══ -->
      <template v-if="activeTab === 'drafts'">
        <!-- Error banner -->
        <div v-if="deleteError" class="flex items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-6 py-4">
          <p class="text-sm font-semibold text-red-700">{{ deleteError }}</p>
          <button class="shrink-0 text-xs font-bold text-red-500 hover:text-red-800" @click="deleteError = null">✕</button>
        </div>
        <div class="flex items-center gap-3">
          <input
            v-model="draftSearch"
            type="text"
            placeholder="Rechercher par sujet…"
            class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/30 outline-none"
          />
          <button
            v-if="selectedDrafts.length > 0"
            class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-100 shadow-sm"
            @click="deleteSelectedDrafts"
          >
            Supprimer ({{ selectedDrafts.length }})
          </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
                <th class="w-10 px-4 py-4">
                  <input
                    type="checkbox"
                    :checked="allDraftsSelected"
                    class="rounded border-slate-300"
                    @change="toggleAllDrafts"
                  />
                </th>
                <th class="px-6 py-4">Sujet</th>
                <th class="px-6 py-4">Dest.</th>
                <th class="px-6 py-4">Type</th>
                <th class="px-6 py-4">Statut</th>
                <th class="px-6 py-4">Modifié</th>
                <th class="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="filteredDrafts.length === 0">
                <td colspan="7" class="px-6 py-16 text-center text-sm font-medium text-slate-400">
                  Aucun brouillon. Créez un <strong>Mail simple</strong> ou un <strong>Envoi multiple</strong> pour commencer.
                </td>
              </tr>
              <tr
                v-for="draft in filteredDrafts"
                :key="draft.id"
                class="hover:bg-slate-50 transition-colors"
              >
                <td class="w-10 px-4 py-4">
                  <input
                    type="checkbox"
                    :value="draft.id"
                    :checked="selectedDrafts.includes(draft.id)"
                    class="rounded border-slate-300"
                    @change="toggleDraft(draft.id)"
                  />
                </td>
                <td class="px-6 py-4 max-w-xs truncate font-bold text-slate-900">{{ draft.subject || '(Sans objet)' }}</td>
                <td class="px-6 py-4 text-slate-600">{{ draft.recipientCount }}</td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-slate-600">
                    {{ draft.type === 'multiple' ? 'Multiple' : 'Simple' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <StatusBadge :status="draft.status" />
                </td>
                <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ formatDateFR(draft.updatedAt) }}</td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      class="text-xs font-bold text-blue-600 hover:text-blue-800"
                      @click="editDraft(draft)"
                    >
                      Éditer
                    </button>
                    <button
                      class="text-xs font-bold text-slate-400 hover:text-slate-600"
                      @click="duplicateDraft(draft.id)"
                    >
                      Dupliquer
                    </button>
                    <button
                      class="text-xs font-bold text-red-500 hover:text-red-700"
                      @click="deleteDraft(draft.id)"
                    >
                      Supprimer
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- ═══ TAB: Programmés ═══ -->
      <template v-if="activeTab === 'scheduled'">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
                <th class="px-6 py-4">Sujet</th>
                <th class="px-6 py-4">Dest.</th>
                <th class="px-6 py-4">Programmé pour</th>
                <th class="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="scheduledDrafts.length === 0">
                <td colspan="4" class="px-6 py-16 text-center text-sm font-medium text-slate-400">
                  Aucun envoi programmé.
                </td>
              </tr>
              <tr
                v-for="draft in scheduledDrafts"
                :key="draft.id"
                class="hover:bg-slate-50 transition-colors"
              >
                <td class="px-6 py-4 max-w-xs truncate font-bold text-slate-900">{{ draft.subject || '(Sans objet)' }}</td>
                <td class="px-6 py-4 text-slate-600">{{ draft.recipientCount }}</td>
                <td class="px-6 py-4 text-sm font-bold text-blue-600">{{ formatDateFR(draft.scheduledAt) }}</td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <button
                      class="text-xs font-bold text-blue-600 hover:text-blue-800"
                      @click="editDraft(draft)"
                    >
                      Éditer
                    </button>
                    <button
                      class="text-xs font-bold text-amber-600 hover:text-amber-800"
                      @click="unscheduleDraft(draft.id)"
                    >
                      Déprogrammer
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';
import MailComposer from '@/Components/Composer/MailComposer.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

const props = defineProps({
  recipients: { type: Array, default: () => [] },
  drafts: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({ sentToday: 0, dailyLimit: 100 }) },
  templates: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ status: 'all' }) },
});

// Determine initial tab from URL query param
function initialTab() {
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab');
  if (tab === 'drafts' || tab === 'scheduled') return tab;
  return 'sent';
}

const activeTab = ref(initialTab());
const statusFilter = ref(props.filters?.status || 'all');
const search = ref('');
const draftSearch = ref('');
const composerOpen = ref(false);
const composerMode = ref('single');
const editingDraft = ref(null);
const selectedDrafts = ref([]);
const deleteError = ref(null);

const composerTitle = computed(() =>
  editingDraft.value ? 'Édition du brouillon' : (composerMode.value === 'multiple' ? 'Envoi multiple' : 'Mail simple'),
);

const quotaPercent = computed(() => {
  if (!props.stats.dailyLimit) return 0;
  return Math.round((props.stats.sentToday / props.stats.dailyLimit) * 100);
});

const draftItems = computed(() => props.drafts.filter(d => d.status === 'draft'));
const scheduledDrafts = computed(() => props.drafts.filter(d => d.status === 'scheduled'));

const tabs = computed(() => [
  { id: 'sent', label: 'Envoyés', count: props.recipients.length },
  { id: 'drafts', label: 'Brouillons', count: draftItems.value.length },
  { id: 'scheduled', label: 'Programmés', count: scheduledDrafts.value.length },
]);

const filteredRecipients = computed(() => {
  let list = props.recipients;
  if (statusFilter.value !== 'all') {
    list = list.filter(r => r.status === statusFilter.value);
  }
  if (search.value.trim()) {
    const q = search.value.toLowerCase();
    list = list.filter(r =>
      r.email?.toLowerCase().includes(q) ||
      r.subject?.toLowerCase().includes(q),
    );
  }
  return list;
});

const filteredDrafts = computed(() => {
  let list = draftItems.value;
  if (draftSearch.value.trim()) {
    const q = draftSearch.value.toLowerCase();
    list = list.filter(d => d.subject?.toLowerCase().includes(q));
  }
  return list;
});

const allDraftsSelected = computed(() =>
  filteredDrafts.value.length > 0 && selectedDrafts.value.length === filteredDrafts.value.length,
);

function openComposer(mode) {
  editingDraft.value = null;
  composerMode.value = mode;
  composerOpen.value = true;
}

function closeComposer() {
  composerOpen.value = false;
  editingDraft.value = null;
}

function onDraftSaved() {
  router.reload({ preserveState: false });
}

function onDraftScheduled() {
  composerOpen.value = false;
  editingDraft.value = null;
  router.reload({ preserveState: false });
}

async function editDraft(draft) {
  try {
    const { data } = await axios.get(`/api/drafts/${draft.id}`);
    editingDraft.value = data.draft;
    composerMode.value = data.draft.type === 'multiple' ? 'multiple' : 'single';
    composerOpen.value = true;
  } catch {
    editingDraft.value = null;
  }
}

async function duplicateDraft(draftId) {
  try {
    await axios.post(`/api/drafts/${draftId}/duplicate`);
    router.reload({ preserveState: false });
  } catch (e) {
    deleteError.value = e.response?.data?.message ?? 'Impossible de dupliquer le brouillon.';
  }
}

async function deleteDraft(draftId) {
  if (!window.confirm('Supprimer ce brouillon ?')) return;
  deleteError.value = null;
  try {
    await axios.delete(`/api/drafts/${draftId}`);
    selectedDrafts.value = selectedDrafts.value.filter(id => id !== draftId);
    router.reload({ preserveState: false });
  } catch (e) {
    deleteError.value = e.response?.data?.message ?? 'Impossible de supprimer le brouillon.';
  }
}

async function deleteSelectedDrafts() {
  if (!window.confirm(`Supprimer ${selectedDrafts.value.length} brouillon(s) ?`)) return;
  deleteError.value = null;
  try {
    await axios.post('/api/drafts/bulk-delete', { ids: selectedDrafts.value });
    selectedDrafts.value = [];
    router.reload({ preserveState: false });
  } catch (e) {
    deleteError.value = e.response?.data?.message ?? 'Impossible de supprimer les brouillons sélectionnés.';
  }
}

async function unscheduleDraft(draftId) {
  try {
    await axios.post(`/api/drafts/${draftId}/unschedule`);
    router.reload({ preserveState: false });
  } catch (e) {
    deleteError.value = e.response?.data?.message ?? 'Impossible de déprogrammer cet envoi.';
  }
}

function toggleDraft(id) {
  const idx = selectedDrafts.value.indexOf(id);
  if (idx >= 0) {
    selectedDrafts.value.splice(idx, 1);
  } else {
    selectedDrafts.value.push(id);
  }
}

function toggleAllDrafts() {
  if (allDraftsSelected.value) {
    selectedDrafts.value = [];
  } else {
    selectedDrafts.value = filteredDrafts.value.map(d => d.id);
  }
}
</script>
