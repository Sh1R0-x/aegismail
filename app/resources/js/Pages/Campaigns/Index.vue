<template>
  <CrmLayout title="Campagnes" subtitle="Suivi de progression des campagnes d'envoi" current-page="campaigns">
    <div v-if="cloneError" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
      {{ cloneError }}
    </div>

    <div v-if="deleteBanner" class="mb-4 rounded-xl border px-4 py-3 text-sm font-medium" :class="deleteBanner.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'">
      {{ deleteBanner.message }}
    </div>

    <template #header-actions>
      <Link
        :href="creationFlow.entryHref"
        class="btn-primary-gradient text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-lg shadow-blue-500/20 hover:opacity-90 transition-all"
        :title="creationFlow.helperText"
      >
        {{ creationFlow.actionLabel || 'Préparer une campagne' }}
      </Link>
    </template>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 flex items-center justify-between gap-4">
        <h2 class="text-sm font-bold text-slate-900">Campagnes d'envoi</h2>
        <label class="flex items-center gap-2 text-xs font-medium text-slate-500 cursor-pointer select-none">
          <input
            type="checkbox"
            class="h-3.5 w-3.5 rounded border-slate-300 accent-blue-600"
            :checked="filters.includeDeleted"
            @change="toggleIncludeDeleted"
          />
          Inclure les supprimées
        </label>
      </div>

      <div v-if="campaigns.length === 0" class="px-6 py-16 text-center">
        <p class="text-sm font-medium text-slate-500">Aucune campagne pour le moment.</p>
        <p class="mt-2 text-xs text-slate-400 max-w-sm mx-auto">{{ creationFlow.helperText }}</p>
        <Link
          :href="creationFlow.entryHref"
          class="mt-5 inline-flex items-center rounded-xl border border-blue-200 bg-blue-50 px-5 py-2.5 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
        >
          {{ creationFlow.actionLabel || 'Préparer une campagne' }} →
        </Link>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200 bg-slate-50 text-left text-[10px] font-black uppercase tracking-[0.1em] text-slate-500">
            <th class="px-6 py-4">Nom</th>
            <th class="px-6 py-4">Statut</th>
            <th class="px-6 py-4">Progression</th>
            <th class="px-6 py-4">Destinataires</th>
            <th class="px-6 py-4">Ouvertures</th>
            <th class="px-6 py-4">Clics</th>
            <th class="px-6 py-4">Réponses</th>
            <th class="px-6 py-4">Rebonds</th>
            <th class="px-6 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="campaign in campaigns" :key="campaign.id" :class="['hover:bg-slate-50 transition-colors', campaign.deletedAt ? 'opacity-60' : '']">
            <td class="px-6 py-4">
              <span class="font-bold text-slate-900">{{ campaign.name }}</span>
              <span v-if="campaign.deletedAt" class="ml-2 inline-flex items-center rounded-md border border-red-200 bg-red-50 px-2 py-0.5 text-[10px] font-black uppercase text-red-600">
                Supprimée
              </span>
            </td>
            <td class="px-6 py-4">
              <StatusBadge :status="campaign.status" />
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="h-2 w-24 rounded-full bg-slate-100">
                  <div
                    class="h-2 rounded-full bg-gradient-to-r from-blue-500 to-violet-500"
                    :style="{ width: campaign.progressPercent + '%' }"
                  />
                </div>
                <span class="text-xs font-bold text-slate-500">{{ campaign.progressPercent }}%</span>
              </div>
            </td>
            <td class="px-6 py-4 font-bold text-slate-600">{{ campaign.recipientCount }}</td>
            <td class="px-6 py-4 font-bold text-slate-600">{{ campaign.openCount }}</td>
            <td class="px-6 py-4 font-bold text-slate-600">{{ campaign.clickCount }}</td>
            <td class="px-6 py-4 font-bold text-slate-600">{{ campaign.replyCount }}</td>
            <td class="px-6 py-4">
              <span v-if="campaign.bounceCount > 0" class="font-bold text-red-600">{{ campaign.bounceCount }}</span>
              <span v-else class="font-medium text-slate-400">0</span>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-3">
                <button
                  v-if="!campaign.deletedAt"
                  :disabled="cloningId === campaign.id"
                  class="text-xs font-bold text-violet-600 hover:text-violet-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  :title="'Cloner cette campagne — crée une copie en brouillon sans historique d\'envoi'"
                  @click="cloneCampaign(campaign)"
                >
                  {{ cloningId === campaign.id ? 'Clonage…' : 'Cloner' }}
                </button>
                <Link
                  :href="`/campaigns/${campaign.id}`"
                  class="text-xs font-bold text-blue-600 hover:text-blue-800"
                >
                  {{ campaign.deletedAt ? 'Consulter' : 'Détails' }}
                </Link>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';

const props = defineProps({
  campaigns: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({ includeDeleted: false }),
  },
  creationFlow: {
    type: Object,
    default: () => ({
      type: 'draft_first',
      entryHref: '/campaigns/create',
      actionLabel: 'Préparer une campagne',
      helperText: 'Le module Campagnes conserve une couche draft technique interne, mais l\'utilisateur prépare, édite et planifie ses campagnes depuis /campaigns.',
    }),
  },
});

const cloningId = ref(null);
const cloneError = ref(null);
const deleteBanner = ref(null);

onMounted(() => {
  const params = new URLSearchParams(window.location.search);
  const mode = params.get('deleted');
  const msg = params.get('message');
  if (mode && msg) {
    deleteBanner.value = { type: 'success', message: msg };
    window.history.replaceState({}, '', window.location.pathname + (props.filters.includeDeleted ? '?includeDeleted=1' : ''));
  }
});

function toggleIncludeDeleted() {
  const next = !props.filters.includeDeleted;
  router.get('/campaigns', next ? { includeDeleted: 1 } : {}, {
    preserveState: true,
    preserveScroll: true,
  });
}

async function cloneCampaign(campaign) {
  if (cloningId.value !== null) return;
  cloningId.value = campaign.id;
  cloneError.value = null;
  try {
    const response = await axios.post(`/api/campaigns/${campaign.id}/clone`);
    const newId = response.data.campaign.id;
    router.visit(`/campaigns/${newId}`, {
      data: { cloned: '1' },
    });
  } catch (error) {
    cloneError.value = error.response?.data?.message ?? 'Impossible de cloner la campagne.';
    cloningId.value = null;
  }
}
</script>
