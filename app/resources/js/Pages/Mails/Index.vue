<template>
  <CrmLayout
    :title="composerOpen ? composerTitle : 'Mails'"
    :subtitle="composerOpen ? 'Composez et planifiez votre envoi' : 'Suivi des envois et statuts de livraison'"
    current-page="mails"
  >
    <template #header-actions>
      <template v-if="composerOpen">
        <button
          class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 shadow-sm transition-all"
          @click="composerOpen = false"
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
      :templates="templates"
      @close="composerOpen = false"
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
                <p class="max-w-[200px] truncate font-bold text-slate-900">{{ r.email }}</p>
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
              <td class="px-6 py-4 text-xs font-medium text-slate-400">{{ r.sentAt ?? '—' }}</td>
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
    </div>
  </CrmLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
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

const composerTitle = computed(() =>
  composerMode.value === 'multiple' ? 'Envoi multiple' : 'Mail simple',
);

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
