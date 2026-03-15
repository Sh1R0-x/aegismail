<template>
  <CrmLayout title="Mails" current-page="mails">
    <template #header-actions>
      <div class="flex items-center gap-2">
        <button
          class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
          @click="openComposer('single')"
        >
          Mail simple
        </button>
        <button
          class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800"
          @click="openComposer('multiple')"
        >
          Envoi multiple
        </button>
      </div>
    </template>

    <div class="space-y-4">

      <!-- Quota bar -->
      <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white px-4 py-2.5">
        <div class="flex items-center gap-2 text-sm">
          <span
            class="h-2 w-2 rounded-full"
            :class="quotaPercent > 90 ? 'bg-red-500' : quotaPercent > 70 ? 'bg-amber-500' : 'bg-green-500'"
          />
          <span class="font-medium text-gray-900">{{ stats.sentToday }}</span>
          <span class="text-gray-500">/ {{ stats.dailyLimit }} envoyés aujourd'hui</span>
        </div>
        <div class="flex-1">
          <div class="h-1.5 w-full rounded-full bg-gray-100">
            <div
              class="h-1.5 rounded-full transition-all"
              :class="quotaPercent > 90 ? 'bg-red-500' : quotaPercent > 70 ? 'bg-amber-500' : 'bg-green-500'"
              :style="{ width: Math.min(quotaPercent, 100) + '%' }"
            />
          </div>
        </div>
        <span class="text-xs text-gray-400">{{ quotaPercent }}%</span>
      </div>

      <!-- Filters -->
      <div class="flex items-center gap-3">
        <select
          v-model="statusFilter"
          class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700"
        >
          <option value="all">Tous les statuts</option>
          <option value="sent">Envoyés</option>
          <option value="delivered_if_known">Délivrés</option>
          <option value="opened">Ouverts</option>
          <option value="clicked">Cliqués</option>
          <option value="replied">Répondus</option>
          <option value="auto_replied">Réponses auto</option>
          <option value="soft_bounced">Soft bounce</option>
          <option value="hard_bounced">Hard bounce</option>
          <option value="unsubscribed">Désinscrits</option>
          <option value="failed">Échec</option>
          <option value="scheduled">Planifiés</option>
        </select>
        <input
          v-model="search"
          type="text"
          placeholder="Rechercher par adresse, sujet…"
          class="flex-1 rounded-md border border-gray-300 px-3 py-1.5 text-sm placeholder:text-gray-400"
        />
      </div>

      <!-- Recipients table -->
      <div class="rounded-lg border border-gray-200 bg-white">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
              <th class="px-4 py-2.5">Destinataire</th>
              <th class="px-4 py-2.5">Sujet</th>
              <th class="px-4 py-2.5">Statut</th>
              <th class="px-4 py-2.5">Type</th>
              <th class="px-4 py-2.5">Envoyé</th>
              <th class="px-4 py-2.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-if="filteredRecipients.length === 0">
              <td colspan="6" class="px-4 py-12 text-center text-gray-400">
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
                'hover:bg-gray-50',
                r.status === 'hard_bounced' ? 'bg-red-50/40' : '',
                r.status === 'auto_replied' ? 'bg-amber-50/30' : '',
              ]"
            >
              <td class="px-4 py-2.5">
                <p class="max-w-[200px] truncate font-medium text-gray-900">{{ r.email }}</p>
              </td>
              <td class="px-4 py-2.5 max-w-xs truncate text-gray-600">{{ r.subject }}</td>
              <td class="px-4 py-2.5">
                <StatusBadge :status="r.status" />
              </td>
              <td class="px-4 py-2.5">
                <span class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">
                  {{ r.type === 'multiple' ? 'Multiple' : 'Simple' }}
                </span>
              </td>
              <td class="px-4 py-2.5 text-xs text-gray-400">{{ r.sentAt ?? '—' }}</td>
              <td class="px-4 py-2.5 text-right">
                <button class="text-xs font-medium text-blue-600 hover:text-blue-800">Voir</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Composer slide-over -->
    <MailComposer
      v-if="composerOpen"
      :mode="composerMode"
      :templates="templates"
      @close="composerOpen = false"
      @saved="onDraftSaved"
      @scheduled="onDraftScheduled"
    />
  </CrmLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import CrmLayout from '@/Layouts/CrmLayout.vue';
import StatusBadge from '@/Components/Badges/StatusBadge.vue';
import MailComposer from '@/Components/Composer/MailComposer.vue';

const props = defineProps({
  recipients: { type: Array, default: () => [] },
  stats: { type: Object, default: () => ({ sentToday: 0, dailyLimit: 100 }) },
  templates: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({ status: 'all' }) },
});

const statusFilter = ref(props.filters?.status || 'all');
const search = ref('');
const composerOpen = ref(false);
const composerMode = ref('single');

const quotaPercent = computed(() => {
  if (!props.stats.dailyLimit) return 0;
  return Math.round((props.stats.sentToday / props.stats.dailyLimit) * 100);
});

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

function openComposer(mode) {
  composerMode.value = mode;
  composerOpen.value = true;
}

function onDraftSaved() {
  // Composer slide-over stays open; nothing to reload on Mails list
}

function onDraftScheduled() {
  composerOpen.value = false;
  router.reload({ preserveState: false });
}
</script>
