<template>
  <CrmLayout :title="campaign.name || 'Campagne'" subtitle="Détails, audience et pilotage de la campagne" current-page="campaigns">
    <template #header-actions>
      <div class="flex items-center gap-3">
        <Link href="/campaigns" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
          ← Retour aux campagnes
        </Link>
        <template v-if="!campaign.deletedAt">
          <button v-if="campaign.draft?.id" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all" @click="editing = !editing">
            {{ editing ? 'Voir le résumé' : 'Modifier' }}
          </button>
          <button
            class="rounded-xl border border-violet-200 bg-violet-50 px-4 py-2.5 text-xs font-bold text-violet-700 hover:bg-violet-100 shadow-sm transition-all"
            :disabled="cloning"
            :title="'Créer une copie de cette campagne en brouillon, sans historique d\'envoi'"
            @click="cloneCampaign"
          >
            {{ cloning ? 'Clonage…' : 'Cloner' }}
          </button>
          <button
            v-if="campaign.status === 'scheduled' && campaign.draft?.id"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-bold text-amber-700 hover:bg-amber-100 shadow-sm transition-all"
            :disabled="unscheduling"
            @click="unscheduleCampaign"
          >
            {{ unscheduling ? 'Déprogrammation…' : 'Déprogrammer' }}
          </button>
          <button class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-bold text-red-700 hover:bg-red-100 shadow-sm transition-all" @click="removeCampaign">
            Supprimer
          </button>
        </template>
        <span v-else class="inline-flex items-center rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-bold text-red-700">
          Campagne supprimée — historique conservé
        </span>
      </div>
    </template>

    <div v-if="banner" class="mb-4 rounded-xl border px-4 py-3 text-sm font-medium" :class="banner.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'">
      {{ banner.message }}
    </div>

    <CampaignEditor
      v-if="editing && campaign.draft?.id"
      :campaign-id="campaign.id"
      :draft-id="campaign.draft.id"
      :initial-name="campaign.name"
      :initial-subject="campaign.draft.subject"
      :initial-text-body="campaign.draft.textBody"
      :initial-html-body="campaign.draft.htmlBody"
      :initial-template-id="campaign.draft.templateId ?? null"
      :initial-recipients="campaign.draft.recipients ?? []"
      :templates="templates"
      @autosaved="onDraftSaved"
      @scheduled="onDraftScheduled"
    />

    <div v-else class="space-y-6">
      <section class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Statut</p>
          <div class="mt-2"><StatusBadge :status="campaign.status" /></div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Destinataires</p>
          <p class="mt-2 text-sm font-bold text-slate-900">{{ campaign.recipientCount }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Réponses</p>
          <p class="mt-2 text-sm font-bold text-slate-900">{{ campaign.replyCount }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Programmation</p>
          <p class="mt-2 text-sm font-bold text-slate-900">{{ formatDateFR(campaign.scheduledAt) || 'Non planifiée' }}</p>
        </div>
      </section>

      <!-- Progress bar -->
      <section v-if="campaign.recipientCount > 0" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Progression</p>
          <p class="text-xs font-bold text-slate-700">{{ campaign.progressPercent ?? 0 }}%</p>
        </div>
        <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
          <div
            class="h-full rounded-full transition-all duration-500"
            :class="campaign.progressPercent >= 100 ? 'bg-emerald-500' : 'bg-blue-500'"
            :style="{ width: Math.min(campaign.progressPercent ?? 0, 100) + '%' }"
          />
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-[10px] font-black uppercase tracking-[0.1em] text-slate-400">Campagne</p>
            <h2 class="mt-1 text-sm font-bold text-slate-900">{{ campaign.name }}</h2>
          </div>
          <span class="text-xs font-medium text-slate-400">{{ formatDateFR(campaign.updatedAt) }}</span>
        </div>
      </section>

      <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
          <div class="flex items-center justify-between gap-4">
            <h3 class="text-sm font-bold text-slate-900">Destinataires et statuts</h3>
            <span v-if="campaign.recipients.length" class="text-xs font-medium text-slate-400">{{ filteredRecipients.length }} / {{ campaign.recipients.length }}</span>
          </div>
          <div v-if="campaign.recipients.length" class="mt-3 flex flex-wrap items-center gap-2">
            <input
              v-model="recipientSearch"
              type="text"
              placeholder="Rechercher par nom, email ou organisation…"
              class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 placeholder-slate-400 shadow-sm focus:border-violet-300 focus:ring-1 focus:ring-violet-300 w-64"
            />
            <select v-model="filterStatus" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 shadow-sm focus:border-violet-300 focus:ring-1 focus:ring-violet-300">
              <option value="">Tous les statuts</option>
              <option v-for="s in availableStatuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
            </select>
            <select v-model="filterOrganization" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 shadow-sm focus:border-violet-300 focus:ring-1 focus:ring-violet-300">
              <option value="">Toutes les organisations</option>
              <option v-for="org in availableOrganizations" :key="org" :value="org">{{ org }}</option>
            </select>
            <select v-model="filterDomain" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 shadow-sm focus:border-violet-300 focus:ring-1 focus:ring-violet-300">
              <option value="">Tous les domaines</option>
              <option v-for="d in availableDomains" :key="d" :value="d">{{ d }}</option>
            </select>
            <button v-if="hasActiveFilters" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500 hover:bg-slate-50 shadow-sm" @click="clearFilters">
              Effacer les filtres
            </button>
          </div>
        </div>
        <div v-if="campaign.recipients.length === 0" class="px-6 py-12 text-sm text-slate-400">
          Aucun destinataire pour le moment. Planifiez la campagne pour construire la liste finale.
        </div>
        <div v-else-if="filteredRecipients.length === 0" class="px-6 py-12 text-sm text-slate-400">
          Aucun destinataire ne correspond aux filtres actifs.
        </div>
        <div v-else class="max-h-[32rem] overflow-auto">
          <table class="w-full text-sm">
            <thead class="sticky top-0 z-10">
              <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
                <th class="px-6 py-4">Destinataire</th>
                <th class="px-6 py-4">Contact</th>
                <th class="px-6 py-4">Organisation</th>
                <th class="px-6 py-4">Statut</th>
                <th class="px-6 py-4">Dernier mail</th>
                <th class="px-6 py-4">Programmé</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="recipient in filteredRecipients" :key="recipient.id" class="hover:bg-slate-50 transition-colors">
                <td class="px-6 py-4 font-bold text-slate-900">{{ recipient.email }}</td>
                <td class="px-6 py-4 text-slate-600">{{ recipient.contactName || '—' }}</td>
                <td class="px-6 py-4 text-slate-600">{{ recipient.organization || '—' }}</td>
                <td class="px-6 py-4"><StatusBadge :status="recipient.status" /></td>
                <td class="px-6 py-4 text-slate-500">
                  <span v-if="recipient.lastSentAt" :title="recipient.lastSentSubject">{{ formatDateFR(recipient.lastSentAt) }}</span>
                  <span v-else class="text-slate-300">—</span>
                </td>
                <td class="px-6 py-4 text-slate-500">{{ formatDateFR(recipient.scheduledFor) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import CampaignEditor from '@/Components/Campaigns/CampaignEditor.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

const props = defineProps({
  campaign: { type: Object, required: true },
  templates: { type: Array, default: () => [] },
});

const editing = ref(!props.campaign.deletedAt && props.campaign.status === 'draft' && Boolean(props.campaign.draft?.id));
const banner = ref(null);
const unscheduling = ref(false);
const cloning = ref(false);

// Recipient search & filters
const recipientSearch = ref('');
const filterStatus = ref('');
const filterOrganization = ref('');
const filterDomain = ref('');

const STATUS_LABELS = {
  draft: 'Brouillon', scheduled: 'Planifié', queued: 'En file', sending: 'Envoi en cours',
  sent: 'Envoyé', delivered_if_known: 'Délivré', opened: 'Ouvert', clicked: 'Cliqué',
  replied: 'Répondu', auto_replied: 'Réponse auto', soft_bounced: 'Rebond temporaire',
  hard_bounced: 'Rebond permanent', unsubscribed: 'Désabonné', failed: 'Échoué', cancelled: 'Annulé',
};

function statusLabel(s) {
  return STATUS_LABELS[s] || s;
}

const availableStatuses = computed(() => {
  const set = new Set(props.campaign.recipients.map(r => r.status));
  return [...set].sort();
});

const availableOrganizations = computed(() => {
  const set = new Set(props.campaign.recipients.map(r => r.organization).filter(Boolean));
  return [...set].sort();
});

const availableDomains = computed(() => {
  const set = new Set(props.campaign.recipients.map(r => r.email.split('@')[1]).filter(Boolean));
  return [...set].sort();
});

const hasActiveFilters = computed(() => {
  return recipientSearch.value || filterStatus.value || filterOrganization.value || filterDomain.value;
});

const filteredRecipients = computed(() => {
  let list = props.campaign.recipients;
  const q = recipientSearch.value.toLowerCase().trim();
  if (q) {
    list = list.filter(r =>
      r.email.toLowerCase().includes(q) ||
      (r.contactName && r.contactName.toLowerCase().includes(q)) ||
      (r.organization && r.organization.toLowerCase().includes(q))
    );
  }
  if (filterStatus.value) list = list.filter(r => r.status === filterStatus.value);
  if (filterOrganization.value) list = list.filter(r => r.organization === filterOrganization.value);
  if (filterDomain.value) list = list.filter(r => r.email.split('@')[1] === filterDomain.value);
  return list;
});

function clearFilters() {
  recipientSearch.value = '';
  filterStatus.value = '';
  filterOrganization.value = '';
  filterDomain.value = '';
}

onMounted(() => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('cloned') === '1') {
    banner.value = { type: 'success', message: 'Campagne clonée avec succès — elle repart en brouillon, prête à être éditée.' };
    window.history.replaceState({}, '', window.location.pathname);
  }
});

function onDraftSaved() {
  // Autosave doesn't close the editor — just silently confirm
}

function onDraftScheduled() {
  editing.value = false;
  router.reload({ preserveState: false });
}

async function cloneCampaign() {
  if (cloning.value) return;
  cloning.value = true;
  banner.value = null;
  try {
    const response = await axios.post(`/api/campaigns/${props.campaign.id}/clone`);
    const newId = response.data.campaign.id;
    router.visit(`/campaigns/${newId}`, {
      data: { cloned: '1' },
    });
  } catch (error) {
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de cloner la campagne.' };
    cloning.value = false;
  }
}

async function removeCampaign() {
  if (!window.confirm('Supprimer cette campagne et son brouillon technique associé ?')) return;

  try {
    const response = await axios.delete(`/api/campaigns/${props.campaign.id}`);
    const mode = response.data.deletionMode;
    const msg = mode === 'hard'
      ? 'Campagne supprimée définitivement.'
      : 'Campagne retirée des listes actives — l\'historique d\'envoi est conservé.';
    router.visit('/campaigns', {
      data: { deleted: mode, message: msg },
    });
  } catch (error) {
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de supprimer la campagne.' };
  }
}

async function unscheduleCampaign() {
  if (!props.campaign.draft?.id) return;
  if (!window.confirm('Déprogrammer cette campagne ? Elle repassera en brouillon.')) return;
  unscheduling.value = true;
  try {
    await axios.post(`/api/drafts/${props.campaign.draft.id}/unschedule`);
    router.reload({ preserveState: false });
  } catch (error) {
    banner.value = { type: 'error', message: error.response?.data?.message ?? 'Impossible de déprogrammer.' };
  } finally {
    unscheduling.value = false;
  }
}
</script>
