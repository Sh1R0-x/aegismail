<template>
  <CrmLayout title="Dashboard" current-page="dashboard">
    <div class="space-y-6">
      <!-- KPI Cards -->
      <div class="grid grid-cols-4 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Envoyés aujourd'hui</p>
          <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.sentToday }}</p>
          <p class="mt-1 text-xs text-gray-400">sur {{ stats.dailyLimit }} / jour</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Quota utilisé</p>
          <div class="mt-1 flex items-end gap-2">
            <p class="text-2xl font-semibold text-gray-900">{{ quotaPercent }}%</p>
          </div>
          <div class="mt-2 h-1.5 w-full rounded-full bg-gray-100">
            <div
              class="h-1.5 rounded-full transition-all"
              :class="quotaPercent > 90 ? 'bg-red-500' : quotaPercent > 70 ? 'bg-amber-500' : 'bg-green-500'"
              :style="{ width: quotaPercent + '%' }"
            />
          </div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Santé d'envoi</p>
          <div class="mt-1 flex items-center gap-2">
            <span
              :class="[
                'h-2.5 w-2.5 rounded-full',
                stats.healthStatus === 'good' ? 'bg-green-500' :
                stats.healthStatus === 'degraded' ? 'bg-amber-500' : 'bg-red-500',
              ]"
            />
            <p class="text-sm font-medium text-gray-900">
              {{ stats.healthStatus === 'good' ? 'Bonne' : stats.healthStatus === 'degraded' ? 'Dégradée' : 'Critique' }}
            </p>
          </div>
          <p class="mt-1 text-xs text-gray-400">{{ stats.bounceRate }}% bounce rate</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4">
          <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Campagnes actives</p>
          <p class="mt-1 text-2xl font-semibold text-gray-900">{{ stats.activeCampaigns }}</p>
          <p class="mt-1 text-xs text-gray-400">{{ stats.scheduledCount }} envois planifiés</p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-6">
        <!-- Dernières réponses -->
        <div class="rounded-lg border border-gray-200 bg-white">
          <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Dernières réponses</h2>
          </div>
          <div class="divide-y divide-gray-50">
            <div v-if="recentReplies.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
              Aucune réponse récente.
            </div>
            <div v-for="reply in recentReplies" :key="reply.id" class="flex items-center gap-3 px-4 py-2.5">
              <StatusBadge :status="reply.status" />
              <div class="min-w-0 flex-1">
                <p class="text-sm text-gray-900 truncate">{{ reply.from }}</p>
                <p class="text-xs text-gray-500 truncate">{{ reply.subject }}</p>
              </div>
              <p class="shrink-0 text-xs text-gray-400">{{ reply.time ?? '—' }}</p>
            </div>
          </div>
        </div>

        <!-- Hard bounces & auto-réponses -->
        <div class="rounded-lg border border-gray-200 bg-white">
          <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-gray-900">Alertes récentes</h2>
          </div>
          <div class="divide-y divide-gray-50">
            <div v-if="recentAlerts.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
              Aucune alerte.
            </div>
            <div v-for="alert in recentAlerts" :key="alert.id" class="flex items-center gap-3 px-4 py-2.5">
              <StatusBadge :status="alert.status" />
              <div class="min-w-0 flex-1">
                <p class="text-sm text-gray-900 truncate">{{ alert.email }}</p>
                <p class="text-xs text-gray-500 truncate">{{ alert.detail }}</p>
              </div>
              <p class="shrink-0 text-xs text-gray-400">{{ alert.time ?? '—' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Prochains envois programmés -->
      <div class="rounded-lg border border-gray-200 bg-white">
        <div class="border-b border-gray-100 px-4 py-3">
          <h2 class="text-sm font-semibold text-gray-900">Prochains envois programmés</h2>
        </div>
        <div class="divide-y divide-gray-50">
          <div v-if="scheduledSends.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
            Aucun envoi programmé.
          </div>
          <div v-for="send in scheduledSends" :key="send.id" class="flex items-center gap-3 px-4 py-2.5">
            <StatusBadge status="scheduled" />
            <div class="min-w-0 flex-1">
              <p class="text-sm text-gray-900 truncate">{{ send.subject }}</p>
              <p class="text-xs text-gray-500">{{ send.recipientCount }} destinataire(s)</p>
            </div>
            <p class="shrink-0 text-xs text-gray-500 font-medium">{{ send.scheduledAt ?? '—' }}</p>
          </div>
        </div>
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { computed } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';

const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      sentToday: 0,
      dailyLimit: 100,
      healthStatus: 'good',
      bounceRate: 0,
      activeCampaigns: 0,
      scheduledCount: 0,
    }),
  },
  recentReplies: { type: Array, default: () => [] },
  recentAlerts: { type: Array, default: () => [] },
  scheduledSends: { type: Array, default: () => [] },
});

const quotaPercent = computed(() => {
  if (!props.stats.dailyLimit) return 0;
  return Math.round((props.stats.sentToday / props.stats.dailyLimit) * 100);
});
</script>
