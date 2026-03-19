<template>
  <CrmLayout title="Tableau de Bord" subtitle="Vue d'ensemble de votre performance de mailing" current-page="dashboard">
    <div class="space-y-8">
      <!-- KPI Metric Cards -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
        <!-- Envoyés aujourd'hui -->
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-colors hover:border-blue-200">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-slate-500">Envoyés aujourd'hui</p>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-blue-600">
                <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
              </svg>
            </div>
          </div>
          <div class="mt-3">
            <p class="text-3xl font-black text-slate-900">{{ stats.sentToday }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">sur {{ stats.dailyLimit }} / jour</p>
          </div>
        </div>

        <!-- Quota utilisé -->
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-colors hover:border-blue-200">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-slate-500">Quota utilisé</p>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-emerald-600">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>
          <div class="mt-3">
            <p class="text-3xl font-black text-slate-900">{{ quotaPercent }}%</p>
            <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
              <div
                class="h-full rounded-full transition-all"
                :class="quotaPercent > 90 ? 'bg-red-500' : quotaPercent > 70 ? 'bg-amber-500' : 'btn-primary-gradient'"
                :style="{ width: quotaPercent + '%' }"
              />
            </div>
          </div>
        </div>

        <!-- Santé d'envoi -->
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-colors hover:border-blue-200">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-slate-500">Santé d'envoi</p>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl" :class="stats.healthStatus === 'good' ? 'bg-emerald-50' : stats.healthStatus === 'degraded' ? 'bg-amber-50' : 'bg-red-50'">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5" :class="stats.healthStatus === 'good' ? 'text-emerald-600' : stats.healthStatus === 'degraded' ? 'text-amber-600' : 'text-red-600'">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>
          <div class="mt-3">
            <div class="flex items-center gap-2">
              <span
                :class="[
                  'h-3 w-3 rounded-full',
                  stats.healthStatus === 'good' ? 'bg-emerald-500' :
                  stats.healthStatus === 'degraded' ? 'bg-amber-500' : 'bg-red-500',
                ]"
              />
              <p class="text-lg font-bold text-slate-900">
                {{ stats.healthStatus === 'good' ? 'Bonne' : stats.healthStatus === 'degraded' ? 'Dégradée' : 'Critique' }}
              </p>
            </div>
            <p class="mt-1 text-xs font-medium text-slate-400">{{ stats.bounceRate }}% bounce rate</p>
          </div>
        </div>

        <!-- Campagnes actives -->
        <div class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-colors hover:border-blue-200">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold text-slate-500">Campagnes actives</p>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-violet-600">
                <path fill-rule="evenodd" d="M4.25 2A2.25 2.25 0 002 4.25v11.5A2.25 2.25 0 004.25 18h11.5A2.25 2.25 0 0018 15.75V4.25A2.25 2.25 0 0015.75 2H4.25zm4.03 6.28a.75.75 0 00-1.06-1.06L4.97 9.47a.75.75 0 000 1.06l2.25 2.25a.75.75 0 001.06-1.06L6.56 10l1.72-1.72zm3.44-1.06a.75.75 0 10-1.06 1.06L12.38 10l-1.72 1.72a.75.75 0 101.06 1.06l2.25-2.25a.75.75 0 000-1.06l-2.25-2.25z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>
          <div class="mt-3">
            <p class="text-3xl font-black text-slate-900">{{ stats.activeCampaigns }}</p>
            <p class="mt-1 text-xs font-medium text-slate-400">{{ stats.scheduledCount }} envois planifiés</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
        <!-- Main column (2/3) -->
        <div class="space-y-8 lg:col-span-2">
          <!-- Dernières réponses -->
          <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
              <h3 class="text-lg font-bold text-slate-900">Dernières réponses</h3>
            </div>
            <div class="divide-y divide-slate-50">
              <div v-if="recentReplies.length === 0" class="px-6 py-8 text-center text-sm font-medium text-slate-400">
                Aucune réponse récente.
              </div>
              <div v-for="reply in recentReplies" :key="reply.id" class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-slate-50">
                <StatusBadge :status="reply.status" />
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-bold text-slate-900 truncate">{{ reply.from }}</p>
                  <p class="text-xs font-medium text-slate-500 truncate">{{ reply.subject }}</p>
                </div>
                <p class="shrink-0 text-xs font-medium text-slate-400">{{ formatDateFR(reply.time) }}</p>
              </div>
            </div>
          </section>

          <!-- Alertes récentes -->
          <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
              <h3 class="text-lg font-bold text-slate-900">Alertes récentes</h3>
            </div>
            <div class="divide-y divide-slate-50">
              <div v-if="recentAlerts.length === 0" class="px-6 py-8 text-center text-sm font-medium text-slate-400">
                Aucune alerte.
              </div>
              <div v-for="alert in recentAlerts" :key="alert.id" class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-slate-50">
                <StatusBadge :status="alert.status" />
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-bold text-slate-900 truncate">{{ alert.email }}</p>
                  <p class="text-xs font-medium text-slate-500 truncate">{{ alert.detail }}</p>
                </div>
                <p class="shrink-0 text-xs font-medium text-slate-400">{{ formatDateFR(alert.time) }}</p>
              </div>
            </div>
          </section>
        </div>

        <!-- Side column (1/3) -->
        <div class="space-y-8">
          <!-- Prochains envois programmés -->
          <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
              <h3 class="text-lg font-bold text-slate-900">Envois planifiés</h3>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-slate-400">
                <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="divide-y divide-slate-50">
              <div v-if="scheduledSends.length === 0" class="px-6 py-8 text-center text-sm font-medium text-slate-400">
                Aucun envoi programmé.
              </div>
              <div v-for="send in scheduledSends" :key="send.id" class="px-6 py-4">
                <p class="text-xs font-bold text-blue-600">{{ formatDateFR(send.scheduledAt) }}</p>
                <p class="mt-1 text-sm font-bold text-slate-900 truncate">{{ send.subject }}</p>
                <p class="text-xs font-medium text-slate-500">{{ send.recipientCount }} destinataire(s)</p>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </CrmLayout>
</template>

<script setup>
import { computed } from 'vue';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';
import { formatDateFR } from '@/Utils/formatDate.js';

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
