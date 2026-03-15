<template>
  <CrmLayout title="Campagnes" current-page="campaigns">
    <div class="rounded-lg border border-gray-200 bg-white">
      <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
        <h2 class="text-sm font-semibold text-gray-900">Campagnes d'envoi</h2>
        <button class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800">
          Nouvelle campagne
        </button>
      </div>

      <div v-if="campaigns.length === 0" class="px-4 py-12 text-center">
        <p class="text-sm text-gray-500">Aucune campagne.</p>
        <p class="mt-1 text-xs text-gray-400">Les campagnes regroupent des envois multiples avec suivi de progression.</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
            <th class="px-4 py-2.5">Nom</th>
            <th class="px-4 py-2.5">Statut</th>
            <th class="px-4 py-2.5">Progression</th>
            <th class="px-4 py-2.5">Destinataires</th>
            <th class="px-4 py-2.5">Ouvertures</th>
            <th class="px-4 py-2.5">Réponses</th>
            <th class="px-4 py-2.5">Bounces</th>
            <th class="px-4 py-2.5 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="campaign in campaigns" :key="campaign.id" class="hover:bg-gray-50">
            <td class="px-4 py-2.5 font-medium text-gray-900">{{ campaign.name }}</td>
            <td class="px-4 py-2.5">
              <StatusBadge :status="campaign.status" />
            </td>
            <td class="px-4 py-2.5">
              <div class="flex items-center gap-2">
                <div class="h-1.5 w-20 rounded-full bg-gray-100">
                  <div
                    class="h-1.5 rounded-full bg-blue-500"
                    :style="{ width: campaign.progressPercent + '%' }"
                  />
                </div>
                <span class="text-xs text-gray-500">{{ campaign.progressPercent }}%</span>
              </div>
            </td>
            <td class="px-4 py-2.5 text-gray-600">{{ campaign.recipientCount }}</td>
            <td class="px-4 py-2.5 text-gray-600">{{ campaign.openCount }}</td>
            <td class="px-4 py-2.5 text-gray-600">{{ campaign.replyCount }}</td>
            <td class="px-4 py-2.5">
              <span v-if="campaign.bounceCount > 0" class="font-medium text-red-600">{{ campaign.bounceCount }}</span>
              <span v-else class="text-gray-400">0</span>
            </td>
            <td class="px-4 py-2.5 text-right">
              <button class="text-xs font-medium text-blue-600 hover:text-blue-800">Détails</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </CrmLayout>
</template>

<script setup>
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';

defineProps({
  campaigns: { type: Array, default: () => [] },
});
</script>
