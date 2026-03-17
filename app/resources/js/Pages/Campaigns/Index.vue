<template>
  <CrmLayout title="Campagnes" subtitle="Suivi de progression des campagnes d'envoi" current-page="campaigns">
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
      <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
        <h2 class="text-sm font-bold text-slate-900">Campagnes d'envoi</h2>
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
            <th class="px-6 py-4">Réponses</th>
            <th class="px-6 py-4">Bounces</th>
            <th class="px-6 py-4 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="campaign in campaigns" :key="campaign.id" class="hover:bg-slate-50 transition-colors">
            <td class="px-6 py-4 font-bold text-slate-900">{{ campaign.name }}</td>
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
            <td class="px-6 py-4 font-bold text-slate-600">{{ campaign.replyCount }}</td>
            <td class="px-6 py-4">
              <span v-if="campaign.bounceCount > 0" class="font-bold text-red-600">{{ campaign.bounceCount }}</span>
              <span v-else class="font-medium text-slate-400">0</span>
            </td>
            <td class="px-6 py-4 text-right">
              <Link
                :href="`/campaigns/${campaign.id}`"
                class="text-xs font-bold text-blue-600 hover:text-blue-800"
              >
                Détails
              </Link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </CrmLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';

const props = defineProps({
  campaigns: { type: Array, default: () => [] },
  creationFlow: {
    type: Object,
    default: () => ({
      type: 'draft_first',
      entryHref: '/campaigns/create',
      actionLabel: 'Préparer une campagne',
      helperText: 'Le module Campagnes conserve une couche draft technique interne, mais l’utilisateur prépare, édite et planifie ses campagnes depuis /campaigns.',
    }),
  },
});
</script>
